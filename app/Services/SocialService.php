<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\SiteNotification;
use App\Models\User;

class SocialService
{
    // ---- Friendships ----

    public static function sendFriendRequest(User $from, User $to): array
    {
        if ($from->id === $to->id) {
            return ['ok' => false, 'message' => 'You cannot add yourself.'];
        }
        if (Block::where(function ($q) use ($from, $to) {
            $q->where('user_id', $from->id)->where('blocked_id', $to->id)
                ->orWhere('user_id', $to->id)->where('blocked_id', $from->id);
        })->exists()) {
            return ['ok' => false, 'message' => 'Not available.'];
        }
        if ($to->friend_request_privacy === 'friends_of_friends'
            && ! self::haveMutualFriend($from, $to)) {
            return ['ok' => false, 'message' => 'This person only accepts requests from friends of friends.'];
        }
        if ($to->friend_request_privacy === 'none') {
            return ['ok' => false, 'message' => 'This person does not accept friend requests.'];
        }

        $existing = Friendship::where(function ($q) use ($from, $to) {
            $q->where('user_id', $from->id)->where('friend_id', $to->id)
                ->orWhere('user_id', $to->id)->where('friend_id', $from->id);
        })->first();
        if ($existing) {
            if ($existing->status === 'accepted') {
                return ['ok' => false, 'message' => 'You are already friends.'];
            }
            if ((int) $existing->user_id === (int) $to->id) {
                // They already sent us one — auto accept
                $existing->update(['status' => 'accepted', 'accepted_at' => now()]);
                self::notify($to, $from, 'friend_accept', 'friend', $from, 'accepted your request');
                return ['ok' => true, 'message' => 'You are now friends!'];
            }
            // our own pending request exists — allow re-sending after privacy widened
            if (! $to->isFriendWith($from->id) && $to->friend_request_privacy === 'friends_of_friends'
                && ! self::haveMutualFriend($from, $to)) {
                return ['ok' => false, 'message' => 'This person only accepts requests from friends of friends.'];
            }
            return ['ok' => false, 'message' => 'Request already sent.'];
        }

        Friendship::create(['user_id' => $from->id, 'friend_id' => $to->id, 'status' => 'pending']);
        self::notify($to, $from, 'friend_request', 'friend', $from, 'sent you a friend request');
        return ['ok' => true, 'message' => 'Request sent.'];
    }

    public static function acceptFriendRequest(User $user, int $requesterId): array
    {
        $f = Friendship::where('user_id', $requesterId)->where('friend_id', $user->id)
            ->where('status', 'pending')->first();
        if (! $f) {
            return ['ok' => false, 'message' => 'Request not found.'];
        }
        $f->update(['status' => 'accepted', 'accepted_at' => now()]);
        $requester = User::find($requesterId);
        if ($requester) {
            self::notify($requester, $user, 'friend_accept', 'friend', $user, 'accepted your friend request');
        }
        return ['ok' => true, 'message' => 'You are now friends!'];
    }

    public static function removeFriendship(User $a, int $b): array
    {
        Friendship::where(function ($q) use ($a, $b) {
            $q->where('user_id', $a->id)->where('friend_id', $b)
                ->orWhere('user_id', $b)->where('friend_id', $a->id);
        })->delete();
        return ['ok' => true, 'message' => 'Friend removed.'];
    }

    public static function mutualFriendIds(User $a, User $b): array
    {
        return array_values(array_intersect($a->friendIds(), $b->friendIds()));
    }

    public static function haveMutualFriend(User $a, User $b): bool
    {
        return count(self::mutualFriendIds($a, $b)) > 0;
    }

    // ---- Follows ----

    public static function toggleFollow(User $follower, User $target): array
    {
        if ($follower->id === $target->id) {
            return ['ok' => false, 'message' => 'You cannot follow yourself.'];
        }
        $exists = Follow::where('follower_id', $follower->id)->where('followee_id', $target->id)->exists();
        if ($exists) {
            Follow::where('follower_id', $follower->id)->where('followee_id', $target->id)->delete();
            return ['ok' => true, 'following' => false, 'message' => 'Unfollowed.'];
        }
        Follow::create(['follower_id' => $follower->id, 'followee_id' => $target->id]);
        self::notify($target, $follower, 'follow', 'follow', $follower, 'started following you');
        return ['ok' => true, 'following' => true, 'message' => 'Following.'];
    }

    // ---- Blocks ----

    public static function toggleBlock(User $user, User $target): array
    {
        if ($user->id === $target->id) {
            return ['ok' => false, 'message' => 'You cannot block yourself.'];
        }
        $exists = Block::where('user_id', $user->id)->where('blocked_id', $target->id)->exists();
        if ($exists) {
            Block::where('user_id', $user->id)->where('blocked_id', $target->id)->delete();
            return ['ok' => true, 'blocked' => false, 'message' => 'Unblocked.'];
        }
        Block::create(['user_id' => $user->id, 'blocked_id' => $target->id]);
        // Remove friendship & follows both directions
        Friendship::where(function ($q) use ($user, $target) {
            $q->where('user_id', $user->id)->where('friend_id', $target->id)
                ->orWhere('user_id', $target->id)->where('friend_id', $user->id);
        })->delete();
        Follow::where(function ($q) use ($user, $target) {
            $q->where('follower_id', $user->id)->where('followee_id', $target->id)
                ->orWhere('follower_id', $target->id)->where('followee_id', $user->id);
        })->delete();
        return ['ok' => true, 'blocked' => true, 'message' => 'Blocked.'];
    }

    public static function blockedBetween(User $a, User $b): bool
    {
        return Block::where(function ($q) use ($a, $b) {
            $q->where('user_id', $a->id)->where('blocked_id', $b->id)
                ->orWhere('user_id', $b->id)->where('blocked_id', $a->id);
        })->exists();
    }

    // ---- Notifications ----

    public static function notify(User $for, ?User $actor, string $type, string $category, $notifiable = null, string $text = ''): void
    {
        if ($actor && $for->id === $actor->id) {
            return; // don't self-notify
        }
        SiteNotification::create([
            'user_id' => $for->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'category' => $category,
            'notifiable_type' => $notifiable ? $notifiable->getMorphClass() : null,
            'notifiable_id' => $notifiable?->getKey() ?? null,
            'data' => json_encode(['text' => $text]),
        ]);
    }
}
