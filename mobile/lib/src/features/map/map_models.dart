import 'dart:convert';

class MapNode {
  MapNode({
    required this.id,
    required this.name,
    required this.type,
    required this.lng,
    required this.lat,
    required this.status,
    required this.interfaces,
  });

  final int id;
  final String name;
  final String type;
  final double lng;
  final double lat;
  final String status;
  final List<MapInterface> interfaces;

  factory MapNode.fromJson(Map<String, dynamic> json) {
    final ifs = (json['interfaces'] as List?) ?? const [];
    return MapNode(
      id: (json['id'] as num).toInt(),
      name: (json['node_name'] ?? json['node_type'] ?? 'node').toString(),
      type: (json['node_type'] ?? 'router').toString(),
      lng: (json['x_position'] as num).toDouble(),
      lat: (json['y_position'] as num).toDouble(),
      status: (json['status'] ?? json['last_status'] ?? 'unknown').toString(),
      interfaces: ifs
          .whereType<Map>()
          .map((e) => MapInterface.fromJson(e.cast<String, dynamic>()))
          .toList(),
    );
  }
}

class MapInterface {
  MapInterface({
    required this.name,
    required this.alias,
    required this.rxPower,
    required this.txPower,
    required this.operStatus,
    required this.isSfp,
  });

  final String name;
  final String? alias;
  final double? rxPower;
  final double? txPower;
  final String? operStatus;
  final bool isSfp;

  factory MapInterface.fromJson(Map<String, dynamic> json) {
    double? parseNum(dynamic v) {
      if (v is num) return v.toDouble();
      return double.tryParse(v?.toString() ?? '');
    }

    return MapInterface(
      name: (json['if_name'] ?? '').toString(),
      alias: json['if_alias']?.toString(),
      rxPower: parseNum(json['rx_power']),
      txPower: parseNum(json['tx_power']),
      operStatus: json['oper_status']?.toString(),
      isSfp: json['is_sfp'] == true || json['is_sfp'] == 1,
    );
  }
}

class MapPathPoint {
  MapPathPoint({required this.lat, required this.lng});

  final double lat;
  final double lng;

  factory MapPathPoint.fromJson(Map<String, dynamic> json) {
    return MapPathPoint(
      lat: (json['lat'] as num).toDouble(),
      lng: (json['lng'] as num).toDouble(),
    );
  }
}

class MapLink {
  MapLink({
    required this.id,
    required this.aId,
    required this.bId,
    required this.interfaceA,
    required this.interfaceB,
    required this.statusA,
    required this.statusB,
    required this.rxA,
    required this.rxB,
    required this.attenuationDb,
    required this.path,
  });

  final int id;
  final int aId;
  final int bId;
  final String? interfaceA;
  final String? interfaceB;
  final int? statusA;
  final int? statusB;
  final double? rxA;
  final double? rxB;
  final double? attenuationDb;
  final List<MapPathPoint> path;

  factory MapLink.fromJson(Map<String, dynamic> json) {
    double? parseNum(dynamic v) {
      if (v is num) return v.toDouble();
      return double.tryParse(v?.toString() ?? '');
    }

    int? parseInt(dynamic v) {
      if (v is int) return v;
      if (v is num) return v.toInt();
      return int.tryParse(v?.toString() ?? '');
    }

    List<MapPathPoint> parsePath(dynamic raw) {
      if (raw == null) return const [];
      dynamic decoded = raw;
      if (raw is String && raw.trim().isNotEmpty) {
        try {
          decoded = jsonDecode(raw);
        } catch (_) {
          return const [];
        }
      }
      if (decoded is! List) return const [];
      final out = <MapPathPoint>[];
      for (final p in decoded) {
        if (p is List && p.length >= 2) {
          final lat = parseNum(p[0]);
          final lng = parseNum(p[1]);
          if (lat != null && lng != null) {
            out.add(MapPathPoint(lat: lat, lng: lng));
          }
          continue;
        }
        if (p is Map) {
          final lat = parseNum(p['lat']);
          final lng = parseNum(p['lng']);
          if (lat != null && lng != null) {
            out.add(MapPathPoint(lat: lat, lng: lng));
          }
        }
      }
      return out;
    }

    return MapLink(
      id: (json['id'] as num).toInt(),
      aId: (json['node_a_id'] as num).toInt(),
      bId: (json['node_b_id'] as num).toInt(),
      interfaceA: json['interface_a_name']?.toString(),
      interfaceB: json['interface_b_name']?.toString(),
      statusA: parseInt(json['interface_a_status']),
      statusB: parseInt(json['interface_b_status']),
      rxA: parseNum(json['interface_a_rx']),
      rxB: parseNum(json['interface_b_rx']),
      attenuationDb: parseNum(json['attenuation_db']),
      path: parsePath(json['path_json']),
    );
  }
}
