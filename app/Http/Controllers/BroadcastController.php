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
        Log::info('BroadcastController auth method called.');

        $token = $request->query('token');
        $user = User::where('access_token', $token)->first();

        if(!$user) {
            return response('Unauthorized', 401);
        }

        $pusher = new Pusher(config('broadcasting.connections.pusher.key'), config('broadcasting.connections.pusher.secret'), config('broadcasting.connections.pusher.app_id'));
        $auth=$pusher->authorizeChannel($request->input('channel_name'), $request->input('socket_id'));
        return response($auth);
    }
}
