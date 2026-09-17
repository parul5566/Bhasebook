import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../api/api_client.dart';
import '../widgets/common.dart';
import '../models/post.dart';
import '../widgets/post_card.dart';

/// Interactive post card that owns its mutation state (react/save/vote)
/// and delegates navigation via callbacks.
class PostCardHost extends StatefulWidget {
  final Post post;
  final VoidCallback? onOpenComments;
  final VoidCallback? onOpenPost;

  const PostCardHost({super.key, required this.post, this.onOpenComments, this.onOpenPost});

  @override
  State<PostCardHost> createState() => _PostCardHostState();
}

class _PostCardHostState extends State<PostCardHost> {
  late Post _post;
  String? _pendingReaction; // optimistic in-flight guard

  @override
  void initState() {
    super.initState();
    _post = widget.post;
  }

  @override
  void didUpdateWidget(covariant PostCardHost old) {
    super.didUpdateWidget(old);
    if (old.post.id != widget.post.id) _post = widget.post;
  }

  Post _copy(Post p,
      {Map<String, int>? reactionCounts,
      int? reactionTotal,
      String? myReaction,
      bool? saved,
      int? commentCount,
      Poll? poll}) {
    return Post(
      id: p.id, author: p.author, group: p.group, content: p.content, type: p.type,
      visibility: p.visibility, feeling: p.feeling, location: p.location, media: p.media,
      poll: poll ?? p.poll, tags: p.tags, sharedPost: p.sharedPost,
      reactionCounts: reactionCounts ?? p.reactionCounts,
      reactionTotal: reactionTotal ?? p.reactionTotal,
      myReaction: myReaction,
      commentCount: commentCount ?? p.commentCount,
      saved: saved ?? p.saved, pinned: p.pinned,
      createdAt: p.createdAt, edited: p.edited, canEdit: p.canEdit,
    );
  }

  Future<void> _react(String type) async {
    if (_pendingReaction != null) return;
    final prev = _post.myReaction;
    final next = prev == type ? null : type;

    // Optimistic update
    setState(() {
      _pendingReaction = type;
      final counts = Map.of(_post.reactionCounts);
      var total = _post.reactionTotal;
      if (prev != null) {
        counts[prev] = (counts[prev] ?? 1) - 1;
        total -= 1;
      }
      if (next != null) {
        counts[next] = (counts[next] ?? 0) + 1;
        total += 1;
      }
      counts.removeWhere((k, v) => v <= 0);
      _post = _copy(_post, reactionCounts: counts, reactionTotal: total, myReaction: next);
    });

    try {
      if (next != null) {
        await ApiClient.instance.post('/posts/${_post.id}/react', {'type': next});
      } else {
        // Toggling off — send the same type again (backend toggles).
        await ApiClient.instance.post('/posts/${_post.id}/react', {'type': type});
      }
    } catch (e) {
      if (mounted) {
        setState(() => _post = _copy(_post, myReaction: prev));
        showSnack(context, e);
      }
    } finally {
      _pendingReaction = null;
    }
  }

  Future<void> _toggleSave() async {
    final target = !_post.saved;
    setState(() => _post = _copy(_post, saved: target));
    try {
      await ApiClient.instance.post('/posts/${_post.id}/save', {});
    } catch (e) {
      if (mounted) {
        setState(() => _post = _copy(_post, saved: !target));
        showSnack(context, e);
      }
    }
  }

  Future<void> _vote(PollOption option) async {
    try {
      final res = await ApiClient.instance.post('/posts/${_post.id}/vote',
          {'option_id': option.id});
      if (res is Map && res['poll'] != null) {
        setState(() => _post = _copy(_post, poll: Poll.fromJson((res['poll'] as Map).cast<String, dynamic>())));
      }
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PostCard(
      post: _post,
      onLikeTap: () => _react(_post.myReaction ?? 'like'),
      onReact: _react,
      onCommentTap: widget.onOpenComments ?? () => context.push('/posts/${_post.id}'),
      onSaveTap: _toggleSave,
      onTap: widget.onOpenPost,
      onVote: _vote,
    );
  }
}
