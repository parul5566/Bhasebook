# Phase 4 — Management, Privacy & Security

Covers: privacy settings enforcement, reporting/moderation workflow, admin dashboard, security hardening, docs.

## Shared schema (this phase)
- `reports`: reporter_id, reportable morph (post|comment|profile|group|page|message|reel/story), reason [spam|harassment|hate|nudity|violence|fake_account|copyright|other], details, status [open|reviewing|resolved_dismissed|resolved_removed], action_taken [warned|suspended|banned|content_removed|none], handled_by, handled_at, timestamps.
- `warnings`: user_id, reason, issued_by, message, acknowledged_at.
- `system_settings`: key, value (JSON) — e.g. `reels_enabled`, `groups_enabled`, `max_upload_mb`, `maintenance_mode_banner`.
- `moderation_logs`: admin_id, action, target morph, note, timestamps (audit trail).

## 1. Privacy settings (enforced everywhere)
Files: `app/Http/Controllers/Settings/PrivacyController`, policies updated to consult `privacy_settings`.
Acceptance:
- [ ] Settings page exposes: default post visibility, who can send friend requests, who can message me, who can see my friends list, who can find me (search), story visibility.
- [ ] Each setting is enforced in the running app: e.g. set "who can message me = friends" → a stranger's composer is blocked with a clear message; "who can find me = friends" → the user is absent from search for strangers.
- [ ] Blocked and restricted lists show in settings with unblock/unrestrict.
- [ ] Restricted users: see the author as always-offline, their messages land hidden (behavior mode), they see only public posts.

## 2. Reporting
Files: `app/Http/Controllers/ReportController`, report modal component (reusable on post/comment/profile/group/page/message/reel).
Acceptance:
- [ ] The 3-dot menu on every content type offers Report → reason picker + details; submission confirms; duplicate reports of the same content by the same user are prevented.
- [ ] Reported-but-unreviewed content remains visible (no auto-removal).

## 3. Admin dashboard
Files: `app/Http/Controllers/Admin/*` (DashboardController, UserController, ContentController, ReportController, SettingsController), `resources/js/Pages/Admin/*`.
Acceptance:
- [ ] Stats overview: total/active/new users, posts, comments, reactions, groups, pages, open reports, banned users — numbers correct vs database.
- [ ] Users: search, view, edit account fields, warn (user sees a warning banner on next login), suspend (time-boxed → "account suspended" on login), ban/unban (login refused with message), delete (with confirm).
- [ ] Content: browse/remove/restore posts, comments, reels, stories; removing makes it vanish user-side; restore brings it back.
- [ ] Reports queue: filter by status, open a report with context preview, dismiss or remove content + optionally warn/suspend/ban the author, one-click resolves; all actions land in the audit log.
- [ ] Groups & pages: search, view, edit/force-delete; stories & reels moderation lists.
- [ ] System settings: toggles (e.g. disable reels/groups platform-wide — when off, the UI hides them), max upload size; changes take effect without deploy.
- [ ] All admin routes guarded — a regular user hitting /admin gets 403.

## 4. Security hardening
Files: middleware (rate limiters on auth/posting/messaging/reporting), `App\Policies\*` complete coverage, upload validators, `config/cors.php`, response headers.
Acceptance:
- [ ] Every write endpoint has authorization (spot-checked with direct-URL attempts: editing others' posts → 403/404).
- [ ] Uploads enforce max size, MIME sniffing (not just extension), and safe serving; oversized/incorrect files are rejected with a friendly error.
- [ ] Rate limits on login, registration, posting, messaging, reporting return 429 with a friendly message; CSRF/XSS verified (script tag in a post renders as text, never executes).
- [ ] Passwords hashed (bcrypt), sessions invalidate on password change, mass-assignment guarded (no `User::create($request->all())`).

## 5. Documentation
- `README.md`: architecture overview, local setup (clone → composer/npm install → .env → migrate --seed → serve), env var table, feature map, admin seed instructions (`php artisan bhasebook:seed-admin`).

## Tests (Pest)
- Privacy setting enforcement per setting (stranger/friend scenarios); report lifecycle; admin action matrix (warn/suspend/ban/remove/restore) + non-admin 403; rate-limit hits; upload rejection (oversize, wrong MIME); settings toggles hide features.

## Edge cases
- Self-report impossible; banned user with active session is logged out on next request; suspended window expiry restores login; deleting a user cascades safely (content remains as "Unavailable" placeholders rather than FK crashes); report on already-removed content resolves instantly; audit log records actor IP.
