import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';

/// Vertical full-screen reels pager — GET /api/v1/reels with cursor pagination.
class ReelsScreen extends StatefulWidget {
  const ReelsScreen({super.key});

  @override
  State<ReelsScreen> createState() => _ReelsScreenState();
}

class _ReelsScreenState extends State<ReelsScreen> {
  final _controller = PageController();
  final List<Post> _reels = [];
  int? _nextCursor;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get('/reels');
      setState(() {
        _reels
          ..clear()
          ..addAll(Post.listFrom(res['reels']));
        _nextCursor = res['next_cursor'];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load reels.';
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _nextCursor == null) return;
    setState(() => _loadingMore = true);
    try {
      final res = await ApiClient.instance
          .get('/reels', query: {'cursor': '$_nextCursor'});
      setState(() {
        _reels.addAll(Post.listFrom(res['reels']));
        _nextCursor = res['next_cursor'];
        _loadingMore = false;
      });
    } catch (_) {
      setState(() => _loadingMore = false);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : _reels.isEmpty
                  ? const EmptyView(
                      icon: Icons.movie_filter_rounded,
                      message: 'No reels yet.',
                      )
                  : PageView.builder(
                      controller: _controller,
                      scrollDirection: Axis.vertical,
                      itemCount: _reels.length + 1,
                      onPageChanged: (i) {
                        if (i >= _reels.length - 1) _loadMore();
                        if (i >= _reels.length && _nextCursor == null) {
                          // End of reels.
                        }
                      },
                      itemBuilder: (context, i) {
                        if (i >= _reels.length) {
                          return const Center(
                            child: SizedBox(
                                width: 26,
                                height: 26,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white)),
                          );
                        }
                        return _ReelPage(
                          reel: _reels[i],
                          onComments: () => context.push('/posts/${_reels[i].id}'),
                        );
                      },
                    ),
    );
  }
}

class _ReelPage extends StatelessWidget {
  final Post reel;
  final VoidCallback onComments;

  const _ReelPage({required this.reel, required this.onComments});

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        // Video surface (placeholder player — actual playback via video_player on device)
        Container(
          color: Colors.black,
          child: const Center(
            child: Icon(Icons.play_circle_fill_rounded,
                size: 88, color: Colors.white24),
          ),
        ),
        // Gradient + meta overlay
        Positioned(
          left: 0, right: 0, bottom: 0,
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 40, 80, 24),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.bottomCenter,
                end: Alignment.topCenter,
                colors: [Colors.black87, Colors.transparent],
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    UserAvatar(avatarPath: null, name: reel.author.name, radius: 16),
                    const SizedBox(width: 8),
                    Text(reel.author.name,
                        style: const TextStyle(
                            color: Colors.white, fontWeight: FontWeight.w700)),
                  ],
                ),
                if (reel.content.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(reel.content,
                      style: const TextStyle(color: Colors.white, fontSize: 14)),
                ],
              ],
            ),
          ),
        ),
        // Action rail
        Positioned(
          right: 8,
          bottom: 32,
          child: Column(
            children: [
              _RailButton(
                icon: reel.myReaction == 'love' ? '❤️' : '🤍',
                label: '${reel.reactionTotal}',
                onTap: () {}, // wired below via PostCardHost semantics
              ),
              _RailButton(
                icon: '💬',
                label: '${reel.commentCount}',
                onTap: onComments,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RailButton extends StatelessWidget {
  final String icon;
  final String label;
  final VoidCallback onTap;

  const _RailButton({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        children: [
          IconButton(
            icon: Text(icon, style: const TextStyle(fontSize: 28)),
            onPressed: onTap,
          ),
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 12)),
        ],
      ),
    );
  }
}
