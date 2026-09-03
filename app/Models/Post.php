<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'page_id', 'group_id', 'shared_post_id', 'content', 'type',
        'visibility', 'feeling', 'location', 'link_url', 'link_title', 'link_description',
        'link_image', 'pinned', 'edited_at',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function sharedPost()
    {
        return $this->belongsTo(Post::class, 'shared_post_id');
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function pollOptions()
    {
        return $this->hasMany(PollOption::class);
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function tags()
    {
        return $this->belongsToMany(User::class, 'post_tags', 'post_id', 'user_id');
    }

    public function saves()
    {
        return $this->morphMany(Save::class, 'savable');
    }

    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class);
    }

    public function visibilityUsers()
    {
        return $this->belongsToMany(User::class, 'post_visibility_users', 'post_id', 'user_id');
    }

    /**
     * Scope: posts visible to the given user (respects blocks, visibility, group/page membership).
     */
    public function scopeVisibleTo(Builder $q, ?User $me): Builder
    {
        return $q->where(function (Builder $w) use ($me) {
            $friendIds = $me ? $me->friendIds() : [];

            $w->where(function (Builder $v) use ($me, $friendIds) {
                // own posts
                if ($me) {
                    $v->where('user_id', $me->id);
                }
                // public posts
                $v->orWhere('visibility', 'public');
                // friends-only posts from friends
                if ($me && count($friendIds)) {
                    $v->orWhere(function ($f) use ($friendIds) {
                        $f->where('visibility', 'friends')->whereIn('user_id', $friendIds);
                    });
                    // custom posts where user is included
                    $v->orWhere(function ($c) use ($friendIds, $me) {
                        $c->where('visibility', 'custom')
                            ->whereIn('user_id', $friendIds)
                            ->whereHas('visibilityUsers', fn ($u) => $u->where('user_id', $me->id));
                    });
                }
            });

            // followed pages' posts (any visibility)
            if ($me) {
                $w->orWhereHas('page', function ($p) use ($me) {
                    $p->whereHas('followers', fn ($f) => $f->where('user_id', $me->id));
                });
                // joined groups' posts
                $w->orWhereHas('group', function ($g) use ($me) {
                    $g->whereHas('members', fn ($m) => $m->where('user_id', $me->id)->where('status', 'active'));
                });
            }
        })->whereHas('user', function ($u) {
            $u->where('status', 'active');
        });
    }
}
