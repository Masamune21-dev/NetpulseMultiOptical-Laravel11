import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
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

  // Token Bearer disimpan di Android Keystore (EncryptedSharedPreferences), bukan
  // SharedPreferences biasa yang berupa XML teks polos di direktori data aplikasi.
  static const _secure = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _defaultApiBaseUrl = 'https://netpulse.kusumavision.net';

  String? accessToken;
  SessionUser? user;
  String apiBaseUrl = _defaultApiBaseUrl;
  final themeModeNotifier = ValueNotifier<ThemeMode>(ThemeMode.system);

  Future<void> load() async {
    _prefs = await SharedPreferences.getInstance();

    // Base URL kustom hanya berlaku di build debug; rilis selalu memakai server resmi
    // (termasuk mengabaikan nilai yang mungkin sempat disimpan versi lama).
    apiBaseUrl = kDebugMode
        ? (_prefs!.getString(_kApiBaseUrl) ?? _defaultApiBaseUrl)
        : _defaultApiBaseUrl;

    accessToken = await _readSecure(_kAccessToken);
    // Migrasi sekali: token dari versi lama (SharedPreferences) dipindah ke penyimpanan
    // aman lalu dihapus dari tempat lamanya.
    final legacy = _prefs!.getString(_kAccessToken);
    if (legacy != null && legacy.isNotEmpty) {
      if (accessToken == null || accessToken!.isEmpty) {
        accessToken = legacy;
        await _writeSecure(_kAccessToken, legacy);
      }
      await _prefs!.remove(_kAccessToken);
    }

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
    await _writeSecure(_kAccessToken, accessToken);
    await _prefs?.setString(_kUserJson, jsonEncode(user.toJson()));
  }

  Future<void> clear() async {
    accessToken = null;
    user = null;
    await _deleteSecure(_kAccessToken);
    await _prefs?.remove(_kAccessToken);
    await _prefs?.remove(_kUserJson);
  }

  // Penyimpanan aman bisa gagal di perangkat tertentu (keystore rusak); jangan sampai
  // itu membuat aplikasi gagal start — perlakukan sebagai belum login.
  Future<String?> _readSecure(String key) async {
    try {
      return await _secure.read(key: key);
    } catch (_) {
      return null;
    }
  }

  Future<void> _writeSecure(String key, String value) async {
    try {
      await _secure.write(key: key, value: value);
    } catch (_) {
      // Token tetap ada di memori untuk sesi ini; pengguna login ulang setelah restart.
    }
  }

  Future<void> _deleteSecure(String key) async {
    try {
      await _secure.delete(key: key);
    } catch (_) {}
  }
}

