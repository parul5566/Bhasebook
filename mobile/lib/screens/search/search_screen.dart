import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:debounce_throttle/debounce_throttle.dart' as dt;
import '../../api/api_client.dart';
import '../../theme/app_theme.dart';
import '../../widgets/common.dart';

/// Search — grouped results (people/posts/groups/pages/reels/hashtags),
/// tab filter, recent searches and live suggestions.
class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _Group {
  final String title;
  final List<Map<String, dynamic>> items;
  const _Group(this.title, this.items);
}

class _SearchScreenState extends State<SearchScreen> {
  final _controller = TextEditingController();
  final _debouncer = dt.Debouncer(const Duration(milliseconds: 350), initialValue: '');
  List<_Group> _results = [];
  List<String> _recents = [];
  List<String> _suggestions = [];
  bool _loading = false;
  bool _searched = false;
  String _tab = 'all';

  @override
  void initState() {
    super.initState();
    _debouncer.values.listen((q) {
      if (q.trim().length >= 2) _suggest(q.trim());
    });
  }

  Future<void> _suggest(String q) async {
    try {
      final res = await ApiClient.instance.get('/search/suggest', query: {'q': q});
      final s = res['suggestions'] ?? res['data'];
      if (mounted) {
        setState(() => _suggestions = ((s as List?) ?? []).map((e) => e.toString()).toList());
      }
    } catch (_) {}
  }

  Future<void> _search([String? qOverride]) async {
    final q = (qOverride ?? _controller.text).trim();
    if (q.isEmpty) return;
    _controller.text = q;
    setState(() {
      _loading = true;
      _searched = true;
      _suggestions = [];
    });
    try {
      final res = await ApiClient.instance.get('/search', query: {'q': q, 'tab': _tab});
      final results = (res['results'] as Map?)?.cast<String, dynamic>() ?? {};
      final groups = <_Group>[
        if (results['people'] != null)
          _Group('People', _asMaps(results['people'])),
        if (results['groups'] != null)
          _Group('Groups', _asMaps(results['groups'])),
        if (results['pages'] != null)
          _Group('Pages', _asMaps(results['pages'])),
        if (results['posts'] != null)
          _Group('Posts', _asMaps(results['posts'])),
        if (results['reels'] != null)
          _Group('Reels', _asMaps(results['reels'])),
        if (results['hashtags'] != null)
          _Group('Hashtags', _asMaps(results['hashtags'])),
      ];
      setState(() {
        _results = groups;
        _recents = ((res['recents'] as List?) ?? []).map((e) => e.toString()).toList();
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      if (mounted) showSnack(context, e);
    }
  }

  List<Map<String, dynamic>> _asMaps(dynamic data) =>
      ((data as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<void> _clearRecents() async {
    try {
      await ApiClient.instance.post('/search/recents/clear', {});
      setState(() => _recents = []);
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  void _openItem(Map<String, dynamic> item, String group) {
    final id = item['id'];
    switch (group) {
      case 'People':
        context.push('/profile/$id');
      case 'Groups':
        context.push('/groups/$id');
      case 'Pages':
        context.push('/pages/$id');
      case 'Posts' || 'Reels':
        context.push('/posts/$id');
      case 'Hashtags':
        context.push('/hashtag/${item['name'] ?? item['tag']}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          decoration: const InputDecoration(
              hintText: 'Search Bhasebook...', border: InputBorder.none,
              filled: false),
          autofocus: true,
          onChanged: (v) => _debouncer.value = v,
          onSubmitted: (_) => _search(),
        ),
        actions: [
          IconButton(icon: const Icon(Icons.search_rounded), onPressed: () => _search()),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          // Suggestions overlay
          if (_suggestions.isNotEmpty && !_searched)
            BhasCard(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Column(
                children: [
                  for (final s in _suggestions.take(6))
                    ListTile(
                      dense: true,
                      leading: const Icon(Icons.search_rounded, size: 18),
                      title: Text(s),
                      onTap: () => _search(s),
                    ),
                ],
              ),
            ),
          // Recents
          if (_recents.isNotEmpty && !_searched) ...[
            Row(
              children: [
                const Text('Recent searches',
                    style: TextStyle(fontWeight: FontWeight.w700)),
                const Spacer(),
                TextButton(onPressed: _clearRecents, child: const Text('Clear')),
              ],
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final r in _recents)
                  ActionChip(
                    label: Text(r),
                    onPressed: () => _search(r),
                  ),
              ],
            ),
          ],
          // Tab filter
          if (_searched) ...[
            SizedBox(
              width: double.infinity,
              child: SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'all', label: Text('All')),
                  ButtonSegment(value: 'people', label: Text('People')),
                  ButtonSegment(value: 'posts', label: Text('Posts')),
                  ButtonSegment(value: 'groups', label: Text('Groups')),
                  ButtonSegment(value: 'pages', label: Text('Pages')),
                ],
                selected: {_tab},
                onSelectionChanged: (s) {
                  _tab = s.first;
                  _search();
                },
              ),
            ),
            const SizedBox(height: 12),
          ],
          if (_loading) const Center(child: CircularProgressIndicator(strokeWidth: 2)),
          if (!_loading && _searched && _results.isEmpty)
            const EmptyView(
                icon: Icons.search_off_rounded, message: 'No results found.'),
          for (final g in _results) ...[
            Text(g.title, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 4),
            ...g.items.take(10).map((item) => _resultTile(g.title, item)),
            const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }

  Widget _resultTile(String group, Map<String, dynamic> item) {
    final name = item['name'] ?? item['content'] ?? '#${item['tag'] ?? ''}';
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: group == 'Hashtags'
          ? CircleAvatar(
              backgroundColor: Bhas.primary.shade100,
              child: Text('#', style: TextStyle(color: Bhas.primary.shade800)))
          : UserAvatar(
              avatarPath: null, name: (name ?? '?').toString(), radius: 20),
      title: Text((name ?? '').toString(),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: item['description'] ?? item['bio'] ?? item['category'] != null
          ? Text((item['description'] ?? item['bio'] ?? item['category'] ?? '').toString(),
              maxLines: 1, overflow: TextOverflow.ellipsis)
          : null,
      onTap: () => _openItem(item, group),
    );
  }
}
