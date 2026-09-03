<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PollOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['post_id', 'text'];

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }
}
