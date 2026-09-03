<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Save extends Model
{
    protected $fillable = ['user_id', 'savable_type', 'savable_id'];

    public function savable()
    {
        return $this->morphTo();
    }
}
