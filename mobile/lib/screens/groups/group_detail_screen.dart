import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';

/// Group detail — group card, member previews, and the group's post feed.
class GroupDetailScreen extends StatefulWidget {
  final int groupId;
  const GroupDetailScreen({super.key, required this.groupId});

  @override
  State<GroupDetailScreen> createState() => _GroupDetailScreenState();
}

class _Member {
  final int id;
  final String name;
  final String role;
  _Member({required this.id, required this.name, required this.role});
  factory _Member.fromJson(Map<String, dynamic> j) =>
      _Member(id: j['id'], name: j['name'] ?? '', role: j['role'] ?? 'member');
}

class _GroupDetailScreenState extends State<GroupDetailScreen> {
  Map<String, dynamic>? _group;
  List<Post> _posts = [];
  List<_Member> _members = [];
  String? _myStatus;
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
      final res =
          await ApiClient.instance.get('/groups/${widget.groupId}');
      setState(() {
        _group = (res['group'] as Map).cast<String, dynamic>();
        _posts = Post.listFrom(res['posts']);
        _members = ((res['members'] as List?) ?? [])
            .map((m) => _Member.fromJson(m.cast<String, dynamic>()))
            .toList();
        _myStatus = res['my_status'];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load group.';
        _loading = false;
      });
    }
  }

  Future<void> _action(String path, String success) async {
    try {
      await ApiClient.instance.post(path, {});
      if (mounted) showSnackMsg(context, success);
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_group?['name'] ?? 'Group')),
      floatingActionButton: (_myStatus == 'active')
          ? FloatingActionButton.extended(
              onPressed: () {},
              icon: const Icon(Icons.edit_rounded),
              label: const Text('Post'),
            )
          : null,
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
                            Text(_group?['name'] ?? '',
                                style: Theme.of(context).textTheme.titleLarge),
                            const SizedBox(height: 4),
                            Text(
                              '${(_group?['privacy'] ?? 'public') == 'private' ? '🔒 Private' : '🌍 Public'} · ${_group?['members_count'] ?? 0} members · ${_group?['created_at'] ?? ''}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            if (( _group?['description'] ?? '').toString().isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(_group!['description']),
                              ),
                            const SizedBox(height: 12),
                            if (_myStatus == null)
                              SizedBox(
                                width: double.infinity,
                                child: FilledButton(
                                  onPressed: () => _action(
                                      '/groups/${widget.groupId}/join',
                                      'Join requested'),
                                  child: const Text('Join group'),
                                ),
                              )
                            else if (_myStatus == 'pending')
                              const SizedBox(
                                width: double.infinity,
                                child: OutlinedButton(
                                  onPressed: null,
                                  child: Text('Waiting for approval'),
                                ),
                              )
                            else
                              SizedBox(
                                width: double.infinity,
                                child: OutlinedButton(
                                  onPressed: () => _action(
                                      '/groups/${widget.groupId}/leave',
                                      'Left group'),
                                  child: const Text('Leave group'),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      // Members preview
                      BhasCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Members',
                                style: Theme.of(context).textTheme.titleSmall),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 12,
                              runSpacing: 8,
                              children: [
                                for (final m in _members.take(8))
                                  GestureDetector(
                                    onTap: () => context.push('/profile/${m.id}'),
                                    child: SizedBox(
                                      width: 64,
                                      child: Column(
                                        children: [
                                          UserAvatar(
                                              avatarPath: null,
                                              name: m.name,
                                              radius: 22),
                                          const SizedBox(height: 4),
                                          Text(
                                            m.name.split(' ').first,
                                            overflow: TextOverflow.ellipsis,
                                            style:
                                                const TextStyle(fontSize: 11),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                              ],
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
                            message: 'No posts in this group yet.'),
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
