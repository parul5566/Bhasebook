# Flutter mobile app (Bhasebook) — key facts

- App lives at `/workspace/mobile` (package `bhasebook_mobile`, org com.bhasebook). **Flutter SDK does NOT survive container rebuilds outside /workspace — current install: `~/flutter` (home persists), installed by downloading flutter_linux_3.24.5-stable.tar.xz from storage.googleapis.com/flutter_infra_release.** `/opt` is NOT writable (permission denied for coder). After any SDK reinstall run `flutter pub get` before analyze. No Java/Android SDK — verify via `flutter analyze` / `flutter test` / `flutter build bundle`.
- Backend: Phase 0 added token API for mobile (Sanctum). `routes/api.php` under `/api/v1/*` with `auth:sanctum`; auth endpoints `auth/login|register|forgot-password|me|logout`. All page controllers follow render + `*Data()` payload pattern (web Inertia and API share one source of truth). Gate 'admin' in AppServiceProvider.
- Flutter structure: lib/api/api_client.dart, lib/api/api_endpoints.dart, lib/models/post.dart, lib/state/auth_state.dart, lib/app_router.dart, screens per feature, widgets/ (post_card, post_card_host, reactions, story_tray, common).
- Gotcha: `write_file` with relative paths can land at /workspace root — use ABSOLUTE paths for mobile files.
- Gotcha: Flutter 3.24 has no `Color.withValues` (3.27+) — use `withOpacity`.
- Story tray IS wired into FeedScreen (ticket #9910): row 0 of the feed list, `_open()` refetches /stories/tray and pushes StoryViewer(rootNavigator). Create-story tile currently opens the viewer (POST /stories needs media upload UI — not built).
- Final verification (2026-09-08): flutter analyze 0 issues; flutter test 4/4 pass; flutter build bundle OK; Laravel suite 132 passed / 545 assertions.
- Credentials for API testing: demo@bhasebook.test / Password@123, admin@bhasebook.test / Admin@12345 (in .drytis/cred.json).
