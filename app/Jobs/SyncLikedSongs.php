<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Song;
use App\Events\SyncLikedSongsCompleted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Playlist;

class SyncLikedSongs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $access_token;
    protected $refresh_token;
    protected $expires_in;
    public $timeout = 600;
    public $tries = 300;

    /**
     * Create a new job instance.
     */
    public function __construct($access_token, $refresh_token, $expires_in) {
        $this->access_token = $access_token;
        $this->refresh_token = $refresh_token;
        $this->expires_in = $expires_in;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
            $headers = [
                'Authorization' => 'Bearer ' . $this->access_token,
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
                    $user->access_token = $this->access_token;
                    $user->refresh_token =$this->refresh_token;
                    $user->expires_in = $this->expires_in;
                    $user->save();
                }

                $all_liked_songs = [];
                $offset = 0;
                $limit = 50;

                while (true) {
                    $endpoint_liked_songs = "https://api.spotify.com/v1/me/tracks?limit={$limit}&offset={$offset}";
                    $response_liked_songs = Http::withHeaders($headers)->get($endpoint_liked_songs);
                    Log::info($response_liked_songs);
                    $liked_songs = $response_liked_songs->json()['items'];

                    if (empty($liked_songs)) {
                        break;
                    }

                    $all_liked_songs = array_merge($all_liked_songs, $liked_songs);
                    $offset += $limit;
                }

                if (!$user->playlists()->where('name', 'Liked Songs Playlist')->exists()) {
                    $user->playlists()->create([
                        'name' => 'Liked Songs Playlist',
                    ]);
                }
                $likedSongsPlaylist = $user->playlists()->where('name', 'Liked Songs Playlist')->first();

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



                    $existingSongCount = Song::join('playlist_song', 'songs.id', '=', 'playlist_song.song_id')
                        ->where('playlist_song.playlist_id', $likedSongsPlaylist->id)
                        ->where('songs.title', $title)
                        ->where('songs.artists', $artists)
                        ->where('songs.album', $album)
                        ->count();

                    if ($existingSongCount > 0) {
                        continue;
                    } else {
                        $songRecord = Song::create([
                            'title' => $title,
                            'artists' => $artists,
                            'album' => $album,
                            'images' => json_encode($song['track']['album']['images']),
                            'preview_url' => $preview_url,
                            'added_at' => $added_at,
                        ]);

                        $likedSongsPlaylist->songs()->attach($songRecord->id);
                    }
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

                $likedSongsPlaylist->last_sync = Carbon::now();
                $likedSongsPlaylist->next_sync = Carbon::now()->addHours(6);
                $likedSongsPlaylist->save();
                DB::commit();

                    event(new SyncLikedSongsCompleted(['status' => 'Sync playlist completed.', 'status_code' => 200, 'user_id' => $user->spotify_id]));

            } catch (Exception $e) {
                DB::rollBack();
                Log::info($e->getMessage());
                event(new SyncLikedSongsCompleted(['status' => 'Sync playlist failed.', 'status_code' => 500, 'user_id' => $user->spotify_id]));
            }
    }
}
