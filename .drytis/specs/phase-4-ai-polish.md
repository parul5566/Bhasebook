# Phase 4 — AI features, recommendations & polish

## Goal
The AI layer on the OpenAI-compatible project key, smart recommendations, seed/demo data, and final polish passes.

## Features

### AI content assistant (composer)
- Button in composer: "AI assist" panel with modes: ideas (from typed topic), captions (3 suggestions), hashtags (relevant set), rewrite (tone: friendly/professional/funny/short). Streaming or quick responses, insert-into-composer action, uses project key (`OPENAI_API_KEY`/`OPENAI_BASE_URL` env), per-user daily quota (rate-limited), graceful degradation with friendly error when API unavailable.

### AI moderation assist
- Async job on post/comment/story creation + on-demand re-scan: flags content (harassment/spam/nudity/violence signals) with priority score; creates entries in admin moderation queue flagged "AI flagged" with the score and reason; ADMIN decides (never auto-remove). Re-scan action per content item; queue-driven so it never blocks posting.

### AI smart recommendations
- Sidebar widgets + dedicated /explore: suggested people (graph: friends-of-friends weighted, shared groups/pages), suggested groups, suggested pages, suggested posts (engagement-based), suggested reels. Cached per user, "show less/why am I seeing this" feedback.

### Polish & hardening pass
- Full responsive audit 360→1920, dark mode audit of every page, empty/loading/error states audit, keyboard accessibility + focus states, SEO meta/OG tags, favicon/PWA manifest, seeders: demo users with avatars (generated originals), friendships, posts with media (generated placeholder images), stories, reels, groups, pages, chats, notifications — one command refreshes demo world. README with setup notes.

## Acceptance criteria (user-visible)
- [ ] Composer "AI assist" returns caption ideas / hashtags / rewrite that can be inserted into the post box; quota message appears after limit.
- [ ] New risky seeded content appears in the admin moderation queue as "AI flagged" with score+reason within seconds (queue-run), without being auto-removed.
- [ ] Suggestions widgets on feed show relevant people/groups/pages; hiding a suggestion removes it.
- [ ] Explore page aggregates recommended content; feedback link works.
- [ ] Seeded demo world loads with rich data across all surfaces; `db:seed` runs idempotently.
- [ ] Every page verified in dark mode and at 360px; no console errors.

## Tests
Pest: assistant endpoint validation + fallback error, moderation flag job creates queue entry with score, recommendation endpoint returns non-friends, seeder idempotency.

## Edge cases
- AI API down → assistant shows retry toast, moderation job retries with backoff and never blocks the post; quota race; empty suggestions fallback; long content truncation for model context.
