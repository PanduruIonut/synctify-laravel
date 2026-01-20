<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\SpotifyController;
use App\Models\User;

class SyncLikedSongsPlaylist extends Command
{
    protected $signature = 'app:sync-liked-songs-playlist';
    protected $description = 'Sync liked songs for all active users';

    public function handle()
    {
        $users = User::where('is_active', true)->get();
        
        foreach ($users as $user) {
            Log::info('Syncing playlist for user ' . $user->id);
            
            // Always refresh token before syncing to ensure it's valid
            try {
                $newToken = SpotifyController::refresh_access_token(
                    $user->client_id, 
                    $user->client_secret, 
                    $user->refresh_token
                );
                
                // Use the fresh token for sync
                SpotifyController::sync_playlist($newToken, $user->refresh_token, $user->expires_in);
                Log::info('Playlist synced for user ' . $user->id);
            } catch (\Exception $e) {
                Log::error('Failed to sync for user ' . $user->id . ': ' . $e->getMessage());
            }
        }
    }
}
