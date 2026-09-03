<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    protected $fillable = ['user_id', 'kind', 'media_path', 'text_content', 'background', 'visibility', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function views()
    {
        return $this->hasMany(StoryView::class);
    }

    public function scopeActive($q)
    {
        return $q->where('expires_at', '>', now());
    }
}
