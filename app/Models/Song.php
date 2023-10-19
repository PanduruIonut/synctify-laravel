<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Song extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'user_id', 'album'];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
