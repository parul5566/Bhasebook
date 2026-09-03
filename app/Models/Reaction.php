<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    public const TYPES = ['like', 'love', 'care', 'haha', 'wow', 'sad', 'angry'];

    protected $fillable = ['user_id', 'reactable_type', 'reactable_id', 'type'];

    public function reactable()
    {
        return $this->morphTo();
    }
}
