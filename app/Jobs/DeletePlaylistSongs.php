<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DeletePlaylistSongs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $access_token;
    protected $refresh_token;
    protected $expires_in;
    protected $user_spotify_id;


    public function __construct($access_token, $user_spotify_id)
    {
        $this->access_token = $access_token;
        $this->user_spotify_id = $user_spotify_id;
    }

    public function handle(): void
    {

        try{
            $user = User::where('spotify_id', $this->user_spotify_id)->first();

            if(!$user){
                Log::info('No user found with spotify id ' . $this->user_spotify_id);
                return;
            }

            Log::info('Deleting synctify playlist for user ' . $user->id);

            $playlist = $user->playlists()->where('name', 'Liked Songs Playlist')->first();

            if(!$playlist){
                Log::info('No synctify playlist found for user ' . $user->id);
                return;
            }

            $songs = $playlist->songs;

            $deleteTracksEndpoint = 'https://api.spotify.com/v1/playlists/' . $playlist->spotify_playlist_id . '/tracks';
            $headers = [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ];

            $trackUris = $songs->map(function ($song) {
                return $song->spotify_uri;
            })->toArray();

            $trackChunks = array_chunk($trackUris, 100);

            foreach ($trackChunks as $chunk) {
                $response = Http::withHeaders($headers)
                    ->delete($deleteTracksEndpoint, [
                        'tracks' => array_map(function ($uri) {
                            return ['uri' => $uri];
                        }, $chunk),
                    ]);

                if ($response->failed()) {
                    Log::error('Failed to delete tracks from playlist. Spotify API response: ' . $response->body());
                }
            }
        } catch(Exception $e){
            Log::info('Failed to delete synctify playlist for user ' . $this->user_spotify_id);
            Log::info($e);
        }
    }
}
