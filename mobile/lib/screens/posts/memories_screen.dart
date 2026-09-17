import 'package:flutter/material.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';

/// Memories — posts from this day in previous years.
class MemoriesScreen extends StatefulWidget {
  const MemoriesScreen({super.key});

  @override
  State<MemoriesScreen> createState() => _MemoriesScreenState();
}

class _MemoriesScreenState extends State<MemoriesScreen> {
  List<Post> _posts = [];
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
      final res = await ApiClient.instance.get('/memories');
      setState(() {
        _posts = Post.listFrom(res['posts']);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load memories.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Memories')),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : _posts.isEmpty
                  ? const EmptyView(
                      icon: Icons.history_rounded,
                      message: 'No memories for today yet.\nCome back next year!')
                  : ListView.separated(
                      padding: const EdgeInsets.all(12),
                      itemCount: _posts.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, i) => PostCardHost(post: _posts[i]),
                    ),
    );
  }
}
