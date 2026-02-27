import '../../api/api_client.dart';
import 'alert_models.dart';

class AlertsService {
  AlertsService(this._api);

  final ApiClient _api;

  Future<List<AlertLogItem>> list({
    int limit = 200,
    String type = 'all',
    String severity = 'all',
    String q = '',
  }) async {
    final json = await _api.getJson(
      '/api/v1/alert-logs',
      query: {
        'limit': limit,
        'type': type,
        'severity': severity,
        'q': q,
      },
    );
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load alerts').toString());
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => AlertLogItem.fromJson(e.cast<String, dynamic>()))
        .toList();
  }
}

