<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'user_id', 'next_sync', 'last_sync', 'spotify_id'];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function songs()
    {
        return $this->belongsToMany(Song::class, 'playlist_song', 'playlist_id', 'song_id');
    }
}
