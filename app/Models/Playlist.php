<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'next_sync',
        'last_sync',
        'spotify_playlist_id',
        'image_url',
        'owner',
        'is_public',
        'tracks_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'last_sync' => 'datetime',
        'next_sync' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function songs()
    {
        return $this->belongsToMany(Song::class, 'playlist_song', 'playlist_id', 'song_id');
    }
}
