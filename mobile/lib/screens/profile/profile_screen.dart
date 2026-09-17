import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../api/api_client.dart';
import '../../models/post.dart';
import '../../state/auth_state.dart';
import '../../widgets/common.dart';
import '../../widgets/post_card_host.dart';

/// Profile screen — header (cover, avatar, stats), action buttons
/// (add friend / follow / message), friends preview and the user's posts.
class ProfileScreen extends StatefulWidget {
  final String userId; // 'me' or numeric id
  const ProfileScreen({super.key, required this.userId});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? _profileUser;
  Map<String, dynamic>? _relation;
  List<Map<String, dynamic>> _friends = [];
  List<Post> _posts = [];
  bool _restricted = false;
  bool _loading = true;
  String? _error;

  bool get _isMe => widget.userId == 'me';

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
      final res = await ApiClient.instance.get('/profile/${widget.userId}');
      setState(() {
        _restricted = res['restricted'] == true;
        _profileUser = (res['profileUser'] as Map?)?.cast<String, dynamic>();
        _relation = (res['relation'] as Map?)?.cast<String, dynamic>();
        _friends = ((res['friends'] as List?) ?? [])
            .map((f) => (f as Map).cast<String, dynamic>())
            .toList();
        _posts = Post.listFrom(res['posts']);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load profile.';
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
    final auth = context.watch<AuthState>();
    final name = _profileUser?['name'] ?? auth.user?.name ?? 'Profile';
    return Scaffold(
      appBar: AppBar(
        title: Text(_isMe ? 'My profile' : name),
        actions: [
          if (_isMe)
            IconButton(
              icon: const Icon(Icons.settings_rounded),
              onPressed: () => context.push('/profile-edit'),
            ),
        ],
      ),
      floatingActionButton: _isMe
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
                      if (_restricted) ...[
                        const SizedBox(height: 60),
                        const EmptyView(
                            icon: Icons.lock_rounded,
                            message:
                                'This profile is private.\nSend a friend request to see more.'),
                        _actionButtons(),
                      ] else ...[
                        _header(),
                        const SizedBox(height: 12),
                        _actionButtons(),
                        const SizedBox(height: 12),
                        if (_friends.isNotEmpty) ...[
                          Text('Friends',
                              style: Theme.of(context).textTheme.titleSmall),
                          const SizedBox(height: 8),
                          SizedBox(
                            height: 96,
                            child: ListView(
                              scrollDirection: Axis.horizontal,
                              children: [
                                for (final f in _friends.take(10))
                                  GestureDetector(
                                    onTap: () =>
                                        context.push('/profile/${f['id']}'),
                                    child: SizedBox(
                                      width: 68,
                                      child: Column(
                                        children: [
                                          UserAvatar(
                                              avatarPath: null,
                                              name: f['name'] ?? '?',
                                              radius: 26),
                                          const SizedBox(height: 4),
                                          Text(
                                            (f['name'] ?? '')
                                                .toString()
                                                .split(' ')
                                                .first,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(fontSize: 11),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        Text('Posts',
                            style: Theme.of(context).textTheme.titleSmall),
                        const SizedBox(height: 8),
                        if (_posts.isEmpty)
                          const EmptyView(
                              icon: Icons.note_rounded,
                              message: 'No posts yet.'),
                        for (final p in _posts)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: PostCardHost(post: p),
                          ),
                      ],
                    ],
                  ),
                ),
    );
  }

  Widget _header() {
    final u = _profileUser ?? {};
    final hue = (u['hue'] ?? 210) as num;
    return BhasCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          Container(
            height: 100,
            width: double.infinity,
            decoration: BoxDecoration(
              color: Color.fromARGB(255, (hue * 255 ~/ 360) % 256, 120, 190),
              borderRadius:
                  const BorderRadius.vertical(top: Radius.circular(16)),
            ),
            child: u['cover_url'] != null
                ? Image.network(u['cover_url'], fit: BoxFit.cover)
                : const SizedBox.shrink(),
          ),
          Transform.translate(
            offset: const Offset(0, -34),
            child: Column(
              children: [
                UserAvatar(avatarPath: null, name: u['name'] ?? '?', radius: 36),
                const SizedBox(height: 6),
                Text(u['name'] ?? '',
                    style: Theme.of(context).textTheme.titleLarge),
                if ((u['bio'] ?? '').toString().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Text(u['bio'],
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodySmall),
                  ),
                const SizedBox(height: 8),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 16,
                  children: [
                    Text('${u['friends_count'] ?? 0} friends'),
                    Text('${u['followers_count'] ?? 0} followers'),
                  ],
                ),
                if (!_isMe) const SizedBox(height: 4),
                if (!_isMe) _relationText(),
                const SizedBox(height: 12),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _relationText() {
    final r = _relation ?? {};
    if (r['is_friend'] == true) return const Text('Friends ✓');
    if (r['request_sent'] == true) return const Text('Request sent');
    if (r['request_incoming'] == true) return const Text('Wants to be friends');
    if (r['following'] == true) return const Text('Following');
    return const SizedBox.shrink();
  }

  Widget _actionButtons() {
    if (_isMe) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => context.push('/profile-edit'),
              child: const Text('Edit profile'),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: OutlinedButton(
              onPressed: () => context.push('/saved'),
              child: const Text('Saved'),
            ),
          ),
        ],
      );
    }
    final r = _relation ?? {};
    final id = _profileUser?['id'];
    return Row(
      children: [
        Expanded(
          child: FilledButton.icon(
            onPressed: () {
              if (r['is_friend'] == true) {
                _action('/friends/$id/unfriend', 'Unfriended');
              } else if (r['request_sent'] == true) {
                _action('/friends/$id/cancel', 'Request cancelled');
              } else {
                _action('/friends/$id/request', 'Request sent');
              }
            },
            icon: Icon(r['is_friend'] == true
                ? Icons.person_remove_rounded
                : r['request_sent'] == true
                    ? Icons.hourglass_top_rounded
                    : Icons.person_add_rounded),
            label: Text(r['is_friend'] == true
                ? 'Friends ✓'
                : r['request_sent'] == true
                    ? 'Requested'
                    : 'Add friend'),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: OutlinedButton.icon(
            onPressed: () => context.push('/messenger'),
            icon: const Icon(Icons.chat_bubble_rounded),
            label: const Text('Message'),
          ),
        ),
      ],
    );
  }
}
