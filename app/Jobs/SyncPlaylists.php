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
use App\Models\Playlist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncPlaylists implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $access_token;
    protected $user_spotify_id;
    public $timeout = 1200;
    public $tries = 3;

    public function __construct($access_token, $user_spotify_id)
    {
        $this->access_token = $access_token;
        $this->user_spotify_id = $user_spotify_id;
    }

    public function handle(): void
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json',
        ];

        $user = User::where('spotify_id', $this->user_spotify_id)->first();

        if (!$user) {
            Log::error("User not found: {$this->user_spotify_id}");
            return;
        }

        Log::info("Starting playlist sync for user {$user->id}");

        try {
            // Fetch all playlists
            $offset = 0;
            $limit = 50;
            $allPlaylists = [];

            while (true) {
                $response = Http::withHeaders($headers)
                    ->get("https://api.spotify.com/v1/me/playlists?limit={$limit}&offset={$offset}");

                if (!$response->successful()) {
                    Log::error("Failed to fetch playlists: " . $response->body());
                    break;
                }

                $playlists = $response->json()['items'] ?? [];

                if (empty($playlists)) {
                    break;
                }

                $allPlaylists = array_merge($allPlaylists, $playlists);
                $offset += $limit;
            }

            Log::info("Found " . count($allPlaylists) . " playlists");

            $syncedCount = 0;

            foreach ($allPlaylists as $spotifyPlaylist) {
                // Skip Liked Songs placeholder or null playlists
                if (!$spotifyPlaylist || !isset($spotifyPlaylist['id'])) {
                    continue;
                }

                DB::beginTransaction();

                try {
                    // Find or create playlist
                    $playlist = Playlist::where('user_id', $user->id)
                        ->where('spotify_playlist_id', $spotifyPlaylist['id'])
                        ->first();

                    if (!$playlist) {
                        $playlist = Playlist::create([
                            'user_id' => $user->id,
                            'name' => $spotifyPlaylist['name'],
                            'spotify_playlist_id' => $spotifyPlaylist['id'],
                            'description' => $spotifyPlaylist['description'] ?? null,
                            'image_url' => $spotifyPlaylist['images'][0]['url'] ?? null,
                            'owner' => $spotifyPlaylist['owner']['display_name'] ?? null,
                            'is_public' => $spotifyPlaylist['public'] ?? false,
                            'tracks_count' => $spotifyPlaylist['tracks']['total'] ?? 0,
                        ]);
                    } else {
                        $playlist->update([
                            'name' => $spotifyPlaylist['name'],
                            'description' => $spotifyPlaylist['description'] ?? null,
                            'image_url' => $spotifyPlaylist['images'][0]['url'] ?? null,
                            'owner' => $spotifyPlaylist['owner']['display_name'] ?? null,
                            'is_public' => $spotifyPlaylist['public'] ?? false,
                            'tracks_count' => $spotifyPlaylist['tracks']['total'] ?? 0,
                        ]);
                    }

                    // Fetch playlist tracks
                    $trackOffset = 0;
                    $trackLimit = 100;
                    $songIds = [];

                    while (true) {
                        $tracksResponse = Http::withHeaders($headers)
                            ->get("https://api.spotify.com/v1/playlists/{$spotifyPlaylist['id']}/tracks?limit={$trackLimit}&offset={$trackOffset}");

                        if (!$tracksResponse->successful()) {
                            Log::error("Failed to fetch tracks for playlist {$spotifyPlaylist['name']}: " . $tracksResponse->body());
                            break;
                        }

                        $tracks = $tracksResponse->json()['items'] ?? [];

                        if (empty($tracks)) {
                            break;
                        }

                        foreach ($tracks as $item) {
                            $track = $item['track'] ?? null;

                            // Skip null tracks or local files
                            if (!$track || !isset($track['id']) || $track['is_local'] ?? false) {
                                continue;
                            }

                            $spotify_uri = $track['uri'];
                            $artists = implode(', ', array_column($track['artists'] ?? [], 'name'));
                            $images = json_encode($track['album']['images'] ?? []);
                            $addedAt = isset($item['added_at']) ? Carbon::parse($item['added_at'])->format("Y-m-d H:i:s") : null;

                            // Find or create song
                            $song = Song::where('spotify_uri', $spotify_uri)->first();

                            if (!$song) {
                                $song = Song::create([
                                    'title' => $track['name'],
                                    'artists' => $artists,
                                    'album' => $track['album']['name'] ?? '',
                                    'images' => $images,
                                    'preview_url' => $track['preview_url'],
                                    'added_at' => $addedAt,
                                    'spotify_uri' => $spotify_uri,
                                ]);
                            }

                            $songIds[] = $song->id;
                        }

                        $trackOffset += $trackLimit;
                    }

                    // Sync songs to playlist (replace all)
                    $playlist->songs()->sync($songIds);
                    $playlist->last_sync = Carbon::now();
                    $playlist->save();

                    DB::commit();
                    $syncedCount++;

                    Log::info("Synced playlist: {$spotifyPlaylist['name']} ({$playlist->songs()->count()} songs)");

                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error("Failed to sync playlist {$spotifyPlaylist['name']}: " . $e->getMessage());
                }
            }

            Log::info("Playlist sync completed. Synced {$syncedCount} playlists.");

        } catch (Exception $e) {
            Log::error("Playlist sync failed: " . $e->getMessage());
        }
    }
}
