<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
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

    public function __construct($access_token, $refresh_token, $expires_in)
    {
        $this->access_token = $access_token;
        $this->refresh_token = $refresh_token;
        $this->expires_in = $expires_in;
    }

    public function handle(): void
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json',
        ];

        $me_response = Http::withHeaders($headers)->get('https://api.spotify.com/v1/me');
        $user_email = $me_response->json()['email'] ?? null;
        $spotify_id = $me_response->json()['id'];
        $name = $me_response->json()['display_name'];

        DB::beginTransaction();

        try {
            $user = User::where('spotify_id', $spotify_id)->first();

            if (!$user) {
                $user = User::create([
                    'email' => $user_email,
                    'spotify_id' => $spotify_id,
                    'name' => $name,
                    'access_token' => $this->access_token,
                    'refresh_token' => $this->refresh_token,
                    'expires_in' => $this->expires_in,
                ]);
            }

            if (!$user->playlists()->where('name', 'Liked Songs Playlist')->exists()) {
                $user->playlists()->create(['name' => 'Liked Songs Playlist']);
            }
            $likedSongsPlaylist = $user->playlists()->where('name', 'Liked Songs Playlist')->first();

            // Get the most recent song's added_at to know when to stop
            $mostRecentSong = $likedSongsPlaylist->songs()->orderBy('added_at', 'desc')->first();
            $lastSyncedAt = $mostRecentSong ? Carbon::parse($mostRecentSong->added_at) : null;

            $offset = 0;
            $limit = 50;
            $newSongsCount = 0;
            $shouldStop = false;

            Log::info("Starting sync for user {$user->id}. Last synced song: " . ($lastSyncedAt ? $lastSyncedAt->toDateTimeString() : 'none'));

            while (!$shouldStop) {
                $endpoint = "https://api.spotify.com/v1/me/tracks?limit={$limit}&offset={$offset}";
                $response = Http::withHeaders($headers)->get($endpoint);
                $likedSongs = $response->json()['items'] ?? [];

                if (empty($likedSongs)) {
                    break;
                }

                foreach ($likedSongs as $song) {
                    $addedAt = Carbon::parse($song['added_at']);

                    // If we've reached songs older than our last sync, stop
                    if ($lastSyncedAt && $addedAt->lte($lastSyncedAt)) {
                        Log::info("Reached already synced songs at offset {$offset}. Stopping.");
                        $shouldStop = true;
                        break;
                    }

                    $title = $song['track']['name'];
                    $spotify_uri = $song['track']['uri'];
                    $artists = implode(', ', array_column($song['track']['artists'], 'name'));
                    $album = $song['track']['album']['name'];
                    $images = json_encode($song['track']['album']['images']);
                    $preview_url = $song['track']['preview_url'];
                    $added_at = $addedAt->format("Y-m-d H:i:s");

                    // Check if song already exists
                    $exists = Song::join('playlist_song', 'songs.id', '=', 'playlist_song.song_id')
                        ->where('playlist_song.playlist_id', $likedSongsPlaylist->id)
                        ->where('songs.spotify_uri', $spotify_uri)
                        ->exists();

                    if (!$exists) {
                        $songRecord = Song::create([
                            'title' => $title,
                            'artists' => $artists,
                            'album' => $album,
                            'images' => $images,
                            'preview_url' => $preview_url,
                            'added_at' => $added_at,
                            'spotify_uri' => $spotify_uri,
                        ]);
                        $likedSongsPlaylist->songs()->attach($songRecord->id);
                        $newSongsCount++;
                    }
                }

                $offset += $limit;
            }

            $likedSongsPlaylist->last_sync = Carbon::now();
            $likedSongsPlaylist->next_sync = Carbon::now()->addHours(6);
            $likedSongsPlaylist->save();

            DB::commit();

            Log::info("Sync completed for user {$user->id}. Added {$newSongsCount} new songs.");
            event(new SyncLikedSongsCompleted([
                'status' => "Sync completed. Added {$newSongsCount} new songs.",
                'status_code' => 200,
                'user_id' => $user->spotify_id
            ]));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Sync failed: " . $e->getMessage());
            event(new SyncLikedSongsCompleted([
                'status' => 'Sync playlist failed.',
                'status_code' => 500,
                'user_id' => $spotify_id
            ]));
        }
    }
}
