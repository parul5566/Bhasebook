# Bhasebook — Scope

Facebook-inspired social network, original branding. Web + responsive mobile web (360px→desktop).

**Roles**: regular user; admin.

**In scope (phases)**
- P0 Foundations: Laravel 12 + Inertia/React + Tailwind 4, MySQL, Reverb WS + DB queue services, env config, design system (blue palette, Inter, rounded cards, light/dark/system), app shell with mobile bottom nav.
- P1 Core: auth (register/verify/forgot/reset/remember/deactivate), profiles, friends+follow+block, posts (text/photos/video/poll/feeling/location/tags/link preview, visibility incl. custom), 6 reactions, nested comments with attachments/reactions, save/share/hashtags/memories, infinite news feed.
- P2 Social: 24h stories (privacy/views/reactions), reels + Watch, global search (6 entity types, suggestions, recents), real-time categorized notifications via WebSockets, groups (roles/approval/group posts/rules), pages (follow/post-as-page/roles/insights/messaging).
- P3 Management: real-time messenger (1:1 + group, media, voice notes, typing/seen), enforced privacy settings + tag review, blocked/restricted lists + message requests, reporting with reasons, admin dashboard (stats/charts, user warn/suspend/ban, content moderation, groups/pages oversight, platform settings), security hardening (policies everywhere, rate limits, MIME-sniffed uploads, secure headers).
- P4 AI & polish: composer AI assistant, AI moderation assist (flags+scores, admin decides), smart recommendations + explore, seed/demo world, responsive/dark/a11y polish, README.

**Out of scope**: native mobile apps, live audio/video calls, marketplace/jobs/events modules, paid ads, external email provider (log mailer), third-party CDNs.

Detailed per-phase specs: `specs/phase-0-foundations.md` … `specs/phase-4-ai-polish.md`.
