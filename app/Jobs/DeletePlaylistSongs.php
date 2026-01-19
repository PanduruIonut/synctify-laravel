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
    protected $user_spotify_id;

    public function __construct($access_token, $user_spotify_id)
    {
        $this->access_token = $access_token;
        $this->user_spotify_id = $user_spotify_id;
    }

    public function handle(): void
    {
        try {
            $user = User::where('spotify_id', $this->user_spotify_id)->first();

            if (!$user) {
                Log::info('No user found with spotify id ' . $this->user_spotify_id);
                return;
            }

            $playlist = $user->playlists()->where('name', 'Liked Songs Playlist')->first();

            if (!$playlist || !$playlist->spotify_playlist_id) {
                Log::info('No synctify playlist found for user ' . $user->id);
                return;
            }

            Log::info('Deleting Synctify Liked Songs playlist for user ' . $user->id);

            $headers = [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ];

            // Unfollow (delete) the playlist entirely
            $response = Http::withHeaders($headers)
                ->delete('https://api.spotify.com/v1/playlists/' . $playlist->spotify_playlist_id . '/followers');

            if ($response->successful()) {
                Log::info('Successfully deleted playlist from Spotify');
                // Clear the spotify_playlist_id so a new one is created next sync
                $playlist->spotify_playlist_id = null;
                $playlist->save();
            } else {
                Log::error('Failed to delete playlist. Spotify API response: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('Failed to delete synctify playlist for user ' . $this->user_spotify_id);
            Log::error($e->getMessage());
        }
    }
}
