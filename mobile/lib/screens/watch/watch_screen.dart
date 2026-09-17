import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';

/// Watch — video posts in a scrollable list with large media.
class WatchScreen extends StatefulWidget {
  const WatchScreen({super.key});

  @override
  State<WatchScreen> createState() => _WatchScreenState();
}

class _WatchScreenState extends State<WatchScreen> {
  final List<Post> _videos = [];
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
      final res = await ApiClient.instance.get('/feed',
          query: {'cursor': '0', 'type': 'video'});
      setState(() {
        _videos
          ..clear()
          ..addAll(Post.listFrom(res['posts'])
              .where((p) => p.type == 'video' || p.hasVideo));
        _nextCursor = res['next_cursor'];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load videos.';
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _nextCursor == null) return;
    setState(() => _loadingMore = true);
    try {
      final res = await ApiClient.instance
          .get('/feed', query: {'cursor': '$_nextCursor', 'type': 'video'});
      setState(() {
        _videos.addAll(Post.listFrom(res['posts'])
            .where((p) => p.type == 'video' || p.hasVideo));
        _nextCursor = res['next_cursor'];
        _loadingMore = false;
      });
    } catch (_) {
      setState(() => _loadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Watch')),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : _videos.isEmpty
                  ? const EmptyView(
                      icon: Icons.smart_display_rounded,
                      message: 'No videos yet.')
                  : ListView.separated(
                      padding: const EdgeInsets.all(12),
                      itemCount: _videos.length + (_nextCursor != null ? 1 : 0),
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) {
                        if (i >= _videos.length) {
                          _loadMore();
                          return const Padding(
                            padding: EdgeInsets.all(16),
                            child: Center(
                                child: CircularProgressIndicator(strokeWidth: 2)),
                          );
                        }
                        return PostCardHost(
                          post: _videos[i],
                          onOpenComments: () =>
                              context.push('/posts/${_videos[i].id}'),
                        );
                      },
                    ),
    );
  }
}
