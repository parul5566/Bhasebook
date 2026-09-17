<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'cover',
        'bio',
        'work',
        'education',
        'location',
        'birthday',
        'gender',
        'is_admin',
        'status',
        'suspended_until',
        'deactivated_at',
        'default_post_visibility',
        'profile_visibility',
        'friend_request_privacy',
        'notification_prefs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthday' => 'date',
            'suspended_until' => 'datetime',
            'deactivated_at' => 'datetime',
            'is_admin' => 'boolean',
            'verified' => 'boolean',
            'password' => 'hashed',
            'notification_prefs' => 'array',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/'.$this->avatar) : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover ? asset('storage/'.$this->cover) : null;
    }

    public function initialsHue(): int
    {
        $sum = 0;
        foreach (mb_str_split($this->name) as $ch) {
            $sum += mb_ord($ch);
        }
        return $sum % 360;
    }

    public function friendsCount(): int
    {
        return \DB::table('friendships')
            ->where(function ($q) {
                $q->where('user_id', $this->id)->orWhere('friend_id', $this->id);
            })
            ->where('status', 'accepted')
            ->count();
    }

    public function friendIds(): array
    {
        return \DB::table('friendships')
            ->where(function ($q) {
                $q->where('user_id', $this->id)->orWhere('friend_id', $this->id);
            })
            ->where('status', 'accepted')
            ->get()
            ->map(fn ($r) => (int) ($r->user_id === $this->id ? $r->friend_id : $r->user_id))
            ->unique()
            ->values()
            ->all();
    }

    public function isFriendWith(int $userId): bool
    {
        return \DB::table('friendships')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $this->id)->where('friend_id', $userId)
                    ->orWhere('user_id', $userId)->where('friend_id', $this->id);
            })
            ->where('status', 'accepted')
            ->exists();
    }

    public function unreadNotificationsCount(): int
    {
        return \DB::table('site_notifications')->where('user_id', $this->id)->where('read_at', null)->count();
    }

    public function unreadMessagesCount(): int
    {
        return \DB::table('messages')
            ->join('conversation_participants as cp', function ($j) {
                $j->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', $this->id);
            })
            ->where('messages.sender_id', '!=', $this->id)
            ->where(function ($q) {
                $q->whereNull('cp.last_read_message_id')
                    ->orWhereColumn('cp.last_read_message_id', '<', 'messages.id');
            })
            ->count();
    }

    // Relationships used in later phases
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class);
    }

    public function followedPages()
    {
        return $this->belongsToMany(Page::class, 'page_followers', 'user_id', 'page_id')->withTimestamps();
    }

    public function joinedGroups()
    {
        return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
            ->withPivot('role', 'status')->withTimestamps();
    }
}
