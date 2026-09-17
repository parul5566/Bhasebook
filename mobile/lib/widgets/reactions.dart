import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import 'common.dart';

/// The 6 reaction types supported by the backend.
const reactions = [
  ('like', '👍', 'Like'),
  ('love', '❤️', 'Love'),
  ('haha', '😆', 'Haha'),
  ('wow', '😮', 'Wow'),
  ('sad', '😢', 'Sad'),
  ('angry', '😠', 'Angry'),
];

const reactionColors = {
  'like': Color(0xFF1e6be8),
  'love': Color(0xFFe0245e),
  'haha': Color(0xFFf7b125),
  'wow': Color(0xFFf7b125),
  'sad': Color(0xFFf7b125),
  'angry': Color(0xFFe75545),
};

/// Long-press reaction picker, matching the web PostCard popover.
Future<String?> showReactionPicker(BuildContext context) {
  return showBhasSheet(
    context,
    (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            for (final (type, emoji, _) in reactions)
              GestureDetector(
                onTap: () => Navigator.of(context).pop(type),
                child: Padding(
                  padding: const EdgeInsets.all(6),
                  child: Text(emoji, style: const TextStyle(fontSize: 34)),
                ),
              ),
          ],
        ),
      ),
    ),
  );
}

/// Reaction bar summary: top emoji + total + comment count.
class ReactionSummary extends StatelessWidget {
  final Map<String, int> counts;
  final int total;
  final int commentCount;

  const ReactionSummary({
    super.key,
    required this.counts,
    required this.total,
    required this.commentCount,
  });

  @override
  Widget build(BuildContext context) {
    if (total == 0 && commentCount == 0) return const SizedBox.shrink();
    final top = counts.entries.where((e) => e.value > 0).take(3).toList();
    final emoji = top.map((e) {
      final match = reactions.firstWhere((r) => r.$1 == e.key,
          orElse: () => ('like', '👍', ''));
      return match.$2;
    }).join('');
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          if (emoji.isNotEmpty) ...[
            Text(emoji, style: const TextStyle(fontSize: 14)),
            const SizedBox(width: 4),
            Text('$total', style: Theme.of(context).textTheme.bodySmall),
          ],
          const Spacer(),
          if (commentCount > 0)
            Text('$commentCount comments',
                style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

/// Rounded action row (Like / Comment / Save) under a post.
class PostActionBar extends StatelessWidget {
  final String? myReaction;
  final VoidCallback onLikeTap;
  final VoidCallback onLikeLongPress;
  final VoidCallback onCommentTap;
  final bool saved;
  final VoidCallback onSaveTap;

  const PostActionBar({
    super.key,
    required this.myReaction,
    required this.onLikeTap,
    required this.onLikeLongPress,
    required this.onCommentTap,
    required this.saved,
    required this.onSaveTap,
  });

  @override
  Widget build(BuildContext context) {
    final emoji = myReaction == null
        ? null
        : reactions
            .firstWhere((r) => r.$1 == myReaction, orElse: () => ('like', '👍', 'Like'))
            .$2;
    return Row(
      children: [
        Expanded(
          child: InkWell(
            onTap: onLikeTap,
            onLongPress: onLikeLongPress,
            borderRadius: BorderRadius.circular(10),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (emoji != null) ...[Text(emoji), const SizedBox(width: 6)],
                  Text('Like',
                      style: TextStyle(
                          fontWeight:
                              myReaction != null ? FontWeight.w700 : FontWeight.w500,
                          color: myReaction != null
                              ? reactionColors[myReaction]
                              : Theme.of(context).colorScheme.outline)),
                ],
              ),
            ),
          ),
        ),
        Expanded(
          child: InkWell(
            onTap: onCommentTap,
            borderRadius: BorderRadius.circular(10),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.chat_bubble_outline_rounded,
                      size: 18, color: Theme.of(context).colorScheme.outline),
                  const SizedBox(width: 6),
                  Text('Comment',
                      style:
                          TextStyle(color: Theme.of(context).colorScheme.outline)),
                ],
              ),
            ),
          ),
        ),
        Expanded(
          child: InkWell(
            onTap: onSaveTap,
            borderRadius: BorderRadius.circular(10),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    saved ? Icons.bookmark_rounded : Icons.bookmark_border_rounded,
                    size: 18,
                    color: saved
                        ? Bhas.primary.shade600
                        : Theme.of(context).colorScheme.outline,
                  ),
                  const SizedBox(width: 6),
                  Text('Save',
                      style: TextStyle(
                          color: saved
                              ? Bhas.primary.shade600
                              : Theme.of(context).colorScheme.outline)),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
