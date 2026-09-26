import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../api/api_client.dart';

/// Firebase Cloud Messaging setup.
///
/// - Registers the FCM token with the backend on login / app start.
/// - Removes it on logout.
/// - Foreground messages surface as a local snackbar.
/// - Background/terminated taps are deep-linked via [onNotificationTap].
///
/// Everything degrades silently when Firebase is not configured
/// (no google-services / FirebaseOptions) — the app keeps working with
/// in-app notifications only.
class PushService {
  static bool _initialized = false;
  static String? _token;
  static void Function(RemoteMessage)? onNotificationTap;

  static Future<void> init(BuildContext? context) async {
    if (_initialized) return;
    try {
      await Firebase.initializeApp();
    } catch (e) {
      debugPrint('Push disabled — Firebase init failed: $e');
      return;
    }
    _initialized = true;

    // Background handler must be top-level/static.
    FirebaseMessaging.onBackgroundMessage(_backgroundHandler);

    FirebaseMessaging.onMessage.listen((msg) {
      // Foreground — the app shows its own in-app notifications list, but a
      // subtle snackbar gives immediate feedback.
      final n = msg.notification;
      if (n != null) {
        _foregroundNotifications.add(msg);
      }
    });
    // Notification tap when app is in background (but alive).
    FirebaseMessaging.onMessageOpenedApp.listen((msg) {
      onNotificationTap?.call(msg);
    });

    // Terminated → cold start via notification tap.
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) onNotificationTap?.call(initial);

    await _registerToken();
    FirebaseMessaging.instance.onTokenRefresh.listen((t) {
      _token = t;
      _registerToken();
    });
  }

  static final _foregroundNotifications = <RemoteMessage>[];
  /// Recent foreground message notifications for the UI to surface.
  static List<RemoteMessage> get foregroundNotifications =>
      List.unmodifiable(_foregroundNotifications);

  static Future<String?> getToken() async {
    if (!_initialized) return null;
    _token ??= await FirebaseMessaging.instance.getToken();
    return _token;
  }

  /// Register the current device token with the backend (after login).
  static Future<void> registerWithBackend() async {
    if (!ApiClient.instance.isAuthed) return;
    final token = await getToken();
    if (token == null) return;
    try {
      await ApiClient.instance.post('/devices', {
        'token': token,
        'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
      });
    } catch (_) {/* offline / backend old — non-fatal */}
  }

  /// Remove the token from the backend (on logout).
  static Future<void> unregisterFromBackend() async {
    final token = _token;
    if (token == null) return;
    try {
      await ApiClient.instance.delete('/devices/$token');
    } catch (_) {
      try {
        await ApiClient.instance.post('/devices/remove', {'token': token});
      } catch (_) {}
    }
  }

  static Future<void> _registerToken() async {
    if (ApiClient.instance.isAuthed) await registerWithBackend();
  }
}

Future<void> _backgroundHandler(RemoteMessage message) async {
  // Ensure Firebase is available in the background isolate; nothing else to
  // do — the system tray already displays the notification.
  debugPrint('FCM background message: ${message.messageId}');
}
