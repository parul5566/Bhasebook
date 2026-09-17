# Phase 3 — Stories, Reels, Watch

## Goal
Full-screen media experiences matching the web app.

## Screens
- StoriesTray (row on Feed + StoriesScreen): GET /api/v1/stories/tray — avatar rings (unseen = colored, seen = grey), tap → StoriesViewer
- StoriesViewer: PageView per user; progress bars per story, tap-right/left to navigate; text stories render with background colour (bhas/forest/sunset palettes); mark viewed via POST /stories/{id}/view; own stories show viewer count (GET /stories/{id}/viewers) + delete
- Story create: text (background picker) or photo/video (from gallery) → POST /stories
- ReelsScreen: vertical PageView (snap), GET /api/v1/reels with cursor; autoplay/mute toggle via visibility; side action rail (react/comment/save/share); comment sheet reuses Phase 2 comments
- WatchScreen: grid of video posts, tap → full player; GET /api/v1/watch equivalent (or same reels endpoint filtered client-side by the seeded type:'video' post)

## Acceptance criteria
- [ ] Tray shows the 3 seeded story users; colored ring disappears for seen stories after viewing
- [ ] Viewer advances stories with taps and swipes; expiry (24h) respected — expired stories don't appear
- [ ] Creating a text story with a background shows it in the tray immediately
- [ ] Own story shows viewer count after another seeded account views it
- [ ] Reels: 3 seeded reels autoplay one at a time; swiping snaps to the next; reacting/saving works from the rail
- [ ] Watch grid shows the seeded video post and plays fullscreen

## Tests
- Widget: tray ring states (seen/unseen); viewer progress bar timing
- Unit: reel visibility-based play/pause controller

## Edge cases
- Video buffering → spinner overlay on the reel
- Muted autoplay by default (store policy), tap-to-unmute
- Story POST failure (file too big) → field error from 422
