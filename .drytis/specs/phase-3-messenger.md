# Phase 3 — Communication (Real-Time Messenger)

Covers: 1-to-1 and group chat, presence, typing, receipts, rich messages, search.

## Shared schema (this phase)
- `conversations`: kind [direct|group], title (group), avatar_path, created_by, last_message_at; direct conversations unique on the (sorted) participant pair via a `direct_key` hash column.
- `participants`: conversation_id, user_id, role [owner|member], joined_at, last_read_at, is_pinned, is_muted, timestamps.
- `messages`: conversation_id, user_id (nullable for system messages), type [text|image|video|file|voice|system], body, duration_seconds, reply_to_id nullable, edited_at, deleted_at (nullable — soft delete shows "message deleted"), timestamps. Index (conversation_id, id).
- `message_attachments`: message_id, path, kind, mime, size.
- `message_reactions`: message_id, user_id, type, unique.

## Features
Files: `app/Http/Controllers/Messenger/*` (ConversationController, MessageController, PresenceController), `app/Events/*` (MessageSent, MessageDeleted, Typing, ReadReceipt), Reverb channels, `resources/js/Pages/Messenger/*` (conversation list, chat window, composer).

Acceptance (running app):
- [ ] Conversation list → chat window → composer layout; sending a text message appears instantly on BOTH sender and recipient screens without refresh (websocket).
- [ ] Online/offline status (green dot), typing indicator ("X is typing…"), single check (sent), double check (delivered), and read receipts (seen) all update live.
- [ ] Group chats: create from conversation list, name/avatar, add/remove members (system messages announce changes).
- [ ] Image, video, file, and voice-message attachments all send with progress and play/preview inline; voice records via MediaRecorder with a max duration.
- [ ] Message reactions, reply-to (quotes the original), edit (shows "edited"), and delete ("message deleted" placeholder) all work live on both sides.
- [ ] Search within a conversation filters messages; conversation-list search filters conversations by name/other participant.
- [ ] Unread badges on the messenger icon and per-conversation update live and clear on open.
- [ ] Blocking a user deletes/hides the direct conversation with them and prevents new messages both ways.
- [ ] All messenger pages are responsive: full-screen chat on mobile, docked window on desktop.

## Real-time plumbing
- Private channels `conversation.{id}` guarded by `Broadcast` authorization (participants only).
- Presence channel for online status; typing events throttled client-side (emit at most every 1.5s while typing).

## Tests (Pest)
- Conversation creation + direct-key uniqueness (no duplicate direct threads); authorization (non-participant cannot read/send → 403); message CRUD + soft delete semantics; receipts update `last_read_at`; blocked user message attempt rejected; attachments validated by mime/size.

## Edge cases
- Message during recipient offline → delivered receipt on reconnect; concurrent senders ordering by id not timestamp; long messages wrap without breaking layout; voice message permission denied → friendly error; conversation with 500+ messages paginates (reverse cursor) without jank; page messaging routes into the messenger using the same direct-thread mechanics.
