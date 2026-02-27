import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/map/map_models.dart';
import '../../features/map/map_service.dart';

enum _LinkFilter { all, up, warning, down }

enum _LinkLevel { up, warning, down }

class MapScreen extends StatefulWidget {
  const MapScreen({super.key});

  @override
  State<MapScreen> createState() => _MapScreenState();
}

class _MapScreenState extends State<MapScreen> {
  bool _loading = true;
  String? _error;
  List<MapNode> _nodes = const [];
  List<MapLink> _links = const [];

  final MapController _mapController = MapController();
  Timer? _animTimer;
  double _phase = 0.0;
  _LinkFilter _filter = _LinkFilter.all;

  MapService get _svc => MapService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _load();
    _animTimer = Timer.periodic(const Duration(milliseconds: 220), (_) {
      if (!mounted) return;
      setState(() {
        _phase = (_phase + 0.025) % 1.0;
      });
    });
  }

  @override
  void dispose() {
    _animTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final nodes = await _svc.nodes();
      final links = await _svc.links();
      setState(() {
        _nodes = nodes;
        _links = links;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _recenterMap() {
    if (_nodes.isEmpty) return;
    _mapController.move(_mapCenter(_nodes), _mapZoom(_nodes));
  }

  @override
  Widget build(BuildContext context) {
    final center = _mapCenter(_nodes);
    final zoom = _mapZoom(_nodes);
    final byId = {for (final n in _nodes) n.id: n};

    final downLinks = _links
        .where((l) => _linkLevel(l) == _LinkLevel.down)
        .length;
    final warningLinks = _links
        .where((l) => _linkLevel(l) == _LinkLevel.warning)
        .length;
    final upLinks = _links.where((l) => _linkLevel(l) == _LinkLevel.up).length;

    final visibleLinks = _links
        .where((l) => _matchesFilter(_linkLevel(l)))
        .toList();

    final polylines = <Polyline>[];
    for (final l in visibleLinks) {
      final a = byId[l.aId];
      final b = byId[l.bId];
      if (a == null || b == null) continue;

      final color = _colorForLevel(_linkLevel(l));
      final points = [
        LatLng(a.lat, a.lng),
        ...l.path.map((p) => LatLng(p.lat, p.lng)),
        LatLng(b.lat, b.lng),
      ];

      polylines.add(
        Polyline(
          points: points,
          strokeWidth: 3,
          color: color.withValues(alpha: 0.25),
        ),
      );
      polylines.addAll(_animatedDashes(points, _phase, color));
    }

    final markers = _nodes.map((n) {
      final ok = _statusIsUp(n.status);
      final icon = _iconForType(n.type);
      final tone = ok ? const Color(0xFF0F766E) : const Color(0xFFDC2626);

      return Marker(
        point: LatLng(n.lat, n.lng),
        width: 44,
        height: 44,
        child: GestureDetector(
          onTap: () => _showNode(n),
          child: Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                colors: [tone, tone.withValues(alpha: 0.7)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              border: Border.all(color: Colors.white, width: 2),
              boxShadow: const [
                BoxShadow(
                  blurRadius: 10,
                  offset: Offset(0, 4),
                  color: Color(0x22000000),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.white, size: 20),
          ),
        ),
      );
    }).toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Network Map'),
        actions: [
          IconButton(
            onPressed: _loading ? null : _recenterMap,
            icon: const Icon(Icons.center_focus_strong),
            tooltip: 'Center map',
          ),
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh data',
          ),
        ],
      ),
      body: _loading
          ? const Center(
              child: SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            )
          : _error != null
          ? Center(child: Text(_error!))
          : Stack(
              children: [
                Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFFF2F8F7), Color(0xFFE8F1F8)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                  ),
                ),
                FlutterMap(
                  mapController: _mapController,
                  options: MapOptions(initialCenter: center, initialZoom: zoom),
                  children: [
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'net.bmkv.netpulse.netpulse_mobile',
                    ),
                    PolylineLayer(polylines: polylines),
                    MarkerLayer(markers: markers),
                  ],
                ),
                Positioned(
                  left: 12,
                  right: 12,
                  top: 12,
                  child: SafeArea(
                    bottom: false,
                    child: _TopPanel(
                      selectedFilter: _filter,
                      total: _links.length,
                      upLinks: upLinks,
                      warningLinks: warningLinks,
                      downLinks: downLinks,
                      onFilterChanged: (value) =>
                          setState(() => _filter = value),
                    ),
                  ),
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 16,
                  child: _LegendCard(
                    nodes: _nodes.length,
                    visibleLinks: visibleLinks.length,
                    totalLinks: _links.length,
                    filter: _filter,
                  ),
                ),
              ],
            ),
    );
  }

  bool _matchesFilter(_LinkLevel level) {
    return switch (_filter) {
      _LinkFilter.all => true,
      _LinkFilter.up => level == _LinkLevel.up,
      _LinkFilter.warning => level == _LinkLevel.warning,
      _LinkFilter.down => level == _LinkLevel.down,
    };
  }

  void _showNode(MapNode n) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        final ifs = n.interfaces;
        return Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                n.name,
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  _StatusBadge(
                    text: _statusIsUp(n.status) ? 'UP' : 'DOWN',
                    color: _statusIsUp(n.status)
                        ? const Color(0xFF16A34A)
                        : const Color(0xFFDC2626),
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: Text('LatLng: ${n.lat}, ${n.lng}')),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                'Interfaces',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              if (ifs.isEmpty)
                const Text('Tidak ada data interface.')
              else
                SizedBox(
                  height: 280,
                  child: ListView.separated(
                    itemCount: ifs.length,
                    separatorBuilder: (context, index) =>
                        const Divider(height: 16),
                    itemBuilder: (context, i) {
                      final it = ifs[i];
                      final rx = it.rxPower;
                      final tx = it.txPower;
                      final status = (it.operStatus ?? '').toLowerCase();
                      final ok = status == 'up' || status == 'ok';
                      final rxColor = _rxColor(rx);
                      return Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 10,
                            height: 10,
                            margin: const EdgeInsets.only(top: 6),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: ok ? Colors.green : Colors.redAccent,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  it.name,
                                  style: TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: rxColor,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                                if (it.alias != null && it.alias!.isNotEmpty)
                                  Text(
                                    it.alias!,
                                    style: Theme.of(
                                      context,
                                    ).textTheme.bodySmall,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                const SizedBox(height: 4),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 4,
                                  children: [
                                    if (it.isSfp)
                                      Text(
                                        'SFP',
                                        style: Theme.of(
                                          context,
                                        ).textTheme.bodySmall,
                                      ),
                                    Text(
                                      'RX ${rx?.toStringAsFixed(2) ?? '-'} dBm',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: rxColor,
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                    Text(
                                      'TX ${tx?.toStringAsFixed(2) ?? '-'} dBm',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _TopPanel extends StatelessWidget {
  const _TopPanel({
    required this.selectedFilter,
    required this.total,
    required this.upLinks,
    required this.warningLinks,
    required this.downLinks,
    required this.onFilterChanged,
  });

  final _LinkFilter selectedFilter;
  final int total;
  final int upLinks;
  final int warningLinks;
  final int downLinks;
  final ValueChanged<_LinkFilter> onFilterChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD8E3EE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Line Filter',
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _filterChip(
                context: context,
                value: _LinkFilter.all,
                label: 'All',
                count: total,
                color: const Color(0xFF334155),
              ),
              _filterChip(
                context: context,
                value: _LinkFilter.up,
                label: 'Up',
                count: upLinks,
                color: const Color(0xFF16A34A),
              ),
              _filterChip(
                context: context,
                value: _LinkFilter.warning,
                label: 'Warning',
                count: warningLinks,
                color: const Color(0xFFF59E0B),
              ),
              _filterChip(
                context: context,
                value: _LinkFilter.down,
                label: 'Down',
                count: downLinks,
                color: const Color(0xFFDC2626),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _filterChip({
    required BuildContext context,
    required _LinkFilter value,
    required String label,
    required int count,
    required Color color,
  }) {
    final selected = selectedFilter == value;
    return ChoiceChip(
      label: Text('$label ($count)'),
      selected: selected,
      showCheckmark: false,
      onSelected: (_) => onFilterChanged(value),
      labelStyle: Theme.of(context).textTheme.labelLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: selected ? color : const Color(0xFF334155),
      ),
      selectedColor: color.withValues(alpha: 0.14),
      backgroundColor: Colors.white,
      side: BorderSide(color: color.withValues(alpha: selected ? 0.35 : 0.2)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    );
  }
}

class _LegendCard extends StatelessWidget {
  const _LegendCard({
    required this.nodes,
    required this.visibleLinks,
    required this.totalLinks,
    required this.filter,
  });

  final int nodes;
  final int visibleLinks;
  final int totalLinks;
  final _LinkFilter filter;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.95),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFD8E3EE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.router, size: 18),
              const SizedBox(width: 6),
              Text('$nodes nodes'),
              const SizedBox(width: 14),
              const Icon(Icons.timeline, size: 18),
              const SizedBox(width: 6),
              Text('$visibleLinks / $totalLinks links'),
              const Spacer(),
              Text(
                _filterLabel(filter),
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: const Color(0xFF475569),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          const Wrap(
            spacing: 12,
            runSpacing: 6,
            children: [
              _LegendDot(color: Color(0xFF16A34A), label: 'Up'),
              _LegendDot(color: Color(0xFFF59E0B), label: 'Warning'),
              _LegendDot(color: Color(0xFFDC2626), label: 'Down'),
            ],
          ),
        ],
      ),
    );
  }

  static String _filterLabel(_LinkFilter f) {
    return switch (f) {
      _LinkFilter.all => 'All Lines',
      _LinkFilter.up => 'Up Only',
      _LinkFilter.warning => 'Warning Only',
      _LinkFilter.down => 'Down Only',
    };
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(label),
      ],
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: color,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

Color _rxColor(double? rx) {
  if (rx == null) return Colors.grey;
  if (rx <= -40) return Colors.redAccent;
  if (rx >= -25 && rx <= -18) return Colors.orange;
  return Colors.green;
}

List<Polyline> _animatedDashes(List<LatLng> points, double phase, Color color) {
  if (points.length < 2) return const [];
  final out = <Polyline>[];
  for (var i = 0; i < points.length - 1; i++) {
    out.addAll(_animatedDashesBetween(points[i], points[i + 1], phase, color));
  }
  return out;
}

List<Polyline> _animatedDashesBetween(
  LatLng start,
  LatLng end,
  double phase,
  Color color,
) {
  const distance = Distance();
  final len = distance(start, end);
  if (!len.isFinite || len <= 0) return const [];

  final dashCount = (len / 200).round().clamp(3, 12);
  final dashSpan = 1.0 / dashCount;
  final dashLen = dashSpan * 0.4;
  final out = <Polyline>[];

  for (var i = 0; i < dashCount; i++) {
    final base = (i * dashSpan + phase) % 1.0;
    final endT = base + dashLen;
    if (endT <= 1.0) {
      out.add(_dashSegment(start, end, base, endT, color));
    } else {
      out.add(_dashSegment(start, end, base, 1.0, color));
      out.add(_dashSegment(start, end, 0.0, endT - 1.0, color));
    }
  }

  return out;
}

Polyline _dashSegment(LatLng a, LatLng b, double t1, double t2, Color color) {
  final p1 = _lerpLatLng(a, b, t1);
  final p2 = _lerpLatLng(a, b, t2);
  return Polyline(
    points: [p1, p2],
    strokeWidth: 3,
    color: color.withValues(alpha: 0.92),
  );
}

LatLng _lerpLatLng(LatLng a, LatLng b, double t) {
  return LatLng(
    a.latitude + (b.latitude - a.latitude) * t,
    a.longitude + (b.longitude - a.longitude) * t,
  );
}

_LinkLevel _linkLevel(MapLink link) {
  final downByInterface =
      _isInterfaceDown(link.statusA) || _isInterfaceDown(link.statusB);
  if (downByInterface) return _LinkLevel.down;

  final attenuation = _effectiveAttenuation(link);
  if (attenuation != null && attenuation <= -40) {
    return _LinkLevel.down;
  }
  if (attenuation != null && attenuation >= -25 && attenuation <= -18) {
    return _LinkLevel.warning;
  }
  return _LinkLevel.up;
}

Color _colorForLevel(_LinkLevel level) {
  return switch (level) {
    _LinkLevel.down => const Color(0xFFDC2626),
    _LinkLevel.warning => const Color(0xFFF59E0B),
    _LinkLevel.up => const Color(0xFF16A34A),
  };
}

bool _isInterfaceDown(int? status) => status != null && status != 1;

bool _statusIsUp(String status) {
  final value = status.trim().toLowerCase();
  return value == 'ok' || value == 'up' || value == 'online';
}

double? _effectiveAttenuation(MapLink link) {
  final rxA = link.rxA;
  final rxB = link.rxB;
  if (rxA != null && rxB != null) {
    return (rxA + rxB) / 2;
  }
  return rxA ?? rxB ?? link.attenuationDb;
}

LatLng _mapCenter(List<MapNode> nodes) {
  if (nodes.isEmpty) {
    return const LatLng(-6.748973663434672, 110.97523378333311);
  }

  var minLat = nodes.first.lat;
  var maxLat = nodes.first.lat;
  var minLng = nodes.first.lng;
  var maxLng = nodes.first.lng;

  for (final n in nodes.skip(1)) {
    if (n.lat < minLat) minLat = n.lat;
    if (n.lat > maxLat) maxLat = n.lat;
    if (n.lng < minLng) minLng = n.lng;
    if (n.lng > maxLng) maxLng = n.lng;
  }

  return LatLng((minLat + maxLat) / 2, (minLng + maxLng) / 2);
}

double _mapZoom(List<MapNode> nodes) {
  if (nodes.isEmpty) return 10;

  var minLat = nodes.first.lat;
  var maxLat = nodes.first.lat;
  var minLng = nodes.first.lng;
  var maxLng = nodes.first.lng;

  for (final n in nodes.skip(1)) {
    if (n.lat < minLat) minLat = n.lat;
    if (n.lat > maxLat) maxLat = n.lat;
    if (n.lng < minLng) minLng = n.lng;
    if (n.lng > maxLng) maxLng = n.lng;
  }

  final latSpan = (maxLat - minLat).abs();
  final lngSpan = (maxLng - minLng).abs();
  final span = latSpan > lngSpan ? latSpan : lngSpan;

  if (span < 0.005) return 14;
  if (span < 0.02) return 13;
  if (span < 0.05) return 12;
  if (span < 0.2) return 11;
  if (span < 0.6) return 10;
  return 9;
}

IconData _iconForType(String type) {
  return switch (type.toLowerCase()) {
    'switch' => Icons.hub_rounded,
    'firewall' => Icons.security_rounded,
    'ap' => Icons.wifi_rounded,
    'server' => Icons.dns_rounded,
    'cloud' => Icons.cloud_rounded,
    _ => Icons.router_rounded,
  };
}
