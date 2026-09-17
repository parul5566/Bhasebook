import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';
import '../../widgets/story_tray.dart';
import '../stories/stories_screen.dart';

/// Feed with cursor pagination, matching GET /api/v1/feed.
class FeedScreen extends StatefulWidget {
  const FeedScreen({super.key});

  @override
  State<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends State<FeedScreen> {
  final _scroll = ScrollController();
  final List<Post> _posts = [];
  int? _nextCursor;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400 &&
          !_loadingMore &&
          _nextCursor != null) {
        _loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiClient.instance.get('/feed');
      setState(() {
        _posts
          ..clear()
          ..addAll(Post.listFrom(res['posts']));
        _nextCursor = res['next_cursor'];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load feed.';
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      final res = await ApiClient.instance
          .get('/feed', query: {'cursor': '$_nextCursor'});
      setState(() {
        _posts.addAll(Post.listFrom(res['posts']));
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
      appBar: AppBar(
        title: const Text('Bhasebook'),
        leading: IconButton(
          icon: const Icon(Icons.menu_rounded),
          onPressed: () => Scaffold.of(context).openEndDrawer(),
        ),
      ),
body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const LoadingView()
            : _error != null
                ? ErrorView(message: _error!, onRetry: _load)
                : ListView.separated(
                        controller: _scroll,
                        padding: const EdgeInsets.all(12),
                        itemCount: _posts.length +
                            (_nextCursor != null || _loadingMore ? 1 : 0) +
                            1,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, i) {
                          if (i == 0) {
                            return StoryTray(
                              onCreateStory: () => _open(context),
                              onOpen: (t) => _open(context, initialUser: t),
                            );
                          }
                          final pi = i - 1;
                          if (pi >= _posts.length) {
                            return const Padding(
                              padding: EdgeInsets.all(16),
                              child: Center(
                                  child:
                                      CircularProgressIndicator(strokeWidth: 2)),
                            );
                          }
                          return PostCardHost(
                            post: _posts[pi],
                            onOpenComments: () => context.go('/posts/${_posts[pi].id}'),
                          );
                        },
                      ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openComposer(context),
        icon: const Icon(Icons.edit_rounded),
        label: const Text('Post'),
      ),
    );
  }
}

/// Fetch the full tray, then open the StoryViewer at [initialUser]'s index.
Future<void> _open(BuildContext context, {StoryTrayData? initialUser}) async {
  final messenger = ScaffoldMessenger.of(context);
  try {
    final res = await ApiClient.instance.get('/stories/tray');
    final tray = StoryTrayData.listFrom(res['tray']);
    if (tray.isEmpty) {
      messenger.showSnackBar(
          const SnackBar(content: Text('No stories yet — create the first one!')));
      return;
    }
    var index = 0;
    if (initialUser != null) {
      final i = tray.indexWhere((t) => t.userId == initialUser.userId);
      if (i >= 0) index = i;
    }
    if (!context.mounted) return;
    Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(
      builder: (_) => StoryViewer(tray: tray, initialIndex: index),
    ));
  } catch (e) {
    messenger.showSnackBar(const SnackBar(content: Text('Could not load stories.')));
  }
}

void _openComposer(BuildContext context) {
  showBhasSheet(context, (context) => const _ComposerSheet());
}

class _ComposerSheet extends StatefulWidget {
  const _ComposerSheet();

  @override
  State<_ComposerSheet> createState() => _ComposerSheetState();
}

class _ComposerSheetState extends State<_ComposerSheet> {
  final _controller = TextEditingController();
  String _visibility = 'public';
  bool _posting = false;

  Future<void> _submit() async {
    final text = _controller.text.trim();
    if (text.isEmpty) return;
    setState(() => _posting = true);
    try {
      await ApiClient.instance.post('/posts', {
        'content': text,
        'type': 'text',
        'visibility': _visibility,
      });
      if (mounted) {
        Navigator.pop(context);
        showSnackMsg(context, 'Posted!');
      }
    } catch (e) {
      if (mounted) showSnack(context, e);
    } finally {
      if (mounted) setState(() => _posting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
        left: 16,
        right: 16,
        top: 8,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text("What's on your mind?",
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          TextField(
            controller: _controller,
            maxLines: 4,
            minLines: 2,
            autofocus: true,
            decoration: const InputDecoration(hintText: 'Share something...'),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'public', label: Text('🌍 Public')),
                    ButtonSegment(value: 'friends', label: Text('👥 Friends')),
                    ButtonSegment(value: 'private', label: Text('🔒 Only me')),
                  ],
                  selected: {_visibility},
                  onSelectionChanged: (s) => setState(() => _visibility = s.first),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _posting ? null : _submit,
            child: _posting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child:
                        CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Post'),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}
