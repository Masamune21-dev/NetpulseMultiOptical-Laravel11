import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../api/api_client.dart';
import '../auth/session_store.dart';
import '../navigation/app_navigator.dart';
import 'push_service.dart';

class FcmService {
  FcmService._();

  static final instance = FcmService._();

  StreamSubscription<String>? _sub;
  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();
  static const String _alertLogsPayload = 'open_alert_logs';
  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    'netpulse_alerts',
    'Netpulse Alerts',
    description: 'Alert notifications from Netpulse',
    importance: Importance.high,
  );

  Future<void> init() async {
    try {
      // Ask permission (Android 13+ requires runtime grant for notifications).
      await FirebaseMessaging.instance.requestPermission();

      await _initLocalNotifications();

      // Sync current token if we already have an authenticated session.
      await syncToken();

      // Keep backend up-to-date when FCM rotates tokens.
      _sub?.cancel();
      _sub = FirebaseMessaging.instance.onTokenRefresh.listen((token) async {
        await _registerIfAuthed(token);
      });

      FirebaseMessaging.onMessage.listen(_showForegroundNotification);
      FirebaseMessaging.onMessageOpenedApp.listen(_openAlertLogsFromRemoteTap);

      final initialMessage = await FirebaseMessaging.instance
          .getInitialMessage();
      if (initialMessage != null) {
        _openAlertLogsFromRemoteTap(initialMessage);
      }
    } catch (_) {
      // Best-effort: do not block app startup on FCM errors.
    }
  }

  Future<void> syncToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) return;
      await _registerIfAuthed(token);
    } catch (_) {
      // best-effort
    }
  }

  Future<void> _registerIfAuthed(String token) async {
    final session = SessionStore.instance;
    final accessToken = session.accessToken ?? '';
    if (accessToken.isEmpty) return;

    final pkg = await PackageInfo.fromPlatform();
    final deviceName =
        'android:${pkg.appName}:${pkg.version}+${pkg.buildNumber}';

    final api = ApiClient(session);
    final push = PushService(api);

    try {
      await push.registerFcmToken(
        token: token,
        platform: 'android',
        deviceName: deviceName,
      );
    } catch (_) {
      // best-effort
    }
  }

  Future<bool> _initLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidInit);
    await _local.initialize(
      initSettings,
      onDidReceiveNotificationResponse: _openAlertLogsFromLocalTap,
    );

    final launchDetails = await _local.getNotificationAppLaunchDetails();
    if ((launchDetails?.didNotificationLaunchApp ?? false)) {
      final response = launchDetails?.notificationResponse;
      if (response != null) {
        _openAlertLogsFromLocalTap(response);
      } else {
        AppNavigator.openAlertLogs();
      }
    }

    final android = _local
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    await android?.createNotificationChannel(_channel);
    final granted = await android?.requestNotificationsPermission();
    return granted ?? true;
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    final details = NotificationDetails(
      android: AndroidNotificationDetails(
        _channel.id,
        _channel.name,
        channelDescription: _channel.description,
        importance: Importance.high,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
      ),
    );

    await _local.show(
      DateTime.now().millisecondsSinceEpoch.remainder(100000),
      notification.title,
      notification.body,
      details,
      payload: _alertLogsPayload,
    );
  }

  void _openAlertLogsFromLocalTap(NotificationResponse response) {
    if (response.payload == _alertLogsPayload || response.payload == null) {
      AppNavigator.openAlertLogs();
    }
  }

  void _openAlertLogsFromRemoteTap(RemoteMessage message) {
    AppNavigator.openAlertLogs();
  }
}
