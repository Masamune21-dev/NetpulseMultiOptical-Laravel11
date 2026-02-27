class DashboardCounts {
  DashboardCounts({
    required this.deviceCount,
    required this.interfaceCount,
    required this.sfpCount,
    required this.badOpticalCount,
    required this.oltCount,
    required this.ponCount,
    required this.onuCount,
    required this.userCount,
  });

  final int deviceCount;
  final int interfaceCount;
  final int sfpCount;
  final int badOpticalCount;
  final int oltCount;
  final int ponCount;
  final int onuCount;
  final int userCount;

  factory DashboardCounts.fromJson(Map<String, dynamic> json) {
    int numVal(String key) {
      final v = json[key];
      if (v is num) return v.toInt();
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    return DashboardCounts(
      deviceCount: numVal('device_count'),
      interfaceCount: numVal('interface_count'),
      sfpCount: numVal('sfp_count'),
      badOpticalCount: numVal('bad_optical_count'),
      oltCount: numVal('olt_count'),
      ponCount: numVal('pon_count'),
      onuCount: numVal('onu_count'),
      userCount: numVal('user_count'),
    );
  }
}
