<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\User;
use Carbon\Carbon;
use App\Http\Controllers\SpotifyController;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            $users = User::all();
            foreach ($users as $user) {
                if ($user->is_active) {
                    Log::info('Syncing playlist for user ' . $user->id);
                    SpotifyController::sync_playlist($user->access_token, $user->refresh_token, $user->expires_in);
                    Log::info('Playlist synced for user ' . $user->id);
                }
            }
        })->everySixHours();

        $schedule->call(function () {
            Log::info('Check if token is about to expire.');
            $users = User::all();

            foreach ($users as $user) {
                if ($this->isTokenAboutToExpire($user)) {
                    Log::info('Refreshing token for user ' . $user->id);
                    SpotifyController::refresh_access_token($user->client_id, $user->client_secret, $user->refresh_token);
                    Log::info('Token refreshed for user ' . $user->id);
                }
            }
        })->everyThirtyMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected function isTokenAboutToExpire($user)
    {
        if (!$user->access_token) {
            return false;
        }

        $now = Carbon::now();
        $expirationDatetime = Carbon::createFromTimestamp($user->expires_in);
        $expirationDatetime->subHour();

        return $now->lessThan($expirationDatetime);
    }
}
