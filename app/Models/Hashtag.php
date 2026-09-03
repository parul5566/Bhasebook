<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hashtag extends Model
{
    protected $fillable = ['tag', 'posts_count'];

    public function posts()
    {
        return $this->belongsToMany(Post::class);
    }
}
