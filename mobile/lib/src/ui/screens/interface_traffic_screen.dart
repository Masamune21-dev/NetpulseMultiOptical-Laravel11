import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/interfaces/interfaces_models.dart';
import '../../features/interfaces/interfaces_service.dart';

class InterfaceTrafficScreen extends StatefulWidget {
  const InterfaceTrafficScreen({
    super.key,
    required this.deviceId,
    required this.ifIndex,
    required this.initialIfName,
  });

  final int deviceId;
  final int ifIndex;
  final String initialIfName;

  @override
  State<InterfaceTrafficScreen> createState() => _InterfaceTrafficScreenState();
}

class _InterfaceTrafficScreenState extends State<InterfaceTrafficScreen> {
  String _range = '1d';
  bool _loading = false;
  String? _error;
  TrafficHistoryResult? _result;

  InterfacesService get _svc =>
      InterfacesService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await _svc.trafficHistory(
        deviceId: widget.deviceId,
        ifIndex: widget.ifIndex,
        range: _range,
      );
      setState(() => _result = res);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _setRange(String r) {
    if (r == _range) return;
    setState(() => _range = r);
    _load();
  }

  String _formatBps(int? bps, {int decimals = 2}) {
    if (bps == null || bps < 0) return '—';
    if (bps == 0) return '0 bps';
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    var v = bps.toDouble();
    var i = 0;
    while (v >= 1000 && i < units.length - 1) {
      v /= 1000;
      i++;
    }
    return '${v.toStringAsFixed(decimals)} ${units[i]}';
  }

