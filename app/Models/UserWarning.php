<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWarning extends Model
{
    protected $fillable = ['user_id', 'warned_by', 'reason', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
