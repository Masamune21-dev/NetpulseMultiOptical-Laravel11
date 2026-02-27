import '../../api/api_client.dart';
import 'map_models.dart';

class MapService {
  MapService(this._api);

  final ApiClient _api;

  Future<List<MapNode>> nodes() async {
    final json = await _api.getJson(
      '/api/v1/map/nodes',
      query: {'with_interfaces': 1},
    );
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load nodes').toString());
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => MapNode.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<List<MapLink>> links() async {
    final json = await _api.getJson('/api/v1/map/links');
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load links').toString());
    }
    final list = (json['data'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((e) => MapLink.fromJson(e.cast<String, dynamic>()))
        .toList();
  }
}
