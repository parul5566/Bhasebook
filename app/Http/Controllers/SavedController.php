<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Save;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SavedController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $saves = Save::where('user_id', $me->id)
            ->where('savable_type', Post::class)
            ->with(['savable.media', 'savable.pollOptions.votes', 'savable.reactions', 'savable.saves', 'savable.tags:id,name,avatar', 'savable.user:id,name,avatar', 'savable.page:id,name,avatar', 'savable.group:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($s) => $s->savable)
            ->filter();

        return Inertia::render('Posts/Saved', [
            'posts' => PostController::serializePosts($saves, $me),
        ]);
    }
}
