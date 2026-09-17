# Phase 7 · Mobile polish — wire story tray into feed + final gate

## Goal
Finish the last gap in the Flutter app: the story tray widget exists
(`lib/widgets/story_tray.dart`) and the story viewer exists
(`lib/screens/stories/stories_screen.dart` → `StoryViewer`), but the feed does
not mount the tray. Wire them together, then run the full verification gate.

## Files to change
- `mobile/lib/screens/feed/feed_screen.dart`
  - Add imports: `../../widgets/story_tray.dart`, `../stories/stories_screen.dart show StoryViewer`
  - Above the posts ListView, add a `StoryTray(onCreateStory: ..., onOpen: ...)`
    handler. `onOpen` must fetch the FULL tray (same `/stories/tray` call the
    tray widget uses — simplest: have the tray widget accept an `onOpen(context, tray, index)`
    callback, or open with the single-user tray it already has). Use
    `Navigator.push(MaterialPageRoute(builder: (_) => StoryViewer(tray: tray, initialIndex: index)))`.
  - `onCreateStory` → open a bottom sheet with a text field + background color
    picker, POST `/stories` with `{kind:'text', text, background}` then refresh feed.
- Remove dead re-exports at the bottom of `stories_screen.dart`
  (`typedef StoryTrayWidget/PostModel/PostHost` lines 271–273) — unused cruft.

## Acceptance criteria (user-visible)
- [ ] Feed shows the horizontal story tray above the first post
- [ ] Tapping a tray avatar opens the full-screen story viewer and marks the story seen
- [ ] Tapping "Your story" lets you post a text story that appears in the tray
- [ ] `flutter analyze` → No issues
- [ ] `flutter test` → all pass
- [ ] `flutter build bundle` succeeds
- [ ] Backend suite still green: `bash run-tests.sh` → 132 pass (545 assertions)

## Edge cases
- Empty tray → hide the section entirely, keep only "Your story" tile
- Story POST validation error (text > 500 chars?) → show firstError snackbar
- Story viewer back gesture must cancel the 5s progress timer (already disposed)
