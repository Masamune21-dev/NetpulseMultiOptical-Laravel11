class OltSummary {
  OltSummary({
    required this.id,
    required this.name,
    required this.pons,
    required this.lastPoll,
    required this.ponCount,
  });

  final String id;
  final String name;
  final List<String> pons;
  final String? lastPoll;
  final int? ponCount;

  factory OltSummary.fromJson(Map<String, dynamic> json) {
    final p = (json['pons'] as List?) ?? const [];
    return OltSummary(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      pons: p.map((e) => e.toString()).toList(),
      lastPoll: json['last_poll']?.toString(),
      ponCount: json['pon_count'] is num ? (json['pon_count'] as num).toInt() : null,
    );
  }
}

class OltDataResponse {
  OltDataResponse({
    required this.lastUpdate,
    required this.data,
  });

  final String? lastUpdate;
  final Map<String, dynamic> data;
}

