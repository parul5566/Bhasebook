<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['title', 'avatar_path', 'is_group', 'created_by', 'last_activity_at'];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
            ->withPivot('last_read_message_id')->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
