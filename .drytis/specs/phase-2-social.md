# Phase 2 — Social (stories, reels, search, notifications, groups, pages)

## Goal
The engagement layer: 24h stories, vertical reels + Watch, global search, real-time notifications, and the two community surfaces — groups and pages.

## Features

### Stories (24h)
- Create photo/video/text story with background gradient, privacy (public/friends/custom/hide-from), view from story rail on feed; auto-expire after 24h (scheduled prune via queue).
- Viewer list for own stories, reactions on stories, quick reply, tap navigation, progress bars, story seen state.

### Reels + Watch
- Vertical full-screen snap-scroll reel feed (videos from posts type=reel), like/comment/share/save inline, follow author, audio "name" label, view counts.
- Watch: grid of all videos with categories filter, detail view with comments.

### Global search
- One search bar (top bar, mobile too): people, posts, groups, pages, reels, hashtags — tabbed results, cursor-paginated, suggestions as-you-type (debounced), recent searches (persisted, clearable), people results include relationship state (Add friend / Cancel / Follow).

### Notifications (real-time)
- Events: reaction, comment (+reply), friend request accepted/declined, tag, mention, story view (optional off), follow, group invite/post, page post, share of your post, poll vote.
- Categories with icons; unread badge count (nav + mobile tab), mark-read (individual + all), notification settings per category, delivered live via Reverb WebSockets (private channel per user), grouped aggregates (e.g. "A and 3 others reacted").

### Groups
- Create group (name, privacy: public/private/hidden, cover, description, topics, rules list), discover/browse + search, join (open / approval-required), leave.
- Roles: owner/admin/moderator/member; member management (approve, remove, role change); group posts (same composer, posted-as-group), group feed, events-lite (optional skip), about/rules pages.
- Invite friends to group; group-level notification to members on new post.

### Pages
- Create page (name, username/slug, category, description, cover/avatar), follow/unfollow, page feed, post-as-page (admins), page roles (admin/editor/moderator), insights (followers count, post reach/engagement per post — computed from views/reactions/comments), page messaging (conversation thread page↔user, appears in Messenger).

## Schema (indicative)
stories (user_id, type, media_path/text, background, expires_at, privacy), story_views, story_reactions, tags on reels via posts, search_recents, notifications (user_id, type, actor_id, notifiable morph, data JSON, read_at), groups (slug, privacy, settings JSON), group_members (role, status), group_invites, pages (slug, category, details), page_follows, page_roles, page_insights (aggregate snapshot), message threads reused from Phase 2 messenger (page threads flag).

## Acceptance criteria (user-visible)
- [ ] Story creation with photo works and auto-disappears after 24h; privacy respected (hidden-from users can't see it).
- [ ] Own story shows a viewer list and reaction counts; reacting to a story notifies the owner.
- [ ] Reels tab shows vertical snap feed; like/comment/share/save/follow all work inline without leaving the feed.
- [ ] Watch shows all videos in a filterable grid; opening one opens comments.
- [ ] Searching any term returns tabbed results for people/posts/groups/pages/reels/hashtags; typing shows suggestions; recent searches persist and clear.
- [ ] Liking a friend's post makes a notification appear on the other account in real time (badge + dropdown), without page reload.
- [ ] Creating a group, inviting a friend, friend joining, and posting as the group all work; group posts appear in members' feeds.
- [ ] Page creation → follow → post-as-page → post appears in followers' feeds attributed to the page with page avatar; insights show follower + per-post engagement numbers.
- [ ] Page messaging thread appears in Messenger for both sides.
- [ ] All lists paginate with skeletons; empty states everywhere; fully usable at 360px.

## Tests
Pest: story expiry scope, story privacy matrix, reels feed pagination, search across entities, notification creation + mark-read + channel auth, group role enforcement (non-admin can't approve), page role enforcement (only admins post), insights numbers match seeded engagement.

## Edge cases
- Multiple stories from one user (ring segments); expired story direct URL → 404/410; notification dedupe/grouping; group hidden from non-members in search; page slug collisions; WebSocket reconnect banner.
