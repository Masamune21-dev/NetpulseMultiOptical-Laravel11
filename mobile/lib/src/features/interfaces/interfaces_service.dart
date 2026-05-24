import '../../api/api_client.dart';
import 'interfaces_models.dart';

class InterfacesService {
  InterfacesService(this._api);

  final ApiClient _api;

  Future<InterfaceListResult> list({
    int page = 1,
    int perPage = 25,
    int? deviceId,
    String status = 'all',
    String q = '',
  }) async {
    final query = <String, dynamic>{
      'page': page,
      'per_page': perPage,
      'status': status,
    };
    if (deviceId != null && deviceId > 0) query['device_id'] = deviceId;
    if (q.trim().isNotEmpty) query['q'] = q.trim();

    final json = await _api.getJson('/api/v1/interfaces', query: query);
    if (json['success'] != true) {
      throw ApiException(
        500,
        (json['error'] ?? 'Failed to load interfaces').toString(),
      );
    }
    final list = (json['data'] as List?) ?? const [];
    final meta = (json['meta'] as Map?)?.cast<String, dynamic>() ?? const {};

    return InterfaceListResult(
      data: list
          .whereType<Map>()
          .map((e) => InterfaceRow.fromJson(e.cast<String, dynamic>()))
          .toList(),
      meta: InterfaceListMeta.fromJson(meta),
    );
  }

  Future<List<InterfaceDevice>> devices() async {
    final json = await _api.getJson('/api/v1/monitoring/devices');
    if (json['success'] != true) {
      throw ApiException(
        500,
        (json['error'] ?? 'Failed to load devices').toString(),
      );
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => InterfaceDevice.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<TrafficHistoryResult> trafficHistory({
    required int deviceId,
    required int ifIndex,
    String range = '1d',
  }) async {
    final json = await _api.getJson(
      '/api/v1/interfaces/traffic-history',
      query: {'device_id': deviceId, 'if_index': ifIndex, 'range': range},
    );
    if (json['success'] != true) {
      throw ApiException(
        500,
        (json['error'] ?? 'Failed to load traffic').toString(),
      );
    }
    final meta = (json['meta'] as Map?)?.cast<String, dynamic>() ?? const {};
    final data = (json['data'] as List?) ?? const [];
    final summary =
        (json['summary'] as Map?)?.cast<String, dynamic>() ?? const {};

    return TrafficHistoryResult(
      meta: InterfaceMeta.fromJson(meta),
      data: data
          .whereType<Map>()
          .map((e) => TrafficPoint.fromJson(e.cast<String, dynamic>()))
          .toList(),
      summary: TrafficSummary.fromJson(summary),
    );
  }
}
