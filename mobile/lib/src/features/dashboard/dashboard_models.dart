class DashboardCounts {
  DashboardCounts({
    required this.deviceCount,
    required this.interfaceCount,
    required this.sfpCount,
    required this.badOpticalCount,
    required this.userCount,
  });

  final int deviceCount;
  final int interfaceCount;
  final int sfpCount;
  final int badOpticalCount;
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
      userCount: numVal('user_count'),
    );
  }
}
