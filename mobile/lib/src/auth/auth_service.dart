import 'package:package_info_plus/package_info_plus.dart';

import '../api/api_client.dart';
import 'session_store.dart';

class AuthService {
  AuthService(this._api, this._session);

  final ApiClient _api;
  final SessionStore _session;

  Future<void> login({
    required String username,
    required String password,
  }) async {
    final pkg = await PackageInfo.fromPlatform();
    final deviceName = 'android:${pkg.appName}:${pkg.version}+${pkg.buildNumber}';

    final json = await _api.postJson(
      '/api/v1/auth/login',
      auth: false,
      body: {
        'username': username,
        'password': password,
        'device_name': deviceName,
      },
    );

    final accessToken = (json['access_token'] ?? '') as String;
    final userJson = (json['user'] ?? const {}) as Map<String, dynamic>;

    if (accessToken.isEmpty) {
      throw ApiException(500, 'Missing access_token from server');
    }

    await _session.setSession(
      accessToken: accessToken,
      user: SessionUser.fromJson(userJson),
    );
  }

  Future<void> logout() async {
    try {
      await _api.postJson('/api/v1/auth/logout');
    } catch (_) {
      // best-effort
    }
    await _session.clear();
  }
}

