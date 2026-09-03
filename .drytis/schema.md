# Bhasebook — Schema (indicative, refined at build time)

All tables InnoDB/utf8mb4, UUID optional but bigint IDs + morphs standard Laravel.

## Identity & social graph
- `users`: name, username (unique), email (unique), password, dob, gender, avatar_path, cover_path, bio, work, education, location, theme_pref, privacy JSON (defaults), status (active/deactivated/suspended_until/banned), email_verified_at, timestamps.
- `friendships`: requester_id, addressee_id, status (pending/accepted/declined/blocked-during), unique pair index.
- `follows`: follower_id, followable morph (user|page).
- `blocks`: blocker_id, blocked_id, type (blocked/restricted).

## Content
- `posts`: author morph (user|page|group), type (text/photo/video/reel/poll/share), content, visibility (public/friends/friends_except/only_me/custom), feeling, location, shared_from_id nullable, is_removed bool + removed_reason, timestamps, soft deletes.
- `post_media`: post_id, path, type (image/video), width/height/duration, order.
- `poll_options`, `poll_votes` (unique user×poll).
- `post_user`: post_id, user_id, mode (include/exclude) for custom visibility.
- `post_tags`: post_id, user_id (people tags) + `tags`/`post_hashtag` for hashtags.
- `link_previews`: url, title, description, image_path, favicon; fk from post.
- `reactions`: user_id, reactionable morph, type enum(like,love,care,haha,wow,angry), unique morph+user.
- `comments`: post_id, user_id, parent_id, content, media_path, is_removed, soft deletes.
- `saved_posts`, `notifications` (user_id, type, actor_id, notifiable morph, data JSON, read_at).

## Stories / groups / pages
- `stories`: user_id, type (photo/video/text), media_path/text, background, privacy JSON, expires_at; `story_views` (unique story+viewer), `story_reactions`.
- `groups`: slug, name, privacy (public/private/hidden), cover, description, rules JSON; `group_members` (role enum owner/admin/moderator/member, status pending/approved/banned); `group_invites`.
- `pages`: slug, name, category, avatar/cover, description; `page_user` (role); `page_insights` (daily snapshot: followers, reach, engagement).

## Messaging & safety
- `threads`: type (direct/group/page), title, avatar, last_message_at; `thread_participants` (thread_id, user_id, last_read_at, muted).
- `messages`: thread_id, user_id, type (text/photo/video/voice), body, media_path, reply_to_id, deleted_for JSON, reactions via `reactions` morph.
- `reports`: reporter_id, reportable morph, reason enum, details, status (open/reviewing/resolved/dismissed), handler_id, resolution note.
- `admin_actions`: admin_id, action, target morph, meta JSON (audit trail).
- `ai_flags`: flaggable morph, score, reasons JSON, model, created_at (moderation assist).
- `settings` (platform key/value), `search_recents`, `login_history`, `tag_reviews`.

## Indexes to remember
friendship pair index, posts(author, created_at), reactions unique morph+user, notifications(user, read_at), stories(expires_at), messages(thread, id).
