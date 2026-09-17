import 'package:flutter/material.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';

/// Page detail — about, follow button, page posts.
class PageDetailScreen extends StatefulWidget {
  final int pageId;
  const PageDetailScreen({super.key, required this.pageId});

  @override
  State<PageDetailScreen> createState() => _PageDetailScreenState();
}

class _PageDetailScreenState extends State<PageDetailScreen> {
  Map<String, dynamic>? _page;
  List<Post> _posts = [];
  bool _following = false;
  bool _loading = true;
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
      final res = await ApiClient.instance.get('/pages/${widget.pageId}');
      setState(() {
        _page = (res['page'] as Map).cast<String, dynamic>();
        _posts = Post.listFrom(res['posts']);
        _following = res['following'] == true;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load page.';
        _loading = false;
      });
    }
  }

  Future<void> _toggleFollow() async {
    try {
      await ApiClient.instance.post('/pages/${widget.pageId}/follow', {});
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_page?['name'] ?? 'Page')),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(12),
                    children: [
                      BhasCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(_page?['name'] ?? '',
                                style: Theme.of(context).textTheme.titleLarge),
                            const SizedBox(height: 4),
                            Text(
                              '${_page?['category'] ?? ''} · ${_page?['followers_count'] ?? 0} followers · ${_page?['created_at'] ?? ''}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            if ((_page?['about'] ?? '').toString().isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(_page!['about']),
                              ),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: _following
                                  ? OutlinedButton(
                                      onPressed: _toggleFollow,
                                      child: const Text('Following ✓'))
                                  : FilledButton(
                                      onPressed: _toggleFollow,
                                      child: const Text('Follow')),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text('Posts',
                          style: Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: 8),
                      if (_posts.isEmpty)
                        const EmptyView(
                            icon: Icons.forum_rounded,
                            message: 'No posts from this page yet.'),
                      for (final p in _posts)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: PostCardHost(post: p),
                        ),
                    ],
                  ),
                ),
    );
  }
}
