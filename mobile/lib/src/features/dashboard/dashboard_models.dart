class DeviceHealth {
  const DeviceHealth({
    required this.total,
    required this.active,
    required this.failed,
    required this.inactive,
  });

  final int total;
  final int active;
  final int failed;
  final int inactive;

  factory DeviceHealth.fromJson(Map<String, dynamic> json) {
    int n(String k) {
      final v = json[k];
      if (v is num) return v.toInt();
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    return DeviceHealth(
      total: n('total'),
      active: n('active'),
      failed: n('failed'),
      inactive: n('inactive'),
    );
  }

  static DeviceHealth get empty => const DeviceHealth(total: 0, active: 0, failed: 0, inactive: 0);
}

class WorstPort {
  const WorstPort({
    required this.ifName,
    required this.ifAlias,
    required this.deviceName,
    required this.rxPower,
    required this.txPower,
  });

  final String ifName;
  final String? ifAlias;
  final String deviceName;
  final double rxPower;
  final double txPower;

  factory WorstPort.fromJson(Map<String, dynamic> json) {
    double d(String k) {
      final v = json[k];
      if (v is num) return v.toDouble();
      return double.tryParse(v?.toString() ?? '') ?? 0.0;
    }

    return WorstPort(
      ifName: json['if_name']?.toString() ?? '-',
      ifAlias: json['if_alias']?.toString(),
      deviceName: json['device_name']?.toString() ?? '-',
      rxPower: d('rx_power'),
      txPower: d('tx_power'),
    );
  }
}

class RecentAlert {
  const RecentAlert({
    required this.eventType,
    required this.severity,
    required this.deviceName,
    required this.ifName,
    required this.message,
    required this.createdAt,
  });

  final String eventType;
  final String severity;
  final String deviceName;
  final String? ifName;
  final String message;
  final String createdAt;

  factory RecentAlert.fromJson(Map<String, dynamic> json) {
    return RecentAlert(
      eventType: json['event_type']?.toString() ?? '',
      severity: json['severity']?.toString() ?? 'info',
      deviceName: json['device_name']?.toString() ?? '-',
      ifName: json['if_name']?.toString(),
      message: json['message']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class DashboardCounts {
  DashboardCounts({
    required this.deviceCount,
    required this.deviceTotal,
    required this.interfaceCount,
    required this.ifUpCount,
    required this.ifDownCount,
    required this.sfpCount,
    required this.badOpticalCount,
    required this.userCount,
    required this.deviceHealth,
    required this.worstPorts,
    required this.recentAlerts,
  });

  final int deviceCount;
  final int deviceTotal;
  final int interfaceCount;
  final int ifUpCount;
  final int ifDownCount;
  final int sfpCount;
  final int badOpticalCount;
  final int userCount;
  final DeviceHealth deviceHealth;
  final List<WorstPort> worstPorts;
  final List<RecentAlert> recentAlerts;

  factory DashboardCounts.fromJson(Map<String, dynamic> json) {
    int numVal(String key) {
      final v = json[key];
      if (v is num) return v.toInt();
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    DeviceHealth health = DeviceHealth.empty;
    if (json['device_health'] is Map<String, dynamic>) {
      health = DeviceHealth.fromJson(json['device_health'] as Map<String, dynamic>);
    }

    List<WorstPort> ports = [];
    if (json['worst_ports'] is List) {
      ports = (json['worst_ports'] as List)
          .whereType<Map<String, dynamic>>()
          .map(WorstPort.fromJson)
          .toList();
    }

    List<RecentAlert> alerts = [];
    if (json['recent_alerts'] is List) {
      alerts = (json['recent_alerts'] as List)
          .whereType<Map<String, dynamic>>()
          .map(RecentAlert.fromJson)
          .toList();
    }

    return DashboardCounts(
      deviceCount: numVal('device_count'),
      deviceTotal: numVal('device_total'),
      interfaceCount: numVal('interface_count'),
      ifUpCount: numVal('if_up_count'),
      ifDownCount: numVal('if_down_count'),
      sfpCount: numVal('sfp_count'),
      badOpticalCount: numVal('bad_optical_count'),
      userCount: numVal('user_count'),
      deviceHealth: health,
      worstPorts: ports,
      recentAlerts: alerts,
    );
  }
}
