<?php

namespace App\Http\Controllers;

use App\Models\AiFlag;
use App\Models\AiRequest;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiController extends Controller
{
    public const DAILY_LIMIT = 25;

    public function __construct(private AiService $ai) {}

    public function assist(Request $request)
    {
        $me = $request->user();

        $used = AiRequest::where('user_id', $me->id)->where('created_at', '>', now()->subDay())->count();
        abort_if($used >= self::DAILY_LIMIT, 429, 'Daily AI limit reached. Try again tomorrow.');

        $data = $request->validate([
            'mode' => 'required|in:ideas,caption,rewrite,hashtags',
            'topic' => 'required|string|max:500',
        ]);

        $prompts = [
            'ideas' => "Give 3 short, creative post ideas for a personal social feed about: \"{$data['topic']}\". One idea per line, no numbering, max 15 words each.",
            'caption' => "Write one catchy, friendly social post (max 220 chars) about: \"{$data['topic']}\". Plain text only.",
            'rewrite' => "Rewrite this social post to be clearer and more engaging, keep the meaning, max 280 chars, plain text only: \"{$data['topic']}\"",
            'hashtags' => "Suggest 6 relevant hashtags (no spaces, lowercase, with #) for a post about: \"{$data['topic']}\". Space separated, nothing else.",
        ];

        $log = ['user_id' => $me->id, 'mode' => $data['mode'], 'succeeded' => true];
        try {
            $text = $this->ai->chat([
                ['role' => 'system', 'content' => 'You are Bhasebook AI, a helpful assistant inside a friendly social network. Be concise, warm and safe.'],
                ['role' => 'user', 'content' => $prompts[$data['mode']]],
            ]);
        } catch (\Throwable $e) {
            $log['succeeded'] = false;
            AiRequest::create($log);
            // graceful degradation — heuristic output
            $text = match ($data['mode']) {
                'ideas' => "Three angles on {$data['topic']}:\n1. A behind-the-scenes moment\n2. A lesson you learned\n3. A question for your friends",
                'caption' => "{$data['topic']} — honestly, this made my day. What do you all think? #bhasebook",
                'rewrite' => $data['topic'],
                'hashtags' => '#'.str_replace(' ', '', preg_replace('/[^a-z0-9 ]/i', '', strtolower($data['topic']))).' #bhasebook #community',
            };
            return response()->json(['result' => $text, 'fallback' => true]);
        }

        AiRequest::create($log);

        return response()->json(['result' => $text, 'fallback' => false]);
    }

    public function recommendations(Request $request)
    {
        $me = $request->user();

        $hidden = DB::table('recommendation_feedback')->where('user_id', $me->id)->get()
            ->groupBy('kind')->map(fn ($rows) => $rows->pluck('item_id'));

        return response()->json([
            'people' => $this->people($me, $hidden->get('user', collect())),
            'groups' => $this->groups($me, $hidden->get('group', collect())),
            'pages' => $this->pages($me, $hidden->get('page', collect())),
        ]);
    }

    public function hideRecommendation(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'kind' => 'required|in:user,group,page,post',
            'item_id' => 'required|integer',
        ]);
        DB::table('recommendation_feedback')->updateOrInsert(
            ['user_id' => $me->id, 'kind' => $data['kind'], 'item_id' => $data['item_id']],
            ['created_at' => now(), 'updated_at' => now()],
        );
        return response()->json(['ok' => true]);
    }

    private function people(User $me, $hidden)
    {
        $friendIds = $me->friendIds();
        $candidateIds = collect();
        // friends of friends (weighted by mutual count)
        if ($friendIds) {
            $candidateIds = DB::table('friendships as f1')
                ->join('friendships as f2', 'f1.user_id', '=', 'f2.friend_id')
                ->whereIn('f2.user_id', $friendIds)
                ->where('f1.status', 'accepted')->where('f2.status', 'accepted')
                ->whereNotIn('f1.friend_id', [...$friendIds, $me->id])
                ->selectRaw('f1.friend_id as id, COUNT(*) as mutual')
                ->groupBy('f1.friend_id')->orderByDesc('mutual')->limit(8)->get();
        }
        $ids = $candidateIds->pluck('id');
        $mutuals = $candidateIds->pluck('mutual', 'id');

        $people = collect();
        if ($ids->isNotEmpty()) {
            $people = User::whereIn('id', $ids)->where('status', 'active')
                ->when($hidden->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $hidden))
                ->get()->map(fn ($u) => [
                    ...ProfileController::basicUser($u),
                    'mutual' => (int) ($mutuals[$u->id] ?? 0),
                    'reason' => 'friends of friends',
                ]);
        }
        if ($people->count() < 6) {
            $filler = User::where('id', '!=', $me->id)->where('status', 'active')
                ->whereNotIn('id', [...$friendIds, $me->id, ...$people->pluck('id')])
                ->when($hidden->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $hidden))
                ->inRandomOrder(strtotime('today'))->limit(6 - $people->count())->get()
                ->map(fn ($u) => [...ProfileController::basicUser($u), 'mutual' => count(\App\Services\SocialService::mutualFriendIds($me, $u)), 'reason' => 'popular on Bhasebook']);
            $people = $people->concat($filler);
        }

        return $people->values()->all();
    }

    private function groups(User $me, $hidden)
    {
        $myGroups = DB::table('group_members')->where('user_id', $me->id)->where('status', 'active')->pluck('group_id');
        return Group::whereNull('deleted_at')->where('privacy', 'public')
            ->when($myGroups->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $myGroups))
            ->when($hidden->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $hidden))
            ->withCount(['members as members_count' => fn ($m) => $m->where('group_members.status', 'active')])
            ->orderByDesc('members_count')->limit(5)->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'members_count' => $g->members_count,
                'description' => mb_substr((string) $g->description, 0, 90),
                'reason' => $g->members_count > 20 ? 'active community' : 'growing fast',
            ])->values()->all();
    }

    private function pages(User $me, $hidden)
    {
        $followed = DB::table('page_followers')->where('user_id', $me->id)->pluck('page_id');
        return Page::whereNull('deleted_at')
            ->when($followed->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $followed))
            ->when($hidden->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $hidden))
            ->withCount('followers as followers_count')
            ->orderByDesc('followers_count')->limit(5)->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'category' => $p->category,
                'followers_count' => $p->followers_count, 'reason' => 'popular in '.$p->category,
            ])->values()->all();
    }
}
