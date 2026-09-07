import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SessionUser {
  SessionUser({
    required this.id,
    required this.username,
    required this.fullName,
    required this.role,
  });

  final int id;
  final String username;
  final String fullName;
  final String role;

  factory SessionUser.fromJson(Map<String, dynamic> json) {
    return SessionUser(
      id: (json['id'] as num).toInt(),
      username: (json['username'] ?? '') as String,
      fullName: (json['full_name'] ?? '') as String,
      role: (json['role'] ?? '') as String,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'username': username,
        'full_name': fullName,
        'role': role,
      };
}

class SessionStore {
  SessionStore._();

  static final instance = SessionStore._();

  static const _kApiBaseUrl = 'api_base_url';
  static const _kAccessToken = 'access_token';
  static const _kUserJson = 'user_json';
  static const _kThemeMode = 'theme_mode';

  SharedPreferences? _prefs;

  String? accessToken;
  SessionUser? user;
  String apiBaseUrl = 'https://netpulse.kusumavision.net';
  final themeModeNotifier = ValueNotifier<ThemeMode>(ThemeMode.system);

  Future<void> load() async {
    _prefs = await SharedPreferences.getInstance();

    apiBaseUrl = _prefs!.getString(_kApiBaseUrl) ?? apiBaseUrl;
    accessToken = _prefs!.getString(_kAccessToken);

    final savedTheme = _prefs!.getString(_kThemeMode);
    if (savedTheme == 'dark') {
      themeModeNotifier.value = ThemeMode.dark;
    } else if (savedTheme == 'light') {
      themeModeNotifier.value = ThemeMode.light;
    } else {
      themeModeNotifier.value = ThemeMode.system;
    }

    final raw = _prefs!.getString(_kUserJson);
    if (raw != null && raw.isNotEmpty) {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        user = SessionUser.fromJson(decoded);
      }
    }
  }

  Future<void> setApiBaseUrl(String value) async {
    apiBaseUrl = value.trim();
    await _prefs?.setString(_kApiBaseUrl, apiBaseUrl);
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    themeModeNotifier.value = mode;
    final val = mode == ThemeMode.dark ? 'dark' : (mode == ThemeMode.light ? 'light' : 'system');
    await _prefs?.setString(_kThemeMode, val);
  }

  Future<void> setSession({
    required String accessToken,
    required SessionUser user,
  }) async {
    this.accessToken = accessToken;
    this.user = user;
    await _prefs?.setString(_kAccessToken, accessToken);
    await _prefs?.setString(_kUserJson, jsonEncode(user.toJson()));
  }

  Future<void> clear() async {
    accessToken = null;
    user = null;
    await _prefs?.remove(_kAccessToken);
    await _prefs?.remove(_kUserJson);
  }
}

