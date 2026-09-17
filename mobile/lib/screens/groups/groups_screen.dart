import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../widgets/common.dart';

/// Group card as returned by GroupController::card().
class GroupCard {
  final int id;
  final String name;
  final String? description;
  final String? coverUrl;
  final String privacy;
  final int membersCount;
  final String? myStatus;
  final int hue;

  GroupCard({
    required this.id,
    required this.name,
    this.description,
    this.coverUrl,
    required this.privacy,
    required this.membersCount,
    this.myStatus,
    this.hue = 210,
  });

  factory GroupCard.fromJson(Map<String, dynamic> j) => GroupCard(
        id: j['id'],
        name: j['name'] ?? '',
        description: j['description'],
        coverUrl: j['cover_url'],
        privacy: j['privacy'] ?? 'public',
        membersCount: j['members_count'] ?? 0,
        myStatus: j['my_status'],
        hue: j['hue'] ?? 210,
      );

  static List<GroupCard> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((g) => GroupCard.fromJson(g.cast<String, dynamic>()))
      .toList();
}

/// Groups — discover / mine tabs with search, join/leave.
class GroupsScreen extends StatefulWidget {
  const GroupsScreen({super.key});

  @override
  State<GroupsScreen> createState() => _GroupsScreenState();
}

class _GroupsScreenState extends State<GroupsScreen> {
  List<GroupCard> _discover = [];
  List<GroupCard> _mine = [];
  bool _loading = true;
  String? _error;
  String _tab = 'discover';
  final _search = TextEditingController();

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
          .get('/groups', query: _search.text.trim().isEmpty ? null : {'q': _search.text.trim()});
      setState(() {
        _discover = GroupCard.listFrom(res['discover']);
        _mine = GroupCard.listFrom(res['mine']);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load groups.';
        _loading = false;
      });
    }
  }

  Future<void> _join(GroupCard g) async {
    try {
      await ApiClient.instance.post('/groups/${g.id}/join', {});
      if (mounted) {
        showSnackMsg(context, 'Joined ${g.name}');
      }
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  Future<void> _leave(GroupCard g) async {
    try {
      await ApiClient.instance.post('/groups/${g.id}/leave', {});
      if (mounted) showSnackMsg(context, 'Left ${g.name}');
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final list = _tab == 'discover' ? _discover : _mine;
    return Scaffold(
      appBar: AppBar(title: const Text('Groups')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showCreateSheet(context),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Group'),
      ),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Expanded(
                            child: SegmentedButton<String>(
                              segments: const [
                                ButtonSegment(value: 'discover', label: Text('Discover')),
                                ButtonSegment(value: 'mine', label: Text('My groups')),
                              ],
                              selected: {_tab},
                              onSelectionChanged: (s) =>
                                  setState(() => _tab = s.first),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: TextField(
                        controller: _search,
                        decoration: const InputDecoration(
                            hintText: 'Search groups...',
                            prefixIcon: Icon(Icons.search_rounded)),
                        onSubmitted: (_) => _load(),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Expanded(
                      child: RefreshIndicator(
                        onRefresh: _load,
                        child: list.isEmpty
                            ? ListView(children: const [
                                SizedBox(height: 100),
                                EmptyView(
                                    icon: Icons.groups_rounded,
                                    message: 'No groups here yet.')
                              ])
                            : ListView.builder(
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 12),
                                itemCount: list.length,
                                itemBuilder: (context, i) {
                                  final g = list[i];
                                  return BhasCard(
                                    margin: const EdgeInsets.only(bottom: 12),
                                    padding: EdgeInsets.zero,
                                    onTap: () => context.push('/groups/${g.id}'),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Container(
                                          height: 70,
                                          width: double.infinity,
                                          decoration: BoxDecoration(
                                            color: Color.fromARGB(255, (g.hue % 360) * 255 ~/ 360, 140, 200),
                                            borderRadius:
                                                const BorderRadius.vertical(
                                                    top: Radius.circular(16)),
                                          ),
                                          child: g.coverUrl != null
                                              ? Image.network(g.coverUrl!,
                                                  fit: BoxFit.cover)
                                              : const Icon(Icons.groups_rounded,
                                                  color: Colors.white54,
                                                  size: 36),
                                        ),
                                        Padding(
                                          padding: const EdgeInsets.all(12),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(g.name,
                                                  style: const TextStyle(
                                                      fontWeight:
                                                          FontWeight.w700)),
                                              const SizedBox(height: 2),
                                              Text(
                                                  '${g.privacy == 'private' ? '🔒 Private' : '🌍 Public'} · ${g.membersCount} members',
                                                  style: Theme.of(context)
                                                      .textTheme
                                                      .bodySmall),
                                              if (g.description != null &&
                                                  g.description!.isNotEmpty)
                                                Padding(
                                                  padding: const EdgeInsets
                                                      .only(top: 6),
                                                  child: Text(g.description!,
                                                      maxLines: 2,
                                                      overflow: TextOverflow
                                                          .ellipsis),
                                                ),
                                              const SizedBox(height: 8),
                                              if (_tab == 'discover' &&
                                                  g.myStatus == null)
                                                SizedBox(
                                                  width: double.infinity,
                                                  child: FilledButton(
                                                    onPressed: () => _join(g),
                                                    child:
                                                        const Text('Join group'),
                                                  ),
                                                )
                                              else if (g.myStatus == 'active')
                                                SizedBox(
                                                  width: double.infinity,
                                                  child: OutlinedButton(
                                                    onPressed: () => _leave(g),
                                                    child:
                                                        const Text('Leave group'),
                                                  ),
                                                )
                                              else if (g.myStatus == 'pending')
                                                const SizedBox(
                                                  width: double.infinity,
                                                  child: OutlinedButton(
                                                    onPressed: null,
                                                    child: Text('Approval pending'),
                                                  ),
                                                ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  );
                                },
                              ),
                      ),
                    ),
                  ],
                ),
    );
  }

  void _showCreateSheet(BuildContext context) {
    final name = TextEditingController();
    final description = TextEditingController();
    String privacy = 'public';
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheet) => Padding(
          padding: EdgeInsets.only(
              bottom: MediaQuery.of(context).viewInsets.bottom,
              left: 16, right: 16, top: 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Create group',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              TextField(controller: name,
                  decoration: const InputDecoration(hintText: 'Group name')),
              const SizedBox(height: 8),
              TextField(controller: description,
                  decoration:
                      const InputDecoration(hintText: 'What is it about?')),
              const SizedBox(height: 8),
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'public', label: Text('🌍 Public')),
                  ButtonSegment(value: 'private', label: Text('🔒 Private')),
                ],
                selected: {privacy},
                onSelectionChanged: (s) => setSheet(() => privacy = s.first),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () async {
                  if (name.text.trim().isEmpty) return;
                  try {
                    await ApiClient.instance.post('/groups', {
                      'name': name.text.trim(),
                      'description': description.text.trim(),
                      'privacy': privacy,
                    });
                    if (context.mounted) {
                      Navigator.pop(context);
                      showSnackMsg(context, 'Group created!');
                      _load();
                    }
                  } catch (e) {
                    if (context.mounted) showSnack(context, e);
                  }
                },
                child: const Text('Create'),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}
