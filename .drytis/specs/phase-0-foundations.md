# Phase 0 — Foundations & Scaffolding

## Goal
Stand up the runnable skeleton: Laravel 12 + Inertia/React (SSR) + Tailwind CSS 4 + MySQL, the Bhasebook design system, background services (Reverb WebSockets + database queue worker), and env configuration. Everything later builds on this.

## Stack decisions (fixed for the project)
- **Backend**: Laravel 12 on PHP 8.4, served natively by Caddy (`public/` docroot, php_server route already exists).
- **Frontend**: Inertia.js v2 + React 19 (SSR enabled), Tailwind CSS v4, Ziggy for routes, Vite build.
- **DB**: MySQL (auto-provisioned — credentials from `get_project_details`, registered as env keys, NEVER hardcoded).
- **Realtime**: Laravel Reverb (`php artisan reverb:start`) as background service; queue worker `php artisan queue:work database` as background service. Registered via `add_background_service`, managed by procmgr.
- **Uploads**: local `public` disk under `storage/app/public` (symlinked), validated by MIME sniffing (`finfo`), max sizes enforced. No external services.
- **AI**: project OpenAI-compatible key minted via `create_openai_api_key`, stored as secret env `OPENAI_API_KEY` + `OPENAI_BASE_URL` (Phase 4).

## Env keys to register (`bulk_add_environment_keys`, file `/workspace/.env`)
APP_NAME=Bhasebook, APP_ENV, APP_DEBUG, APP_URL ({{SITE_URL}}), DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD (database_* tags, DB_PASSWORD secret), SESSION_DRIVER=database, QUEUE_CONNECTION=database, BROADCAST_CONNECTION=reverb, FILESYSTEM_DISK=public, REVERB_* set to local app values, MAIL_MAILER=log, OPENAI_API_KEY (secret, Phase 4).

## Design system ("Bhasebook" original identity)
- **Brand**: original wordmark + SVG logo (custom lowercase "b" mark in a rounded square). NOT Facebook's assets.
- **Palette**: primary blue `#1D7AFC`, hover `#1667DB`, deep navy `#0B1F3F` (text), soft blue-gray surfaces `#F0F4F8`, semantic success/warn/danger. Dark theme equivalents defined as CSS variables.
- **Type**: Inter (self-hosted via @fontsource or npm), scale 12–24px.
- **Shapes**: rounded-xl (16px) cards, rounded-full buttons/avatars, soft layered shadows.
- **Themes**: light / dark / system, persisted per user (DB column + localStorage, Tailwind `dark:` class strategy).
- **App shell**: desktop — left sidebar nav, center feed column, right rail (contacts/suggestions); mobile (<md) — top search bar + bottom tab bar (Home, Reels/Watch, Create, Notifications, Messenger, Profile). Active/hover/focus states everywhere.

## Files (indicative)
- `composer.json`, `package.json`, `vite.config.js`, `tailwind.config` / `app.css` tokens, `resources/js/{App.jsx,Layouts,Components,Pages}`, `resources/css`, `app/Http/Middleware/SetTheme.php`, config files, `.env` (generated from env keys), public logo/icon SVGs, favicon.

## Acceptance criteria (user-visible)
- [ ] The app loads at the preview URL showing an original Bhasebook welcome/landing page (logo, wordmark, login/register buttons) — no Laravel default page.
- [ ] Light/dark/system theme toggle works and the choice survives reload.
- [ ] Layout is usable from 360px to desktop; mobile shows the bottom tab bar.
- [ ] `php artisan migrate` runs clean against MySQL; Reverb + queue worker show running in `procmgr status`.
- [ ] No PHP/JS errors in logs on first page load.

## Tests
- Feature test hitting `/` returns 200 and contains "Bhasebook".

## Edge cases
- Reverb restart on container resume (background services survive pause); theme flash on load avoided (inline script sets class before paint).
