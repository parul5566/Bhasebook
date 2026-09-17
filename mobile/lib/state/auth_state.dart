import 'package:flutter/material.dart';
import '../api/api_client.dart';

/// Global auth state — drives router redirects (login vs app shell).
class AuthState extends ChangeNotifier {
  bool initialized = false;
  bool get authed => ApiClient.instance.isAuthed;
  ApiUser? get user => ApiClient.instance.currentUser;

  AuthState() {
    ApiClient.instance.onAuthChanged(_onChange);
    _init();
  }

  Future<void> _init() async {
    await ApiClient.instance.loadSavedToken();
  }

  void _onChange() {
    initialized = true;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final res = await ApiClient.instance.post('/auth/login', {
      'email': email,
      'password': password,
      'device_name': 'flutter',
    });
    await ApiClient.instance.setAuth(
      res['token'] as String,
      ApiUser.fromJson((res['user'] as Map).cast<String, dynamic>()),
    );
  }

  Future<void> register(String name, String email, String password) async {
    final res = await ApiClient.instance.post('/auth/register', {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': password,
      'device_name': 'flutter',
    });
    await ApiClient.instance.setAuth(
      res['token'] as String,
      ApiUser.fromJson((res['user'] as Map).cast<String, dynamic>()),
    );
  }

  Future<void> logout() => ApiClient.instance.logout();
}
