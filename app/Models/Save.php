<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Save extends Model
{
    public function savable()
    {
        return $this->morphTo();
    }
}
