<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details', 'status', 'moderator_note', 'handled_by', 'handled_at'];

    protected $casts = ['handled_at' => 'datetime'];

    public const REASONS = ['spam', 'harassment', 'nudity', 'violence', 'misinformation', 'hate', 'other'];

    public function reportable()
    {
        return $this->morphTo();
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
