<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class SpotifyController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $code = $data['code'];
        $CLIENT_ID = $data['client_id'];
        $CLIENT_SECRET = $data['client_secret'];
        $REDIRECT_URI = $data['redirect_uri'];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $auth_url = 'https://accounts.spotify.com/api/token';
        $auth_response = Http::withHeaders($headers)->asForm()->post($auth_url, [
            'grant_type' => 'authorization_code',
            'scope' => 'user-top-read',
            'code' => $code,
            'redirect_uri' => $REDIRECT_URI,
            'client_id' => $CLIENT_ID,
            'client_secret' => $CLIENT_SECRET,
        ]);

        if ($auth_response->status() != 200) {
            Log::info($auth_response->json());
            return response()->json(['error' => $auth_response->json()], $auth_response->status());
        }
        $auth_response_data = $auth_response->json();
        Log::info(json_encode($auth_response_data));
        $access_token = $auth_response_data['access_token'];
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];
        $spotify_response = Http::withHeaders($headers)->get('https://api.spotify.com/v1/me');
        if ($spotify_response->status() == 200) {
            $response_data = $spotify_response->json();
            $user_id = $response_data['id'];
            $user = User::where('spotify_id', $user_id)->first();
            if ($user) {
                $user->is_active = true;
                $user->save();
            }
        }
        return $auth_response_data;
    }


public function me(Request $request){
        $data = $request->json()->all();

        $access_token = $data['access_token'];

        if (!$access_token) {
            throw new Exception('Access token not found. Please authorize the app first.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];

        $spotify_response = Http::withHeaders($headers)->get('https://api.spotify.com/v1/me');
        if ($spotify_response->status() == 200) {
            $response_data = $spotify_response->json();
            return $response_data;
        } else {
            throw new Exception('Failed to fetch Spotify data');
        }
    }
}
