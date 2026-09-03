<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteNotification extends Model
{
    protected $fillable = ['user_id', 'actor_id', 'type', 'category', 'notifiable_type', 'notifiable_id', 'data', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
