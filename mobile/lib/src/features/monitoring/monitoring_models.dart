class MonitoringDevice {
  MonitoringDevice({required this.id, required this.name});

  final int id;
  final String name;

  factory MonitoringDevice.fromJson(Map<String, dynamic> json) {
    return MonitoringDevice(
      id: (json['id'] as num).toInt(),
      name: (json['device_name'] ?? '').toString(),
    );
  }
}

class MonitoringInterface {
  MonitoringInterface({
    required this.ifIndex,
    required this.ifName,
    required this.ifAlias,
    required this.rxPower,
    required this.txPower,
  });

  final int ifIndex;
  final String ifName;
  final String? ifAlias;
  final double? rxPower;
  final double? txPower;

  factory MonitoringInterface.fromJson(Map<String, dynamic> json) {
    return MonitoringInterface(
      ifIndex: (json['if_index'] as num).toInt(),
      ifName: (json['if_name'] ?? '').toString(),
      ifAlias: json['if_alias']?.toString(),
      rxPower: json['rx_power'] is num ? (json['rx_power'] as num).toDouble() : null,
      txPower: json['tx_power'] is num ? (json['tx_power'] as num).toDouble() : null,
    );
  }
}

class ChartPoint {
  ChartPoint({
    required this.createdAt,
    required this.rxPower,
    required this.txPower,
    required this.loss,
  });

  final String createdAt;
  final double? rxPower;
  final double? txPower;
  final double? loss;

  factory ChartPoint.fromJson(Map<String, dynamic> json) {
    return ChartPoint(
      createdAt: (json['created_at'] ?? '').toString(),
      rxPower: json['rx_power'] is num ? (json['rx_power'] as num).toDouble() : null,
      txPower: json['tx_power'] is num ? (json['tx_power'] as num).toDouble() : null,
      loss: json['loss'] is num ? (json['loss'] as num).toDouble() : null,
    );
  }
}

