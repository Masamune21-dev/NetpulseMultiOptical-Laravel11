import '../../api/api_client.dart';
import 'monitoring_models.dart';

class MonitoringService {
  MonitoringService(this._api);

  final ApiClient _api;

  Future<List<MonitoringDevice>> devices() async {
    final json = await _api.getJson('/api/v1/monitoring/devices');
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load devices').toString());
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => MonitoringDevice.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<List<MonitoringInterface>> interfaces(int deviceId) async {
    final json = await _api.getJson(
      '/api/v1/monitoring/interfaces',
      query: {'device_id': deviceId},
    );
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load interfaces').toString());
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => MonitoringInterface.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<List<ChartPoint>> chart({
    required int deviceId,
    required int ifIndex,
    String range = '1h',
  }) async {
    final json = await _api.getJson(
      '/api/v1/monitoring/chart',
      query: {
        'device_id': deviceId,
        'if_index': ifIndex,
        'range': range,
      },
    );
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load chart').toString());
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => ChartPoint.fromJson(e.cast<String, dynamic>()))
        .toList();
  }
}

