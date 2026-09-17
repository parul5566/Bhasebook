# Phase 5 — Groups, Pages, Friends, Search

## Goal
Community screens at parity with web.

## Screens
- GroupsScreen: tabs Discover / My groups (GET /groups returns both); create dialog (name, description, privacy); group card shows member count + creator
- GroupDetailScreen: GET /groups/{id} (?tab=feed|members|about|requests) — feed posts (reuse PostCard), join/leave (POST /join /leave), invite (POST /invite), owner: approve/remove member, role change (admin/moderator)
- PagesScreen + PageDetailScreen: same pattern; follow/unfollow; page admin: role management; page posts authored as the page (author.type === 'page' rendering)
- FriendsScreen: tabs Friends / Requests / Suggestions (GET /friends); accept/decline/cancel request, unfriend with confirm, block (POST /users/{id}/block) with confirm
- SearchScreen: GET /search?q= — people/posts/groups/pages/reels/hashtags sections; debounced suggestions (GET /api/v1/search/suggest?q=); recent searches via POST /search/recents/clear

## Acceptance criteria
- [ ] Discover shows the 3 seeded groups with member counts; joining one moves it to My groups and the member count +1
- [ ] Group feed shows the seeded cupping-session post; posting inside the group works
- [ ] Pages: following "Bhasebook Official" toggles the button state; page post renders with page avatar/name
- [ ] Friends tab lists the seeded friendships; accepting nisha's request makes her appear in Friends
- [ ] Search "biryani" finds the post; searching a person's name returns them under People; suggestions appear while typing
- [ ] Blocking a user hides their content from feed/reels (server already enforces)

## Tests
- Widget: group card, friend request row states (pending/accept/decline)
- Integration-style: search renders all sections with seeded data

## Edge cases
- Private group → join request state, members tab hidden until approved
- Search with empty q → suggestions only; no crash
- Unfriend confirm dialog cancel → no action
