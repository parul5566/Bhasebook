# Phase 0 — Backend token API for mobile

## Goal
Give the Flutter app Sanctum personal-access-token auth at `/api/v1/*` without touching the existing web (session/Inertia) behaviour. After this phase every endpoint the app needs must respond correctly with `Accept: application/json` + `Authorization: Bearer <token>` and no CSRF/cookie requirements.

## Files to change
- `config/sanctum.php` (publish: `php artisan vendor:publish --tag=sanctum-config`), set `expiration => null`, add `guard => ['web']`
- `bootstrap/app.php`: add `->withRouting(api: routes/api.php)`; add `$middleware->api()` defaults; register `EnsureFrontendRequestsAreStateful` is NOT needed (tokens, not cookies)
- `routes/api.php` (new): all mobile endpoints
- `app/Http/Controllers/Api/AuthController.php` (new): login/register/logout/me/forgot-password — returns `token` + `user`
- `app/Models/User.php`: add `HasApiTokens` trait
- CORS: `config/cors.php` publish, allow the app's origins (or `api/*` paths, `*` origins for dev)

## Route set (routes/api.php)
- POST /api/v1/register → 201 {token, user}
- POST /api/v1/login → 200 {token, user}
- POST /api/v1/logout → 204 (auth:sanctum)
- GET /api/v1/me → 200 {user} (auth:sanctum)
- POST /api/v1/forgot-password → 200 (uses existing Password broker; with log mailer the token lands in storage/logs/laravel.log)
- auth:sanctum group proxying (reuse existing controllers as-is):
  - GET /api/v1/feed, GET /api/v1/stories/tray, GET /api/v1/reels, GET /api/v1/recommendations
  - GET /api/v1/notifications/unread, GET /api/v1/messages/search?q=, GET /api/v1/search/suggest?q=
  - POST /api/v1/ai/assist, POST /api/v1/recommendations/hide
  - posts: GET /api/v1/posts/{post} (+ POST /posts, PATCH, DELETE, /react /comments /vote /save /pin)
  - stories: POST /stories, POST /stories/{story}/view, DELETE /stories/{story}, GET /stories/{story}/viewers
  - friends: POST /friends/{user}/request|accept|decline|cancel|unfriend, POST /users/{user}/block|follow
  - groups: GET /groups, GET|PATCH /groups/{group}, POST /groups + /join /leave /invite /members/{user}/approve|remove|role, POST /group-invites/{invite}/respond
  - pages: GET /pages, GET|PATCH /pages/{page}, POST /pages + /follow /roles
  - messenger: POST /messenger/start, GET /messenger/{conversation}/messages, POST /messenger/{conversation}/send, POST /messages/{message}/react, DELETE /messages/{message}
  - notifications: GET /notifications, POST /notifications/{n}/read, POST /notifications/mark-all-read, POST /notifications/settings
  - search: GET /search?q=, POST /search/recents/clear
  - profile: GET /profile/{user}, PATCH /profile, PUT /password, POST /profile/deactivate, DELETE /profile
  - GET /memories, GET /saved, GET /hashtag/{tag}, POST /reports
  - admin: GET /admin, POST /admin/users/{user}/action, POST /admin/reports/{report}/action, POST /admin/ai-flags/{flag}/action, POST /admin/settings

Implementation shortcut: these can largely reuse existing controller methods — wrap with `Route::middleware('auth:sanctum')` and reuse the same controller@method targets. The web routes stay untouched.

## Acceptance criteria (user-visible, app-relevant)
- [ ] POST /api/v1/login with seeded demo@bhasebook.test / Password@123 returns 200 with a token string and the user object
- [ ] GET /api/v1/me with `Authorization: Bearer <token>` returns the logged-in user; without it returns 401 JSON
- [ ] GET /api/v1/feed with the token returns the same JSON shape as /api/feed on web
- [ ] POST /api/v1/register creates a verified-status user and returns a token in one call
- [ ] Existing web app continues to work unchanged (login via browser still works, Inertia pages unaffected)
- [ ] Full existing test suite still passes

## Tests
- Feature test: login returns token; me endpoint works with token; 401 without; register flow; one proxied endpoint (feed) via token
- Run `./run-tests.sh` — suite must stay green

## Edge cases
- Bearer token from a deleted/revoked session → 401 {message} JSON, not an HTML redirect
- Validation errors → 422 with Laravel's standard {message, errors} shape (Flutter parses this)
- Login with wrong password → 422, not 500
- Rate limiting on login (throttle:5,1) to avoid brute force
