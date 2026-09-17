# Phase 2 — Feed & posts in Flutter

## Goal
The Feed tab mirroring the web Dashboard: composer, infinite-scroll post list, and full post interactions.

## Screens
- FeedScreen: GET /api/v1/feed with cursor pagination (infinite scroll + pull-to-refresh); Composer (text, poll options, photo/video pick via image_picker + multipart upload); Recommendations row (GET /api/v1/recommendations, hide via POST)
- PostDetailScreen: GET /api/v1/posts/{id} — comments list, add comment, reply, comment reactions, delete own comment
- HashtagScreen: GET /api/v1/hashtag/{tag} post list

## Post interactions (PostCard widget, shared everywhere)
- Reactions: POST /posts/{id}/react (like/love/haha/wow/sad/angry picker), show counts + my_reaction state
- Comments: GET/POST /posts/{id}/comments, threaded replies
- Poll: POST /posts/{id}/vote {option_id}, show percentages, block duplicate votes ("Already voted" → show results)
- Save: POST /posts/{id}/save toggle; Pin (own posts): POST /posts/{id}/pin
- Share/repost + delete own post; media rendering (photo grid, video player via video_player)
- Report dialog: POST /reports {reportable_type:'post', reportable_id, reason, details}

## Acceptance criteria
- [ ] Feed loads seeded posts (14) with author, media, poll, reactions visible; scrolling down fetches more; pull-to-refresh resets
- [ ] Reacting to a post updates its counts immediately and persists after app restart
- [ ] Writing a comment shows it instantly; replying nests it under the parent
- [ ] Voting on the seeded poll shows percentages and locks further votes
- [ ] Posting text/photo/poll from the composer appears at the top of the feed
- [ ] Opening a hashtag from a post shows that tag's posts
- [ ] Saving a post marks it saved (heart/bookmark filled) — visible later in Saved tab
- [ ] No unhandled exceptions on any of the above; API errors show a snack bar

## Tests
- Unit: feed pagination state machine (idle/loading/loaded/end)
- Widget: PostCard renders each post type (text/photo/video/poll); reaction picker updates state

## Edge cases
- Post with 0 media / deleted shared post / user whose profile is restricted → graceful render
- Slow network → shimmer skeletons, not blank
- Duplicate reaction taps → optimistic update with rollback on error
