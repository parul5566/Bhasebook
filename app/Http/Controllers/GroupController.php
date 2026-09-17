<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Groups/Index', $this->indexData($request));
    }

    public function indexData(Request $request): array
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));

        $discover = Group::query()
            ->whereNull('deleted_at')
            ->where('privacy', 'public')
            ->withCount(['members as members_count' => fn ($m) => $m->where('group_members.status', 'active')])
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderByDesc('members_count')->limit(12)->get();

        $mine = $me->joinedGroups()->withCount(['members as members_count' => fn ($m) => $m->where('group_members.status', 'active')])->get();

        return [
            'discover' => $discover->map(fn ($g) => $this->card($g, $me)),
            'mine' => $mine->map(fn ($g) => $this->card($g, $me)),
            'q' => $q,
            'invites' => GroupInvite::where('user_id', $me->id)->where('status', 'pending')
                ->with(['group:id,name,description', 'inviter:id,name,avatar'])
                ->get()->map(fn ($i) => [
                    'id' => $i->id,
                    'group' => ['id' => $i->group->id, 'name' => $i->group->name, 'description' => $i->group->description],
                    'inviter' => ProfileController::basicUser($i->inviter),
                ]),
        ];
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'description' => 'nullable|string|max:2000',
            'privacy' => 'required|in:public,private',
            'requires_approval' => 'boolean',
            'cover' => 'nullable|image|max:8192|mimes:jpg,jpeg,png,webp',
        ]);

        $cover = null;
        if ($request->hasFile('cover')) {
            $cover = $request->file('cover')->store('groups', 'public');
        }

        $group = Group::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'privacy' => $data['privacy'],
            'requires_approval' => $data['requires_approval'] ?? true,
            'cover' => $cover,
            'created_by' => $me->id,
            'rules' => "1. Be kind and respectful.\n2. No spam or self-promotion without permission.\n3. Keep posts relevant to the group.",
        ]);

        $group->members()->attach($me->id, ['role' => 'owner', 'status' => 'active']);

        return redirect()->route('groups.show', ['group' => $group->id]);
    }

    public function show(Request $request, Group $group)
    {
        return Inertia::render('Groups/Show', $this->showData($request, $group));
    }

    public function showData(Request $request, Group $group): array
    {
        $me = $request->user();
        $membership = $group->members()->where('user_id', $me->id)->first();
        $role = $membership?->pivot->role;
        $status = $membership?->pivot->status;

        // Private groups: members only
        if ($group->privacy === 'private' && ! $membership && ! $me->is_admin) {
            abort(404);
        }

        $posts = $group->posts()
            ->with(['media', 'pollOptions.votes', 'reactions', 'saves', 'tags', 'user:id,name,avatar', 'page:id,name,avatar', 'group:id,name'])
            ->withCount(['reactions as reaction_count', 'comments as comment_count'])
            ->orderByDesc('id')->limit(30)->get();

        $members = $group->members()->wherePivot('status', 'active')
            ->withPivot('role')->limit(40)->get()
            ->map(fn ($u) => [...ProfileController::basicUser($u), 'role' => $u->pivot->role]);

        $pending = ($role === 'owner' || $role === 'admin' || $me->is_admin)
            ? $group->members()->wherePivot('status', 'pending')->withPivot('role')->get()
                ->map(fn ($u) => [...ProfileController::basicUser($u), 'role' => $u->pivot->role])
            : [];

        return [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'cover_url' => $group->cover ? asset('storage/'.$group->cover) : null,
                'privacy' => $group->privacy,
                'requires_approval' => (bool) $group->requires_approval,
                'rules' => $group->rules,
                'members_count' => $group->activeMembersCount(),
                'created_at' => $group->created_at->diffForHumans(),
            ],
            'posts' => PostController::serializePosts($posts, $me),
            'members' => $members,
            'pending' => $pending,
            'my_role' => $status === 'active' ? $role : null,
            'my_status' => $status,
        ];
    }

    public function join(Request $request, Group $group)
    {
        $me = $request->user();
        $existing = $group->members()->where('user_id', $me->id)->first();
        if ($existing) {
            return back();
        }
        $active = ! $group->requires_approval;
        $group->members()->attach($me->id, ['role' => 'member', 'status' => $active ? 'active' : 'pending']);
        return back()->with('status', $active ? 'Joined the group!' : 'Request sent — waiting for approval.');
    }

    public function leave(Request $request, Group $group)
    {
        $me = $request->user();
        $group->members()->detach($me->id);
        return redirect()->route('groups.index');
    }

    public function approve(Request $request, Group $group, User $user)
    {
        $me = $request->user();
        $this->requireAdmin($group, $me);
        $group->members()->updateExistingPivot($user->id, ['status' => 'active']);
        SocialService::notify($user, $me, 'group_invite', 'group', $group, "approved your request to join {$group->name}");
        return back();
    }

    public function removeMember(Request $request, Group $group, User $user)
    {
        $me = $request->user();
        $this->requireAdmin($group, $me);
        $member = $group->members()->where('user_id', $user->id)->first();
        abort_if($member && $member->pivot->role === 'owner', 403, 'Owner cannot be removed.');
        $group->members()->detach($user->id);
        return back();
    }

    public function changeRole(Request $request, Group $group, User $user)
    {
        $me = $request->user();
        abort_unless($this->isOwner($group, $me) || $me->is_admin, 403, 'Only the owner can change roles.');
        $data = $request->validate(['role' => 'required|in:admin,member']);
        abort_if($this->isOwner($group, $user), 403, "The owner's role cannot be changed.");
        $group->members()->updateExistingPivot($user->id, ['role' => $data['role']]);
        return back();
    }

    public function invite(Request $request, Group $group)
    {
        $me = $request->user();
        abort_unless($group->members()->where('user_id', $me->id)->wherePivot('status', 'active')->exists(), 403, 'Join the group first.');
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $target = User::findOrFail($data['user_id']);

        if (! $group->members()->where('user_id', $target->id)->exists()) {
            GroupInvite::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $target->id],
                ['invited_by' => $me->id],
            );
            SocialService::notify($target, $me, 'group_invite', 'group', $group, "invited you to join {$group->name}");
        }
        return back();
    }

    public function respondInvite(Request $request, GroupInvite $invite)
    {
        $me = $request->user();
        abort_unless($invite->user_id === $me->id, 403);
        $data = $request->validate(['action' => 'required|in:accept,decline']);

        if ($data['action'] === 'accept') {
            $invite->update(['status' => 'accepted']);
            if (! $invite->group->members()->where('user_id', $me->id)->exists()) {
                $invite->group->members()->attach($me->id, ['role' => 'member', 'status' => 'active']);
            }
        } else {
            $invite->update(['status' => 'declined']);
        }
        return back();
    }

    public function update(Request $request, Group $group)
    {
        $me = $request->user();
        $this->requireAdmin($group, $me);
        $data = $request->validate([
            'name' => 'sometimes|string|min:3|max:100',
            'description' => 'nullable|string|max:2000',
            'rules' => 'nullable|string|max:4000',
            'requires_approval' => 'sometimes|boolean',
            'cover' => 'nullable|image|max:8192|mimes:jpg,jpeg,png,webp',
        ]);
        if ($request->hasFile('cover')) {
            if ($group->cover) Storage::disk('public')->delete($group->cover);
            $data['cover'] = $request->file('cover')->store('groups', 'public');
        }
        $group->update(array_filter($data, fn ($v) => $v !== null));
        return back();
    }

    private function requireAdmin(Group $group, User $me): void
    {
        $role = $group->members()->where('user_id', $me->id)->wherePivot('status', 'active')->first()?->pivot->role;
        abort_unless(in_array($role, ['owner', 'admin']) || $me->is_admin, 403, 'Admins only.');
    }

    private function isOwner(Group $group, User $me): bool
    {
        return $group->members()->where('user_id', $me->id)->wherePivot('role', 'owner')->exists() || $group->created_by === $me->id;
    }

    private function card(Group $g, User $me): array
    {
        $membership = $g->members->firstWhere('id', $me->id);
        return [
            'id' => $g->id,
            'name' => $g->name,
            'description' => mb_substr((string) $g->description, 0, 140),
            'cover_url' => $g->cover ? asset('storage/'.$g->cover) : null,
            'privacy' => $g->privacy,
            'members_count' => $g->members_count,
            'my_status' => $membership?->pivot->status,
            'hue' => crc32($g->name) % 360,
        ];
    }
}
