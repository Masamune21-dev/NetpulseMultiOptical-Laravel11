import '../api/api_client.dart';

class PushService {
  PushService(this._api);

  final ApiClient _api;

  Future<void> registerFcmToken({
    required String token,
    String platform = 'android',
    String? deviceName,
  }) async {
    await _api.postJson(
      '/api/v1/device-token',
      body: {
        'token': token,
        'platform': platform,
        if (deviceName != null) 'device_name': deviceName,
      },
    );
  }
}

