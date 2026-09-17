import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'state/auth_state.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/auth/forgot_password_screen.dart';
import 'screens/shell/home_shell.dart';
import 'screens/feed/feed_screen.dart';
import 'screens/reels/reels_screen.dart';
import 'screens/watch/watch_screen.dart';
import 'screens/stories/stories_screen.dart';
import 'screens/groups/groups_screen.dart';
import 'screens/pages/pages_screen.dart';
import 'screens/friends/friends_screen.dart';
import 'screens/search/search_screen.dart';
import 'screens/messenger/messenger_screen.dart';
import 'screens/notifications/notifications_screen.dart';
import 'screens/profile/profile_screen.dart';
import 'screens/profile/profile_edit_screen.dart';
import 'screens/posts/post_detail_screen.dart';
import 'screens/posts/hashtag_screen.dart';
import 'screens/posts/memories_screen.dart';
import 'screens/posts/saved_screen.dart';
import 'screens/admin/admin_screen.dart';
import 'screens/groups/group_detail_screen.dart';
import 'screens/pages/page_detail_screen.dart';

GoRouter buildRouter(AuthState auth) {
  return GoRouter(
    initialLocation: '/feed',
    redirect: (context, state) {
      if (!auth.initialized) return null;
      final loggingIn = state.matchedLocation == '/login' ||
          state.matchedLocation == '/register' ||
          state.matchedLocation == '/forgot-password';
      if (!auth.authed && !loggingIn) return '/login';
      if (auth.authed && loggingIn) return '/feed';
      return null;
    },
    refreshListenable: auth,
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, __) => const RegisterScreen()),
      GoRoute(
          path: '/forgot-password',
          builder: (_, __) => const ForgotPasswordScreen()),
      ShellRoute(
        builder: (context, state, child) => HomeShell(child: child),
        routes: [
          GoRoute(path: '/feed', builder: (_, __) => const FeedScreen()),
          GoRoute(path: '/reels', builder: (_, __) => const ReelsScreen()),
          GoRoute(path: '/watch', builder: (_, __) => const WatchScreen()),
          GoRoute(
              path: '/notifications',
              builder: (_, __) => const NotificationsScreen()),
          GoRoute(
              path: '/profile/:id',
              builder: (_, s) =>
                  ProfileScreen(userId: s.pathParameters['id'] ?? 'me')),
          GoRoute(
              path: '/profile',
              builder: (_, __) => const ProfileScreen(userId: 'me')),
          GoRoute(
              path: '/profile-edit',
              builder: (_, __) => const ProfileEditScreen()),
          GoRoute(path: '/groups', builder: (_, __) => const GroupsScreen()),
          GoRoute(
              path: '/groups/:id',
              builder: (_, s) =>
                  GroupDetailScreen(groupId: int.parse(s.pathParameters['id']!))),
          GoRoute(path: '/pages', builder: (_, __) => const PagesScreen()),
          GoRoute(
              path: '/pages/:id',
              builder: (_, s) =>
                  PageDetailScreen(pageId: int.parse(s.pathParameters['id']!))),
          GoRoute(path: '/friends', builder: (_, __) => const FriendsScreen()),
          GoRoute(path: '/search', builder: (_, __) => const SearchScreen()),
          GoRoute(
              path: '/messenger',
              builder: (_, __) => const MessengerScreen()),
          GoRoute(path: '/stories', builder: (_, __) => const StoriesScreen()),
          GoRoute(
              path: '/posts/:id',
              builder: (_, s) =>
                  PostDetailScreen(postId: int.parse(s.pathParameters['id']!))),
          GoRoute(
              path: '/hashtag/:tag',
              builder: (_, s) => HashtagScreen(tag: s.pathParameters['tag']!)),
          GoRoute(
              path: '/memories', builder: (_, __) => const MemoriesScreen()),
          GoRoute(path: '/saved', builder: (_, __) => const SavedScreen()),
          GoRoute(path: '/admin', builder: (_, __) => const AdminScreen()),
        ],
      ),
    ],
    errorBuilder: (_, __) => Scaffold(
      appBar: AppBar(title: const Text('Not found')),
      body: const Center(child: Text('This page does not exist.')),
    ),
  );
}
