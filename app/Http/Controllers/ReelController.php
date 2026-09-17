<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReelController extends Controller
{
    public function reels(Request $request)
    {
        $me = $request->user();
        $cursor = (int) $request->query('cursor', 0);
        $limit = 5;

        $q = Post::where('type', 'reel')
            ->with(['media', 'reactions', 'saves', 'tags:id,name,avatar', 'user:id,name,avatar', 'page:id,name,avatar'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->orderByDesc('id')
            ->limit($limit + 1);

        if ($cursor > 0) {
            $q->where('id', '<', $cursor);
        }

        $reels = $q->get()
            ->filter(fn ($p) => ! $p->user_id || $p->user_id === $me->id || ! \App\Services\SocialService::blockedBetween($me, $p->user))
            ->values();

        $hasMore = $reels->count() > $limit;
        $reels = $reels->take($limit);

        return response()->json([
            'reels' => PostController::serializePosts($reels, $me),
            'next_cursor' => $hasMore ? (int) $reels->last()->id : null,
        ]);
    }

    public function storeReel(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'video' => 'required|file|max:51200|mimes:mp4,webm',
            'content' => 'nullable|string|max:2000',
            'visibility' => 'in:public,friends,private',
        ]);

        $mime = $request->file('video')->getMimeType();
        abort_unless(in_array($mime, ['video/mp4', 'video/webm', 'application/mp4']), 422, 'Invalid video.');

        $post = Post::create([
            'user_id' => $me->id,
            'content' => $data['content'] ?? null,
            'type' => 'reel',
            'visibility' => $data['visibility'] ?? 'public',
        ]);

        $path = $request->file('video')->store("posts/{$post->id}", 'public');
        $post->media()->create([
            'path' => $path,
            'mime' => $mime,
            'kind' => 'video',
        ]);

        $post->load(['media', 'reactions', 'saves', 'tags:id,name,avatar', 'user:id,name,avatar']);
        return response()->json(['reel' => PostController::serializePost($post, $me)], 201);
    }

    public function watch(Request $request)
    {
        $me = $request->user();
        $videos = Post::where('type', 'video')
            ->with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags:id,name,avatar', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->visibleTo($me)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return \Inertia\Inertia::render('Reels/Watch', [
            'videos' => PostController::serializePosts($videos, $me),
        ]);
    }

    public function reelsPage(Request $request)
    {
        return \Inertia\Inertia::render('Reels/Index');
    }
}
