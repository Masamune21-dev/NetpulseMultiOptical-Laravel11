class AlertLogItem {
  AlertLogItem({
    required this.id,
    required this.createdAt,
    required this.eventType,
    required this.severity,
    required this.message,
    required this.deviceName,
    required this.deviceIp,
    required this.ifName,
    required this.ifAlias,
    required this.rxPower,
    required this.txPower,
  });

  final int id;
  final String createdAt;
  final String eventType;
  final String severity;
  final String message;
  final String? deviceName;
  final String? deviceIp;
  final String? ifName;
  final String? ifAlias;
  final double? rxPower;
  final double? txPower;

  factory AlertLogItem.fromJson(Map<String, dynamic> json) {
    return AlertLogItem(
      id: (json['id'] as num).toInt(),
      createdAt: (json['created_at'] ?? '').toString(),
      eventType: (json['event_type'] ?? '').toString(),
      severity: (json['severity'] ?? '').toString(),
      message: (json['message'] ?? '').toString(),
      deviceName: json['device_name']?.toString(),
      deviceIp: json['device_ip']?.toString(),
      ifName: json['if_name']?.toString(),
      ifAlias: json['if_alias']?.toString(),
      rxPower: json['rx_power'] is num ? (json['rx_power'] as num).toDouble() : null,
      txPower: json['tx_power'] is num ? (json['tx_power'] as num).toDouble() : null,
    );
  }
}

