<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public static function serializePosts($posts, ?User $me): array
    {
        return collect($posts)->map(fn ($p) => self::serializePost($p, $me))->values()->all();
    }

    public static function serializePost(Post $p, ?User $me): array
    {
        $reactionCounts = $p->reactions->groupBy('type')->map->count()->all();
        $myReaction = $me
            ? $p->reactions->firstWhere('user_id', $me->id)?->type
            : null;

        $author = null;
        if ($p->page_id && $p->page) {
            $author = [
                'type' => 'page',
                'id' => $p->page->id,
                'name' => $p->page->name,
                'avatar_url' => $p->page->avatar ? asset('storage/'.$p->page->avatar) : null,
                'hue' => crc32($p->page->name) % 360,
            ];
        } elseif ($p->user) {
            $author = array_merge(ProfileController::basicUser($p->user), ['type' => 'user']);
        }

        return [
            'id' => $p->id,
            'author' => $author,
            'group' => $p->group ? ['id' => $p->group->id, 'name' => $p->group->name] : null,
            'content' => $p->content,
            'type' => $p->type,
            'visibility' => $p->visibility,
            'feeling' => $p->feeling,
            'location' => $p->location,
            'link' => $p->link_url ? [
                'url' => $p->link_url,
                'title' => $p->link_title,
                'description' => $p->link_description,
                'image' => $p->link_image ? asset('storage/'.$p->link_image) : null,
            ] : null,
            'media' => $p->media->map(fn ($m) => [
                'url' => asset('storage/'.$m->path),
                'mime' => $m->mime,
                'kind' => $m->kind,
            ])->all(),
            'poll' => $p->type === 'poll' ? [
                'options' => $p->pollOptions->map(function ($o) use ($me, $p) {
                    $votes = $o->votes()->count();
                    return [
                        'id' => $o->id,
                        'text' => $o->text,
                        'votes' => $votes,
                        'percent' => null,
                        'voted' => $me ? $o->votes()->where('user_id', $me->id)->exists() : false,
                    ];
                })->all(),
                'total_votes' => $p->pollOptions->sum(fn ($o) => $o->votes()->count()),
                'my_vote' => $me ? $p->pollOptions->map(fn ($o) => $o->votes->contains('user_id', $me->id) ? $o->id : null)->filter()->first() : null,
            ] : null,
            'tags' => $p->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
            'shared_post' => $p->sharedPost ? self::serializePost($p->sharedPost->loadMissing('media', 'pollOptions.votes', 'reactions', 'tags', 'user:id,name,avatar', 'page:id,name,avatar'), $me) : null,
            'shared_content' => $p->sharedPost ? null : null,
            'reaction_counts' => $reactionCounts,
            'reaction_total' => array_sum($reactionCounts),
            'my_reaction' => $myReaction,
            'comment_count' => $p->comments_count ?? $p->comments->count(),
            'saved' => $me ? $p->saves()->where('user_id', $me->id)->exists() : false,
            'pinned' => (bool) $p->pinned,
            'view_count' => $p->view_count,
            'created_at' => $p->created_at->diffForHumans(),
            'created_at_iso' => $p->created_at->toISOString(),
            'edited' => $p->edited_at !== null,
            'can_edit' => $me && (($p->user_id === $me->id) || ($p->page_id && $p->page->roles()->where('user_id', $me->id)->whereIn('role', ['admin', 'editor'])->exists())),
        ];
    }

    // Feed endpoint (cursor paginated)
    public function feed(Request $request)
    {
        $me = $request->user();
        $cursor = (int) $request->query('cursor', 0);
        $limit = 8;

        $q = Post::with(['media', 'pollOptions.votes', 'reactions', 'comments', 'tags:id,name', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->whereNull('page_id') // page posts need page follow; handled below
            ->visibleTo($me)
            ->orderByDesc('id')
            ->limit($limit + 1);

        if ($cursor > 0) {
            $q->where('id', '<', $cursor);
        }

        $posts = $q->get()->filter(
            fn ($p) => $p->user_id === null || ! \App\Services\SocialService::blockedBetween($me, $p->user)
        )->values();

        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit);

        return response()->json([
            'posts' => self::serializePosts($posts, $me),
            'next_cursor' => $hasMore ? $posts->last()->id : null,
        ]);
    }
}
