import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/interfaces/interfaces_models.dart';
import '../../features/interfaces/interfaces_service.dart';
import 'interface_traffic_screen.dart';

class InterfacesScreen extends StatefulWidget {
  const InterfacesScreen({super.key});

  @override
  State<InterfacesScreen> createState() => _InterfacesScreenState();
}

class _InterfacesScreenState extends State<InterfacesScreen> {
  static const _perPage = 25;

  final _scrollCtrl = ScrollController();

  bool _loading = false;
  bool _loadingMore = false;
  bool _loadingDevices = false;
  bool _hasMore = true;
  String? _error;
  String? _deviceError;
  int _page = 1;
  int _total = 0;
  int _selectedDeviceId = 0;
  final List<InterfaceDevice> _devices = [];
  final List<InterfaceRow> _rows = [];

  InterfacesService get _svc =>
      InterfacesService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _scrollCtrl.addListener(_onScroll);
    _loadDevices();
    _load(reset: true);
  }

  @override
  void dispose() {
    _scrollCtrl.removeListener(_onScroll);
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_loadingMore || _loading || !_hasMore) return;
    if (_scrollCtrl.position.pixels >=
        _scrollCtrl.position.maxScrollExtent - 200) {
      _loadMore();
    }
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
        _hasMore = true;
        _rows.clear();
      });
    } else {
      setState(() => _loading = true);
    }

    try {
      final res = await _svc.list(
        page: 1,
        perPage: _perPage,
        deviceId: _selectedDeviceId,
      );
      setState(() {
        _rows
          ..clear()
          ..addAll(res.data);
        _total = res.meta.total;
        _page = res.meta.page;
        _hasMore = res.meta.page < res.meta.lastPage;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);

    try {
      final next = _page + 1;
      final res = await _svc.list(
        page: next,
        perPage: _perPage,
        deviceId: _selectedDeviceId,
      );
      setState(() {
        _rows.addAll(res.data);
        _page = res.meta.page;
        _total = res.meta.total;
        _hasMore = res.meta.page < res.meta.lastPage;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _loadDevices() async {
    setState(() {
      _loadingDevices = true;
      _deviceError = null;
    });

    try {
      final devices = await _svc.devices();
      if (!mounted) return;
      setState(() {
        _devices
          ..clear()
          ..addAll(devices);
        if (_selectedDeviceId > 0 &&
            !_devices.any((device) => device.id == _selectedDeviceId)) {
          _selectedDeviceId = 0;
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _deviceError = e.toString());
    } finally {
      if (mounted) setState(() => _loadingDevices = false);
    }
  }

  Future<void> _refresh() async {
    await _loadDevices();
    await _load(reset: true);
  }

  void _setDevice(int? id) {
    final next = id ?? 0;
    if (next == _selectedDeviceId) return;
    setState(() => _selectedDeviceId = next);
    _load(reset: true);
  }

  void _openDetail(InterfaceRow r) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => InterfaceTrafficScreen(
          deviceId: r.deviceId,
          ifIndex: r.ifIndex,
          initialIfName: r.ifName ?? 'if${r.ifIndex}',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Interfaces'),
        actions: [
          IconButton(
            onPressed: (_loading || _loadingDevices) ? null : () => _refresh(),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          _FilterBar(
            devices: _devices,
            selectedDeviceId: _selectedDeviceId,
            loadingDevices: _loadingDevices,
            deviceError: _deviceError,
            onDeviceChanged: _setDevice,
            total: _total,
          ),
          Expanded(
            child: RefreshIndicator(onRefresh: _refresh, child: _buildBody()),
          ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading && _rows.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 80),
          Center(
            child: Column(
              children: const [
                CircularProgressIndicator(),
                SizedBox(height: 10),
                Text('Loading interfaces...'),
              ],
            ),
          ),
        ],
      );
    }

    if (_error != null && _rows.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 80),
          Center(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  const Icon(
                    Icons.error_outline,
                    size: 40,
                    color: Colors.redAccent,
                  ),
                  const SizedBox(height: 8),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => _load(reset: true),
                    child: const Text('Coba lagi'),
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    }

    if (_rows.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 80),
          Center(
            child: Padding(
              padding: EdgeInsets.all(20),
              child: Text(
                'Tidak ada interface ditemukan',
                style: TextStyle(color: Color(0xFF94A3B8)),
              ),
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      controller: _scrollCtrl,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
      itemCount: _rows.length + (_hasMore ? 1 : 0),
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, idx) {
        if (idx >= _rows.length) {
          return Padding(
            padding: const EdgeInsets.all(16),
            child: Center(
              child: _loadingMore
                  ? const CircularProgressIndicator(strokeWidth: 2)
                  : const SizedBox.shrink(),
            ),
          );
        }
        return _InterfaceCard(
          row: _rows[idx],
          onTap: () => _openDetail(_rows[idx]),
        );
      },
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.devices,
    required this.selectedDeviceId,
    required this.loadingDevices,
    required this.deviceError,
    required this.onDeviceChanged,
    required this.total,
  });

  final List<InterfaceDevice> devices;
  final int selectedDeviceId;
  final bool loadingDevices;
  final String? deviceError;
  final ValueChanged<int?> onDeviceChanged;
  final int total;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      elevation: 0,
      color: scheme.surface,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<int>(
              key: ValueKey(selectedDeviceId),
              initialValue: selectedDeviceId,
              isExpanded: true,
              menuMaxHeight: 320,
              icon: loadingDevices
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.keyboard_arrow_down_rounded),
              items: [
                const DropdownMenuItem<int>(
                  value: 0,
                  child: Text('Semua device'),
                ),
                ...devices.map(
                  (device) => DropdownMenuItem<int>(
                    value: device.id,
                    child: Text(
                      device.name.isNotEmpty
                          ? device.name
                          : 'Device ${device.id}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
              ],
              onChanged: loadingDevices ? null : onDeviceChanged,
              decoration: InputDecoration(
                labelText: 'Filter device',
                prefixIcon: const Icon(Icons.devices_other_outlined),
                isDense: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
            if (deviceError != null) ...[
              const SizedBox(height: 6),
              Text(
                'Filter device gagal dimuat',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: const Color(0xFFDC2626),
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.centerRight,
              child: Text(
                '$total interfaces',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: const Color(0xFF64748B),
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InterfaceCard extends StatelessWidget {
  const _InterfaceCard({required this.row, required this.onTap});

  final InterfaceRow row;
  final VoidCallback onTap;

  String _formatBps(int? bps, {int decimals = 1}) {
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

  Color _rxColor(double? rx) {
    if (rx == null) return const Color(0xFF94A3B8);
    if (rx <= -40) return const Color(0xFFDC2626);
    if (rx < -25) return const Color(0xFFEA580C);
    if (rx < -18) return const Color(0xFFCA8A04);
    return const Color(0xFF16A34A);
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = row.isUp
        ? const Color(0xFF16A34A)
        : const Color(0xFFDC2626);
    final statusLabel = row.isUp ? 'UP' : 'DOWN';
    final desc = row.description;

    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // header: if name + status pill
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        row.ifName ?? 'if${row.ifIndex}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          color: Color(0xFF1E293B),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        row.deviceName ?? 'Device ${row.deviceId}',
                        style: const TextStyle(
                          color: Color(0xFF64748B),
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    border: Border.all(
                      color: statusColor.withValues(alpha: 0.35),
                    ),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      color: statusColor,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                const Icon(
                  Icons.chevron_right,
                  size: 18,
                  color: Color(0xFF94A3B8),
                ),
              ],
            ),

            if (desc.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                desc,
                style: const TextStyle(
                  color: Color(0xFF475569),
                  fontSize: 11.5,
                  fontStyle: FontStyle.italic,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],

            const SizedBox(height: 10),

            // grid: RX / TX / Speed
            Row(
              children: [
                _metricBox(
                  label: 'RX',
                  value: row.rxPower != null
                      ? '${row.rxPower!.toStringAsFixed(2)} dBm'
                      : '—',
                  color: _rxColor(row.rxPower),
                ),
                const SizedBox(width: 6),
                _metricBox(
                  label: 'TX',
                  value: row.txPower != null
                      ? '${row.txPower!.toStringAsFixed(2)} dBm'
                      : '—',
                  color: _rxColor(row.txPower),
                ),
                const SizedBox(width: 6),
                _metricBox(
                  label: 'Speed',
                  value: _formatBps(row.ifSpeed, decimals: 0),
                  color: const Color(0xFF334155),
                ),
              ],
            ),

            const SizedBox(height: 8),

            // traffic in/out
            Row(
              children: [
                Expanded(
                  child: _trafficBox(
                    icon: Icons.arrow_downward,
                    label: 'In',
                    value: _formatBps(row.inRateBps),
                    color: const Color(0xFF16A34A),
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: _trafficBox(
                    icon: Icons.arrow_upward,
                    label: 'Out',
                    value: _formatBps(row.outRateBps),
                    color: const Color(0xFF2563EB),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _metricBox({
    required String label,
    required String value,
    required Color color,
  }) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w700,
                color: Color(0xFF94A3B8),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              value,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: color,
                fontFamily: 'monospace',
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }

  Widget _trafficBox({
    required IconData icon,
    required String label,
    required String value,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.18)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: color.withValues(alpha: 0.85),
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w800,
              color: color,
              fontFamily: 'monospace',
            ),
          ),
        ],
      ),
    );
  }
}
