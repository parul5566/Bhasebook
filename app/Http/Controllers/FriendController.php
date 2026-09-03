<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FriendController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $friendIds = $me->friendIds();

        $friends = User::whereIn('id', $friendIds)
            ->when($request->q, fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->orderBy('name')->limit(100)->get()
            ->map(fn ($u) => ProfileController::basicUser($u));

        $received = Friendship::where('friend_id', $me->id)->where('status', 'pending')
            ->with('user')->latest()->get()
            ->map(fn ($f) => ProfileController::basicUser($f->user));

        $sent = Friendship::where('user_id', $me->id)->where('status', 'pending')
            ->with('friend')->latest()->get()
            ->map(fn ($f) => ProfileController::basicUser($f->friend));

        // Suggestions: friends-of-friends, not friends, not pending, not blocked
        $suggestions = collect();
        if (count($friendIds) > 0) {
            $mutualCounts = \DB::table('friendships as f1')
                ->join('friendships as f2', function ($j) use ($me) {
                    $j->on('f1.friend_id', '=', 'f2.user_id')
                        ->where(function ($qq) {
                            $qq->where('f2.status', 'accepted')->orWhereNull('f2.status');
                        });
                })
                ->select('f2.friend_id as uid', \DB::raw('count(*) as mutual'))
                ->where('f1.status', 'accepted')
                ->where('f2.status', 'accepted')
                ->where('f2.friend_id', '!=', $me->id)
                ->whereNotIn('f2.friend_id', $friendIds)
                ->whereNotIn('f2.friend_id', function ($q) use ($me) {
                    $q->select('friend_id')->from('friendships')
                        ->where('user_id', $me->id)->where('status', 'pending');
                })
                ->whereNotIn('f2.friend_id', function ($q) use ($me) {
                    $q->select('user_id')->from('friendships')
                        ->where('friend_id', $me->id)->where('status', 'pending');
                })
                ->groupBy('f2.friend_id')
                ->orderByDesc('mutual')
                ->limit(12)
                ->get();
            $suggestions = User::whereIn('id', $mutualCounts->pluck('uid'))
                ->whereNotIn('id', function ($q) use ($me) {
                    $q->select('blocked_id')->from('blocks')->where('user_id', $me->id);
                })
                ->get()
                ->map(fn ($u) => array_merge(ProfileController::basicUser($u), [
                    'mutual' => $mutualCounts->firstWhere('uid', $u->id)->mutual ?? 0,
                ]));
        }

        if ($suggestions->isEmpty()) {
            $suggestions = User::where('id', '!=', $me->id)
                ->whereNotIn('id', $friendIds)
                ->where('status', 'active')
                ->inRandomOrder()->limit(8)->get()
                ->map(fn ($u) => array_merge(ProfileController::basicUser($u), ['mutual' => 0]));
        }

        return Inertia::render('Friends/Index', [
            'friends' => $friends,
            'received' => $received,
            'sent' => $sent,
            'suggestions' => $suggestions,
            'filter' => $request->string('tab', 'all'),
        ]);
    }

    public function request(Request $request, User $user)
    {
        $res = SocialService::sendFriendRequest($request->user(), $user);
        return back()->with($res['ok'] ? 'status' : 'error', $res['message']);
    }

    public function accept(Request $request, User $user)
    {
        $res = SocialService::acceptFriendRequest($request->user(), $user->id);
        return back()->with($res['ok'] ? 'status' : 'error', $res['message']);
    }

    public function decline(Request $request, User $user)
    {
        Friendship::where('user_id', $user->id)->where('friend_id', $request->user()->id)
            ->where('status', 'pending')->delete();
        return back()->with('status', 'Request declined.');
    }

    public function cancel(Request $request, User $user)
    {
        Friendship::where('user_id', $request->user()->id)->where('friend_id', $user->id)
            ->where('status', 'pending')->delete();
        return back()->with('status', 'Request cancelled.');
    }

    public function unfriend(Request $request, User $user)
    {
        $res = SocialService::removeFriendship($request->user(), $user->id);
        return back()->with('status', $res['message']);
    }

    public function toggleFollow(Request $request, User $user)
    {
        $res = SocialService::toggleFollow($request->user(), $user);
        return back()->with('status', $res['message']);
    }

    public function toggleBlock(Request $request, User $user)
    {
        $res = SocialService::toggleBlock($request->user(), $user);
        return back()->with('status', $res['message']);
    }

    public function blockedIndex(Request $request)
    {
        $blocked = \App\Models\Block::where('user_id', $request->user()->id)
            ->with('blocked:id,name,avatar')->get()
            ->map(fn ($b) => ProfileController::basicUser($b->blocked));
        return Inertia::render('Settings/Blocked', ['blocked' => $blocked]);
    }
}
