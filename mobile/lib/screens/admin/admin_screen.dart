import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../api/api_client.dart';
import '../../state/auth_state.dart';
import '../../widgets/common.dart';

/// Admin dashboard — stats + user/report/ai-flag moderation.
/// Tabs fetched from GET /api/v1/admin?tab=...
class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key});

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  Map<String, dynamic>? _data;
  String _tab = 'overview';
  bool _loading = true;
  String? _error;
  bool _denied = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _denied = false;
    });
    try {
      final res =
          await ApiClient.instance.get('/admin', query: {'tab': _tab});
      setState(() {
        _data = (res as Map).cast<String, dynamic>();
        _loading = false;
      });
    } on ApiException catch (e) {
      if (e.statusCode == 403) {
        setState(() {
          _denied = true;
          _loading = false;
        });
      } else {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Could not load admin data.';
        _loading = false;
      });
    }
  }

  Future<void> _post(String path, Map<String, dynamic> body) async {
    try {
      await ApiClient.instance.post(path, body);
      if (mounted) showSnackMsg(context, 'Done.');
      await _load();
    } catch (e) {
      if (mounted) showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAdmin = context.watch<AuthState>().user?.isAdmin ?? false;
    if (!isAdmin) {
      return Scaffold(
        appBar: AppBar(title: const Text('Admin')),
        body: const EmptyView(
            icon: Icons.admin_panel_settings_rounded,
            message: 'Admin access only.'),
      );
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Admin')),
      body: _loading
          ? const LoadingView()
          : _denied
              ? const EmptyView(
                  icon: Icons.lock_rounded, message: 'Admin access only.')
              : _error != null
                  ? ErrorView(message: _error!, onRetry: _load)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.all(12),
                        children: [
                          SizedBox(
                            width: double.infinity,
                            child: Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              alignment: WrapAlignment.center,
                              children: [
                                for (final t in [
                                  'overview', 'users', 'reports',
                                  'ai-flags', 'settings'
                                ])
                                  ChoiceChip(
                                    label: Text(_tabLabel(t)),
                                    selected: _tab == t,
                                    onSelected: (_) {
                                      setState(() => _tab = t);
                                      _load();
                                    },
                                  ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),
                          _body(),
                        ],
                      ),
                    ),
    );
  }

  String _tabLabel(String t) {
    switch (t) {
      case 'ai-flags': return 'AI flags';
      case 'overview': return 'Overview';
      default: return t[0].toUpperCase() + t.substring(1);
    }
  }

  Widget _body() {
    final d = _data ?? {};
    switch (_tab) {
      case 'overview':
        final stats = (d['stats'] as Map?)?.cast<String, dynamic>() ?? {};
        final entries = stats.entries.toList();
        return GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          childAspectRatio: 2.2,
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          children: [
            for (final e in entries.take(8))
              BhasCard(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text('${e.value}',
                        style: Theme.of(context).textTheme.headlineSmall),
                    Text(e.key.replaceAll('_', ' '),
                        style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
              ),
          ],
        );
      case 'users':
        final users = (d['users'] as List?) ?? [];
        return Column(
          children: [
            for (final raw in users.take(30))
              _userTile((raw as Map).cast<String, dynamic>()),
          ],
        );
      case 'reports':
        final reports = (d['reports'] as List?) ?? [];
        if (reports.isEmpty) return const EmptyView(message: 'No reports.');
        return Column(
          children: [
            for (final raw in reports.take(30))
              _reportTile((raw as Map).cast<String, dynamic>()),
          ],
        );
      case 'ai-flags':
        final flags = (d['aiFlags'] as List?) ?? (d['ai_flags'] as List?) ?? [];
        if (flags.isEmpty) return const EmptyView(message: 'No AI flags.');
        return Column(
          children: [
            for (final raw in flags.take(30))
              _flagTile((raw as Map).cast<String, dynamic>()),
          ],
        );
      case 'settings':
        final settings = (d['settings'] as Map?)?.cast<String, dynamic>() ?? {};
        if (settings.isEmpty) {
          return const EmptyView(message: 'No settings configured.');
        }
        return Column(
          children: [
            for (final e in settings.entries)
              ListTile(
                title: Text(e.key),
                subtitle: Text('${e.value}'),
              ),
          ],
        );
      default:
        return const SizedBox.shrink();
    }
  }

  Widget _userTile(Map<String, dynamic> u) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: UserAvatar(avatarPath: null, name: u['name'] ?? '?', radius: 20),
        title: Text(u['name'] ?? ''),
        subtitle: Text('${u['email'] ?? ''} · ${u['status'] ?? 'active'}'),
        trailing: PopupMenuButton<String>(
          onSelected: (v) => _post('/admin/users/${u['id']}/action', {'action': v}),
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'suspend', child: Text('Suspend')),
            PopupMenuItem(value: 'ban', child: Text('Ban')),
            PopupMenuItem(value: 'activate', child: Text('Activate')),
            PopupMenuItem(value: 'make_admin', child: Text('Make admin')),
          ],
        ),
      );

  Widget _reportTile(Map<String, dynamic> r) => BhasCard(
        margin: const EdgeInsets.only(bottom: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${r['reason'] ?? 'Report'} · ${r['type'] ?? ''}',
                style: const TextStyle(fontWeight: FontWeight.w700)),
            if (r['note'] != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(r['note'], maxLines: 2, overflow: TextOverflow.ellipsis),
              ),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () =>
                      _post('/admin/reports/${r['id']}/action', {'action': 'dismiss'}),
                  child: const Text('Dismiss'),
                ),
                TextButton(
                  onPressed: () =>
                      _post('/admin/reports/${r['id']}/action', {'action': 'resolve'}),
                  child: const Text('Resolve'),
                ),
              ],
            ),
          ],
        ),
      );

  Widget _flagTile(Map<String, dynamic> f) => BhasCard(
        margin: const EdgeInsets.only(bottom: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Flag #${f['id']} · ${f['severity'] ?? 'review'}',
                style: const TextStyle(fontWeight: FontWeight.w700)),
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text('${f['reason'] ?? ''}',
                  maxLines: 2, overflow: TextOverflow.ellipsis),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () =>
                      _post('/admin/ai-flags/${f['id']}/action', {'action': 'dismiss'}),
                  child: const Text('Dismiss'),
                ),
                TextButton(
                  onPressed: () =>
                      _post('/admin/ai-flags/${f['id']}/action', {'action': 'remove_content'}),
                  child: const Text('Remove content'),
                ),
              ],
            ),
          ],
        ),
      );
}
