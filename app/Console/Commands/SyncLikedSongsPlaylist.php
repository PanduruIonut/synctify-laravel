<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\SpotifyController;
use App\Models\User;

class SyncLikedSongsPlaylist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-liked-songs-playlist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();
        foreach ($users as $user) {
            if ($user->is_active) {
                Log::info('Syncing playlist for user ' . $user->id);
                SpotifyController::sync_playlist($user->access_token, $user->refresh_token, $user->expires_in);
                Log::info('Playlist synced for user ' . $user->id);
            }
        }
    }
}
