import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../theme/app_theme.dart';
import '../../widgets/common.dart';

/// Notifications list with tabs (all / mentions) — GET /api/v1/notifications.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationItem {
  final int id;
  final String type;
  final String category;
  final Map<String, dynamic>? actor;
  final String text;
  final bool read;
  final String createdAt;

  _NotificationItem({
    required this.id,
    required this.type,
    required this.category,
    this.actor,
    required this.text,
    required this.read,
    required this.createdAt,
  });

  factory _NotificationItem.fromJson(Map<String, dynamic> j) =>
      _NotificationItem(
        id: j['id'],
        type: j['type'] ?? '',
        category: j['category'] ?? '',
        actor: (j['actor'] as Map?)?.cast<String, dynamic>(),
        text: j['text'] ?? '',
        read: j['read'] == true,
        createdAt: j['created_at'] ?? '',
      );

  static List<_NotificationItem> listFrom(dynamic data) =>
      ((data as List?) ?? [])
          .map((n) => _NotificationItem.fromJson(n.cast<String, dynamic>()))
          .toList();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<_NotificationItem> _items = [];
  Map<String, dynamic> _unread = {};
  String _tab = 'all';
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
      final res = await ApiClient.instance
          .get('/notifications', query: {'tab': _tab});
      setState(() {
        _items = _NotificationItem.listFrom(res['items']);
        _unread = (res['unread'] as Map?)?.cast<String, dynamic>() ?? {};
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load notifications.';
        _loading = false;
      });
    }
  }

  Future<void> _markAllRead() async {
    try {
      await ApiClient.instance.post('/notifications/mark-all-read', {});
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  String _text(_NotificationItem n) {
    final name = n.actor?['name'] ?? 'Someone';
    final map = {
      'friend_request': '$name sent you a friend request',
      'friend_accept': '$name accepted your friend request',
      'reaction': '$name reacted to your post',
      'comment': '$name commented on your post',
      'mention': '$name mentioned you',
      'tag': '$name tagged you',
      'follow': '$name started following you',
      'message': 'New message from $name',
      'group_invite': '$name invited you to a group',
      'group_join': '$name joined your group',
      'page_follow': '$name followed your page',
    };
    return map[n.type] ?? (n.text.isNotEmpty ? n.text : 'Notification');
  }

  IconData _icon(_NotificationItem n) {
    switch (n.category) {
      case 'friend':
        return Icons.person_add_rounded;
      case 'reaction':
        return Icons.thumb_up_rounded;
      case 'comment':
        return Icons.chat_bubble_rounded;
      case 'follow':
        return Icons.rss_feed_rounded;
      case 'group':
        return Icons.groups_rounded;
      case 'page':
        return Icons.flag_rounded;
      case 'message':
        return Icons.chat_rounded;
      default:
        return Icons.notifications_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    final unreadAll = (_unread['all'] as num?)?.toInt() ?? 0;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (unreadAll > 0)
            TextButton(
              onPressed: _markAllRead,
              child: const Text('Mark all read'),
            ),
        ],
      ),
      body: Column(
        children: [
          SegmentedButton<String>(
            segments: [
              ButtonSegment(
                  value: 'all',
                  label: Text('All${unreadAll > 0 ? ' ($unreadAll)' : ''}')),
              const ButtonSegment(value: 'mentions', label: Text('Mentions')),
            ],
            selected: {_tab},
            onSelectionChanged: (s) {
              setState(() => _tab = s.first);
              _load();
            },
          ),
          const SizedBox(height: 8),
          Expanded(
            child: _loading
                ? const LoadingView()
                : _error != null
                    ? ErrorView(message: _error!, onRetry: _load)
                    : _items.isEmpty
                        ? const EmptyView(
                            icon: Icons.notifications_off_rounded,
                            message: 'No notifications yet.')
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.builder(
                              itemCount: _items.length,
                              itemBuilder: (context, i) =>
                                  _NotificationTile(
                                item: _items[i],
                                icon: _icon(_items[i]),
                                text: _text(_items[i]),
                                onTap: () {
                                  if (_items[i].category == 'message') {
                                    context.go('/messenger');
                                  } else if (_items[i].actor != null) {
                                    context.go('/profile/${_items[i].actor!['id']}');
                                  }
                                },
                              ),
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  final _NotificationItem item;
  final IconData icon;
  final String text;
  final VoidCallback onTap;

  const _NotificationTile({
    required this.item,
    required this.icon,
    required this.text,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      tileColor: item.read ? null : Bhas.primary.shade50,
      leading: Stack(
        clipBehavior: Clip.none,
        children: [
          UserAvatar(
              avatarPath: null,
              name: item.actor?['name'] ?? '?',
              radius: 22),
          Positioned(
            right: -4,
            bottom: -4,
            child: CircleAvatar(
              radius: 11,
              backgroundColor: Bhas.primary.shade600,
              child: Icon(icon, size: 12, color: Colors.white),
            ),
          ),
        ],
      ),
      title: Text(text,
          style: TextStyle(
              fontWeight: item.read ? FontWeight.w400 : FontWeight.w700,
              fontSize: 14)),
      subtitle: Text(item.createdAt,
          style: Theme.of(context).textTheme.bodySmall),
    );
  }
}
