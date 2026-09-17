import 'package:flutter_test/flutter_test.dart';
import 'package:bhasebook_mobile/api/api_client.dart';
import 'package:bhasebook_mobile/api/api_endpoints.dart';

void main() {
  group('ApiUser', () {
    test('parses backend user payload', () {
      final u = ApiUser.fromJson({
        'id': 10,
        'name': 'Demo User',
        'email': 'demo@bhasebook.test',
        'avatar': null,
        'is_admin': true,
        'email_verified': true,
      });
      expect(u.id, 10);
      expect(u.name, 'Demo User');
      expect(u.isAdmin, isTrue);
      expect(u.emailVerified, isTrue);
    });
  });

  group('ApiException', () {
    test('extracts first validation error', () {
      final e = ApiException(422, 'The given data was invalid.', {
        'email': ['The email has already been taken.'],
        'password': ['The password is too short.'],
      });
      expect(e.firstError, 'The email has already been taken.');
      expect(e.isAuthError, isFalse);
    });

    test('401 is auth error', () {
      expect(ApiException(401, 'Unauthenticated.').isAuthError, isTrue);
    });
  });

  group('Api endpoints', () {
    test('build correct v1 URLs', () {
      expect(Api.apiBase, endsWith('/api/v1'));
      expect(Api.post(5), endsWith('/api/v1/posts/5'));
      expect(Api.storyView(2), endsWith('/api/v1/stories/2/view'));
      expect(Api.groupJoin(7), endsWith('/api/v1/groups/7/join'));
      expect(Api.profile(3), endsWith('/api/v1/profile/3'));
    });

    test('asset resolves relative storage paths', () {
      expect(Api.asset('avatars/x.jpg'), contains('/storage/avatars/x.jpg'));
      expect(Api.asset('https://cdn.example.com/a.png'), 'https://cdn.example.com/a.png');
      expect(Api.asset(null), '');
    });
  });
}
