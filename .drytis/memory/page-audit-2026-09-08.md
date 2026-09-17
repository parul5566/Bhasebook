# Page audit postmortem — 2026-09-08 (20:45–21:00)

## Bugs found & fixed
1. **`/settings/blocked` was 500** — `FriendController::blockedIndex` eager-loads `blocked` on `App\Models\Block`, but the Block model had NO relationships defined. Added `blocker()` + `blocked()` belongsTo relations to `app/Models/Block.php`. (Note: Block model needs no `use App\Models\User` — same namespace.)
2. **`/reels` stuck on "Loading reels…" forever** — the empty-state check was `reels.length === 0 && !error`, which is also true after a successful-but-empty fetch. Added `loaded` state + proper empty state ("No reels yet"). `resources/js/Pages/Reels/Index.tsx`.
3. **No video content existed** — Reels & Watch pages were functionally dead because demo data had zero video/reel posts. ffmpeg not available, no internet; generated a REAL 21KB WebM (320×568, 2s animated gradient) via headless Chrome `canvas.captureStream()` + `MediaRecorder` (script pattern: /tmp/audit/genvideo.js). Saved as `database/seeders/fixtures/demo-reel.webm`; DemoSeeder now creates 3 reel posts + 1 watch video post pointing at `storage/app/public/seed/demo-reel.webm`. Video verified playable in browser (readyState 4, no error).

## Environment notes
- `run-tests.sh` and `.drytis/cred.json` were LOST at some point (untracked files; likely wiped during the DB/InnoDB recovery chaos). Recreated both — REMEMBER: they are untracked; publish them or they vanish again.
- A browser-audit harness lives at /tmp/audit/*.js (uses playwright from /opt/node/24/lib/node_modules/@playwright/mcp/node_modules/playwright + /usr/bin/google-chrome --no-sandbox). /tmp is wiped on container restart — rebuild if needed.

## Verification state (all green)
- 19 pages render in real Chrome with zero console/page errors, zero failed requests, no blank screens
- Watch video playable, 3 reels on /reels, stories tray populated
- 124/124 tests pass, live DB untouched (11 users)
- No new error-log entries since fixes

## Remaining known limitations
- Admin sub-routes (/admin/users etc.) don't exist by design — single /admin?tab= page
- New users must verify email before dashboard (standard Laravel; mail goes to log driver)
