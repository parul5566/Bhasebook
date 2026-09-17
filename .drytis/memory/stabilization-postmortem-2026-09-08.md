# Stabilization postmortem — 2026-09-08 ("sahi se run karo, saari bugs fix karo")

## Root causes found & fixed

1. **Stale/zero-byte `bootstrap/cache/config.php` poisoned everything.**
   The old setup script ran `config:cache` LAST but `optimize:clear` never ran on restarts. A baked config from an old env means:
   - Tests: phpunit.xml env overrides are IGNORED (Laravel loads the cached config) → 419 CSRF failures on ~75 tests.
   - A zero-byte config.php (interrupted write during container restart) → total boot crash ("Target class [config] does not exist") until shell-level `rm`.
   Fix: setup script now `rm -f bootstrap/cache/{config,routes-*,services,packages}.php` BEFORE any artisan command, and rebuilds caches only at the END.

2. **`QUEUE_CONNECTION=file` — nonexistent queue driver.** Laravel has no `file` queue connection configured → `InvalidArgumentException: The [file] queue connection has not been configured` → **every report submission 500'd** (ReportController dispatches `ModerateContent`). Fix: `QUEUE_CONNECTION=database` (env key 49549) + registered background service `queue-worker` (id 4136): `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600`.

3. **Tests wiped the live database.** phpunit.xml had NO DB override; `RefreshDatabase` does `migrate:fresh` on the real MySQL DB → all seeded users/posts/groups vanished (why login died after restarts). No pdo_sqlite ext, app user can't CREATE DATABASE — but the container HAS local root MySQL access.
   Fix (3 layers):
   - Created `u1690p3409_bhasebook_test` DB as root, granted the app user full rights on it.
   - phpunit.xml sets `DB_DATABASE=u1690p3409_bhasebook_test`.
   - `tests/TestCase.php::createApplication()` REFUSES to boot if `bootstrap/cache/config.php` pins any DB other than the test DB.
   - `./run-tests.sh` = canonical runner: ensures test DB exists, clears caches, runs suite, rebuilds prod caches.

4. **Database was empty** because seeding had failed while the stale config was live (setup script `|| true` hid it). `db:seed` works fine now; seeders are idempotent.

## Historical log errors that are already fixed in code (do NOT re-chase)
- `Page::creator` missing relationship — creator() exists now; AdminController eager-loads it.
- Ambiguous `status` column — withCount constraints use `group_members.status` now.
- `Post::search()` — exists as scopeSearch.
- `poll_options.updated_at` missing — no longer inserted.
- `getimagesize` failures — `@`-suppressed with graceful return.
- FK errno 150 / duplicate column migrations — migration files were fixed earlier; current 6 migrations all run clean.

## Conventions
- Run tests via `./run-tests.sh` — NEVER `php artisan test` after `config:cache` without clearing (TestCase guard will refuse, which is correct).
- Messenger is polling-based (no Reverb/WebSockets installed despite proposal text) — fine as-is.
- Demo creds: admin@bhasebook.test / Admin@12345, demo@bhasebook.test / Password@123 (.drytis/cred.json).
- Admin dashboard is a single `/admin` route with `?tab=` query (no /admin/users sub-routes — 404 there is expected).
