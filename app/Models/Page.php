<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'category', 'about', 'avatar', 'cover', 'created_by'];

    public function roles()
    {
        return $this->belongsToMany(User::class, 'page_roles', 'page_id', 'user_id')
            ->withPivot('role')->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'page_followers', 'page_id', 'user_id')->withTimestamps();
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/'.$this->avatar) : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover ? asset('storage/'.$this->cover) : null;
    }
}
