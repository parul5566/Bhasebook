# Phase 3 — Messenger, privacy, reporting & moderation, admin

## Goal
Real-time 1:1 and group chat, plus the safety/management layer: enforced privacy, blocking/restricting, reporting, and the full admin dashboard.

## Features

### Messenger (real-time)
- Thread list (recency, unread badges, online presence dots) + conversation view: text, emoji picker, photo/video attachments, voice notes (record in browser, upload), reply-to, forward, reactions on messages, seen indicators, typing indicator.
- Group conversations (name + avatar, add/remove participants), search within conversations, message deletion (for me / for everyone), mark unread.
- Delivery: messages sent via Reverb private channels — instant on both sides; unread counts live in nav badge.
- Blocked users can't initiate/continue threads; page threads from Phase 2 appear here.

### Privacy settings (enforced server-side)
- Profile field visibility (email, birthday, friends list per-field: everyone/friends/only-me).
- Default post visibility default, who can send friend requests, who can message, tagged-post review (approve before appearing on profile), story visibility defaults, activity log page (login history + tag review queue).

### Blocking & restricting
- Blocked list page (unblock), restricted list (restricted can only see public posts; chat hidden from them → messages go to message requests), message requests inbox (accept/decline).

### Reporting
- Report user/post/comment/story/reel/group/page with reason taxonomy (spam, harassment, nudity, violence, misinformation, other+text), reporter sees status of own reports page, rate-limited.

### Admin dashboard (/admin, admin-gated)
- Overview: stat cards (users, DAU proxy, posts, comments, reports open/closed, messages) + trend charts (canvas-based, no heavy libs) over 30 days.
- Users: search/filter table, detail drawer (profile, stats, warns), actions: warn (email+banner), suspend (until date), ban (permanent), force logout, reset avatar; audit trail.
- Content moderation: queue of reported content with context (report reasons stacked), remove content (soft-delete), restore, dismiss reports; bulk actions.
- Groups & pages: list, force-close/dissolve, transfer ownership, approve.
- Messages: message-report handling (view thread snippet, warn/ban).
- Settings: platform toggles (registration open/closed, max upload sizes, rate-limit presets, banned words list applied on create).

### Security hardening
- Policies on every route (post/comment/group/page/admin), rate limiting (throttle:api on auth, posting, messaging, reporting), MIME-sniffed uploads (finfo) + extension whitelist + max sizes, secure headers middleware (X-Frame-Options, CSP, X-Content-Type-Options, Referrer-Policy), mass-assignment protection, form request validation everywhere.

## Acceptance criteria (user-visible)
- [ ] Two browsers: message sent from A appears instantly at B with typing indicator and seen state; group conversation with 3 users delivers to all.
- [ ] Voice note records, uploads, and plays back in-thread.
- [ ] Blocked user cannot message; restricted user's messages land in message requests.
- [ ] Privacy toggles actually hide email/birthday/friends from non-friends viewing a profile.
- [ ] Tag review: post where user is tagged by someone else doesn't show on their profile until approved.
- [ ] Any content type can be reported with a reason; reporter's "My reports" page shows statuses.
- [ ] Admin overview shows live stats and charts; suspending a user logs them out and blocks login until the date; banning works permanently; warned users see a warning banner.
- [ ] Admin can remove a reported post — it disappears for everyone, shows "removed by admin" state, and can be restored.
- [ ] Registration toggle in settings actually closes open registration.
- [ ] Non-admin hitting /admin gets 403.

## Tests
Pest: messaging send/receive/read, block/restrict enforcement, privacy field matrix, report lifecycle (open → reviewed → resolved), admin policy (403s for regular user), suspension enforcement on login, banned-words filter.

## Edge cases
- Race: two users send simultaneously in a thread (ordering by created_at + id); unsent message states; large voice note upload limit; report spamming rate-limited; admin acting on own account.
