import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/monitoring/monitoring_models.dart';
import '../../features/monitoring/monitoring_service.dart';

class MonitoringScreen extends StatefulWidget {
  const MonitoringScreen({super.key});

  @override
  State<MonitoringScreen> createState() => _MonitoringScreenState();
}

class _MonitoringScreenState extends State<MonitoringScreen> {
  bool _loadingDevices = true;
  String? _error;
  List<MonitoringDevice> _devices = const [];
  MonitoringDevice? _device;

  bool _loadingIfs = false;
  List<MonitoringInterface> _ifs = const [];
  MonitoringInterface? _selectedIf;

  bool _loadingChart = false;
  List<ChartPoint> _chart = const [];
  String _range = '1h';

  MonitoringService get _svc =>
      MonitoringService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _loadDevices();
  }

  Future<void> _loadDevices() async {
    setState(() {
      _loadingDevices = true;
      _error = null;
    });
    try {
      final devs = await _svc.devices();
      setState(() {
        _devices = devs;
        _device = devs.isNotEmpty ? devs.first : null;
      });
      if (_device != null) await _loadInterfaces();
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingDevices = false);
    }
  }

  Future<void> _loadInterfaces() async {
    final d = _device;
    if (d == null) return;

    setState(() {
      _loadingIfs = true;
      _error = null;
      _ifs = const [];
      _selectedIf = null;
      _chart = const [];
    });

    try {
      final ifs = await _svc.interfaces(d.id);
      setState(() {
        _ifs = ifs;
        _selectedIf = ifs.isNotEmpty ? ifs.first : null;
      });
      if (_selectedIf != null) await _loadChart();
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingIfs = false);
    }
  }

  Future<void> _loadChart() async {
    final d = _device;
    final i = _selectedIf;
    if (d == null || i == null) return;

    setState(() {
      _loadingChart = true;
      _error = null;
      _chart = const [];
    });

    try {
      final pts = await _svc.chart(
        deviceId: d.id,
        ifIndex: i.ifIndex,
        range: _range,
      );
      setState(() => _chart = pts);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingChart = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Monitoring'),
        actions: [
          IconButton(
            onPressed: _loadingDevices ? null : _loadDevices,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        children: [
          _SummaryCard(device: _device, iface: _selectedIf, chart: _chart),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Device',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (_loadingDevices)
                    const LinearProgressIndicator(minHeight: 2),
                  DropdownButtonFormField<int>(
                    value: _device?.id,
                    items: _devices
                        .map(
                          (d) => DropdownMenuItem(
                            value: d.id,
                            child: Text(d.name),
                          ),
                        )
                        .toList(),
                    onChanged: _loadingDevices
                        ? null
                        : (id) async {
                            final d = _devices.firstWhere(
                              (x) => x.id == id,
                              orElse: () => _devices.first,
                            );
                            setState(() => _device = d);
                            await _loadInterfaces();
                          },
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.devices_other_outlined),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_error != null)
            Card(
              child: ListTile(
                leading: const Icon(Icons.error_outline),
                title: const Text('Error'),
                subtitle: Text(_error!),
              ),
            ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Interface (SFP)',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (_loadingIfs) const LinearProgressIndicator(minHeight: 2),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    value: _selectedIf?.ifIndex,
                    items: _ifs
                        .map(
                          (i) => DropdownMenuItem(
                            value: i.ifIndex,
                            child: Text(
                              '${i.ifName}  ${i.ifAlias ?? ''}'.trim(),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    selectedItemBuilder: (context) {
                      return _ifs.map((i) {
                        return Text(
                          '${i.ifName}  ${i.ifAlias ?? ''}'.trim(),
                          overflow: TextOverflow.ellipsis,
                        );
                      }).toList();
                    },
                    onChanged: _loadingIfs
                        ? null
                        : (idx) async {
                            final it = _ifs.firstWhere(
                              (x) => x.ifIndex == idx,
                              orElse: () => _ifs.first,
                            );
                            setState(() => _selectedIf = it);
                            await _loadChart();
                          },
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.cable_outlined),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    children: [
                      _RangeChip(
                        label: '1h',
                        value: '1h',
                        selected: _range == '1h',
                        onTap: _setRange,
                      ),
                      _RangeChip(
                        label: '1d',
                        value: '1d',
                        selected: _range == '1d',
                        onTap: _setRange,
                      ),
                      _RangeChip(
                        label: '7d',
                        value: '7d',
                        selected: _range == '7d',
                        onTap: _setRange,
                      ),
                      _RangeChip(
                        label: '30d',
                        value: '30d',
                        selected: _range == '30d',
                        onTap: _setRange,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: SizedBox(
                height: 220,
                child: _loadingChart
                    ? const Center(
                        child: SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : _chart.isEmpty
                    ? const Center(child: Text('No data'))
                    : LineChart(_chartData(context)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _setRange(String v) async {
    setState(() => _range = v);
    await _loadChart();
  }

  LineChartData _chartData(BuildContext context) {
    final rxSpots = <FlSpot>[];
    final labels = <String>[];
    for (var i = 0; i < _chart.length; i++) {
      final p = _chart[i];
      if (p.rxPower != null) rxSpots.add(FlSpot(i.toDouble(), p.rxPower!));
      labels.add(_formatTimeLabel(p.createdAt, i, _range));
    }

    final values = <double>[...rxSpots.map((e) => e.y)];
    final minY = values.isEmpty
        ? null
        : (values.reduce((a, b) => a < b ? a : b) - 1.5);
    final maxY = values.isEmpty
        ? null
        : (values.reduce((a, b) => a > b ? a : b) + 1.5);

    return LineChartData(
      gridData: const FlGridData(show: true),
      minY: minY,
      maxY: maxY,
      titlesData: FlTitlesData(
        show: true,
        leftTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 36,
            interval: 5,
            getTitlesWidget: (value, meta) {
              return Text(
                value.toStringAsFixed(0),
                style: const TextStyle(fontSize: 10),
              );
            },
          ),
        ),
        bottomTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: _range == '7d' ? 44 : 32,
            interval: _labelInterval(labels.length, _range),
            getTitlesWidget: (value, meta) {
              final idx = value.round();
              if (idx < 0 || idx >= labels.length)
                return const SizedBox.shrink();
              final label = labels[idx];
              final tilt =
                  _range == '7d' || _range == '30d' || label.length > 5;
              return Padding(
                padding: const EdgeInsets.only(top: 6),
                child: tilt
                    ? Transform.rotate(
                        angle: -0.55,
                        child: Text(
                          label,
                          style: const TextStyle(fontSize: 10),
                        ),
                      )
                    : Text(label, style: const TextStyle(fontSize: 10)),
              );
            },
          ),
        ),
        rightTitles: const AxisTitles(
          sideTitles: SideTitles(showTitles: false),
        ),
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
      ),
      borderData: FlBorderData(show: false),
      lineBarsData: [
        LineChartBarData(
          isCurved: true,
          spots: rxSpots,
          color: Colors.redAccent,
          barWidth: 2,
          dotData: const FlDotData(show: false),
        ),
      ],
    );
  }
}

String _formatTimeLabel(String raw, int fallback, String range) {
  final normalized = raw.replaceFirst(' ', 'T');
  final parsed = DateTime.tryParse(normalized);
  if (parsed == null) return '#$fallback';
  final hh = parsed.hour.toString().padLeft(2, '0');
  final mm = parsed.minute.toString().padLeft(2, '0');
  final dd = parsed.day.toString().padLeft(2, '0');
  final mo = parsed.month.toString().padLeft(2, '0');
  final dow = _weekdayShort(parsed.weekday);

  if (range == '7d') {
    return '$dow $dd/$mo $hh:$mm';
  }
  if (range == '30d') {
    return '$dd/$mo';
  }
  return '$hh:$mm';
}

String _weekdayShort(int weekday) {
  return switch (weekday) {
    DateTime.monday => 'Sen',
    DateTime.tuesday => 'Sel',
    DateTime.wednesday => 'Rab',
    DateTime.thursday => 'Kam',
    DateTime.friday => 'Jum',
    DateTime.saturday => 'Sab',
    DateTime.sunday => 'Min',
    _ => '',
  };
}

double _labelInterval(int len, String range) {
  if (len <= 6) return 1;
  if (range == '7d') return (len / 4).floorToDouble().clamp(1, len.toDouble());
  if (range == '30d') return (len / 5).floorToDouble().clamp(1, len.toDouble());
  return (len / 3).floorToDouble().clamp(1, len.toDouble());
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.device,
    required this.iface,
    required this.chart,
  });

  final MonitoringDevice? device;
  final MonitoringInterface? iface;
  final List<ChartPoint> chart;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final last = chart.isNotEmpty ? chart.last : null;
    final rx = iface?.rxPower ?? last?.rxPower;
    final tx = iface?.txPower ?? last?.txPower;
    final loss = (rx != null && tx != null) ? (tx - rx) : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          colors: [scheme.primary, scheme.secondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            device?.name ?? 'Pilih device',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            iface != null
                ? '${iface!.ifName} ${iface!.ifAlias ?? ''}'.trim()
                : '-',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.white.withOpacity(0.85),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _MetricChip(label: 'RX', value: rx, unit: 'dBm'),
              const SizedBox(width: 8),
              _MetricChip(label: 'TX', value: tx, unit: 'dBm'),
              const SizedBox(width: 8),
              _MetricChip(label: 'Loss', value: loss, unit: 'dB'),
            ],
          ),
        ],
      ),
    );
  }
}

class _MetricChip extends StatelessWidget {
  const _MetricChip({
    required this.label,
    required this.value,
    required this.unit,
  });

  final String label;
  final double? value;
  final String unit;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.2),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        value != null
            ? '$label ${value!.toStringAsFixed(2)} $unit'
            : '$label -',
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _RangeChip extends StatelessWidget {
  const _RangeChip({
    required this.label,
    required this.value,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final String value;
  final bool selected;
  final Future<void> Function(String) onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final selectedColor = scheme.primary.withValues(alpha: 0.16);
    final unselectedColor = Colors.white;
    final selectedText = scheme.primary;
    final unselectedText = scheme.onSurface.withValues(alpha: 0.86);

    return ChoiceChip(
      label: Text(label),
      selected: selected,
      labelStyle: Theme.of(context).textTheme.labelLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: selected ? selectedText : unselectedText,
      ),
      selectedColor: selectedColor,
      backgroundColor: unselectedColor,
      side: BorderSide(
        color: selected
            ? scheme.primary.withValues(alpha: 0.36)
            : scheme.onSurface.withValues(alpha: 0.12),
      ),
      showCheckmark: false,
      onSelected: (_) => onTap(value),
    );
  }
}
