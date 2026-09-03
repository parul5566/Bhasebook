<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    protected $fillable = ['user_id', 'friend_id', 'status', 'accepted_at'];

    protected $casts = ['accepted_at' => 'datetime'];
}
