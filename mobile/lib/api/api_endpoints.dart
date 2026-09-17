/// Central API endpoint definitions for the Bhasebook mobile app.
/// Mirrors routes/api.php (prefix /api/v1) on the Laravel backend.
class Api {
  // Change per environment (dev preview URL by default).
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://bhasebook-umcq4u.drytis.dev',
  );

  static const String apiBase = '$baseUrl/api/v1';
  static const String storageBase = '$baseUrl/storage';

  // Auth
  static const login = '$apiBase/auth/login';
  static const register = '$apiBase/auth/register';
  static const forgotPassword = '$apiBase/auth/forgot-password';
  static const me = '$apiBase/auth/me';
  static const logout = '$apiBase/auth/logout';

  // Feed & posts
  static const feed = '$apiBase/feed';
  static const posts = '$apiBase/posts';
  static String post(int id) => '$apiBase/posts/$id';
  static String postReact(int id) => '$apiBase/posts/$id/react';
  static String postComments(int id) => '$apiBase/posts/$id/comments';
  static String postVote(int id) => '$apiBase/posts/$id/vote';
  static String postSave(int id) => '$apiBase/posts/$id/save';
  static String postPin(int id) => '$apiBase/posts/$id/pin';
  static String comment(int id) => '$apiBase/comments/$id';
  static String commentReact(int id) => '$apiBase/comments/$id/react';
  static String hashtag(String tag) => '$apiBase/hashtag/$tag';
  static const memories = '$apiBase/memories';
  static const saved = '$apiBase/saved';

  // Stories
  static const storyTray = '$apiBase/stories/tray';
  static const stories = '$apiBase/stories';
  static String storyView(int id) => '$apiBase/stories/$id/view';
  static String storyViewers(int id) => '$apiBase/stories/$id/viewers';
  static String story(int id) => '$apiBase/stories/$id';

  // Reels
  static const reels = '$apiBase/reels';

  // Profile
  static String profile(userId) => '$apiBase/profile/$userId';
  static const profileUpdate = '$apiBase/profile';
  static const profileDeactivate = '$apiBase/profile/deactivate';

  // Friends
  static const friends = '$apiBase/friends';
  static String friendRequest(userId) => '$apiBase/friends/$userId/request';
  static String friendAccept(userId) => '$apiBase/friends/$userId/accept';
  static String friendDecline(userId) => '$apiBase/friends/$userId/decline';
  static String friendCancel(userId) => '$apiBase/friends/$userId/cancel';
  static String friendUnfriend(userId) => '$apiBase/friends/$userId/unfriend';
  static String follow(userId) => '$apiBase/users/$userId/follow';
  static String block(userId) => '$apiBase/users/$userId/block';
  static const blocked = '$apiBase/settings/blocked';

  // Search
  static String search(String q, {String tab = 'all'}) =>
      '$apiBase/search?q=${Uri.encodeComponent(q)}&tab=$tab';
  static String searchSuggest(String q) =>
      '$apiBase/search/suggest?q=${Uri.encodeComponent(q)}';
  static const searchRecentsClear = '$apiBase/search/recents/clear';

  // Notifications
  static const notifications = '$apiBase/notifications';
  static const notificationsUnread = '$apiBase/notifications/unread';
  static const notificationsMarkAllRead = '$apiBase/notifications/mark-all-read';
  static String notificationRead(id) => '$apiBase/notifications/$id/read';
  static const notificationSettings = '$apiBase/notifications/settings';

  // Groups
  static String groups({String q = ''}) =>
      q.isEmpty ? '$apiBase/groups' : '$apiBase/groups?q=${Uri.encodeComponent(q)}';
  static const groupCreate = '$apiBase/groups';
  static String group(int id) => '$apiBase/groups/$id';
  static String groupJoin(int id) => '$apiBase/groups/$id/join';
  static String groupLeave(int id) => '$apiBase/groups/$id/leave';
  static String groupApprove(int groupId, int userId) =>
      '$apiBase/groups/$groupId/members/$userId/approve';
  static String groupRemoveMember(int groupId, int userId) =>
      '$apiBase/groups/$groupId/members/$userId/remove';
  static String groupMemberRole(int groupId, int userId) =>
      '$apiBase/groups/$groupId/members/$userId/role';
  static String groupInvite(int id) => '$apiBase/groups/$id/invite';
  static String groupInviteRespond(int inviteId) =>
      '$apiBase/group-invites/$inviteId/respond';

  // Pages
  static String pages({String q = ''}) =>
      q.isEmpty ? '$apiBase/pages' : '$apiBase/pages?q=${Uri.encodeComponent(q)}';
  static const pageCreate = '$apiBase/pages';
  static String page(int id) => '$apiBase/pages/$id';
  static String pageFollow(int id) => '$apiBase/pages/$id/follow';
  static String pageRoles(int id) => '$apiBase/pages/$id/roles';

  // Messenger
  static const messenger = '$apiBase/messenger';
  static String conversationMessages(int id) => '$apiBase/messenger/$id/messages';
  static const messengerStart = '$apiBase/messenger/start';
  static String messageSend(int conversationId) =>
      '$apiBase/messenger/$conversationId/send';
  static String messageReact(int messageId) => '$apiBase/messages/$messageId/react';
  static String message(int messageId) => '$apiBase/messages/$messageId';
  static const messageSearch = '$apiBase/messages/search';

  // Reports & AI
  static const reports = '$apiBase/reports';
  static const aiAssist = '$apiBase/ai/assist';
  static const recommendations = '$apiBase/recommendations';
  static const recommendationHide = '$apiBase/recommendations/hide';

  // Admin
  static const admin = '$apiBase/admin';
  static String adminUserAction(int userId) => '$apiBase/admin/users/$userId/action';
  static String adminReportAction(int reportId) =>
      '$apiBase/admin/reports/$reportId/action';
  static String adminFlagAction(int flagId) => '$apiBase/admin/ai-flags/$flagId/action';
  static const adminSettings = '$apiBase/admin/settings';

  /// Resolve a relative storage path (e.g. "avatars/abc.jpg") to a full URL.
  static String asset(String? path) {
    if (path == null || path.isEmpty) return '';
    if (path.startsWith('http')) return path;
    return '$storageBase/$path';
  }
}
