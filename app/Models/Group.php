<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'cover', 'privacy', 'requires_approval', 'rules', 'created_by'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')
            ->withPivot('role', 'status')->withTimestamps();
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activeMembersCount(): int
    {
        return $this->members()->wherePivot('status', 'active')->count();
    }
}
