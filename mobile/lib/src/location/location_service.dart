import 'package:geolocator/geolocator.dart';

import '../api/api_client.dart';

class LocationService {
  LocationService(this._api);

  final ApiClient _api;

  Future<bool> ensurePermission() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return false;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      return false;
    }

    if (permission == LocationPermission.deniedForever) {
      return false;
    }

    return true;
  }

  Future<void> sendCurrentLocation() async {
    final ok = await ensurePermission();
    if (!ok) {
      throw ApiException(400, 'Location permission not granted');
    }

    final pos = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );

    await _api.postJson(
      '/api/v1/location',
      body: {
        'latitude': pos.latitude,
        'longitude': pos.longitude,
        'accuracy': pos.accuracy,
        'recorded_at': pos.timestamp.toIso8601String(),
      },
    );
  }
}
