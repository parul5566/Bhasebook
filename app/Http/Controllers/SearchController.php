<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Hashtag;
use App\Models\Page;
use App\Models\Post;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Search/Results', $this->showData($request));
    }

    public function showData(Request $request): array
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));
        $tab = (string) $request->query('tab', 'all');
        if ($q === '') {
            return [
                'q' => '',
                'tab' => 'all',
                'results' => [],
                'recents' => $this->recents($me),
            ];
        }

        SearchHistory::firstOrCreate(
            ['user_id' => $me->id, 'term' => mb_substr(mb_strtolower($q), 0, 100)],
        )->touch();

        $results = match ($tab) {
            'people' => ['people' => $this->people($me, $q, 30)],
            'posts' => ['posts' => $this->posts($me, $q, 20)],
            'groups' => ['groups' => $this->groups($me, $q, 20)],
            'pages' => ['pages' => $this->pages($me, $q, 20)],
            'reels' => ['reels' => $this->reels($me, $q, 20)],
            'hashtags' => ['hashtags' => $this->hashtags($q, 20)],
            default => [
                'people' => $this->people($me, $q, 6),
                'posts' => $this->posts($me, $q, 5),
                'groups' => $this->groups($me, $q, 4),
                'pages' => $this->pages($me, $q, 4),
                'reels' => $this->reels($me, $q, 4),
                'hashtags' => $this->hashtags($q, 6),
            ],
        };

        return [
            'q' => $q,
            'tab' => $tab,
            'results' => $results,
            'recents' => $this->recents($me),
        ];
    }

    public function suggestions(Request $request)
    {
        $me = $request->user();
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        if (mb_strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $suggestions = [];
        foreach ($this->people($me, $q, 4) as $p) {
            $suggestions[] = ['type' => 'person', ...$p];
        }
        foreach ($this->groups($me, $q, 3) as $g) {
            $suggestions[] = ['type' => 'group', ...$g];
        }
        foreach ($this->pages($me, $q, 3) as $p) {
            $suggestions[] = ['type' => 'page', ...$p];
        }
        foreach ($this->hashtags($q, 3) as $h) {
            $suggestions[] = ['type' => 'hashtag', ...$h];
        }

        return response()->json(['suggestions' => array_slice($suggestions, 0, 10)]);
    }

    public function clearRecents(Request $request)
    {
        SearchHistory::where('user_id', $request->user()->id)->delete();
        return response()->json(['ok' => true]);
    }

    private function recents(User $me): array
    {
        return SearchHistory::where('user_id', $me->id)
            ->latest('updated_at')->distinct()->limit(8)
            ->pluck('term')->values()->all();
    }

    private function people(User $me, string $q, int $limit): array
    {
        $blocked = DB::table('blocks')->where(function ($b) use ($me) {
            $b->where('user_id', $me->id)->orWhere('blocked_id', $me->id);
        })->get()->map(fn ($r) => $r->user_id === $me->id ? $r->blocked_id : $r->user_id)->all();

        $users = User::where('id', '!=', $me->id)
            ->whereIn('status', ['active', 'suspended'])
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            })
            ->when($blocked, fn ($w) => $w->whereNotIn('id', $blocked))
            ->orderByRaw('LOCATE(?, name) asc', [$q])
            ->limit($limit)->get();

        $friendIds = $me->friendIds();
        return $users->map(function ($u) use ($me, $friendIds) {
            $incoming = \App\Models\Friendship::where('user_id', $u->id)->where('friend_id', $me->id)->where('status', 'pending')->exists();
            $outgoing = \App\Models\Friendship::where('user_id', $me->id)->where('friend_id', $u->id)->where('status', 'pending')->exists();
            return [
                ...ProfileController::basicUser($u),
                'bio' => $u->bio,
                'mutual' => count(SocialService::mutualFriendIds($me, $u)),
                'is_friend' => in_array($u->id, $friendIds),
                'request_sent' => $outgoing,
                'request_incoming' => $incoming,
                'following' => \App\Models\Follow::where('follower_id', $me->id)->where('followee_id', $u->id)->exists(),
            ];
        })->values()->all();
    }

    private function posts(User $me, string $q, int $limit): array
    {
        $posts = Post::search($q)
            ->visibleTo($me)
            ->with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->latest()->limit($limit)->get()
            ->filter(fn ($p) => ! $p->user_id || $p->user_id === $me->id || ! SocialService::blockedBetween($me, $p->user))
            ->values();

        return PostController::serializePosts($posts, $me);
    }

    private function groups(User $me, string $q, int $limit): array
    {
        $memberIds = DB::table('group_members')->where('user_id', $me->id)->where('status', 'active')->pluck('group_id');
        return Group::whereNull('deleted_at')
            ->where('name', 'like', "%{$q}%")
            ->where(function ($w) use ($me, $memberIds) {
                $w->where('privacy', 'public')->orWhereIn('id', $memberIds);
            })
            ->withCount(['members as members_count' => fn ($m) => $m->where('group_members.status', 'active')])
            ->limit($limit)->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'privacy' => $g->privacy,
                'description' => mb_substr((string) $g->description, 0, 160),
                'members_count' => $g->members_count,
                'joined' => $memberIds->contains($g->id),
            ])->values()->all();
    }

    private function pages(User $me, string $q, int $limit): array
    {
        $followed = DB::table('page_followers')->where('user_id', $me->id)->pluck('page_id');
        return Page::whereNull('deleted_at')
            ->where('name', 'like', "%{$q}%")
            ->withCount('followers as followers_count')
            ->limit($limit)->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'category' => $p->category,
                'about' => mb_substr((string) $p->about, 0, 160),
                'followers_count' => $p->followers_count,
                'following' => $followed->contains($p->id),
            ])->values()->all();
    }

    private function reels(User $me, string $q, int $limit): array
    {
        $reels = Post::where('type', 'reel')
            ->where('content', 'like', "%{$q}%")
            ->with(['media', 'reactions', 'saves', 'user:id,name,avatar', 'page:id,name,avatar'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->visibleTo($me)
            ->latest()->limit($limit)->get();

        return collect(PostController::serializePosts($reels, $me))->map(fn ($r) => [
            'id' => $r['id'],
            'author' => $r['author'],
            'content' => $r['content'],
            'media' => $r['media'],
            'reaction_total' => $r['reaction_total'],
            'comment_count' => $r['comment_count'],
        ])->values()->all();
    }

    private function hashtags(string $q, int $limit): array
    {
        $tag = ltrim($q, '#');
        return Hashtag::where('tag', 'like', "%{$tag}%")
            ->orderByDesc('posts_count')->limit($limit)->get()
            ->map(fn ($h) => ['tag' => $h->tag, 'posts_count' => $h->posts_count])
            ->values()->all();
    }
}
