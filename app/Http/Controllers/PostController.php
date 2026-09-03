<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Reaction;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    // ---------- Serialization ----------

    public static function serializePosts($posts, ?User $me): array
    {
        return collect($posts)->map(fn ($p) => self::serializePost($p, $me))->values()->all();
    }

    public static function serializePost(Post $p, ?User $me): array
    {
        $reactionCounts = $p->reactions->groupBy('type')->map->count()->all();
        $myReaction = $me ? $p->reactions->firstWhere('user_id', $me->id)?->type : null;

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

        $poll = null;
        if ($p->type === 'poll') {
            $options = $p->pollOptions->map(function ($o) {
                $votes = $o->votes->count();
                return ['id' => $o->id, 'text' => $o->text, 'votes' => $votes];
            });
            $total = $options->sum('votes');
            $myVoteId = $me ? $p->pollOptions->filter(fn ($o) => $o->votes->contains('user_id', $me->id))->pluck('id')->first() : null;
            $poll = [
                'options' => $options->map(fn ($o) => array_merge($o, [
                    'percent' => $total > 0 ? (int) round($o['votes'] * 100 / $total) : 0,
                    'voted' => $myVoteId === $o['id'],
                ]))->values()->all(),
                'total_votes' => $total,
                'my_vote' => $myVoteId,
            ];
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
            ])->values()->all(),
            'poll' => $poll,
            'tags' => $p->tags->map(fn ($t) => ProfileController::basicUser($t))->values()->all(),
            'shared_post' => $p->sharedPost ? self::serializePost($p->sharedPost, $me) : null,
            'reaction_counts' => $reactionCounts,
            'reaction_total' => array_sum($reactionCounts),
            'my_reaction' => $myReaction,
            'comment_count' => $p->comments_count ?? $p->comments->count(),
            'saved' => $me ? $p->saves->contains('user_id', $me->id) : ($p->relationLoaded('saves') ? false : false),
            'pinned' => (bool) $p->pinned,
            'view_count' => $p->view_count,
            'created_at' => $p->created_at->diffForHumans(),
            'created_at_iso' => $p->created_at->toISOString(),
            'edited' => $p->edited_at !== null,
            'can_edit' => $me && self::canEdit($p, $me),
        ];
    }

    public static function canEdit(Post $p, User $me): bool
    {
        if ($p->user_id === $me->id) {
            return true;
        }
        if ($p->page_id && $p->page) {
            return $p->page->roles()->where('user_id', $me->id)->whereIn('role', ['admin', 'editor'])->exists();
        }
        if ($p->group_id && $p->group) {
            $role = $p->group->members()->where('user_id', $me->id)->wherePivot('status', 'active')->first()?->pivot->role;
            return in_array($role, ['owner', 'admin']);
        }
        return false;
    }

    public static function serializeComments($comments, ?User $me): array
    {
        return collect($comments)->map(function ($c) use ($me) {
            $reactionCounts = $c->reactions->groupBy('type')->map->count()->all();
            return [
                'id' => $c->id,
                'post_id' => $c->post_id,
                'parent_id' => $c->parent_id,
                'author' => $c->user ? ProfileController::basicUser($c->user) : null,
                'content' => $c->content,
                'attachment' => $c->attachment_path ? ['url' => asset('storage/'.$c->attachment_path), 'mime' => $c->attachment_mime] : null,
                'reaction_counts' => $reactionCounts,
                'reaction_total' => array_sum($reactionCounts),
                'my_reaction' => $me ? $c->reactions->firstWhere('user_id', $me->id)?->type : null,
                'replies' => isset($c->replies) ? self::serializeComments($c->replies, $me) : [],
                'created_at' => $c->created_at->diffForHumans(),
                'edited' => $c->edited_at !== null,
                'can_edit' => $me && $c->user_id === $me->id,
            ];
        })->values()->all();
    }

    // ---------- Feed ----------

    public function feed(Request $request)
    {
        $me = $request->user();
        $cursor = (int) $request->query('cursor', 0);
        $limit = 8;

        $q = Post::with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->visibleTo($me)
            ->orderByDesc('id')
            ->limit($limit + 1);

        if ($cursor > 0) {
            $q->where('id', '<', $cursor);
        }

        $posts = $q->get()->filter(function ($p) use ($me) {
            if ($p->user_id && $p->user_id !== $me->id && SocialService::blockedBetween($me, $p->user)) {
                return false;
            }
            return true;
        })->values();

        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit);

        return response()->json([
            'posts' => self::serializePosts($posts, $me),
            'next_cursor' => $hasMore ? (int) $posts->last()->id : null,
        ]);
    }

    // ---------- Create ----------

    public function store(Request $request)
    {
        $me = $request->user();
        abort_unless($me->status === 'active', 403, 'Your account cannot post.');

        $data = $request->validate([
            'content' => 'nullable|string|max:6000',
            'type' => 'required|in:text,photo,video,reel,poll,share',
            'visibility' => 'required|in:public,friends,private,custom',
            'feeling' => 'nullable|string|max:40',
            'location' => 'nullable|string|max:100',
            'link_url' => 'nullable|url:http,https|max:500',
            'group_id' => 'nullable|exists:groups,id',
            'page_id' => 'nullable|exists:pages,id',
            'shared_post_id' => 'nullable|exists:posts,id',
            'tag_ids' => 'nullable|array|max:20',
            'tag_ids.*' => 'integer|exists:users,id',
            'visibility_user_ids' => 'nullable|array|max:500',
            'visibility_user_ids.*' => 'integer|exists:users,id',
            'media.*' => 'nullable|file|max:51200|mimes:jpg,jpeg,png,webp,gif,mp4,webm,quicktime',
            'poll_options' => 'nullable|array|min:2|max:6',
            'poll_options.*' => 'string|max:100',
        ]);

        abort_unless($data['type'] !== 'poll' || ! empty($data['poll_options']), 422, 'Poll needs options.');

        // Page posting permission
        if (! empty($data['page_id'])) {
            $page = \App\Models\Page::findOrFail($data['page_id']);
            abort_unless($page->roles()->where('user_id', $me->id)->whereIn('role', ['admin', 'editor'])->exists(), 403, 'No permission to post as this page.');
        }
        // Group posting permission
        if (! empty($data['group_id'])) {
            $group = \App\Models\Group::findOrFail($data['group_id']);
            abort_unless($group->members()->where('user_id', $me->id)->wherePivot('status', 'active')->exists(), 403, 'Join the group to post.');
        }

        // Link preview
        $linkData = [];
        if (! empty($data['link_url'])) {
            $linkData = $this->fetchLinkPreview($data['link_url']);
        }

        $post = Post::create([
            'user_id' => empty($data['page_id']) ? $me->id : null,
            'page_id' => $data['page_id'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'shared_post_id' => $data['shared_post_id'] ?? null,
            'content' => $data['content'] ?? null,
            'type' => $data['type'],
            'visibility' => $data['visibility'],
            'feeling' => $data['feeling'] ?? null,
            'location' => $data['location'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'link_title' => $linkData['title'] ?? null,
            'link_description' => $linkData['description'] ?? null,
            'link_image' => $linkData['image_path'] ?? null,
        ]);

        // Media
        if ($request->hasFile('media')) {
            foreach (array_slice($request->file('media'), 0, 10) as $file) {
                $this->storeMedia($post, $file);
            }
        }

        // Poll options
        if ($data['type'] === 'poll') {
            foreach (array_slice($data['poll_options'], 0, 6) as $opt) {
                $post->pollOptions()->create(['text' => $opt]);
            }
        }

        // Custom visibility
        if ($data['visibility'] === 'custom' && ! empty($data['visibility_user_ids'])) {
            $post->visibilityUsers()->sync(array_slice($data['visibility_user_ids'], 0, 500));
        }

        // Tags
        if (! empty($data['tag_ids'])) {
            $post->tags()->sync(array_slice($data['tag_ids'], 0, 20));
            foreach ($post->tags as $tagged) {
                SocialService::notify($tagged, $me, 'tag', 'general', $post, 'tagged you in a post');
            }
        }

        // Hashtags
        $this->syncHashtags($post);

        // Notify: share / friends
        if ($post->shared_post_id && $post->sharedPost && $post->sharedPost->user_id) {
            SocialService::notify($post->sharedPost->user, $me, 'share', 'general', $post, 'shared your post');
        }

        $post->load(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name', 'sharedPost.media', 'sharedPost.user:id,name,avatar', 'sharedPost.page:id,name,avatar']);
        return response()->json(['post' => self::serializePost($post, $me)], 201);
    }

    public function update(Request $request, Post $post)
    {
        $me = $request->user();
        abort_unless(self::canEdit($post, $me), 403);

        $data = $request->validate([
            'content' => 'nullable|string|max:6000',
            'visibility' => 'in:public,friends,private,custom',
            'feeling' => 'nullable|string|max:40',
            'location' => 'nullable|string|max:100',
        ]);

        $post->update(array_filter($data, fn ($v) => $v !== null) + ['edited_at' => now()]);
        $this->syncHashtags($post);

        return response()->json(['post' => self::serializePost($post->load(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar']), $me)]);
    }

    public function destroy(Request $request, Post $post)
    {
        $me = $request->user();
        abort_unless(self::canEdit($post, $me) || $me->is_admin, 403);
        foreach ($post->media as $m) {
            Storage::disk('public')->delete($m->path);
        }
        $post->delete();
        return response()->json(['ok' => true]);
    }

    // ---------- Reactions ----------

    public function react(Request $request, Post $post)
    {
        $me = $request->user();
        $data = $request->validate(['type' => 'required|in:'.implode(',', Reaction::TYPES)]);

        $existing = $post->reactions()->where('user_id', $me->id)->first();
        if ($existing && $existing->type === $data['type']) {
            $existing->delete(); // toggle off
        } elseif ($existing) {
            $existing->update(['type' => $data['type']]);
        } else {
            $post->reactions()->create(['user_id' => $me->id, 'type' => $data['type']]);
            if ($post->user_id && $post->user_id !== $me->id) {
                SocialService::notify($post->user, $me, 'reaction', 'reaction', $post, 'reacted to your post');
            }
        }

        $post->load('reactions');
        $counts = $post->reactions->groupBy('type')->map->count()->all();
        return response()->json([
            'reaction_counts' => $counts,
            'reaction_total' => array_sum($counts),
            'my_reaction' => $post->reactions->firstWhere('user_id', $me->id)?->type,
        ]);
    }

    // ---------- Comments ----------

    public function comments(Request $request, Post $post)
    {
        $me = $request->user();
        $comments = $post->comments()
            ->whereNull('parent_id')
            ->with(['user:id,name,avatar', 'reactions', 'replies.user:id,name,avatar', 'replies.reactions'])
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return response()->json(['comments' => self::serializeComments($comments, $me)]);
    }

    public function storeComment(Request $request, Post $post)
    {
        $me = $request->user();
        $data = $request->validate([
            'content' => 'nullable|string|max:2000|required_without:attachment',
            'parent_id' => 'nullable|exists:comments,id',
            'attachment' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,webp,gif,mp4',
        ]);

        $path = null; $mime = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('comments', 'public');
            $mime = $request->file('attachment')->getMimeType();
        }

        $comment = $post->comments()->create([
            'user_id' => $me->id,
            'parent_id' => $data['parent_id'] ?? null,
            'content' => $data['content'] ?? null,
            'attachment_path' => $path,
            'attachment_mime' => $mime,
        ]);

        if ($post->user_id && $post->user_id !== $me->id && ! $comment->parent_id) {
            SocialService::notify($post->user, $me, 'comment', 'comment', $post, 'commented on your post');
        }
        if ($comment->parent_id) {
            $parent = $comment->parent;
            if ($parent && $parent->user_id && $parent->user_id !== $me->id) {
                SocialService::notify($parent->user, $me, 'comment', 'comment', $post, 'replied to your comment');
            }
        }

        $comment->load(['user:id,name,avatar', 'reactions', 'replies.user:id,name,avatar', 'replies.reactions']);
        return response()->json(['comment' => self::serializeComments([$comment], $me)[0]], 201);
    }

    public function destroyComment(Request $request, \App\Models\Comment $comment)
    {
        $me = $request->user();
        abort_unless($comment->user_id === $me->id || $me->is_admin, 403);
        if ($comment->attachment_path) {
            Storage::disk('public')->delete($comment->attachment_path);
        }
        $comment->delete();
        return response()->json(['ok' => true]);
    }

    public function reactComment(Request $request, \App\Models\Comment $comment)
    {
        $me = $request->user();
        $data = $request->validate(['type' => 'required|in:'.implode(',', Reaction::TYPES)]);

        $existing = $comment->reactions()->where('user_id', $me->id)->first();
        if ($existing && $existing->type === $data['type']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['type' => $data['type']]);
        } else {
            $comment->reactions()->create(['user_id' => $me->id, 'type' => $data['type']]);
            if ($comment->user_id && $comment->user_id !== $me->id) {
                SocialService::notify($comment->user, $me, 'reaction', 'reaction', $comment->post, 'reacted to your comment');
            }
        }

        $counts = $comment->reactions()->get()->groupBy('type')->map->count()->all();
        return response()->json([
            'reaction_counts' => $counts,
            'reaction_total' => array_sum($counts),
            'my_reaction' => $comment->reactions()->where('user_id', $me->id)->first()?->type,
        ]);
    }

    // ---------- Poll ----------

    public function vote(Request $request, Post $post)
    {
        $me = $request->user();
        $data = $request->validate(['option_id' => 'required|integer']);
        $option = $post->pollOptions()->findOrFail($data['option_id']);

        $already = $post->pollOptions()->whereHas('votes', fn ($q) => $q->where('user_id', $me->id))->exists();
        if ($already) {
            return response()->json(['error' => 'Already voted.'], 422);
        }
        $option->votes()->create(['user_id' => $me->id]);

        $post->load(['pollOptions.votes']);
        $options = $post->pollOptions->map(fn ($o) => ['id' => $o->id, 'text' => $o->text, 'votes' => $o->votes->count()]);
        $total = $options->sum('votes');
        return response()->json([
            'poll' => [
                'options' => $options->map(fn ($o) => array_merge($o, [
                    'percent' => $total > 0 ? (int) round($o['votes'] * 100 / $total) : 0,
                    'voted' => $o['id'] === $option->id,
                ]))->values()->all(),
                'total_votes' => $total,
                'my_vote' => $option->id,
            ],
        ]);
    }

    // ---------- Save / share / pin ----------

    public function toggleSave(Request $request, Post $post)
    {
        $me = $request->user();
        $existing = $post->saves()->where('user_id', $me->id)->first();
        if ($existing) {
            $existing->delete();
            return response()->json(['saved' => false]);
        }
        $post->saves()->create(['user_id' => $me->id]);
        return response()->json(['saved' => true]);
    }

    public function togglePin(Request $request, Post $post)
    {
        $me = $request->user();
        abort_unless($post->user_id === $me->id, 403);
        if ($post->pinned) {
            $post->update(['pinned' => false]);
            return response()->json(['pinned' => false]);
        }
        Post::where('user_id', $me->id)->where('pinned', true)->update(['pinned' => false]);
        $post->update(['pinned' => true]);
        return response()->json(['pinned' => true]);
    }

    public function show(Request $request, Post $post)
    {
        $me = $request->user();
        abort_unless($post->visibleTo($me)->where('posts.id', $post->id)->exists(), 403);
        $post->increment('view_count');
        $post->load(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name', 'sharedPost.media', 'sharedPost.user:id,name,avatar', 'sharedPost.page:id,name,avatar']);

        return \Inertia\Inertia::render('Posts/Show', [
            'post' => self::serializePost($post, $me),
        ]);
    }

    // ---------- Hashtags & memories ----------

    public function hashtag(Request $request, string $tag)
    {
        $me = $request->user();
        $tag = mb_strtolower(trim($tag));
        $hashtag = Hashtag::where('tag', $tag)->first();
        $posts = collect();
        if ($hashtag) {
            $posts = $hashtag->posts()
                ->visibleTo($me)
                ->with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
                ->withCount(['reactions as reaction_count', 'comments as comment_count'])
                ->latest()->limit(30)->get();
        }
        return \Inertia\Inertia::render('Posts/Hashtag', [
            'tag' => $tag,
            'count' => $hashtag?->posts_count ?? 0,
            'posts' => self::serializePosts($posts, $me),
        ]);
    }

    public function memories(Request $request)
    {
        $me = $request->user();
        $today = now();
        $posts = Post::where('user_id', $me->id)
            ->whereYear('created_at', '!=', $today->year)
            ->whereMonth('created_at', $today->month)
            ->whereDay('created_at', $today->day)
            ->with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->latest()->limit(20)->get();

        return \Inertia\Inertia::render('Posts/Memories', [
            'posts' => self::serializePosts($posts, $me),
        ]);
    }

    // ---------- Helpers ----------

    private function storeMedia(Post $post, $file): void
    {
        $mime = $file->getMimeType();
        $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';
        // sniff: extension must match detected content
        if ($kind === 'image' && ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            abort(422, 'Invalid image.');
        }
        if ($kind === 'video' && ! in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'])) {
            abort(422, 'Invalid video.');
        }
        $path = $file->store("posts/{$post->id}", 'public');
        $w = $h = null;
        if ($kind === 'image') {
            $abs = Storage::disk('public')->path($path);
            $info = @getimagesize($abs);
            if ($info) {
                [$w, $h] = $info;
                $this->downscale($abs, $info, 1600);
            }
        }
        PostMedia::create([
            'post_id' => $post->id,
            'path' => $path,
            'mime' => $mime,
            'kind' => $kind,
            'width' => $w,
            'height' => $h,
        ]);
    }

    private function downscale(string $abs, array $info, int $max): void
    {
        [$w, $h] = $info;
        if ($w <= $max && $h <= $max) {
            return;
        }
        $ratio = min($max / $w, $max / $h);
        $nw = (int) round($w * $ratio);
        $nh = (int) round($h * $ratio);
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($abs),
            IMAGETYPE_PNG => imagecreatefrompng($abs),
            IMAGETYPE_WEBP => imagecreatefromwebp($abs),
            IMAGETYPE_GIF => imagecreatefromgif($abs),
            default => null,
        };
        if (! $src) {
            return;
        }
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($dst, $abs, 85),
            IMAGETYPE_PNG => imagepng($dst, $abs, 6),
            IMAGETYPE_WEBP => imagewebp($dst, $abs, 85),
            IMAGETYPE_GIF => imagegif($dst, $abs),
            default => null,
        };
        imagedestroy($src);
        imagedestroy($dst);
    }

    private function syncHashtags(Post $post): void
    {
        preg_match_all('/#([\p{L}\p{N}_]{2,50})/u', (string) $post->content, $m);
        $tags = array_slice(array_unique(array_map(fn ($t) => mb_strtolower($t), $m[1] ?? [])), 0, 10);
        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = Hashtag::firstOrCreate(['tag' => $tag])->id;
        }
        $post->hashtags()->sync($ids);
        foreach (Hashtag::whereIn('id', $ids)->get() as $h) {
            $h->update(['posts_count' => $h->posts()->count()]);
        }
    }

    private function fetchLinkPreview(string $url): array
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            if (! $host || filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [];
            }
            $resp = Http::timeout(6)->withHeaders(['User-Agent' => 'BhasebookBot/1.0'])->get($url);
            if (! $resp->successful()) {
                return [];
            }
            $html = $resp->body();
            $title = $this->metaContent($html, 'og:title') ?? $this->titleTag($html);
            $description = $this->metaContent($html, 'og:description') ?? $this->metaContent($html, 'description');
            $image = $this->metaContent($html, 'og:image');

            $out = [
                'title' => $title ? mb_substr($title, 0, 150) : null,
                'description' => $description ? mb_substr($description, 0, 300) : null,
                'image_path' => null,
            ];
            if ($image && str_starts_with($image, 'http')) {
                try {
                    $img = Http::timeout(6)->get($image);
                    if ($img->successful() && strlen($img->body()) < 5_000_000) {
                        $ext = str_contains($img->header('Content-Type') ?? '', 'png') ? 'png' : 'jpg';
                        $path = 'links/'.sha1($image).'.'.$ext;
                        Storage::disk('public')->put($path, $img->body());
                        $out['image_path'] = $path;
                    }
                } catch (\Throwable) {
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function metaContent(string $html, string $property): ?string
    {
        $prop = preg_quote($property, '/');
        $pattern1 = '/<meta[^>]+(?:property|name)="'.$prop.'"[^>]+content="([^"]*)"/iu';
        $pattern2 = '/<meta[^>]+content="([^"]*)"[^>]+(?:property|name)="'.$prop.'"/iu';
        if (preg_match($pattern1, $html, $m) || preg_match($pattern2, $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        return null;
    }

    private function titleTag(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES));
        }
        return null;
    }
}
