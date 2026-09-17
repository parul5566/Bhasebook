# Phase 1 — Flutter scaffold: theme, API client, auth state, router, shell

## Goal
A runnable Flutter project at `/workspace/mobile` that boots to a login screen against the live backend, holds a token, and has the bottom-nav shell — so every later phase is just adding screens.

## Structure
- `mobile/` (new Flutter project, `flutter create --org dev.bhasebook --project-name bhasebook`)
- `lib/core/theme/` — bhas palette (light + dark) from tailwind.config.js, Inter via google_fonts, BhasCard/BhasButton/BhasInput widgets mirroring the CSS classes
- `lib/core/api/` — Dio client, `Authorization: Bearer` interceptor, 401 → auto-logout, 422 errors → typed ApiException with field errors, base URL from `--dart-define=API_BASE_URL` (default https://bhasebook-umcq4u.drytis.dev)
- `lib/core/auth/` — Riverpod auth controller: login/register/logout/me, token persisted via flutter_secure_storage
- `lib/core/router/` — go_router with auth redirect (unauthenticated → /login, authenticated → shell)
- `lib/core/widgets/` — ErrorRetry, EmptyState, LoadingSpinner, Avatar (hue-based initials fallback like web), PostCard shell
- `lib/features/shell/` — bottom nav: Feed / Search / Reels(centre) / Messenger / Profile, with unread notification badge from /api/v1/notifications/unread

## Acceptance criteria
- [ ] `flutter run` (or build) launches; without a token the app shows Login
- [ ] Logging in with demo@bhasebook.test / Password@123 lands on the shell; wrong password shows a field error, no crash
- [ ] Killing and reopening the app keeps the session (secure storage)
- [ ] Logout returns to Login and the stored token is gone
- [ ] Bottom nav switches between 5 placeholder tabs with correct badges where the API has unread counts
- [ ] Dark mode toggles (follows system by default, like web ThemeToggle)

## Tests
- Widget test: login form validation, error display
- Unit test: Dio interceptor adds bearer; 401 triggers logout

## Edge cases
- Backend down / no network → friendly retry UI, not a red screen
- Token expired mid-session → auto-logout to login screen
- Android: cleartext not needed (https), but INTERNET permission must be in the manifest
