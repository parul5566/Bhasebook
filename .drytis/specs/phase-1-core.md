# Phase 1 — Core (auth, profiles, friends, posts, feed)

## Goal
The full core social loop: onboarding → profile → friendships → post composer with media → reactions/comments → personalized infinite-scroll news feed.

## Features

### Auth
- Register (name, email, password, DOB 13+, gender) with Breeze-style Inertia pages; session + "remember me".
- Email verification flow (signed URL; mail via `log` driver, verification notice UI, resend with throttle).
- Password forgot/reset (tokenized, throttled).
- Account deactivation: user can deactivate (profile hidden, content retained, reversible via re-login), distinct from deletion.

### Profiles
- `/profile/{username}`: cover photo + avatar + bio (work/education/location/birthday fields), intro card, tabs (Posts, About, Friends, Photos). Avatar/cover uploads with validation + client crop UI. Edit own profile page.

### Friends / follow / block
- Friend request send/accept/decline/cancel, friends list, mutual friends, friend suggestions ("people you may know").
- Follow/unfollow (asymmetric) independent of friendship.
- Block (severs friendship, hides content both ways, blocks messaging) and unfriend.

### Posts & composer
- Types: text, photos (multi, up to 10), video (mp4/webm ≤ 50MB), poll (2–6 options, single choice, duration), plus optional feeling/activity, location, tagged friends (max 10), link attachment with server-fetched OG preview (title/description/image/favicon).
- Visibility per post: public / friends / friends-except / only-me / custom (include/exclude specific people) — enforced server-side.
- Feed post card: author, time+audience icon, content, media grid (1/2/3+/carousel), poll UI with live results %, reaction summary, comment/share/save counts.

### Reactions & engagement
- 6 reactions: Like, Love, Care, Haha, Wow, Sad, Angry → wait, that's 7 — pick 6: Like, Love, Care, Haha, Wow, Angry. One per user per post; quick like on click, picker on long-press/hover.
- Save post (private Saved page), share post (native repost with optional comment; shares count), hashtags parsed from text → clickable tag pages, "On this day" memories surface.

### Comments
- One-level nesting (reply to comment), text + image attachment, reactions on comments, edit/delete own, load-more pagination, live count.

### News feed
- Ranked-ish: friends' + followed pages/groups + own posts, newest-first with simple weighting; cursor-paginated, infinite scroll, skeleton loaders, empty state, "new posts" pill.
- Memories widget on feed sidebar.

## Schema (indicative)
users (extend), profiles/fields on users, friendships (requester_id, addressee_id, status, timestamps), follows, blocks, posts (type, content JSON, visibility, feeling, location, shared_from_id, author morphs: user|page|group), post_media, polls/poll_options/poll_votes, post_tags, tags, link_previews, reactions (morph), comments (parent_id, post_id, attachments morph), saved_posts, notifications (Phase 2), post_user (custom visibility lists).

## Acceptance criteria (user-visible)
- [ ] New user can register, verify via the logged/signed verification link, log out/in with remember-me across sessions.
- [ ] Forgot-password flow sends reset link (in log mail) and the reset page changes the password.
- [ ] Deactivated account's profile returns 404 to others and re-login restores it.
- [ ] Profile page shows avatar/cover/bio; uploads reject oversized/wrong-type files with a friendly error.
- [ ] Friend request → accept → both see each other in friends; suggestions show non-friends; block hides a user's content and blocks DMs.
- [ ] A post with 3 photos + feeling + location + a tagged friend appears correctly for the audience and NOT for excluded viewers.
- [ ] Poll with 4 options shows live percentages after voting; voter counts update.
- [ ] All 6 reactions can be set/switched/removed; reaction summary bar reflects who reacted.
- [ ] Comment, reply (nested), attach image to comment, react to comment — all appear without reload errors; deleted comments disappear.
- [ ] News feed scrolls infinitely with skeletons; saving a post shows it on the Saved page; sharing creates a proper repost card.
- [ ] Hashtag click opens a feed of tagged posts.
- [ ] Every form has loading, success, error and empty states; layout intact at 360px.

## Tests
Pest feature tests per flow: register+login, password reset, visibility matrix (public/friends/only-me/custom vs viewer relationship), reaction uniqueness, comment nesting, poll single-vote, block enforcement.

## Edge cases
- Audience icon matches actual visibility; concurrent friend requests (both directions) auto-accept; emoji-heavy text not truncated; media grid with mixed orientation; cursor pagination stable after new inserts.
