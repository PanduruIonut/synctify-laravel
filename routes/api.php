<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpotifyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/callback', [SpotifyController::class, 'callback']);
Route::post('/me', [SpotifyController::class, 'me']);
Route::post('/create_playlist', [SpotifyController::class, 'create_playlist']);
Route::get('/user/get_liked_songs/{id}', [SpotifyController::class, 'get_liked_songs']);
Route::post('/user/refresh_token', [SpotifyController::class, 'refresh_token']);
Route::post('/broadcasting/auth', [App\Http\Controllers\BroadcastController::class, 'auth']);

Route::get('/user/on_this_day/{id}', [SpotifyController::class, 'on_this_day']);
Route::get('/user/auth_status/{id}', [SpotifyController::class, 'get_auth_status']);

// Playlist routes
Route::post('/user/sync_playlists', [SpotifyController::class, 'sync_playlists']);
Route::get('/user/playlists/{id}', [SpotifyController::class, 'get_playlists']);
Route::get('/user/{spotify_id}/playlist/{playlist_id}', [SpotifyController::class, 'get_playlist_songs']);
Route::post('/user/{spotify_id}/playlist/{playlist_id}/import-to-liked', [SpotifyController::class, 'import_to_liked_songs']);
