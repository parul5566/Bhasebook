<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MessengerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Messenger/Index', $this->indexData($request));
    }

    public function indexData(Request $request): array
    {
        $me = $request->user();
        $activeId = $request->query('c');

        $conversations = $this->conversationList($me);

        $active = null;
        $messages = collect();
        if ($activeId) {
            $conv = Conversation::find($activeId);
            if ($conv && $conv->participants()->where('user_id', $me->id)->exists()) {
                $active = $this->serializeConversation($conv, $me, true);
                Message::where('conversation_id', $conv->id)->whereNull('deleted_at')->get(); // warm
                $messages = $this->messagesFor($conv, $me);
                $last = $messages->first();
                if ($last) {
                    DB::table('conversation_participants')->where('conversation_id', $conv->id)
                        ->where('user_id', $me->id)->update(['last_read_message_id' => $last['id']]);
                }
            }
        }

        return [
            'conversations' => $conversations,
            'active' => $active,
            'messages' => $messages,
            'friends' => $me->friendIds()
                ? User::whereIn('id', $me->friendIds())->where('status', 'active')->get()
                    ->map(fn ($u) => ProfileController::basicUser($u))->values()
                : [],
        ];
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($conversation->participants()->where('user_id', $me->id)->exists(), 403);
        $before = $request->query('before');
        $messages = $this->messagesFor($conversation, $me, $before ? (int) $before : null);
        return response()->json(['messages' => $messages]);
    }

    public function start(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'participants' => 'nullable|array|max:20',
            'participants.*' => 'integer|exists:users,id',
            'title' => 'nullable|string|max:100',
        ]);

        $ids = collect();
        if (isset($data['user_id'])) $ids->push((int) $data['user_id']);
        if (isset($data['participants'])) $ids = $ids->merge($data['participants']);
        $ids = $ids->unique()->values()->reject(fn ($id) => $id === $me->id);
        abort_if($ids->isEmpty(), 422, 'Pick at least one person.');

        foreach ($ids as $id) {
            abort_if(SocialService::blockedBetween($me, User::find($id)), 422, 'You cannot message this person.');
        }

        if ($ids->count() === 1) {
            $target = $ids->first();
            $direct = Conversation::where('is_group', false)
                ->whereHas('participants', fn ($p) => $p->where('user_id', $me->id))
                ->whereHas('participants', fn ($p) => $p->where('user_id', $target))
                ->whereDoesntHave('participants', fn ($p) => $p->whereNotIn('user_id', [$me->id, $target]))
                ->first();
            if ($direct) {
                return response()->json(['conversation_id' => $direct->id]);
            }
        }

        $isGroup = $ids->count() > 1;
        $conv = Conversation::create([
            'title' => $isGroup ? ($data['title'] ?? $me->name."'s group chat") : null,
            'is_group' => $isGroup,
            'created_by' => $me->id,
            'last_activity_at' => now(),
        ]);
        $conv->participants()->attach($me->id);
        $conv->participants()->attach($ids->all());

        return response()->json(['conversation_id' => $conv->id]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $me = $request->user();
        abort_unless($conversation->participants()->where('user_id', $me->id)->exists(), 403);

        $data = $request->validate([
            'body' => 'nullable|string|max:5000',
            'type' => 'nullable|in:text,image,video,file,voice',
            'reply_to_id' => 'nullable|integer',
            'attachment' => 'nullable|file|max:30720',
        ]);

        if (! $request->hasFile('attachment') && blank($data['body'] ?? null)) {
            return response()->json(['message' => 'Empty message.'], 422);
        }

        $path = $mime = null;
        $type = $data['type'] ?? 'text';
        if ($request->hasFile('attachment')) {
            /** @var UploadedFile $file */
            $file = $request->file('attachment');
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $path = $file->store('messages', 'public');
            if ($type === 'text') {
                $type = str_starts_with($mime, 'image/') ? 'image'
                    : (str_starts_with($mime, 'video/') ? 'video'
                    : (str_starts_with($mime, 'audio/') ? 'voice' : 'file'));
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $me->id,
            'body' => $data['body'] ?? null,
            'attachment_path' => $path,
            'attachment_mime' => $mime,
            'reply_to_id' => $data['reply_to_id'] ?? null,
        ]);

        $conversation->update(['last_activity_at' => now()]);
        DB::table('conversation_participants')->where('conversation_id', $conversation->id)
            ->where('user_id', $me->id)->update(['last_read_message_id' => $message->id]);

        // site notification for other participants
        $others = $conversation->participants()->where('user_id', '!=', $me->id)->get();
        foreach ($others as $other) {
            SocialService::notify($other, $me, 'message', 'message', $message,
                'sent you a message'.($conversation->is_group ? " in {$conversation->title}" : ''));
        }

        return response()->json(['message' => $this->serializeMessage($message, $me)], 201);
    }

    public function react(Request $request, Message $message)
    {
        $me = $request->user();
        abort_unless($message->conversation->participants()->where('user_id', $me->id)->exists(), 403);
        $data = $request->validate(['type' => 'required|string|max:12']);
        $existing = MessageReaction::where('message_id', $message->id)->where('user_id', $me->id)->first();
        if ($existing && $existing->type === $data['type']) {
            $existing->delete();
            return response()->json(['ok' => true, 'removed' => true]);
        }
        MessageReaction::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $me->id],
            ['type' => $data['type']],
        );
        return response()->json(['ok' => true]);
    }

    public function edit(Request $request, Message $message)
    {
        $me = $request->user();
        abort_unless($message->sender_id === $me->id, 403);
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $message->update(['body' => $data['body'], 'edited_at' => now()]);
        return response()->json(['message' => $this->serializeMessage($message, $me)]);
    }

    public function destroy(Request $request, Message $message)
    {
        $me = $request->user();
        abort_unless($message->sender_id === $me->id, 403);
        $message->delete();
        return response()->json(['ok' => true]);
    }

    public function search(Request $request)
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));
        if ($q === '') return response()->json(['results' => []]);

        $results = Message::query()
            ->where('body', 'like', "%{$q}%")
            ->whereIn('conversation_id', DB::table('conversation_participants')->where('user_id', $me->id)->pluck('conversation_id'))
            ->whereNull('deleted_at')
            ->with(['conversation:id,title,is_group', 'sender:id,name,avatar'])
            ->orderByDesc('id')->limit(25)->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'body' => mb_substr($m->body, 0, 120),
                'conversation_id' => $m->conversation_id,
                'conversation_title' => $m->conversation?->title ?? ($m->conversation?->is_group ? 'Group chat' : null),
                'sender' => $m->sender ? ProfileController::basicUser($m->sender) : null,
                'created_at' => $m->created_at->diffForHumans(),
            ]);

        return response()->json(['results' => $results]);
    }

    /** @return array<int, array> */
    private function conversationList(User $me): array
    {
        return Conversation::query()
            ->whereHas('participants', fn ($p) => $p->where('user_id', $me->id))
            ->with(['participants' => fn ($p) => $p->withPivot('last_read_message_id'), 'lastMessage.sender:id,name,avatar'])
            ->orderByDesc('last_activity_at')->limit(50)->get()
            ->filter(function ($conv) use ($me) {
                if ($conv->is_group) return true;
                $other = $conv->participants->firstWhere('id', '!=', $me->id);
                return $other && ! SocialService::blockedBetween($me, $other);
            })
            ->map(fn ($conv) => $this->serializeConversation($conv, $me))
            ->values()->all();
    }

    private function serializeConversation(Conversation $conv, User $me, bool $withParticipants = false): array
    {
        $other = $conv->is_group ? null : $conv->participants->firstWhere('id', '!=', $me->id);
        $last = $conv->lastMessage;
        $pivot = $conv->participants->firstWhere('id', $me->id)?->pivot;
        $unread = 0;
        if ($last && $pivot && $pivot->last_read_message_id !== null) {
            $unread = Message::where('conversation_id', $conv->id)
                ->where('id', '>', $pivot->last_read_message_id)
                ->where('sender_id', '!=', $me->id)->count();
        } elseif ($last && (! $pivot || $pivot->last_read_message_id === null)) {
            $unread = Message::where('conversation_id', $conv->id)
                ->where('sender_id', '!=', $me->id)->count();
        }

        return [
            'id' => $conv->id,
            'kind' => $conv->is_group ? 'group' : 'direct',
            'title' => $conv->is_group ? $conv->title : $other?->name,
            'avatar_url' => $conv->is_group ? null : $other?->avatar_url,
            'hue' => $conv->is_group ? crc32((string) $conv->title) % 360 : ($other?->initialsHue() ?? 220),
            'other_id' => $other?->id,
            'last_message' => $last ? [
                'body' => $last->body ?? 'Attachment',
                'sender' => $last->sender?->name,
                'mine' => $last->sender_id === $me->id,
                'created_at' => $last->created_at->diffForHumans(),
            ] : null,
            'unread' => $unread,
            'updated_at' => $conv->last_activity_at?->diffForHumans(),
            'participants' => $withParticipants ? $conv->participants->map(fn ($u) => ProfileController::basicUser($u))->values()->all() : null,
        ];
    }

    private function messagesFor(Conversation $conv, User $me, ?int $before = null): \Illuminate\Support\Collection
    {
        $q = Message::where('conversation_id', $conv->id)
            ->whereNull('deleted_at')
            ->with(['reactions.user:id,name,avatar', 'replyTo.sender:id,name', 'sender:id,name,avatar'])
            ->orderByDesc('id')
            ->when($before, fn ($w) => $w->where('id', '<', $before))
            ->limit(60);

        $rows = $q->get()->reverse()->values();

        return $rows->map(fn ($m) => $this->serializeMessage($m, $me));
    }

    private function serializeMessage(Message $m, User $me): array
    {
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender' => $m->sender ? ProfileController::basicUser($m->sender) : null,
            'mine' => $m->sender_id === $me->id,
            'body' => $m->body,
            'type' => $m->attachment_path ? $this->typeOf($m) : 'text',
            'attachment_url' => $m->attachment_path ? asset('storage/'.$m->attachment_path) : null,
            'attachment_mime' => $m->attachment_mime,
            'reply_to' => $m->replyTo ? [
                'id' => $m->replyTo->id,
                'body' => mb_substr($m->replyTo->body ?? 'Attachment', 0, 80),
                'sender' => $m->replyTo->sender?->name,
            ] : null,
            'reactions' => $m->reactions->map(fn ($r) => [
                'type' => $r->type,
                'user' => ProfileController::basicUser($r->user),
            ])->values()->all(),
            'edited' => $m->edited_at !== null,
            'created_at' => $m->created_at->diffForHumans(),
            'created_at_iso' => $m->created_at->toIso8601String(),
        ];
    }

    private function typeOf(Message $m): string
    {
        return match (true) {
            str_starts_with((string) $m->attachment_mime, 'image/') => 'image',
            str_starts_with((string) $m->attachment_mime, 'video/') => 'video',
            str_starts_with((string) $m->attachment_mime, 'audio/') => 'voice',
            default => 'file',
        };
    }
}
