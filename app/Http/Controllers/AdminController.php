<?php

namespace App\Http\Controllers;

use App\Models\AiFlag;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserWarning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Admin/Dashboard', $this->dashboardData($request));
    }

    public function dashboardData(Request $request): array
    {
        $me = $request->user();
        abort_unless($me->is_admin, 403, 'Admins only.');

        $tab = (string) $request->query('tab', 'overview');

        return [
            'tab' => $tab,
            'stats' => $this->stats(),
            'users' => $tab === 'users' ? $this->users($request) : null,
            'posts' => $tab === 'posts' ? $this->recentPosts($request) : null,
            'comments' => $tab === 'comments' ? $this->recentComments() : null,
            'reports' => $tab === 'reports' ? $this->reports($request) : null,
            'groups' => $tab === 'groups' ? $this->recentGroups() : null,
            'pages' => $tab === 'pages' ? $this->recentPages() : null,
            'aiFlags' => $tab === 'moderation' ? $this->aiFlags() : null,
            'settings' => $tab === 'settings' ? Setting::pluck('value', 'key')->all() : null,
        ];
    }

    public function userAction(Request $request, User $user)
    {
        $me = $request->user();
        abort_unless($me->is_admin, 403);
        abort_if($user->id === $me->id, 403, 'You cannot moderate yourself.');

        $action = $request->validate(['action' => 'required|in:warn,suspend,unsuspend,ban,unban,delete,verify'])['action'];

        switch ($action) {
            case 'warn':
                $data = $request->validate(['reason' => 'required|string|max:200']);
                UserWarning::create(['user_id' => $user->id, 'warned_by' => $me->id, 'reason' => $data['reason']]);
                \App\Services\SocialService::notify($user, $me, 'warning', 'general', $user, "You received a warning: {$data['reason']}");
                break;
            case 'suspend':
                $days = (int) $request->input('days', 7);
                $user->forceFill(['status' => 'suspended', 'suspended_until' => now()->addDays($days)])->save();
                break;
            case 'unsuspend':
                $user->forceFill(['status' => 'active', 'suspended_until' => null])->save();
                break;
            case 'ban':
                $user->forceFill(['status' => 'banned'])->save();
                $user->tokens()->delete();
                break;
            case 'unban':
                $user->forceFill(['status' => 'active', 'suspended_until' => null])->save();
                break;
            case 'delete':
                $user->delete();
                break;
            case 'verify':
                $user->forceFill(['verified' => ! $user->verified])->save();
                break;
        }

        return back()->with('status', 'Done.');
    }

    public function reportAction(Request $request, Report $report)
    {
        $me = $request->user();
        abort_unless($me->is_admin, 403);
        $action = $request->validate(['action' => 'required|in:dismiss,resolve_remove,warn,suspend,ban'])['action'];

        $target = $report->reportable;

        switch ($action) {
            case 'dismiss':
                $report->update(['status' => 'dismissed', 'handled_by' => $me->id, 'handled_at' => now(),
                    'moderator_note' => $request->input('note')]);
                break;
            case 'resolve_remove':
                if ($target) $target->delete();
                $report->update(['status' => 'resolved', 'handled_by' => $me->id, 'handled_at' => now(), 'moderator_note' => 'Content removed']);
                break;
            case 'warn':
            case 'suspend':
            case 'ban':
                $owner = match (true) {
                    $target instanceof Post => $target->user,
                    $target instanceof Comment => $target->user,
                    $target instanceof Group => $target->creator,
                    $target instanceof Page => $target->creator,
                    $target instanceof User => $target,
                    default => null,
                };
                if ($owner && $owner->id !== $me->id) {
                    if ($action === 'warn') {
                        UserWarning::create(['user_id' => $owner->id, 'warned_by' => $me->id, 'reason' => "Report: {$report->reason}"]);
                    } elseif ($action === 'suspend') {
                        $owner->forceFill(['status' => 'suspended', 'suspended_until' => now()->addDays(7)])->save();
                    } else {
                        $owner->forceFill(['status' => 'banned'])->save();
                    }
                }
                $report->update(['status' => 'resolved', 'handled_by' => $me->id, 'handled_at' => now(), 'moderator_note' => "Author {$action}ed"]);
                break;
        }

        return back()->with('status', 'Report handled.');
    }

    public function aiFlagAction(Request $request, AiFlag $flag)
    {
        $me = $request->user();
        abort_unless($me->is_admin, 403);
        $action = $request->validate(['action' => 'required|in:dismiss,remove_content,ban_author'])['action'];

        if ($action === 'dismiss') {
            $flag->update(['reviewed_at' => now()]);
            return back()->with('status', 'Flag dismissed.');
        }

        $target = $flag->flaggable;
        if ($action === 'remove_content' && $target) {
            $target->delete();
        }
        if ($action === 'ban_author' && $target) {
            $owner = match (true) {
                $target instanceof Post => $target->user,
                $target instanceof Comment => $target->user,
                $target instanceof Message => $target->sender,
                $target instanceof User => $target,
                default => null,
            };
            $owner?->forceFill(['status' => 'banned'])->save();
        }
        $flag->update(['reviewed_at' => now()]);

        return back()->with('status', 'Action taken.');
    }

    public function saveSettings(Request $request)
    {
        $me = $request->user();
        abort_unless($me->is_admin, 403);
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:2000',
        ]);
        foreach ($data['settings'] as $key => $value) {
            Setting::set($key, $value === null ? null : (string) $value);
        }
        return back()->with('status', 'Settings saved.');
    }

    private function stats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'new_users_7d' => User::where('created_at', '>', now()->subDays(7))->count(),
            'total_posts' => Post::count(),
            'total_comments' => Comment::count(),
            'total_reactions' => DB::table('reactions')->count(),
            'total_groups' => Group::count(),
            'total_pages' => Page::count(),
            'open_reports' => Report::where('status', 'open')->count(),
            'banned_users' => User::where('status', 'banned')->count(),
            'pending_ai_flags' => AiFlag::whereNull('reviewed_at')->count(),
            'daily_new_users' => User::where('created_at', '>', now()->subDays(14))
                ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
                ->groupBy('d')->orderBy('d')->get(),
        ];
    }

    private function users(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        return User::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->withCount(['posts', 'comments'])
            ->orderByDesc('id')->limit(60)->get()
            ->map(fn ($u) => [
                ...ProfileController::basicUser($u),
                'email' => $u->email,
                'status' => $u->status,
                'suspended_until' => $u->suspended_until?->diffForHumans(),
                'is_admin' => $u->is_admin,
                'posts_count' => $u->posts_count,
                'comments_count' => $u->comments_count,
                'joined' => $u->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function recentPosts(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        return Post::query()
            ->when($q !== '', fn ($w) => $w->where('content', 'like', "%{$q}%"))
            ->with(['user:id,name,avatar', 'page:id,name'])
            ->withCount(['reactions', 'comments'])
            ->orderByDesc('id')->limit(50)->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'content' => mb_substr($p->content ?? '(media post)', 0, 140),
                'author' => $p->user?->name ?? $p->page?->name,
                'type' => $p->type,
                'visibility' => $p->visibility,
                'reactions' => $p->reactions_count,
                'comments' => $p->comments_count,
                'deleted' => $p->deleted_at !== null,
                'created_at' => $p->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function recentComments(): array
    {
        return Comment::query()
            ->with(['user:id,name,avatar', 'post:id'])
            ->withCount('reactions')
            ->orderByDesc('id')->limit(50)->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'content' => mb_substr($c->content ?? '(attachment)', 0, 140),
                'author' => $c->user?->name,
                'post_id' => $c->post_id,
                'reactions' => $c->reactions_count,
                'created_at' => $c->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function reports(Request $request): array
    {
        $status = (string) $request->query('status', 'open');
        return Report::query()
            ->whereIn('status', $status === 'all' ? ['open', 'reviewing', 'resolved', 'dismissed'] : [$status])
            ->with(['reporter:id,name,avatar'])
            ->orderByDesc('id')->limit(50)->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'reason' => $r->reason,
                'details' => $r->details,
                'status' => $r->status,
                'reporter' => $r->reporter?->name,
                'target_type' => class_basename($r->reportable_type),
                'target_id' => $r->reportable_id,
                'target_preview' => $this->targetPreview($r),
                'handled' => $r->handled_at !== null,
                'created_at' => $r->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function targetPreview(Report $r): ?string
    {
        $target = $r->reportable;
        return match (true) {
            $target instanceof Post => mb_substr($target->content ?? '(media post)', 0, 100),
            $target instanceof Comment => mb_substr($target->content ?? '(attachment)', 0, 100),
            $target instanceof User => $target->name,
            $target instanceof Group => $target->name,
            $target instanceof Page => $target->name,
            $target instanceof \App\Models\Message => mb_substr($target->body ?? '(attachment)', 0, 100),
            default => '(deleted)',
        };
    }

    private function recentGroups(): array
    {
        return Group::query()->withCount(['members as members_count' => fn ($m) => $m->where('group_members.status', 'active')])
            ->with('creator:id,name')
            ->orderByDesc('id')->limit(40)->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'privacy' => $g->privacy,
                'members_count' => $g->members_count, 'creator' => $g->creator?->name,
                'created_at' => $g->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function recentPages(): array
    {
        return Page::query()->withCount('followers')
            ->with('creator:id,name')
            ->orderByDesc('id')->limit(40)->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'category' => $p->category,
                'followers_count' => $p->followers_count, 'creator' => $p->creator?->name,
                'created_at' => $p->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function aiFlags(): array
    {
        return AiFlag::query()
            ->whereNull('reviewed_at')
            ->orderByDesc('risk_score')->limit(50)->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'target_type' => class_basename($f->flaggable_type),
                'target_id' => $f->flaggable_id,
                'preview' => $this->flagPreview($f),
                'risk_score' => $f->risk_score,
                'reasons' => $f->reasons,
                'suggested_action' => $f->suggested_action,
                'created_at' => $f->created_at->diffForHumans(),
            ])->values()->all();
    }

    private function flagPreview(AiFlag $f): ?string
    {
        $target = $f->flaggable;
        return match (true) {
            $target instanceof Post => mb_substr($target->content ?? '(media post)', 0, 120),
            $target instanceof Comment => mb_substr($target->content ?? '(attachment)', 0, 120),
            $target instanceof Message => mb_substr($target->body ?? '(attachment)', 0, 120),
            $target instanceof Group => $target->name,
            $target instanceof Page => $target->name,
            $target instanceof User => $target->name,
            default => '(deleted)',
        };
    }
}
