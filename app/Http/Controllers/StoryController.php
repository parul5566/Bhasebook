<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StoryController extends Controller
{
    public function tray(Request $request)
    {
        $me = $request->user();
        $friendIds = $me->friendIds();

        $groups = Story::active()
            ->with(['views', 'user:id,name,avatar'])
            ->where(function ($q) use ($me, $friendIds) {
                $q->where('user_id', $me->id)
                    ->orWhere(function ($v) use ($friendIds) {
                        $v->whereIn('user_id', $friendIds)->whereIn('visibility', ['friends', 'custom']);
                    })
                    ->orWhere('visibility', 'public');
            })
            ->whereNotIn('user_id', function ($q) use ($me) {
                $q->select('blocked_id')->from('blocks')->where('user_id', $me->id);
            })
            ->whereNotIn('user_id', function ($q) use ($me) {
                $q->select('user_id')->from('blocks')->where('blocked_id', $me->id);
            })
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id');

        $tray = $groups->map(function ($stories, $userId) use ($me) {
            $user = $stories->first()->user;
            $seen = $stories->every(fn ($s) => $s->views->contains('user_id', $me->id));

            return [
                'user' => $user ? ProfileController::basicUser($user) : ['id' => $userId, 'name' => 'Unknown', 'avatar_url' => null, 'hue' => 0],
                'seen' => $seen,
                'count' => $stories->count(),
                'stories' => $stories->map(fn ($s) => [
                    'id' => $s->id,
                    'kind' => $s->kind,
                    'media_url' => $s->media_path ? asset('storage/'.$s->media_path) : null,
                    'text' => $s->text_content,
                    'background' => $s->background,
                    'created_at' => $s->created_at->diffForHumans(),
                    'expires_at' => $s->expires_at->toISOString(),
                    'seen_by_me' => $s->views->contains('user_id', $me->id),
                ])->values()->all(),
            ];
        })->values()->sortBy(fn ($t) => $t['seen'] ? 1 : 0)->values();

        return response()->json(['tray' => $tray]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'kind' => 'required|in:photo,video,text',
            'media' => 'required_if:kind,photo,video|nullable|file|max:30720|mimes:jpg,jpeg,png,webp,mp4,webm',
            'text' => 'required_if:kind,text|nullable|string|max:200',
            'background' => 'nullable|string|max:20',
            'visibility' => 'required|in:public,friends,custom',
        ]);

        $path = null;
        if ($request->hasFile('media')) {
            $mime = $request->file('media')->getMimeType();
            $expected = $data['kind'] === 'video' ? ['video/mp4', 'video/webm'] : ['image/jpeg', 'image/png', 'image/webp'];
            abort_unless(in_array($mime, $expected), 422, 'Invalid media type.');
            $path = $request->file('media')->store('stories', 'public');
        }

        $story = Story::create([
            'user_id' => $me->id,
            'kind' => $data['kind'],
            'media_path' => $path,
            'text_content' => $data['text'] ?? null,
            'background' => $data['background'] ?? 'bhas',
            'visibility' => $data['visibility'],
            'expires_at' => now()->addDay(),
        ]);

        return response()->json(['story' => ['id' => $story->id]], 201);
    }

    public function view(Request $request, Story $story)
    {
        $me = $request->user();
        StoryView::firstOrCreate(['story_id' => $story->id, 'user_id' => $me->id]);
        return response()->json(['ok' => true]);
    }

    public function viewers(Request $request, Story $story)
    {
        $me = $request->user();
        abort_unless($story->user_id === $me->id, 403);
        $viewers = $story->views()->with('user:id,name,avatar')->get()
            ->map(fn ($v) => ProfileController::basicUser($v->user));
        return response()->json(['viewers' => $viewers]);
    }

    public function destroy(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id || $request->user()->is_admin, 403);
        if ($story->media_path) {
            Storage::disk('public')->delete($story->media_path);
        }
        $story->delete();
        return response()->json(['ok' => true]);
    }

    public function index(Request $request)
    {
        return Inertia::render('Stories/Index');
    }
}
