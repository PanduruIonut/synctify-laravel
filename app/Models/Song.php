<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Song extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'user_id', 'album', 'title', 'artists', 'preview_url', 'images', 'added_at', 'spotify_uri'];

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class, 'playlist_song', 'song_id', 'playlist_id');
    }
}
