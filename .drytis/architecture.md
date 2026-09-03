# Bhasebook — Architecture

## Stack
- **Laravel 12** (PHP 8.4) on MySQL, served by Caddy php_server at `/` → `public/`.
- **Inertia.js v2 + React 19 (SSR)**, Tailwind CSS v4, Ziggy routes, Vite.
- **Laravel Reverb** WebSockets + **database queue** worker — both registered background services (procmgr-managed, survive container pause/resume).
- **Broadcasting**: `reverb` driver; private channels `user.{id}`, `thread.{id}`, notifications via `Illuminate\Notifications`-style events to a `Notifications` React context.
- **Uploads**: local `public` disk (`storage/app/public` symlinked), finfo MIME sniffing, extension whitelist, size caps.
- **AI**: OpenAI-compatible endpoint (project key via env), called from queued jobs + one rate-limited HTTP controller; never blocks the request path except the assistant panel.

## Structure
- `app/Models/*` — User, Profile fields on User, Friendship, Follow, Block, Post, PostMedia, Poll*, Reaction (morph), Comment, SavedPost, Tag, LinkPreview, Story*, Notification, Group*, Page*, Thread, Message, Report, AdminAction…
- `app/Http/Controllers/*` grouped: `Auth`, `Profile`, `Social`, `Posts`, `Feed`, `Stories`, `Reels`, `Search`, `Notifications`, `Groups`, `Pages`, `Messenger`, `Privacy`, `Reports`, `Admin`, `Ai`.
- `app/Policies/*` for every model; `app/Http/Middleware/` — AdminOnly, SetTheme, SecureHeaders.
- `app/Services/` — FeedService (cursor pagination + ranking), VisibilityService (audience resolution incl. custom lists), MediaService (validation/sniff/store), SearchService, RecommendationService, Moderation/AiServices.
- `resources/js/Pages/*` mirror features; shared `Layouts/AppLayout.jsx` (sidebar + right rail + mobile bottom tabs), `Layouts/MobileNav.jsx`, `components/*` design-system primitives.
- Events/jobs: `PostCreated`, `CommentAdded`, `MessageSent` → broadcast; `ScanContentForRisk`, `NotifyUser`, `PruneExpiredStories` queued.

## Data flow rules
- Feeds are cursor-paginated JSON (Inertia partial reloads) — no offset pagination.
- All mutations via validated FormRequests + policies; rate-limited groups: auth, write, message, report, ai.
- Visibility resolved server-side in `VisibilityService` — single source of truth for posts/stories/profile fields; UI just renders.
- Soft deletes for posts/comments/messages (admin restore, "removed" states).

## Environments
- Env keys registered per key (tags for DB/app vars; secrets flagged); `.env` regenerated at deploy — never edit by hand.
- Background services: `reverb` (artisan reverb:start), `queue-worker` (artisan queue:work database); logs under /var/log/services.
