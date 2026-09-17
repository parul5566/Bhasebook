<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PageController extends Controller
{
    public const CATEGORIES = ['Community', 'Business', 'Creator', 'Public Figure', 'Brand', 'Entertainment', 'Sports', 'Tech', 'Education', 'Nonprofit'];

    public function index(Request $request)
    {
        return Inertia::render('Pages/Index', $this->indexData($request));
    }

    public function indexData(Request $request): array
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));
        $followed = DB::table('page_followers')->where('user_id', $me->id)->pluck('page_id');

        $discover = Page::query()->whereNull('deleted_at')
            ->withCount('followers as followers_count')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderByDesc('followers_count')->limit(12)->get();

        $mine = Page::query()->whereNull('deleted_at')
            ->whereHas('roles', fn ($r) => $r->where('user_id', $me->id))
            ->withCount('followers as followers_count')->get();

        return [
            'discover' => $discover->map(fn ($p) => $this->card($p, $followed)),
            'mine' => $mine->map(fn ($p) => $this->card($p, $followed)),
            'followed' => Page::whereIn('id', $followed)->whereNull('deleted_at')
                ->withCount('followers as followers_count')->get()
                ->map(fn ($p) => $this->card($p, $followed)),
            'q' => $q,
            'categories' => self::CATEGORIES,
        ];
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'category' => 'required|string|max:50',
            'about' => 'nullable|string|max:2000',
            'avatar' => 'nullable|image|max:4096|mimes:jpg,jpeg,png,webp',
            'cover' => 'nullable|image|max:8192|mimes:jpg,jpeg,png,webp',
        ]);

        $page = Page::create([
            'name' => $data['name'],
            'category' => in_array($data['category'], self::CATEGORIES) ? $data['category'] : 'Community',
            'about' => $data['about'] ?? null,
            'avatar' => $request->hasFile('avatar') ? $request->file('avatar')->store('pages', 'public') : null,
            'cover' => $request->hasFile('cover') ? $request->file('cover')->store('pages', 'public') : null,
            'created_by' => $me->id,
        ]);
        $page->roles()->attach($me->id, ['role' => 'admin']);
        $page->followers()->attach($me->id);

        return redirect()->route('pages.show', ['page' => $page->id]);
    }

    public function show(Request $request, Page $page)
    {
        return Inertia::render('Pages/Show', $this->showData($request, $page));
    }

    public function showData(Request $request, Page $page): array
    {
        $me = $request->user();
        $posts = $page->posts()
            ->with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->orderByDesc('id')->limit(30)->get();

        $myRole = $page->roles()->where('user_id', $me->id)->first()?->pivot->role;
        $admins = $page->roles()->withPivot('role')->get()
            ->map(fn ($u) => [...ProfileController::basicUser($u), 'role' => $u->pivot->role]);

        return [
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'category' => $page->category,
                'about' => $page->about,
                'avatar_url' => $page->avatar_url,
                'cover_url' => $page->cover_url,
                'followers_count' => $page->followers()->count(),
                'created_at' => $page->created_at->diffForHumans(),
            ],
            'posts' => PostController::serializePosts($posts, $me),
            'my_role' => $myRole,
            'following' => $page->followers()->where('user_id', $me->id)->exists(),
            'team' => $admins,
            'insights' => $myRole ? $this->insights($page) : null,
        ];
    }

    public function toggleFollow(Request $request, Page $page)
    {
        $me = $request->user();
        if ($page->followers()->where('user_id', $me->id)->exists()) {
            $page->followers()->detach($me->id);
            return back()->with('status', 'Unfollowed.');
        }
        $page->followers()->attach($me->id);
        return back()->with('status', "Following {$page->name}.");
    }

    public function addRole(Request $request, Page $page)
    {
        $me = $request->user();
        abort_unless($this->isAdmin($page, $me), 403, 'Page admins only.');
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|in:admin,editor',
        ]);
        $target = User::findOrFail($data['user_id']);
        $page->roles()->syncWithoutDetaching([$target->id => ['role' => $data['role']]]);
        SocialService::notify($target, $me, 'page_post', 'page', $page, "added you as {$data['role']} of {$page->name}");
        return back();
    }

    public function update(Request $request, Page $page)
    {
        $me = $request->user();
        abort_unless($this->isAdmin($page, $me), 403, 'Page admins only.');
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'category' => 'sometimes|string|max:50',
            'about' => 'nullable|string|max:2000',
            'avatar' => 'nullable|image|max:4096|mimes:jpg,jpeg,png,webp',
            'cover' => 'nullable|image|max:8192|mimes:jpg,jpeg,png,webp',
        ]);
        foreach (['avatar', 'cover'] as $f) {
            if ($request->hasFile($f)) {
                if ($page->{$f}) Storage::disk('public')->delete($page->{$f});
                $data[$f] = $request->file($f)->store('pages', 'public');
            }
        }
        $page->update(array_filter($data, fn ($v) => $v !== null));
        return back();
    }

    private function isAdmin(Page $page, User $me): bool
    {
        return $me->is_admin || $page->roles()->where('user_id', $me->id)->wherePivot('role', 'admin')->exists();
    }

    private function insights(Page $page): array
    {
        $posts = $page->posts()->withCount(['reactions', 'comments'])->latest()->limit(10)->get();
        return [
            'followers' => $page->followers()->count(),
            'posts' => $page->posts()->count(),
            'total_reactions' => (clone $posts)->sum('reactions_count'),
            'total_comments' => (clone $posts)->sum('comments_count'),
            'recent' => $posts->map(fn ($p) => [
                'id' => $p->id,
                'content' => mb_substr((string) $p->content, 0, 60),
                'reactions' => $p->reactions_count,
                'comments' => $p->comments_count,
                'views' => $p->view_count,
                'created_at' => $p->created_at->diffForHumans(),
            ]),
        ];
    }

    private function card(Page $p, $followed): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category,
            'about' => mb_substr((string) $p->about, 0, 120),
            'avatar_url' => $p->avatar_url,
            'followers_count' => $p->followers_count,
            'following' => $followed->contains($p->id),
            'hue' => crc32($p->name) % 360,
        ];
    }
}
