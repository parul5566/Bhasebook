# Phase 6 — Profile, Memories, Saved, Admin + polish

## Goal
Remaining screens and app-wide polish; release-ready.

## Screens
- ProfileScreen (own + others): GET /profile/{user} — cover/avatar (hue fallback), bio, stats, tabs (posts/about/friends); edit (PATCH /profile: name, bio, avatar/cover upload, visibility, default post visibility); change password (PUT /password with current_password); deactivate/delete account flows; blocked list (GET /settings/blocked, unblock)
- MemoriesScreen: GET /memories — on-this-day posts; SavedScreen: GET /saved — saved posts (reuse PostCard)
- AdminScreen (is_admin only): bottom sheet/tab UI for the 9 admin tabs (overview stats, users list + warn/suspend/ban/verify actions, recent posts/comments, reports with approve/dismiss, groups, pages, AI moderation flags with action, settings key-value editor) — actions POST to the admin endpoints
- Polish: global error/empty/loading states audited on every screen; pull-to-refresh everywhere; haptics on react; app icon + splash (flutter_native_splash) using the Bhasebook logo; Android release build config

## Acceptance criteria
- [ ] Own profile shows the demo user's posts/bio; editing bio persists and shows after restart
- [ ] Change password with wrong current_password shows a field error; with correct one succeeds
- [ ] Memories shows the seeded year-old post; Saved lists posts saved in Phase 2
- [ ] Admin account sees the Admin entry; overview stats match web (users/posts counts); warning a user from the app reflects on web
- [ ] Non-admin accounts see no admin entry
- [ ] Every screen has a designed empty and error state (no raw exceptions anywhere)
- [ ] `flutter build apk --release` succeeds

## Tests
- Widget: profile stats, admin action confirmation dialogs
- Golden: theme components (BhasCard/BhasButton) light + dark

## Edge cases
- Restricted/private profile → RestrictedScreen like web
- Deactivating account → logout to login screen
- Admin actions on self → 403 from server, shown as snack bar
