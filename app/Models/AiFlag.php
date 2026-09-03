<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlag extends Model
{
    protected $fillable = ['flaggable_type', 'flaggable_id', 'risk_score', 'reasons', 'suggested_action', 'reviewed_at'];

    protected $casts = [
        'reasons' => 'array',
        'risk_score' => 'float',
        'reviewed_at' => 'datetime',
    ];
}
