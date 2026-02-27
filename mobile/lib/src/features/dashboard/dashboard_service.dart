import '../../api/api_client.dart';
import 'dashboard_models.dart';

class DashboardService {
  DashboardService(this._api);

  final ApiClient _api;

  Future<DashboardCounts> counts() async {
    final json = await _api.getJson('/api/v1/dashboard');
    if (json['success'] != true) {
      throw ApiException(500, (json['error'] ?? 'Failed to load dashboard').toString());
    }
    final data = (json['data'] as Map?)?.cast<String, dynamic>() ?? const {};
    return DashboardCounts.fromJson(data);
  }
}
