import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../theme/app_theme.dart';
import '../../widgets/common.dart';

/// A person row used across friends / suggestions / search results.
class UserRow {
  final int id;
  final String name;
  final String? avatarUrl;
  final int hue;
  final String? bio;
  final int mutual;
  final bool isFriend;
  final bool requestSent;
  final bool requestIncoming;

  UserRow({
    required this.id,
    required this.name,
    this.avatarUrl,
    this.hue = 210,
    this.bio,
    this.mutual = 0,
    this.isFriend = false,
    this.requestSent = false,
    this.requestIncoming = false,
  });

  factory UserRow.fromJson(Map<String, dynamic> j) => UserRow(
        id: j['id'],
        name: j['name'] ?? '',
        avatarUrl: j['avatar_url'],
        hue: j['hue'] ?? 210,
        bio: j['bio'],
        mutual: j['mutual'] ?? 0,
        isFriend: j['is_friend'] == true,
        requestSent: j['request_sent'] == true,
        requestIncoming: j['request_incoming'] == true,
      );

  static List<UserRow> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((u) => UserRow.fromJson(u.cast<String, dynamic>()))
      .toList();
}

/// Friends — tabs: all / requests / sent / suggestions.
class FriendsScreen extends StatefulWidget {
  const FriendsScreen({super.key});

  @override
  State<FriendsScreen> createState() => _FriendsScreenState();
}

class _FriendsScreenState extends State<FriendsScreen> {
  List<UserRow> _friends = [];
  List<UserRow> _received = [];
  List<UserRow> _sent = [];
  List<UserRow> _suggestions = [];
  bool _loading = true;
  String? _error;
  String _tab = 'all';

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
      final res = await ApiClient.instance.get('/friends');
      setState(() {
        _friends = UserRow.listFrom(res['friends']);
        _received = UserRow.listFrom(res['received']);
        _sent = UserRow.listFrom(res['sent']);
        _suggestions = UserRow.listFrom(res['suggestions']);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load friends.';
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
      appBar: AppBar(title: const Text('Friends')),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: SegmentedButton<String>(
                        segments: [
                          ButtonSegment(
                              value: 'all', label: Text('Friends (${_friends.length})')),
                          ButtonSegment(
                              value: 'requests',
                              label: Text('Requests (${_received.length})')),
                          const ButtonSegment(
                              value: 'suggestions',
                              label: Text('Suggested')),
                        ],
                        selected: {_tab},
                        onSelectionChanged: (s) =>
                            setState(() => _tab = s.first),
                      ),
                    ),
                    Expanded(
                      child: RefreshIndicator(
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          children: [
                            if (_tab == 'all') ...[
                              if (_friends.isEmpty)
                                const EmptyView(
                                    icon: Icons.people_rounded,
                                    message: 'No friends yet.\nCheck suggestions!'),
                              for (final u in _friends) _friendTile(u),
                            ],
                            if (_tab == 'requests') ...[
                              if (_received.isEmpty)
                                const EmptyView(
                                    icon: Icons.mark_email_read_rounded,
                                    message: 'No pending requests.'),
                              for (final u in _received)
                                _requestTile(u),
                              if (_sent.isNotEmpty) ...[
                                const Padding(
                                  padding: EdgeInsets.all(12),
                                  child: Text('Sent requests',
                                      style:
                                          TextStyle(fontWeight: FontWeight.w700)),
                                ),
                                for (final u in _sent) _sentTile(u),
                              ],
                            ],
                            if (_tab == 'suggestions') ...[
                              if (_suggestions.isEmpty)
                                const EmptyView(
                                    icon: Icons.explore_rounded,
                                    message: 'No suggestions right now.'),
                              for (final u in _suggestions) _suggestionTile(u),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _friendTile(UserRow u) => ListTile(
        leading: UserAvatar(avatarPath: null, name: u.name, radius: 22),
        title: Text(u.name, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: u.mutual > 0 ? Text('$u mutuals'.replaceFirst('u mutuals', '${u.mutual} mutual friends')) : null,
        onTap: () => context.push('/profile/${u.id}'),
        trailing: IconButton(
          icon: const Icon(Icons.person_remove_rounded),
          tooltip: 'Unfriend',
          onPressed: () => _action('/friends/${u.id}/unfriend', 'Unfriended ${u.name}'),
        ),
      );

  Widget _requestTile(UserRow u) => ListTile(
        leading: UserAvatar(avatarPath: null, name: u.name, radius: 22),
        title: Text(u.name, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: u.mutual > 0 ? Text('${u.mutual} mutual friends') : null,
        onTap: () => context.push('/profile/${u.id}'),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              icon: Icon(Icons.check_circle_rounded, color: Bhas.primary.shade600),
              tooltip: 'Accept',
              onPressed: () => _action('/friends/${u.id}/accept', 'Accepted ${u.name}'),
            ),
            IconButton(
              icon: const Icon(Icons.cancel_rounded, color: Colors.redAccent),
              tooltip: 'Decline',
              onPressed: () => _action('/friends/${u.id}/decline', 'Declined'),
            ),
          ],
        ),
      );

  Widget _sentTile(UserRow u) => ListTile(
        leading: UserAvatar(avatarPath: null, name: u.name, radius: 22),
        title: Text(u.name),
        subtitle: const Text('Request sent'),
        onTap: () => context.push('/profile/${u.id}'),
        trailing: TextButton(
          onPressed: () => _action('/friends/${u.id}/cancel', 'Request cancelled'),
          child: const Text('Cancel'),
        ),
      );

  Widget _suggestionTile(UserRow u) => ListTile(
        leading: UserAvatar(avatarPath: null, name: u.name, radius: 22),
        title: Text(u.name, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: u.bio != null && u.bio!.isNotEmpty
            ? Text(u.bio!, maxLines: 1, overflow: TextOverflow.ellipsis)
            : (u.mutual > 0 ? Text('${u.mutual} mutual friends') : null),
        onTap: () => context.push('/profile/${u.id}'),
        trailing: FilledButton(
          onPressed: () => _action('/friends/${u.id}/request', 'Request sent to ${u.name}'),
          child: const Text('Add'),
        ),
      );
}
