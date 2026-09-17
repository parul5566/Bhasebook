<?php

namespace App\Http\Controllers;

use App\Models\SiteNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public const CATEGORIES = ['friend', 'reaction', 'comment', 'follow', 'group', 'page', 'message', 'general'];

    public function index(Request $request)
    {
        return Inertia::render('Notifications/Index', $this->indexData($request));
    }

    public function indexData(Request $request): array
    {
        return $this->notificationPayload($request);
    }

    private function notificationPayload(Request $request): array
    {
        $me = $request->user();
        $tab = (string) $request->query('tab', 'all');

        $q = SiteNotification::where('user_id', $me->id)
            ->with('actor:id,name,avatar')
            ->orderByDesc('id')
            ->limit(80);

        if ($tab === 'mentions') {
            $q->whereIn('type', ['tag', 'mention']);
        } elseif ($tab !== 'all') {
            $q->where('category', $tab);
        }

        $items = $q->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'type' => $n->type,
                'category' => $n->category,
                'actor' => $n->actor ? ProfileController::basicUser($n->actor) : null,
                'text' => $n->data['text'] ?? '',
                'href' => $this->href($n),
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
            ];
        })->values();

        $unread = [
            'all' => SiteNotification::where('user_id', $me->id)->whereNull('read_at')->count(),
            'mentions' => SiteNotification::where('user_id', $me->id)->whereNull('read_at')->whereIn('type', ['tag', 'mention'])->count(),
        ];

        return [
            'items' => $items,
            'tab' => $tab,
            'unread' => $unread,
            'prefs' => $this->prefs($me),
        ];
    }

    public function markRead(Request $request, ?SiteNotification $notification = null)
    {
        $me = $request->user();
        if ($notification) {
            abort_unless($notification->user_id === $me->id, 403);
            $notification->update(['read_at' => $notification->read_at ?? now()]);
        } else {
            SiteNotification::where('user_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        }
        return back();
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $request->user()->unreadNotificationsCount()]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'prefs' => 'required|array',
            'prefs.*' => 'in:0,1',
        ]);
        $prefs = array_intersect_key($data['prefs'], array_flip(self::CATEGORIES));
        $request->user()->forceFill(['notification_prefs' => $prefs])->save();
        return back();
    }

    private function prefs(User $me): array
    {
        $saved = $me->notification_prefs ?? [];
        $out = [];
        foreach (self::CATEGORIES as $c) {
            $out[$c] = (bool) ($saved[$c] ?? 1);
        }
        return $out;
    }

    private function href(SiteNotification $n): ?string
    {
        $type = $n->notifiable_type;
        $id = $n->notifiable_id;
        return match (true) {
            $type === 'App\\Models\\Post' => route('posts.show', ['post' => $id]),
            $type === 'App\\Models\\User' && in_array($n->type, ['friend_request', 'friend_accept', 'follow']) => route('profile.show', ['user' => $id]),
            $type === 'App\\Models\\User' => route('friends.index'),
            $type === 'App\\Models\\Group' => route('groups.show', ['group' => $id]),
            $type === 'App\\Models\\Page' => route('pages.show', ['page' => $id]),
            $n->type === 'message' => route('messenger.index'),
            default => route('notifications.index'),
        };
    }
}
