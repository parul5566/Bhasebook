import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'api_endpoints.dart';

class ApiException implements Exception {
  final int statusCode;
  final String message;
  final Map<String, dynamic>? errors;

  ApiException(this.statusCode, this.message, [this.errors]);

  /// First validation error message, for snackbars.
  String get firstError {
    if (errors == null || errors!.isEmpty) return message;
    final first = errors!.values.first;
    return first is List ? first.first.toString() : first.toString();
  }

  bool get isAuthError => statusCode == 401;

  @override
  String toString() => message;
}

/// Signed-in user identity as returned by the backend.
class ApiUser {
  final int id;
  final String name;
  final String email;
  final String? avatar;
  final String? cover;
  final String? bio;
  final bool isAdmin;
  final bool emailVerified;

  ApiUser({
    required this.id,
    required this.name,
    required this.email,
    this.avatar,
    this.cover,
    this.bio,
    this.isAdmin = false,
    this.emailVerified = false,
  });

  factory ApiUser.fromJson(Map<String, dynamic> j) => ApiUser(
        id: j['id'],
        name: j['name'] ?? '',
        email: j['email'] ?? '',
        avatar: j['avatar'],
        cover: j['cover'],
        bio: j['bio'],
        isAdmin: j['is_admin'] == true,
        emailVerified: j['email_verified'] == true,
      );
}

/// HTTP client for the Bhasebook API with Sanctum token auth.
class ApiClient {
  static final ApiClient instance = ApiClient._();
  ApiClient._();

  final _storage = const FlutterSecureStorage();
  final _client = http.Client();

  String? _token;
  ApiUser? currentUser;
  final List<void Function()> _authListeners = [];

  bool get isAuthed => _token != null;

  void onAuthChanged(void Function() cb) => _authListeners.add(cb);

  Future<void> loadSavedToken() async {
    _token = await _storage.read(key: 'auth_token');
    if (_token != null) {
      try {
        final res = await get('/auth/me');
        currentUser = ApiUser.fromJson((res['user'] as Map).cast<String, dynamic>());
      } on ApiException {
        await _clearAuth();
      }
    }
    _notify();
  }

  Future<void> setAuth(String token, ApiUser user) async {
    _token = token;
    currentUser = user;
    await _storage.write(key: 'auth_token', value: token);
    _notify();
  }

  Future<void> _clearAuth() async {
    _token = null;
    currentUser = null;
    await _storage.delete(key: 'auth_token');
    _notify();
  }

  Future<void> logout() async {
    try {
      await post('/auth/logout', {});
    } catch (_) {}
    await _clearAuth();
  }

  void _notify() {
    for (final cb in List.of(_authListeners)) {
      cb();
    }
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Uri _uri(String path) => path.startsWith('http')
      ? Uri.parse(path)
      : Uri.parse('${Api.apiBase}$path');

  Future<dynamic> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
  }) async {
    Uri uri = _uri(path);
    if (query != null && query.isNotEmpty) {
      uri = uri.replace(queryParameters: query);
    }

    http.Response res;
    try {
      final req = http.Request(method, uri)..headers.addAll(_headers);
      if (body != null) req.body = jsonEncode(body);
      res = await _client.send(req).then((r) => http.Response.fromStream(r));
    } catch (e) {
      throw ApiException(0, 'Network error — please check your connection.');
    }

    dynamic decoded;
    final contentType = res.headers['content-type'] ?? '';
    if (contentType.contains('json') && res.body.isNotEmpty) {
      decoded = jsonDecode(res.body);
    }

    if (res.statusCode >= 400) {
      Map<String, dynamic>? errors;
      String message = res.reasonPhrase ?? 'Request failed';
      if (decoded is Map) {
        final m = decoded.cast<String, dynamic>();
        errors = (m['errors'] as Map?)?.cast<String, dynamic>();
        message = m['message'] ?? message;
      }
      final ex = ApiException(res.statusCode, message, errors);
      if (ex.isAuthError && _token != null && !path.contains('/auth/login')) {
        await _clearAuth();
      }
      throw ex;
    }
    return decoded;
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) =>
      _send('GET', path, query: query);

  Future<dynamic> post(String path, Map<String, dynamic> body) =>
      _send('POST', path, body: body);

  Future<dynamic> patch(String path, Map<String, dynamic> body) =>
      _send('PATCH', path, body: body);

  Future<dynamic> put(String path, Map<String, dynamic> body) =>
      _send('PUT', path, body: body);

  Future<dynamic> delete(String path) => _send('DELETE', path);

  /// Multipart upload (posts, stories, avatars). Returns decoded JSON.
  Future<dynamic> upload(
    String path,
    List<http.MultipartFile> files, {
    Map<String, String> fields = const {},
  }) async {
    final uri = _uri(path);
    final req = http.MultipartRequest('POST', uri)
      ..headers.addAll({
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      })
      ..files.addAll(files)
      ..fields.addAll(fields);

    final streamed = await _client.send(req);
    final res = await http.Response.fromStream(streamed);
    dynamic decoded;
    if (res.body.isNotEmpty) {
      try {
        decoded = jsonDecode(res.body);
      } catch (_) {}
    }
    if (res.statusCode >= 400) {
      Map<String, dynamic>? errors;
      String message = res.reasonPhrase ?? 'Upload failed';
      if (decoded is Map) {
        final m = decoded.cast<String, dynamic>();
        errors = (m['errors'] as Map?)?.cast<String, dynamic>();
        message = m['message'] ?? message;
      }
      throw ApiException(res.statusCode, message, errors);
    }
    return decoded;
  }
}
