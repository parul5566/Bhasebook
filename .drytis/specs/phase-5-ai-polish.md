# Phase 5 — AI Features & Final Polish

Covers: AI content assistant, AI moderation assist, AI recommendations, seed/demo data, final polish pass.

## AI key management
- Mint a project API key (OpenAI-compatible) via `create_openai_api_key`, store as `AI_API_KEY` (secret) + `AI_BASE_URL` in `/workspace/.env` via environment keys.
- Server-side proxy controller (`AiController`) — the browser never sees the key. Features degrade gracefully (friendly "AI unavailable" toast) when the key/quota fails.

## 1. AI content assistant (post composer)
Acceptance (running app):
- [ ] Composer's "AI assist" button opens a panel with: generate post ideas (from a topic input), improve/rewrite my draft caption, suggest hashtags, suggest title for long posts.
- [ ] Generated text is inserted into the composer as an editable draft — never auto-published.
- [ ] While waiting, a loading state shows; errors surface a friendly retry, never a stack trace.

## 2. AI moderation assist
Files: `ModerationAiService` + queue job on content create/report; `ai_flags` columns on `reports` (ai_score, ai_summary).
Acceptance:
- [ ] New posts/comments/reels/reports receive an AI risk score + short summary; scores appear to admins on the report detail page with the reasoning.
- [ ] High-score reports are auto-prioritized (sorted to top of the queue) — but AI NEVER removes content or bans users on its own; every irreversible action needs an admin click.
- [ ] A settings toggle can disable AI moderation.

## 3. AI recommendations
Files: `RecommendationService`, surfaced as "Suggested for you" rails.
Acceptance:
- [ ] Home right rail shows suggested people (mutual-friend weighted), and "suggested groups/pages" with working join/follow buttons.
- [ ] Feed occasionally interleaves recommended posts (labeled "Suggested for you") based on the user's reactions/follows; reels feed mixes recommended reels.
- [ ] With a fresh account (no interactions) suggestions still populate (popularity fallback) and never repeat content the user just dismissed.

## 4. Seed/demo data + final polish
Files: `database/seeders/*` (demo users with avatars, friendships, posts with images, comments, reactions, stories, reels, groups, pages, conversations, notifications), `php artisan bhasebook:seed-demo`.
Acceptance:
- [ ] `migrate --seed` (or seed-demo command) produces a lively demo: ≥10 users, avatars/cover images, posts with varied media, comments/replies/reactions, active stories, a few reels, 2 groups, 2 pages, sample conversations and notifications, and 1 admin account.
- [ ] Login as a demo user works instantly (credentials listed on the login page in the local/demo environment only).
- [ ] Final pass: every page renders its loading skeleton, empty state, and error state; broken/missing images show branded placeholders; dark mode has no contrast bugs on any screen; 360px layout verified on all main pages; feed scroll is smooth with lazy media.
- [ ] README documents everything and the preview URL shows a complete, polished app.

## Tests (Pest)
- AI proxy endpoint auth + graceful failure (mocked upstream); assistant output inserted as draft; moderation job writes score and reorders queue; recommendation service returns sane sets for cold + warm users (mocked model where needed).

## Edge cases
- AI provider timeout/quota → features show "unavailable" states, core app unaffected; concurrent AI calls rate-limited per user (5/min); recommendation rail contains no blocked/deactivated users; long AI outputs truncated for UI; demo seed is idempotent (re-running doesn't duplicate).
