import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../api/api_client.dart';
import '../../widgets/common.dart';

/// Page card as returned by PageController::card().
class PageCard {
  final int id;
  final String name;
  final String? category;
  final String? about;
  final String? avatarUrl;
  final String? coverUrl;
  final int followersCount;
  final bool following;
  final int hue;

  PageCard({
    required this.id,
    required this.name,
    this.category,
    this.about,
    this.avatarUrl,
    this.coverUrl,
    required this.followersCount,
    required this.following,
    this.hue = 210,
  });

  factory PageCard.fromJson(Map<String, dynamic> j) => PageCard(
        id: j['id'],
        name: j['name'] ?? '',
        category: j['category'],
        about: j['about'],
        avatarUrl: j['avatar_url'],
        coverUrl: j['cover_url'],
        followersCount: j['followers_count'] ?? 0,
        following: j['following'] == true,
        hue: j['hue'] ?? 210,
      );

  static List<PageCard> listFrom(dynamic data) => ((data as List?) ?? [])
      .map((p) => PageCard.fromJson(p.cast<String, dynamic>()))
      .toList();
}

/// Pages — discover / mine / followed tabs with search, follow/unfollow.
class PagesScreen extends StatefulWidget {
  const PagesScreen({super.key});

  @override
  State<PagesScreen> createState() => _PagesScreenState();
}

class _PagesScreenState extends State<PagesScreen> {
  List<PageCard> _discover = [];
  List<PageCard> _mine = [];
  List<PageCard> _followed = [];
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
      final res = await ApiClient.instance.get('/pages',
          query: _search.text.trim().isEmpty ? null : {'q': _search.text.trim()});
      setState(() {
        _discover = PageCard.listFrom(res['discover']);
        _mine = PageCard.listFrom(res['mine']);
        _followed = PageCard.listFrom(res['followed']);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load pages.';
        _loading = false;
      });
    }
  }

  Future<void> _toggleFollow(PageCard p) async {
    try {
      await ApiClient.instance.post('/pages/${p.id}/follow', {});
      if (mounted) {
        showSnackMsg(context, p.following ? 'Unfollowed ${p.name}' : 'Following ${p.name}');
      }
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final list = _tab == 'discover'
        ? _discover
        : _tab == 'mine'
            ? _mine
            : _followed;
    return Scaffold(
      appBar: AppBar(title: const Text('Pages')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showCreateSheet(context),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Page'),
      ),
      body: _loading
          ? const LoadingView()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _load)
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'discover', label: Text('Discover')),
                          ButtonSegment(value: 'followed', label: Text('Following')),
                          ButtonSegment(value: 'mine', label: Text('Owned')),
                        ],
                        selected: {_tab},
                        onSelectionChanged: (s) => setState(() => _tab = s.first),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: TextField(
                        controller: _search,
                        decoration: const InputDecoration(
                            hintText: 'Search pages...',
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
                                    icon: Icons.flag_rounded,
                                    message: 'No pages here yet.')
                              ])
                            : ListView.builder(
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 12),
                                itemCount: list.length,
                                itemBuilder: (context, i) {
                                  final p = list[i];
                                  return BhasCard(
                                    margin: const EdgeInsets.only(bottom: 12),
                                    padding: EdgeInsets.zero,
                                    onTap: () => context.push('/pages/${p.id}'),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Container(
                                          height: 70,
                                          width: double.infinity,
                                          decoration: BoxDecoration(
                                            color: Color.fromARGB(255, (p.hue % 360) * 255 ~/ 360, 140, 200),
                                            borderRadius:
                                                const BorderRadius.vertical(
                                                    top: Radius.circular(16)),
                                          ),
                                          child: p.coverUrl != null
                                              ? Image.network(p.coverUrl!,
                                                  fit: BoxFit.cover)
                                              : const Icon(Icons.flag_rounded,
                                                  color: Colors.white54,
                                                  size: 36),
                                        ),
                                        Padding(
                                          padding: const EdgeInsets.all(12),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(p.name,
                                                  style: const TextStyle(
                                                      fontWeight:
                                                          FontWeight.w700)),
                                              const SizedBox(height: 2),
                                              Text(
                                                  '${p.category ?? 'Page'} · ${p.followersCount} followers',
                                                  style: Theme.of(context)
                                                      .textTheme
                                                      .bodySmall),
                                              if (p.about != null &&
                                                  p.about!.isNotEmpty)
                                                Padding(
                                                  padding: const EdgeInsets
                                                      .only(top: 6),
                                                  child: Text(p.about!,
                                                      maxLines: 2,
                                                      overflow: TextOverflow
                                                          .ellipsis),
                                                ),
                                              const SizedBox(height: 8),
                                              SizedBox(
                                                width: double.infinity,
                                                child: p.following
                                                    ? OutlinedButton(
                                                        onPressed: () =>
                                                            _toggleFollow(p),
                                                        child: const Text(
                                                            'Following ✓'),
                                                      )
                                                    : FilledButton(
                                                        onPressed: () =>
                                                            _toggleFollow(p),
                                                        child: const Text(
                                                            'Follow'),
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
    final about = TextEditingController();
    String category = 'Community';
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
              Text('Create page',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              TextField(controller: name,
                  decoration: const InputDecoration(hintText: 'Page name')),
              const SizedBox(height: 8),
              TextField(controller: about,
                  decoration: const InputDecoration(hintText: 'About')),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                value: category,
                decoration: const InputDecoration(hintText: 'Category'),
                items: const [
                  DropdownMenuItem(value: 'Community', child: Text('Community')),
                  DropdownMenuItem(value: 'Business', child: Text('Business')),
                  DropdownMenuItem(value: 'Creator', child: Text('Creator')),
                  DropdownMenuItem(value: 'Public Figure', child: Text('Public Figure')),
                  DropdownMenuItem(value: 'Brand', child: Text('Brand')),
                  DropdownMenuItem(value: 'Entertainment', child: Text('Entertainment')),
                  DropdownMenuItem(value: 'Sports', child: Text('Sports')),
                  DropdownMenuItem(value: 'Tech', child: Text('Tech')),
                  DropdownMenuItem(value: 'Education', child: Text('Education')),
                  DropdownMenuItem(value: 'Nonprofit', child: Text('Nonprofit')),
                ],
                onChanged: (v) => setSheet(() => category = v ?? category),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () async {
                  if (name.text.trim().isEmpty) return;
                  try {
                    await ApiClient.instance.post('/pages', {
                      'name': name.text.trim(),
                      'about': about.text.trim(),
                      'category': category,
                    });
                    if (context.mounted) {
                      Navigator.pop(context);
                      showSnackMsg(context, 'Page created!');
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
