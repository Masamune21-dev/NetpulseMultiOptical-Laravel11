class InterfaceRow {
  InterfaceRow({
    required this.id,
    required this.deviceId,
    required this.deviceName,
    required this.deviceIp,
    required this.ifIndex,
    required this.ifName,
    required this.ifAlias,
    required this.ifDescription,
    required this.rxPower,
    required this.txPower,
    required this.operStatus,
    required this.ifSpeed,
    required this.inRateBps,
    required this.outRateBps,
    required this.lastSeen,
    required this.interfaceType,
  });

  final int id;
  final int deviceId;
  final String? deviceName;
  final String? deviceIp;
  final int ifIndex;
  final String? ifName;
  final String? ifAlias;
  final String? ifDescription;
  final double? rxPower;
  final double? txPower;
  final int? operStatus;
  final int? ifSpeed;
  final int? inRateBps;
  final int? outRateBps;
  final String? lastSeen;
  final String? interfaceType;

  bool get isUp => operStatus == 1;

  String get description {
    final v = (ifAlias?.isNotEmpty == true) ? ifAlias! : (ifDescription ?? '');
    return v;
  }

  factory InterfaceRow.fromJson(Map<String, dynamic> json) {
    int? asInt(dynamic v) => v is num ? v.toInt() : null;
    double? asDouble(dynamic v) => v is num ? v.toDouble() : null;
    String? asStr(dynamic v) => v?.toString();

    return InterfaceRow(
      id: (json['id'] as num).toInt(),
      deviceId: (json['device_id'] as num).toInt(),
      deviceName: asStr(json['device_name']),
      deviceIp: asStr(json['device_ip']),
      ifIndex: (json['if_index'] as num).toInt(),
      ifName: asStr(json['if_name']),
      ifAlias: asStr(json['if_alias']),
      ifDescription: asStr(json['if_description']),
      rxPower: asDouble(json['rx_power']),
      txPower: asDouble(json['tx_power']),
      operStatus: asInt(json['oper_status']),
      ifSpeed: asInt(json['if_speed']),
      inRateBps: asInt(json['in_rate_bps']),
      outRateBps: asInt(json['out_rate_bps']),
      lastSeen: asStr(json['last_seen']),
      interfaceType: asStr(json['interface_type']),
    );
  }
}

class InterfaceListMeta {
  InterfaceListMeta({
    required this.total,
    required this.page,
    required this.perPage,
    required this.lastPage,
  });

  final int total;
  final int page;
  final int perPage;
  final int lastPage;

  factory InterfaceListMeta.fromJson(Map<String, dynamic> json) {
    return InterfaceListMeta(
      total: (json['total'] as num?)?.toInt() ?? 0,
      page: (json['page'] as num?)?.toInt() ?? 1,
      perPage: (json['per_page'] as num?)?.toInt() ?? 25,
      lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}

class InterfaceListResult {
  InterfaceListResult({required this.data, required this.meta});
  final List<InterfaceRow> data;
  final InterfaceListMeta meta;
}

class InterfaceDevice {
  InterfaceDevice({required this.id, required this.name});

  final int id;
  final String name;

  factory InterfaceDevice.fromJson(Map<String, dynamic> json) {
    return InterfaceDevice(
      id: (json['id'] as num).toInt(),
      name: (json['device_name'] ?? '').toString(),
    );
  }
}

class TrafficPoint {
  TrafficPoint({
    required this.createdAt,
    required this.inRateBps,
    required this.outRateBps,
  });

  final String createdAt;
  final int? inRateBps;
  final int? outRateBps;

  DateTime? get timestamp {
    try {
      return DateTime.parse(createdAt.replaceAll(' ', 'T'));
    } catch (_) {
      return null;
    }
  }

  factory TrafficPoint.fromJson(Map<String, dynamic> json) {
    return TrafficPoint(
      createdAt: (json['created_at'] ?? '').toString(),
      inRateBps: json['in_rate_bps'] is num
          ? (json['in_rate_bps'] as num).toInt()
          : null,
      outRateBps: json['out_rate_bps'] is num
          ? (json['out_rate_bps'] as num).toInt()
          : null,
    );
  }
}

class TrafficSummary {
  TrafficSummary({
    required this.inCur,
    required this.inAvg,
    required this.inMax,
    required this.outCur,
    required this.outAvg,
    required this.outMax,
  });

  final int? inCur;
  final int? inAvg;
  final int? inMax;
  final int? outCur;
  final int? outAvg;
  final int? outMax;

  factory TrafficSummary.fromJson(Map<String, dynamic> json) {
    int? asInt(dynamic v) => v is num ? v.toInt() : null;
    return TrafficSummary(
      inCur: asInt(json['in_cur']),
      inAvg: asInt(json['in_avg']),
      inMax: asInt(json['in_max']),
      outCur: asInt(json['out_cur']),
      outAvg: asInt(json['out_avg']),
      outMax: asInt(json['out_max']),
    );
  }
}

class InterfaceMeta {
  InterfaceMeta({
    required this.deviceName,
    required this.deviceIp,
    required this.ifName,
    required this.ifAlias,
    required this.ifSpeed,
    required this.operStatus,
    required this.interfaceType,
    required this.range,
  });

  final String? deviceName;
  final String? deviceIp;
  final String? ifName;
  final String? ifAlias;
  final int? ifSpeed;
  final int? operStatus;
  final String? interfaceType;
  final String range;

  bool get isUp => operStatus == 1;

  factory InterfaceMeta.fromJson(Map<String, dynamic> json) {
    return InterfaceMeta(
      deviceName: json['device_name']?.toString(),
      deviceIp: json['device_ip']?.toString(),
      ifName: json['if_name']?.toString(),
      ifAlias: json['if_alias']?.toString(),
      ifSpeed: json['if_speed'] is num
          ? (json['if_speed'] as num).toInt()
          : null,
      operStatus: json['oper_status'] is num
          ? (json['oper_status'] as num).toInt()
          : null,
      interfaceType: json['interface_type']?.toString(),
      range: (json['range'] ?? '1d').toString(),
    );
  }
}

class TrafficHistoryResult {
  TrafficHistoryResult({
    required this.meta,
    required this.data,
    required this.summary,
  });

  final InterfaceMeta meta;
  final List<TrafficPoint> data;
  final TrafficSummary summary;
}
