import 'dart:async';
import 'package:flutter/material.dart';
import '../../api/api_client.dart';
import '../../theme/app_theme.dart';
import '../../widgets/common.dart';

/// Conversation summary as returned by MessengerController::indexData.
class Conversation {
  final int id;
  final String title;
  final String? avatarUrl;
  final String? lastMessageBody;
  final String? lastMessageSender;
  final bool lastMine;
  final String? lastAt;
  final int unread;

  Conversation({
    required this.id,
    required this.title,
    this.avatarUrl,
    this.lastMessageBody,
    this.lastMessageSender,
    required this.lastMine,
    this.lastAt,
    required this.unread,
  });

  factory Conversation.fromJson(Map<String, dynamic> j) {
    final last = (j['last_message'] as Map?)?.cast<String, dynamic>();
    return Conversation(
      id: j['id'],
      title: j['title'] ?? 'Conversation',
      avatarUrl: j['avatar_url'],
      lastMessageBody: last?['body'],
      lastMessageSender: last?['sender'],
      lastMine: last?['mine'] == true,
      lastAt: last?['created_at'],
      unread: j['unread'] ?? 0,
    );
  }

  static List<Conversation> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((c) => Conversation.fromJson(c.cast<String, dynamic>()))
      .toList();
}

/// Messenger — conversation list + chat view with ~4s polling.
class MessengerScreen extends StatefulWidget {
  const MessengerScreen({super.key});

  @override
  State<MessengerScreen> createState() => _MessengerScreenState();
}

class _MessengerScreenState extends State<MessengerScreen> {
  List<Conversation> _conversations = [];
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
      final res = await ApiClient.instance.get('/messenger');
      setState(() {
        _conversations = Conversation.listFrom(res['conversations']);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load chats.';
        _loading = false;
      });
    }
  }

  void _openChat(Conversation c) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => ChatScreen(conversation: c),
    ));
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Messenger')),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : _conversations.isEmpty
                  ? const EmptyView(
                      icon: Icons.chat_bubble_rounded,
                      message: 'No conversations yet.\nStart one from a friend\'s profile.')
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        itemCount: _conversations.length,
                        itemBuilder: (context, i) {
                          final c = _conversations[i];
                          return ListTile(
                            leading: UserAvatar(
                                avatarPath: null, name: c.title, radius: 24),
                            title: Text(c.title,
                                style: const TextStyle(fontWeight: FontWeight.w700)),
                            subtitle: Text(
                              c.lastMessageBody != null
                                  ? '${c.lastMine ? 'You: ' : ''}${c.lastMessageBody!}'
                                  : 'Say hi!',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(c.lastAt ?? '',
                                    style:
                                        Theme.of(context).textTheme.bodySmall),
                                if (c.unread > 0)
                                  Container(
                                    margin: const EdgeInsets.only(top: 4),
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 7, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Bhas.primary.shade600,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text('${c.unread}',
                                        style: const TextStyle(
                                            color: Colors.white, fontSize: 11)),
                                  ),
                              ],
                            ),
                            onTap: () => _openChat(c),
                          );
                        },
                      ),
                    ),
    );
  }
}

/// Chat thread — GET /messenger/{id}/messages + POST /messenger/{id}/send,
/// with 4s polling like the web Messenger panel.
class ChatScreen extends StatefulWidget {
  final Conversation conversation;
  const ChatScreen({super.key, required this.conversation});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatMessage {
  final int id;
  final bool mine;
  final String body;
  final String createdAt;

  _ChatMessage(
      {required this.id, required this.mine, required this.body, required this.createdAt});

  factory _ChatMessage.fromJson(Map<String, dynamic> j) => _ChatMessage(
        id: j['id'],
        mine: j['mine'] == true,
        body: j['body'] ?? '',
        createdAt: j['created_at'] ?? '',
      );

  static List<_ChatMessage> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((m) => _ChatMessage.fromJson(m.cast<String, dynamic>()))
      .toList();
}

class _ChatScreenState extends State<ChatScreen> {
  final _controller = TextEditingController();
  final _scroll = ScrollController();
  List<_ChatMessage> _messages = [];
  bool _loading = true;
  bool _sending = false;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(const Duration(seconds: 4), (_) => _pollMessages());
  }

  Future<void> _load() async {
    try {
      final res = await ApiClient.instance
          .get('/messenger/${widget.conversation.id}/messages');
      setState(() {
        _messages = _ChatMessage.listFrom(res['messages'] ?? res['data']);
        _loading = false;
      });
      _jumpBottom();
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        showSnack(context, e);
      }
    }
  }

  Future<void> _pollMessages() async {
    try {
      final res = await ApiClient.instance
          .get('/messenger/${widget.conversation.id}/messages');
      final fresh = _ChatMessage.listFrom(res['messages'] ?? res['data']);
      if (fresh.length != _messages.length && mounted) {
        setState(() => _messages = fresh);
        _jumpBottom();
      }
    } catch (_) {}
  }

  void _jumpBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.jumpTo(_scroll.position.maxScrollExtent);
      }
    });
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await ApiClient.instance.post('/messenger/${widget.conversation.id}/send',
          {'body': text, 'type': 'text'});
      _controller.clear();
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  void dispose() {
    _poll?.cancel();
    _controller.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            UserAvatar(
                avatarPath: null, name: widget.conversation.title, radius: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(widget.conversation.title,
                  overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const LoadingView()
                : _messages.isEmpty
                    ? const EmptyView(
                        icon: Icons.waving_hand_rounded,
                        message: 'Say hi to start the conversation!')
                    : ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.all(12),
                        itemCount: _messages.length,
                        itemBuilder: (context, i) {
                          final m = _messages[i];
                          return Align(
                            alignment: m.mine
                                ? Alignment.centerRight
                                : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.symmetric(vertical: 3),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 14, vertical: 9),
                              constraints: BoxConstraints(
                                  maxWidth:
                                      MediaQuery.of(context).size.width * 0.75),
                              decoration: BoxDecoration(
                                color: m.mine
                                    ? Bhas.primary.shade600
                                    : Theme.of(context).cardTheme.color,
                                borderRadius: BorderRadius.only(
                                  topLeft: const Radius.circular(16),
                                  topRight: const Radius.circular(16),
                                  bottomLeft:
                                      Radius.circular(m.mine ? 16 : 4),
                                  bottomRight:
                                      Radius.circular(m.mine ? 4 : 16),
                                ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(m.body,
                                      style: TextStyle(
                                          color: m.mine
                                              ? Colors.white
                                              : null)),
                                  const SizedBox(height: 2),
                                  Text(m.createdAt,
                                      style: TextStyle(
                                          fontSize: 10,
                                          color: m.mine
                                              ? Colors.white70
                                              : Theme.of(context)
                                                  .colorScheme
                                                  .outline)),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      decoration: const InputDecoration(
                          hintText: 'Type a message...'),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  IconButton(
                    icon: _sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child:
                                CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.send_rounded),
                    onPressed: _send,
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
