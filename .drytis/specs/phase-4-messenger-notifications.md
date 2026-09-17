# Phase 4 — Messenger & Notifications

## Goal
Chat and notifications at feature parity with the web app.

## Screens
- ConversationsScreen (tab): GET messenger conversations — avatars, last message preview, unread dot, sorted by last_activity_at; new-chat button (search users → POST /messenger/start)
- ChatScreen: GET /messenger/{conversation}/messages?after_id= (polling every ~4s like web), bubbles mine/theirs, POST send (text), message reactions (POST /messages/{id}/react), delete own, reply-to rendering, message search (GET /api/v1/messages/search?q=)
- NotificationsScreen: GET /notifications — grouped icons by type (friend_request, friend_accept, follow, message, warning…), POST /{id}/read on tap, mark-all-read, unread badge on the shell
- NotificationSettingsScreen: POST /notifications/settings {prefs:{...}} toggles (persist server-side)

## Acceptance criteria
- [ ] Seeded conversation shows with Aarav's messages; sending a message appends it and clears the input
- [ ] Polling picks up messages sent from another session (web) within ~5s
- [ ] Reacting to a message shows the emoji on the bubble; deleting own message removes it
- [ ] Seeded notification (message from Aarav) appears; opening it marks read and badge count drops
- [ ] Toggling a setting persists (toggle survives app restart)

## Tests
- Widget: bubble layout mine/theirs/reply; unread dot logic
- Unit: polling timer lifecycle (cancel on dispose)

## Edge cases
- Conversation with a user who blocked you → friendly error, not a crash
- Long text wraps; emoji renders; empty conversation shows "Say hi 👋" state
- 401 during polling → stop polling and go to login
