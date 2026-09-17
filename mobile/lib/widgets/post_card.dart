import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';
import '../models/post.dart';
import 'reactions.dart';

/// Full post card — mirrors the web PostCard.tsx layout:
/// header (avatar, name, meta) → content → media → poll → reactions → actions.
class PostCard extends StatelessWidget {
  final Post post;
  final VoidCallback onLikeTap;
  final ValueChanged<String> onReact;
  final VoidCallback onCommentTap;
  final VoidCallback onSaveTap;
  final VoidCallback? onTap;
  final ValueChanged<PollOption>? onVote;

  const PostCard({
    super.key,
    required this.post,
    required this.onLikeTap,
    required this.onReact,
    required this.onCommentTap,
    required this.onSaveTap,
    this.onTap,
    this.onVote,
  });

  @override
  Widget build(BuildContext context) {
    return BhasCard(
      padding: EdgeInsets.zero,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 0),
            child: Row(
              children: [
                UserAvatar(avatarPath: null, name: post.author.name, radius: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(post.author.name,
                                overflow: TextOverflow.ellipsis,
                                style:
                                    const TextStyle(fontWeight: FontWeight.w700)),
                          ),
                          if (post.group != null) ...[
                            Text('  ▸  ',
                                style: TextStyle(
                                    fontSize: 12,
                                    color:
                                        Theme.of(context).colorScheme.outline)),
                            Flexible(
                              child: Text(post.group!['name'] ?? '',
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                      fontWeight: FontWeight.w600,
                                      color: Bhas.primary.shade600)),
                            ),
                          ],
                        ],
                      ),
                      Text(
                        [
                          post.createdAt,
                          if (post.visibility == 'private') ' · 🔒',
                          if (post.visibility == 'friends') ' · 👥',
                          if (post.pinned) ' · 📌',
                          if (post.edited) ' · edited',
                        ].join(),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.more_horiz_rounded),
                  onPressed: () => _showPostMenu(context),
                ),
              ],
            ),
          ),
          // Content (with hashtag highlighting)
          if (post.content.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
              child: _ContentText(content: post.content),
            ),
          if (post.feeling != null || post.location != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
              child: Text(
                [
                  if (post.feeling != null) 'feeling ${post.feeling}',
                  if (post.location != null) 'at ${post.location}',
                ].join(' · '),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          // Shared-post quote
          if (post.sharedPost != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(context).scaffoldBackgroundColor,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                      color: Theme.of(context).dividerTheme.color ??
                          Colors.grey.shade300),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(post.sharedPost!.authorName ?? 'Post',
                        style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(post.sharedPost!.content,
                        maxLines: 4, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
            ),
          // Media
          if (post.firstImage != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Image.network(
                post.firstImage!.url,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(),
              ),
            ),
          if (post.hasVideo)
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: _VideoPlaceholder(),
            ),
          // Poll
          if (post.poll != null)
            _PollView(poll: post.poll!, onVote: onVote),
          // Hashtag chips
          if (post.tags.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: Wrap(
                spacing: 6,
                children: [
                  for (final t in post.tags)
                    Chip(
                      label: Text('#${t.name}',
                          style: const TextStyle(fontSize: 12)),
                      visualDensity: VisualDensity.compact,
                    ),
                ],
              ),
            ),
          ReactionSummary(
            counts: post.reactionCounts,
            total: post.reactionTotal,
            commentCount: post.commentCount,
          ),
          const Divider(height: 1, indent: 8, endIndent: 8),
          PostActionBar(
            myReaction: post.myReaction,
            onLikeTap: onLikeTap,
            onLikeLongPress: () async {
              final r = await showReactionPicker(context);
              if (r != null) onReact(r);
            },
            onCommentTap: onCommentTap,
            saved: post.saved,
            onSaveTap: onSaveTap,
          ),
        ],
      ),
    );
  }

  void _showPostMenu(BuildContext context) {
    showBhasSheet(
      context,
      (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.chat_bubble_outline_rounded),
              title: const Text('View comments'),
              onTap: () {
                Navigator.pop(context);
                onCommentTap();
              },
            ),
            ListTile(
              leading: Icon(post.saved
                  ? Icons.bookmark_remove_rounded
                  : Icons.bookmark_add_rounded),
              title: Text(post.saved ? 'Remove from saved' : 'Save post'),
              onTap: () {
                Navigator.pop(context);
                onSaveTap();
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _ContentText extends StatelessWidget {
  final String content;
  const _ContentText({required this.content});

  @override
  Widget build(BuildContext context) {
    final parts = content.split(RegExp(r'(#[\w]+)'));
    final spans = <TextSpan>[];
    for (final part in parts) {
      if (part.startsWith('#') && part.length > 1) {
        spans.add(TextSpan(
          text: part,
          style:
              TextStyle(color: Bhas.primary.shade600, fontWeight: FontWeight.w600),
        ));
      } else if (part.isNotEmpty) {
        spans.add(TextSpan(text: part));
      }
    }
    return SelectableText.rich(
      TextSpan(style: Theme.of(context).textTheme.bodyLarge, children: spans),
    );
  }
}

class _VideoPlaceholder extends StatelessWidget {
  const _VideoPlaceholder();

  @override
  Widget build(BuildContext context) {
    return const AspectRatio(
      aspectRatio: 16 / 9,
      child: ClipRRect(
        borderRadius: BorderRadius.all(Radius.circular(12)),
        child: ColoredBox(
          color: Colors.black87,
          child: Center(
            child: Icon(Icons.play_circle_fill_rounded, size: 56, color: Colors.white70),
          ),
        ),
      ),
    );
  }
}

class _PollView extends StatelessWidget {
  final Poll poll;
  final ValueChanged<PollOption>? onVote;
  const _PollView({required this.poll, this.onVote});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      child: Column(
        children: [
          for (final opt in poll.options)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Stack(
                children: [
                  // Result bar (only shown after voting, like the web)
                  if (poll.myVote != null)
                    FractionallySizedBox(
                      widthFactor: opt.percent / 100,
                      child: Container(
                        height: 40,
                        decoration: BoxDecoration(
                          color: Bhas.primary.shade50,
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                    ),
                  Positioned.fill(
                    child: Row(
                      children: [
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            opt.text,
                            style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: opt.voted ? Bhas.primary.shade700 : null,
                            ),
                          ),
                        ),
                        if (poll.myVote != null) ...[
                          Text('${opt.percent}%',
                              style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: opt.voted
                                      ? Bhas.primary.shade700
                                      : Theme.of(context).colorScheme.outline)),
                          const SizedBox(width: 12),
                        ],
                      ],
                    ),
                  ),
                  if (poll.myVote == null && onVote != null)
                    Positioned.fill(
                      child: Material(
                        color: Colors.transparent,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(10),
                          onTap: () => onVote!(opt),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          Align(
            alignment: Alignment.centerLeft,
            child: Text('${poll.totalVotes} votes',
                style: Theme.of(context).textTheme.bodySmall),
          ),
        ],
      ),
    );
  }
}
