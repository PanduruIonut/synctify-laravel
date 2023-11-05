<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Song;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
                $user->save();
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
        } catch (e) {
            throw new HTTPException(400, "Invalid JSON data");
        }

        $access_token = $data->get('access_token');
        $refresh_token = $data->get('refresh_token');
        $expires_in = $data->get('expires_in');

        $this->sync_playlist($access_token, $refresh_token, $expires_in);
    }

    public function sync_playlist(
        $access_token,
        $refresh_token,
        $expires_in
    ) {

        if (!$access_token) {
            return response()->json(['error' => 'Access token not found. Please authorize the app first.'], 400);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        $me_response = Http::withHeaders($headers)->get('https://api.spotify.com/v1/me');
        $user_email = null;
        if (isset($me_response->json()['email'])) {
            $user_email = $me_response->json()['email'];
        }
        $spotify_id = $me_response->json()['id'];
        $name = $me_response->json()['display_name'];

        DB::beginTransaction();

        try {
            $user = User::where('spotify_id', $spotify_id)->first();

            if (!$user) {
                $user_data = [
                    'email' => $user_email,
                    'spotify_id' => $spotify_id,
                    'name' => $name,
                ];

                $user = User::create($user_data);
                $user->access_token = $access_token;
                $user->refresh_token = $refresh_token;
                $user->expires_in = $expires_in;
                $user->save();
            }

            $all_liked_songs = [];
            $offset = 0;
            $limit = 50;

            while (true) {
                $endpoint_liked_songs = "https://api.spotify.com/v1/me/tracks?limit={$limit}&offset={$offset}";
                $response_liked_songs = Http::withHeaders($headers)->get($endpoint_liked_songs);
                $liked_songs = $response_liked_songs->json()['items'];

                if (empty($liked_songs)) {
                    break;
                }

                $all_liked_songs = array_merge($all_liked_songs, $liked_songs);
                $offset += $limit;
            }


            foreach ($all_liked_songs as $song) {
                $title = $song['track']['name'];

                $artistNames = $song['track']['artists'];
                $artistNames = array_column($artistNames, 'name');
                $artists = implode(', ', $artistNames);

                $album = $song['track']['album']['name'];
                $images = json_encode($song['track']['album']['images']);
                $preview_url = $song['track']['preview_url'];
                $addedAt = Carbon::createFromTimestamp(strtotime($song['added_at']));
                $added_at = $addedAt->format("Y-m-d H:i:s");



                $songRecord = Song::updateOrCreate(
                    ['title' => $title, 'artists' => $artists, 'album' => $album, 'images' => $images, 'preview_url' => $preview_url,
                'added_at' => $added_at],
                    [
                        'title' => $title,
                        'artists' => $artists,
                        'album' => $album,
                        'images' => json_encode($song['track']['album']['images']),
                        'preview_url' => $preview_url,
                        'added_at' => $added_at,
                    ]
                );
                if (!$user->playlists()->where('name', 'Liked Songs Playlist')->exists()) {
                    $user->playlists()->create([
                        'name' => 'Liked Songs Playlist',
                    ]);
                }
                $likedSongsPlaylist = $user->playlists()->where('name', 'Liked Songs Playlist')->first();
                $likedSongsPlaylist->songs()->attach($songRecord->id);
            }

            $playlistName = 'Liked Songs Playlist';
            $playlistResponse = Http::withHeaders($headers)
                ->post('https://api.spotify.com/v1/me/playlists', [
                    'name' => $playlistName,
                    'public' => false,
                ]);
            $playlistData = $playlistResponse->json();
            $playlistId = $playlistData['id'];

            $playlistTracksEndpoint = "https://api.spotify.com/v1/playlists/{$playlistId}/tracks";
            $track_uris = [];

            foreach ($all_liked_songs as $likedSong) {
                $track_uris[] = $likedSong['track']['uri'];
            }

            $tracksChunks = array_chunk($track_uris, 100);

            foreach ($tracksChunks as $chunk) {
                Http::withHeaders($headers)
                    ->post($playlistTracksEndpoint, ['uris' => $chunk]);
            }

            DB::commit();

            return response()->json(['message' => 'Playlist created with liked songs!'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function get_liked_songs($spotify_id)
    {
        $user = User::where('spotify_id', $spotify_id)->first();
        $liked_songs = Playlist::where('user_id', $user->id)->where('name', 'Liked Songs Playlist')->first();


        if ($liked_songs) {
            $songs = $liked_songs->songs()->get();
            return response()->json(['liked_songs' => $songs]);
        } else {
            return response()->json(['error' => 'No liked songs found'], 404);
        }
    }
}
