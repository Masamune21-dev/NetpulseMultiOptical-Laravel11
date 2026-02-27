import '../../api/api_client.dart';
import 'olt_models.dart';

class OltService {
  OltService(this._api);

  final ApiClient _api;

  Future<List<OltSummary>> listOlts() async {
    final json = await _api.getJson('/api/v1/olt');
    final ok = json['success'] == true;
    if (!ok) {
      throw ApiException(500, (json['error'] ?? 'Failed to load OLT').toString());
    }

    final data = (json['data'] as List?) ?? const [];
    return data
        .whereType<Map>()
        .map((e) => OltSummary.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<OltDataResponse> fetchOltData({
    required String oltId,
    required String pon,
  }) async {
    final json = await _api.getJson(
      '/api/v1/olt-data',
      query: {
        'olt': oltId,
        'pon': pon,
      },
    );

    final ok = json['success'] == true;
    if (!ok) {
      throw ApiException(
        500,
        (json['error'] ?? 'Failed to load OLT data').toString(),
      );
    }

    final data = (json['data'] as Map?)?.cast<String, dynamic>() ?? const {};
    return OltDataResponse(
      lastUpdate: json['last_update']?.toString(),
      data: data,
    );
  }
}

