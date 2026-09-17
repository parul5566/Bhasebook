# Flutter Mobile App — Blueprint

## Goal
A Flutter app mirroring the Bhasebook web app: all screens, same design language (bhas palette, Inter font, dark/light), wired to the existing Laravel backend with zero runtime errors.

## Critical constraint discovered during planning
The Laravel backend serves **web routes only** (session cookies + CSRF + Inertia). A mobile app cannot reuse these. Plan:
- **Backend phase**: add Sanctum **token auth** (`/api/v1/*` endpoints: login, register, logout, me + forgot-password). Sanctum is already in composer.json; publish config, register API routes, keep existing web routes untouched.
- The Flutter app calls `https://bhasebook-umcq4u.drytis.dev/api/v1/...` with `Authorization: Bearer <token>`.
- Existing endpoints that return JSON already (api/feed, api/stories/tray, api/reels, api/recommendations, api/notifications/unread, api/messages/search, api/search/suggest, api/ai/assist) currently live on the **web** middleware — they must ALSO be reachable via token auth (register duplicate /api/v1 routes or add the stateful/token guard).

## Backend endpoints inventory (what the app consumes)
Already JSON: /api/feed, /api/stories/tray, /api/reels, /api/recommendations, /api/notifications/unread, /api/messages/search, /api/search/suggest, /api/ai/assist, /api/recommendations/hide.
Inertia/JSON hybrid (work with Accept: application/json): posts CRUD + react/comment/vote/save/pin, stories CRUD, groups (index/show/join/leave/invite/roles), pages (index/show/follow/roles), friends (request/accept/decline/cancel/unfriend/block), messenger (start/send/messages), notifications (index/read/settings), search (show/recents), profile (show/update), memories, saved, hashtag/{tag}, reports, admin (dashboard + actions).

## Design system mapping (web → Flutter)
- Palette: bhas-* (from tailwind.config.js) → ColorScheme light/dark
- Font: Inter (google_fonts)
- Components: bhas-card/btn/input → Flutter widgets (BhasCard, BhasButton, BhasInput)
- Icons: custom Icons.tsx set → Material + custom SVG (flutter_svg)
- Layout: mobile-first already (bottom nav exists on web) → reuse same nav model

## Phases
0. Backend token API (prerequisite)
1. Flutter scaffold: project, theme, API client (Dio + token interceptor), auth state (Riverpod), router (go_router), error/empty/loading widgets
2. Auth screens (login, register, forgot/reset password, verify-email notice) + main shell (bottom nav + unread badges)
3. Feed + post interactions (composer, reactions, comments, poll vote, save, share, hashtag, post detail)
4. Stories (tray, viewer, create) + Reels (vertical pager) + Watch (grid) 
5. Messenger (conversations, chat, send, poll) + Notifications (list, settings, unread badge)
6. Groups, Pages, Friends, Search (results + suggest), Profile (show/edit/password), Memories, Saved
7. Admin (tabs: overview/users/posts/comments/reports/groups/pages/moderation/settings) + polish: empty/error states everywhere, pull-to-refresh, offline handling

## Non-goals
- Push notifications (FCM) — later
- Media upload from app can reuse existing endpoints (multipart), included in feed/stories phases
- App store publishing
