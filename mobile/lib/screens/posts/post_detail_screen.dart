import 'package:flutter/material.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';

/// Single post + comment thread (GET /api/v1/posts/{id}, /posts/{id}/comments).
class PostDetailScreen extends StatefulWidget {
  final int postId;
  const PostDetailScreen({super.key, required this.postId});

  @override
  State<PostDetailScreen> createState() => _PostDetailScreenState();
}

class _PostDetailScreenState extends State<PostDetailScreen> {
  Post? _post;
  List<Comment> _comments = [];
  bool _loading = true;
  bool _sending = false;
  final _commentController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await ApiClient.instance.get('/posts/${widget.postId}');
      final comments =
          await ApiClient.instance.get('/posts/${widget.postId}/comments');
      setState(() {
        _post = Post.fromJson((res['post'] as Map).cast<String, dynamic>());
        _comments = Comment.listFrom(comments['comments'] ?? comments['data']);
        _loading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        showSnack(context, e);
      }
    }
  }

  Future<void> _sendComment() async {
    final text = _commentController.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await ApiClient.instance
          .post('/posts/${widget.postId}/comments', {'text': text});
      _commentController.clear();
      final comments =
          await ApiClient.instance.get('/posts/${widget.postId}/comments');
      setState(() {
        _comments = Comment.listFrom(comments['comments'] ?? comments['data']);
        if (_post != null) {
          _post = Post(
            id: _post!.id, author: _post!.author, group: _post!.group,
            content: _post!.content, type: _post!.type, visibility: _post!.visibility,
            feeling: _post!.feeling, location: _post!.location, media: _post!.media,
            poll: _post!.poll, tags: _post!.tags, sharedPost: _post!.sharedPost,
            reactionCounts: _post!.reactionCounts, reactionTotal: _post!.reactionTotal,
            myReaction: _post!.myReaction, commentCount: _comments.length,
            saved: _post!.saved, pinned: _post!.pinned, createdAt: _post!.createdAt,
            edited: _post!.edited, canEdit: _post!.canEdit,
          );
        }
      });
    } catch (e) {
      if (mounted) showSnack(context, e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Post')),
      body: _loading
          ? const LoadingView()
          : _post == null
              ? const EmptyView(message: 'Post unavailable.')
              : Column(
                  children: [
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(12),
                        children: [
                          PostCardHost(post: _post!),
                          const SizedBox(height: 12),
                          Text('Comments (${_comments.length})',
                              style: Theme.of(context).textTheme.titleSmall),
                          const SizedBox(height: 8),
                          if (_comments.isEmpty)
                            const Padding(
                              padding: EdgeInsets.all(16),
                              child: EmptyView(
                                  icon: Icons.chat_bubble_outline_rounded,
                                  message: 'No comments yet.\nBe the first!'),
                            ),
                          for (final c in _comments) _CommentTile(comment: c),
                        ],
                      ),
                    ),
                    SafeArea(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _commentController,
                                decoration:
                                    const InputDecoration(hintText: 'Write a comment...'),
                                onSubmitted: (_) => _sendComment(),
                              ),
                            ),
                            IconButton(
                              icon: _sending
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Icon(Icons.send_rounded),
                              onPressed: _sendComment,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _CommentTile extends StatelessWidget {
  final Comment comment;
  const _CommentTile({required this.comment});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          UserAvatar(avatarPath: null, name: comment.authorName, radius: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardTheme.color,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(comment.authorName,
                          style: const TextStyle(
                              fontWeight: FontWeight.w700, fontSize: 13)),
                      Text(comment.text),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(left: 12, top: 2),
                  child: Text(comment.createdAt,
                      style: Theme.of(context).textTheme.bodySmall),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
