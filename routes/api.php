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

