<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Song;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Jobs\SyncLikedSongs as SyncLikedSongsJob;
use App\Jobs\SyncPlaylists as SyncPlaylistsJob;

class SpotifyController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $code = $data['code'];
        $CLIENT_ID = $data['client_id'];
        $CLIENT_SECRET = $data['client_secret'];
        $REDIRECT_URI = $data['redirect_uri'];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $auth_url = 'https://accounts.spotify.com/api/token';
        $auth_response = Http::withHeaders($headers)->asForm()->post($auth_url, [
            'grant_type' => 'authorization_code',
            'scope' => 'user-top-read',
            'code' => $code,
            'redirect_uri' => $REDIRECT_URI,
            'client_id' => $CLIENT_ID,
            'client_secret' => $CLIENT_SECRET,
        ]);

        if ($auth_response->status() != 200) {
            return response()->json(['error' => $auth_response->json()], $auth_response->status());
        }
        $auth_response_data = $auth_response->json();
        $access_token = $auth_response_data['access_token'];
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];
        $spotify_response = Http::withHeaders($headers)->get('https://api.spotify.com/v1/me');
        if ($spotify_response->status() == 200) {
            $response_data = $spotify_response->json();
            $user_id = $response_data['id'];
            $user = User::where('spotify_id', $user_id)->first();
            if ($user) {
                $user->is_active = true;
                $user->access_token = $access_token;
                $user->refresh_token = $auth_response_data['refresh_token'];
                $user->expires_in = $auth_response_data['expires_in'];
                // Reset auth status on successful authorization
                $user->auth_status = 'valid';
                $user->auth_failed_at = null;
                $user->auth_error_message = null;
                $user->save();
            } else {
                $user_data = [
                    'spotify_id' => $response_data['id'],
                    'name' => $response_data['display_name'],
                    'access_token' => $access_token,
                    'refresh_token' => $auth_response_data['refresh_token'],
                    'expires_in' => $auth_response_data['expires_in'],
                    'client_id' => $CLIENT_ID,
                    'client_secret' => $CLIENT_SECRET,
                ];
                $user = User::create($user_data);
            }
        }
        return $auth_response_data;
    }

    public function me(Request $request)
    {
        $data = $request->json()->all();

        $access_token = $data['access_token'];

        if (!$access_token) {
            throw new Exception('Access token not found. Please authorize the app first.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];

        $spotify_response = Http::withHeaders($headers)->get('https://api.spotify.com/v1/me');
        if ($spotify_response->status() == 200) {
            $response_data = $spotify_response->json();
            return $response_data;
        } else {
            throw new Exception('Failed to fetch Spotify data');
        }
    }

    public function create_playlist(Request $request)
    {
        try {
            $data = $request->json();
        } catch (\Exception $e) {
            throw new HTTPException(400, "Invalid JSON data");
        }

        $access_token = $data->get('access_token');
        $refresh_token = $data->get('refresh_token');
        $expires_in = $data->get('expires_in');

        $this->sync_playlist($access_token, $refresh_token, $expires_in);
    }

    public static function sync_playlist(
        $access_token,
        $refresh_token,
        $expires_in
    ) {

        dispatch(new SyncLikedSongsJob($access_token, $refresh_token, $expires_in));

        return response()->json(['message' => 'Sync job has been started.'], 202);
    }

    public function get_liked_songs($spotify_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();
        $liked_songs = Playlist::where('user_id', $user->id)->where('name', 'Liked Songs Playlist')->first();


        if ($liked_songs) {
            $songs = $liked_songs->songs()->orderBy('added_at', 'desc')->get();
            return response()->json(['liked_songs' => $songs, 'last_sync' => $liked_songs->last_sync]);
        } else {
            return response()->json(['error' => 'No liked songs found'], 404);
        }
    }

    public static function refresh_access_token($client_id, $client_secret, $refresh_token)
    {
        $token_url = 'https://accounts.spotify.com/api/token';
        $data = [
            'scope' => 'user-top-read',
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($token_url, $data);

        $user = User::where('client_id', $client_id)->first();

        if ($response->status() == 200) {
            $token_data = $response->json();
            $new_access_token = $token_data['access_token'];
            
            if ($user) {
                $user->access_token = $new_access_token;
                // Reset auth status on successful refresh
                $user->auth_status = 'valid';
                $user->auth_failed_at = null;
                $user->auth_error_message = null;
                $user->save();
            }
            
            return $new_access_token;
        } else {
            // Mark user as needing re-authorization
            if ($user) {
                $user->auth_status = 'needs_reauth';
                $user->auth_failed_at = now();
                $user->auth_error_message = $response->body();
                $user->save();
            }

            Log::error("Token refresh failed for user", [
                'client_id' => $client_id,
                'user_id' => $user ? $user->id : null,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            throw new Exception("Token refresh failed: " . $response->status());
        }
    }

    public function refresh_token(Request $request)
    {
        $data = $request->all();

        $user_id = $data['user_id'];
        $refresh_token = $data['refresh_token'];
        $client_secret = $data['client_id'];
        $client_id = $data['client_secret'];

        $user = User::where('spotify_id', $user_id)->first();

        if (!$user) {
            throw new Exception("User not found");
        }

        $token_url = 'https://accounts.spotify.com/api/token';
        $data = [
            'scope' => 'user-top-read',
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($token_url, $data);

        if ($response->status() != 200) {
            throw new Exception("Token refresh failed");
        }

        $token_data = $response->json();
        $new_access_token = $token_data['access_token'];

        $user->access_token = $new_access_token;
        $user->save();

        return response()->json([
            "message" => "Token refreshed successfully",
            "new_access_token" => $new_access_token
        ], 200);
    }

    public function get_auth_status($spotify_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'auth_status' => $user->auth_status ?? 'valid',
            'auth_failed_at' => $user->auth_failed_at,
            'message' => $user->auth_status === 'needs_reauth' 
                ? 'Please re-authorize the app to continue syncing' 
                : 'Authorization is valid'
        ]);
    }

    public function on_this_day($spotify_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $playlist = Playlist::where('user_id', $user->id)->where('name', 'Liked Songs Playlist')->first();

        if (!$playlist) {
            return response()->json(['error' => 'No playlist found'], 404);
        }

        $today = Carbon::now();
        $currentMonth = $today->month;
        $currentDay = $today->day;
        $currentYear = $today->year;

        // Get songs saved on this day in previous years
        $songs = $playlist->songs()
            ->whereMonth('songs.added_at', $currentMonth)
            ->whereDay('songs.added_at', $currentDay)
            ->whereYear('songs.added_at', '<', $currentYear)
            ->orderBy('songs.added_at', 'desc')
            ->get()
            ->groupBy(function($song) {
                return Carbon::parse($song->added_at)->year;
            });

        return response()->json([
            'date' => $today->format('F j'),
            'songs_by_year' => $songs,
            'total_count' => $songs->flatten()->count()
        ]);
    }

    public function sync_playlists(Request $request)
    {
        $data = $request->json()->all();
        $access_token = $data['access_token'] ?? null;
        $spotify_id = $data['spotify_id'] ?? null;

        if (!$access_token || !$spotify_id) {
            return response()->json(['error' => 'Missing access_token or spotify_id'], 400);
        }

        dispatch(new SyncPlaylistsJob($access_token, $spotify_id));

        return response()->json(['message' => 'Playlist sync job has been started.'], 202);
    }

    public function get_playlists($spotify_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $playlists = Playlist::where('user_id', $user->id)
            ->where('name', '!=', 'Liked Songs Playlist')
            ->orderBy('name')
            ->get()
            ->map(function ($playlist) {
                return [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'description' => $playlist->description,
                    'image_url' => $playlist->image_url,
                    'owner' => $playlist->owner,
                    'is_public' => $playlist->is_public,
                    'tracks_count' => $playlist->tracks_count,
                    'last_sync' => $playlist->last_sync,
                    'spotify_playlist_id' => $playlist->spotify_playlist_id,
                ];
            });

        return response()->json(['playlists' => $playlists]);
    }

    public function get_playlist_songs($spotify_id, $playlist_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $playlist = Playlist::where('user_id', $user->id)
            ->where('id', $playlist_id)
            ->first();

        if (!$playlist) {
            return response()->json(['error' => 'Playlist not found'], 404);
        }

        $songs = $playlist->songs()->get();

        return response()->json([
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'image_url' => $playlist->image_url,
                'tracks_count' => $playlist->tracks_count,
            ],
            'songs' => $songs,
        ]);
    }

    public function import_to_liked_songs($spotify_id, $playlist_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $sourcePlaylist = Playlist::where('user_id', $user->id)->where('id', $playlist_id)->first();

        if (!$sourcePlaylist) {
            return response()->json(['error' => 'Source playlist not found'], 404);
        }

        $likedSongsPlaylist = Playlist::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Liked Songs Playlist'],
            ['description' => 'Your liked songs from Spotify']
        );

        $sourceSongIds = $sourcePlaylist->songs()->pluck('songs.id')->toArray();
        $existingSongIds = $likedSongsPlaylist->songs()->pluck('songs.id')->toArray();
        $newSongIds = array_diff($sourceSongIds, $existingSongIds);

        $likedSongsPlaylist->songs()->attach($newSongIds);

        $likedSongsPlaylist->tracks_count = $likedSongsPlaylist->songs()->count();
        $likedSongsPlaylist->save();

        return response()->json([
            'success' => true,
            'imported_count' => count($newSongIds),
            'skipped_count' => count($sourceSongIds) - count($newSongIds),
            'total_liked_songs' => $likedSongsPlaylist->tracks_count,
        ]);
    }
}
