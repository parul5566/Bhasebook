import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../state/auth_state.dart';

/// Bottom navigation shell — Feed / Reels / Watch / Notifications / Profile.
/// Mirrors the web app's sidebar navigation destinations.
class HomeShell extends StatelessWidget {
  final Widget child;
  const HomeShell({super.key, required this.child});

  static const _tabs = [
    ('/feed', Icons.home_rounded, 'Feed'),
    ('/reels', Icons.movie_filter_rounded, 'Reels'),
    ('/watch', Icons.smart_display_rounded, 'Watch'),
    ('/notifications', Icons.notifications_rounded, 'Alerts'),
    ('/profile', Icons.person_rounded, 'Profile'),
  ];

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final auth = context.watch<AuthState>();
    final isAdmin = auth.user?.isAdmin ?? false;

    int index = _tabs.indexWhere((t) => location.startsWith(t.$1));
    if (index < 0) index = 0;

    return Scaffold(
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (i) => context.go(_tabs[i].$1),
        destinations: [
          for (final t in _tabs)
            NavigationDestination(icon: Icon(t.$2), label: t.$3),
        ],
      ),
      // Drawer for the secondary sections: groups, pages, friends, search,
      // messenger, stories, memories, saved, admin, settings.
      endDrawer: _MainDrawer(isAdmin: isAdmin),
    );
  }
}

class _MainDrawer extends StatelessWidget {
  final bool isAdmin;
  const _MainDrawer({required this.isAdmin});

  static const _items = [
    ('/stories', Icons.auto_awesome_rounded, 'Stories'),
    ('/groups', Icons.groups_rounded, 'Groups'),
    ('/pages', Icons.flag_rounded, 'Pages'),
    ('/friends', Icons.people_rounded, 'Friends'),
    ('/search', Icons.search_rounded, 'Search'),
    ('/messenger', Icons.chat_bubble_rounded, 'Messenger'),
    ('/watch', Icons.smart_display_rounded, 'Watch'),
    ('/memories', Icons.history_rounded, 'Memories'),
    ('/saved', Icons.bookmark_rounded, 'Saved'),
  ];

  @override
  Widget build(BuildContext context) {
    return Drawer(
      child: SafeArea(
        child: ListView(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Bhasebook',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
            ),
            for (final item in _items)
              ListTile(
                leading: Icon(item.$2),
                title: Text(item.$3),
                onTap: () {
                  Navigator.of(context).pop();
                  context.go(item.$1);
                },
              ),
            if (isAdmin)
              ListTile(
                leading: const Icon(Icons.admin_panel_settings_rounded),
                title: const Text('Admin'),
                onTap: () {
                  Navigator.of(context).pop();
                  context.go('/admin');
                },
              ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.tune_rounded),
              title: const Text('Edit profile'),
              onTap: () {
                Navigator.of(context).pop();
                context.go('/profile-edit');
              },
            ),
            ListTile(
              leading: const Icon(Icons.logout_rounded),
              title: const Text('Log out'),
              onTap: () async {
                Navigator.of(context).pop();
                await context.read<AuthState>().logout();
                if (context.mounted) context.go('/login');
              },
            ),
          ],
        ),
      ),
    );
  }
}
