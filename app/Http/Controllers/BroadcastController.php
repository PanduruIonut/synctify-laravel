<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use App\Models\User;

class BroadcastController extends Controller
{
    public function auth(Request $request)
    {
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        
        // Extract spotify_id from channel name (private-user-{spotify_id})
        if (preg_match('/^private-user-(.+)$/', $channelName, $matches)) {
            $spotifyId = $matches[1];
            $user = User::where('spotify_id', $spotifyId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'User not found'], 401);
            }
        } else {
            return response()->json(['error' => 'Invalid channel'], 401);
        }

        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            ['cluster' => 'eu']
        );

        $auth = $pusher->authorizeChannel($channelName, $socketId);

        return response()->json(json_decode($auth));
    }
}
