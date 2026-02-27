import 'package:flutter/material.dart';

import '../auth/session_store.dart';
import '../ui/screens/settings_screen.dart';

class AppNavigator {
  AppNavigator._();

  static final navigatorKey = GlobalKey<NavigatorState>();

  static bool _pendingOpenAlertLogs = false;
  static DateTime? _lastOpenAt;

  static void openAlertLogs() {
    final nav = navigatorKey.currentState;
    final hasToken = (SessionStore.instance.accessToken ?? '').isNotEmpty;
    if (nav == null || !hasToken) {
      _pendingOpenAlertLogs = true;
      return;
    }

    final now = DateTime.now();
    if (_lastOpenAt != null &&
        now.difference(_lastOpenAt!) < const Duration(seconds: 1)) {
      return;
    }

    _pendingOpenAlertLogs = false;
    _lastOpenAt = now;
    nav.push(MaterialPageRoute(builder: (_) => const SettingsScreen()));
  }

  static void flushPendingAlertLogs() {
    if (!_pendingOpenAlertLogs) return;
    _pendingOpenAlertLogs = false;
    openAlertLogs();
  }
}