  @override
  Widget build(BuildContext context) {
    final meta = _result?.meta;
    final title = meta?.ifName ?? widget.initialIfName;

    return Scaffold(
      appBar: AppBar(
        title: Text(title, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 24),
          children: [
            _headerCard(meta),
            const SizedBox(height: 12),
            _rangeBar(),
            const SizedBox(height: 12),
            _chartCard(),
            const SizedBox(height: 12),
            _summaryCard(),
          ],
        ),
      ),
    );
  }

  Widget _headerCard(InterfaceMeta? meta) {
    final isUp = meta?.isUp ?? false;
    final statusColor = isUp ? const Color(0xFF16A34A) : const Color(0xFFDC2626);
    final speed = meta?.ifSpeed != null ? _formatBps(meta!.ifSpeed, decimals: 0) : '—';
    final alias = meta?.ifAlias ?? '';
    final device = meta?.deviceName ?? '';
    final ip = meta?.deviceIp ?? '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Row(
                  children: [
                    const Icon(Icons.circle_outlined, size: 16, color: Color(0xFF6366F1)),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        meta?.ifName ?? widget.initialIfName,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF1E293B),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Text(
                        speed,
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF475569),
                          fontFamily: 'monospace',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.14),
                  border: Border.all(color: statusColor.withValues(alpha: 0.35)),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  isUp ? 'UP' : 'DOWN',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    color: statusColor,
                  ),
                ),
              ),
            ],
          ),
          if (alias.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              alias,
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF64748B),
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
          if (device.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              ip.isNotEmpty ? '$device · $ip' : device,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: Color(0xFF94A3B8),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _rangeBar() {
    Widget chip(String label, String value) {
      final selected = _range == value;
      return Padding(
        padding: const EdgeInsets.only(right: 6),
        child: ChoiceChip(
          label: Text(label),
          selected: selected,
          onSelected: (_) => _setRange(value),
          labelStyle: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 12,
            color: selected ? Colors.white : const Color(0xFF475569),
          ),
          selectedColor: const Color(0xFF6366F1),
          side: BorderSide(
            color: selected ? const Color(0xFF6366F1) : const Color(0xFFE2E8F0),
          ),
          visualDensity: VisualDensity.compact,
        ),
      );
    }

    return Row(
      children: [
        chip('1d', '1d'),
        chip('7d', '7d'),
        chip('30d', '30d'),
      ],
    );
  }

  Widget _chartCard() {
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 12, 12, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: SizedBox(
        height: 260,
        child: _loading
            ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
            : _error != null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.redAccent),
                      ),
                    ),
                  )
                : (_result?.data.isEmpty ?? true)
                    ? const Center(child: Text('Belum ada data traffic'))
                    : LineChart(_chartData()),
      ),
    );
  }

  LineChartData _chartData() {
    final points = _result?.data ?? const [];
    final inSpots = <FlSpot>[];
    final outSpots = <FlSpot>[];

    for (var i = 0; i < points.length; i++) {
      final p = points[i];
      if (p.inRateBps != null) {
        inSpots.add(FlSpot(i.toDouble(), p.inRateBps! / 1000000));
      }
      if (p.outRateBps != null) {
        outSpots.add(FlSpot(i.toDouble(), p.outRateBps! / 1000000));
      }
    }

    final all = <double>[...inSpots.map((e) => e.y), ...outSpots.map((e) => e.y)];
    final maxY = all.isEmpty ? 1.0 : (all.reduce((a, b) => a > b ? a : b) * 1.15).clamp(1, double.infinity).toDouble();

    final len = points.length;
    final labelInterval = len <= 6
        ? 1.0
        : (len / 5).floorToDouble().clamp(1.0, len.toDouble()).toDouble();

    return LineChartData(
      minY: 0,
      maxY: maxY,
      gridData: FlGridData(
        show: true,
        drawVerticalLine: false,
        horizontalInterval: maxY / 4,
        getDrawingHorizontalLine: (_) => FlLine(
          color: const Color(0xFFE2E8F0),
          strokeWidth: 1,
        ),
      ),
      titlesData: FlTitlesData(
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        leftTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 46,
            interval: maxY / 4,
            getTitlesWidget: (v, _) => Text(
              _formatMbpsTick(v),
              style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
            ),
          ),
        ),
        bottomTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 28,
            interval: labelInterval,
            getTitlesWidget: (v, _) {
              final idx = v.toInt();
              if (idx < 0 || idx >= points.length) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  _formatLabel(points[idx].createdAt),
                  style: const TextStyle(fontSize: 9.5, color: Color(0xFF64748B)),
                ),
              );
            },
          ),
        ),
      ),
      borderData: FlBorderData(
        show: true,
        border: Border(
          left: BorderSide(color: Colors.grey.shade300),
          bottom: BorderSide(color: Colors.grey.shade300),
        ),
      ),
      lineBarsData: [
        LineChartBarData(
          spots: inSpots,
          color: const Color(0xFF16A34A),
          barWidth: 1.6,
          isCurved: true,
          curveSmoothness: 0.25,
          dotData: const FlDotData(show: false),
          belowBarData: BarAreaData(
            show: true,
            color: const Color(0xFF16A34A).withValues(alpha: 0.18),
          ),
        ),
        LineChartBarData(
          spots: outSpots,
          color: const Color(0xFF2563EB),
          barWidth: 1.6,
          isCurved: true,
          curveSmoothness: 0.25,
          dotData: const FlDotData(show: false),
          belowBarData: BarAreaData(
            show: true,
            color: const Color(0xFF2563EB).withValues(alpha: 0.18),
          ),
        ),
      ],
      lineTouchData: LineTouchData(
        touchTooltipData: LineTouchTooltipData(
          getTooltipColor: (_) => Colors.black87,
          getTooltipItems: (spots) => spots.map((s) {
            final color = s.bar.color ?? Colors.white;
            return LineTooltipItem(
              '${color == const Color(0xFF16A34A) ? 'In' : 'Out'}: ${_formatMbps(s.y)}',
              TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 11),
            );
          }).toList(),
        ),
      ),
    );
  }

  String _formatMbps(double v) {
    if (v >= 1000) return '${(v / 1000).toStringAsFixed(2)} Gbps';
    if (v >= 1) return '${v.toStringAsFixed(2)} Mbps';
    if (v >= 0.001) return '${(v * 1000).toStringAsFixed(0)} Kbps';
    return '0 bps';
  }

  String _formatMbpsTick(double v) {
    if (v >= 1000) return '${(v / 1000).toStringAsFixed(1)} G';
    if (v >= 1) return '${v.toStringAsFixed(0)} M';
    return v.toStringAsFixed(1);
  }

  String _formatLabel(String raw) {
    final p = DateTime.tryParse(raw.replaceFirst(' ', 'T'));
    if (p == null) return '';
    final hh = p.hour.toString().padLeft(2, '0');
    final mm = p.minute.toString().padLeft(2, '0');
    final dd = p.day.toString().padLeft(2, '0');
    final mo = p.month.toString().padLeft(2, '0');
    if (_range == '30d') return '$dd/$mo';
    if (_range == '7d') return '$dd/$mo $hh:$mm';
    return '$hh:$mm';
  }

  Widget _summaryCard() {
    final s = _result?.summary;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          _summaryRow(
            icon: Icons.arrow_downward,
            label: 'In',
            color: const Color(0xFF16A34A),
            cur: s?.inCur,
            avg: s?.inAvg,
            max: s?.inMax,
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: Divider(height: 1),
          ),
          _summaryRow(
            icon: Icons.arrow_upward,
            label: 'Out',
            color: const Color(0xFF2563EB),
            cur: s?.outCur,
            avg: s?.outAvg,
            max: s?.outMax,
          ),
        ],
      ),
    );
  }

  Widget _summaryRow({
    required IconData icon,
    required String label,
    required Color color,
    required int? cur,
    required int? avg,
    required int? max,
  }) {
    Widget pair(String key, int? v) {
      return Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              key,
              style: const TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: Color(0xFF94A3B8),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              _formatBps(v),
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
                color: color,
                fontFamily: 'monospace',
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      );
    }

    return Row(
      children: [
        SizedBox(
          width: 64,
          child: Row(
            children: [
              Icon(icon, size: 16, color: color),
              const SizedBox(width: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: color,
                ),
              ),
            ],
          ),
        ),
        pair('Cur', cur),
        pair('Avg', avg),
        pair('Max', max),
      ],
    );
  }
}
