import 'dart:async';

import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/interfaces/interfaces_models.dart';
import '../../features/interfaces/interfaces_service.dart';
import '../../theme/status.dart';
import '../../theme/tokens.dart';
import '../widgets/async_state.dart';
import '../widgets/heartbeat_bar.dart';
import '../widgets/rx_meter.dart';
import '../widgets/screen_header.dart';
import '../widgets/section_card.dart';
import '../widgets/status_badge.dart';
import 'interface_traffic_screen.dart';

/// Daftar interface SFP: cari, chip perangkat, segmen status, urutan; tiap
/// baris membawa bar riwayat 24 jam, RX mono + meter, dan badge berkata.
class InterfacesScreen extends StatefulWidget {
  const InterfacesScreen({super.key});

  @override
  State<InterfacesScreen> createState() => _InterfacesScreenState();
}

class _InterfacesScreenState extends State<InterfacesScreen> {
  static const _perPage = 25;
  static const _statusOptions = [('all', 'Semua'), ('warn', 'Marjinal'), ('down', 'Down')];

  final _scrollCtrl = ScrollController();
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  bool _loading = false;
  bool _loadingMore = false;
  bool _loadingDevices = false;
  bool _hasMore = true;
  String? _error;
  int _page = 1;
  int _total = 0;
  int _selectedDeviceId = 0;
  String _status = 'all';
  String _sort = 'device';
  String _q = '';
  final List<InterfaceDevice> _devices = [];
  final List<InterfaceRow> _rows = [];
  RxThresholds _thresholds = RxThresholds.current;

  InterfacesService get _svc => InterfacesService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _scrollCtrl.addListener(_onScroll);
    _loadDevices();
    _load(reset: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _scrollCtrl.removeListener(_onScroll);
    _scrollCtrl.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_loadingMore || _loading || !_hasMore) return;
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) _loadMore();
  }

  Future<void> _load({bool reset = false}) async {
    setState(() {
      _loading = true;
      _error = null;
      if (reset) {
        _page = 1;
        _hasMore = true;
      }
    });
    try {
      final res = await _svc.list(page: 1, perPage: _perPage, deviceId: _selectedDeviceId, status: _status, q: _q, sort: _sort);
      if (!mounted) return;
      setState(() {
        _rows
          ..clear()
          ..addAll(res.data);
        _total = res.meta.total;
        _page = res.meta.page;
        _hasMore = res.meta.page < res.meta.lastPage;
        _thresholds = res.meta.thresholds;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final res = await _svc.list(page: _page + 1, perPage: _perPage, deviceId: _selectedDeviceId, status: _status, q: _q, sort: _sort);
      if (!mounted) return;
      setState(() {
        _rows.addAll(res.data);
        _page = res.meta.page;
        _total = res.meta.total;
        _hasMore = res.meta.page < res.meta.lastPage;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _loadDevices() async {
    setState(() => _loadingDevices = true);
    try {
      final devices = await _svc.devices();
      if (!mounted) return;
      setState(() {
        _devices
          ..clear()
          ..addAll(devices);
        if (_selectedDeviceId > 0 && !_devices.any((d) => d.id == _selectedDeviceId)) _selectedDeviceId = 0;
      });
    } catch (_) {
      // Chip perangkat tidak wajib; daftar tetap bisa dimuat tanpa filter.
    } finally {
      if (mounted) setState(() => _loadingDevices = false);
    }
  }

  Future<void> _refresh() async {
    await _loadDevices();
    await _load(reset: true);
  }

  void _setDevice(int id) {
    if (id == _selectedDeviceId) return;
    setState(() => _selectedDeviceId = id);
    _load(reset: true);
  }

  void _setStatus(String s) {
    if (s == _status) return;
    setState(() => _status = s);
    _load(reset: true);
  }

  void _toggleSort() {
    setState(() => _sort = _sort == 'rx' ? 'device' : 'rx');
    _load(reset: true);
  }

  void _onSearchChanged(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      final next = v.trim();
      if (next == _q) return;
      _q = next;
      _load(reset: true);
    });
  }

  void _openDetail(InterfaceRow r) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => InterfaceTrafficScreen(deviceId: r.deviceId, ifIndex: r.ifIndex, initialIfName: r.ifName ?? 'if${r.ifIndex}'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(NpSpace.lg, NpSpace.sm, NpSpace.lg, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                title: 'Interface',
                subtitle: '$_total port · ${_devices.length} perangkat',
                actions: [
                  HeaderIconButton(
                    icon: Icons.refresh_rounded,
                    tooltip: 'Muat ulang',
                    busy: _loading || _loadingDevices,
                    onTap: _refresh,
                  ),
                ],
              ),
              const SizedBox(height: NpSpace.md),
              TextField(
                controller: _searchCtrl,
                onChanged: _onSearchChanged,
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Cari perangkat, port, atau alias',
                  prefixIcon: const Icon(Icons.search_rounded, size: 20),
                  isDense: true,
                  suffixIcon: _searchCtrl.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.close_rounded, size: 18),
                          onPressed: () {
                            _searchCtrl.clear();
                            _onSearchChanged('');
                          },
                        ),
                ),
              ),
              const SizedBox(height: NpSpace.md),
              _DeviceChips(devices: _devices, selectedId: _selectedDeviceId, onSelect: _setDevice),
              const SizedBox(height: NpSpace.md),
              _Segmented(options: _statusOptions, value: _status, onChanged: _setStatus),
              const SizedBox(height: NpSpace.sm),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: _toggleSort,
                        child: Text.rich(
                          TextSpan(
                            text: 'Diurutkan: ',
                            children: [
                              TextSpan(
                                text: _sort == 'rx' ? 'RX terendah' : 'perangkat',
                                style: TextStyle(color: k.accent, fontWeight: FontWeight.w600),
                              ),
                              const TextSpan(text: ' ▾'),
                            ],
                          ),
                          style: t.bodySmall,
                        ),
                      ),
                    ),
                    Text('Riwayat 24 jam', style: t.bodySmall),
                  ],
                ),
              ),
              const SizedBox(height: NpSpace.sm),
              Expanded(child: _list()),
              const SizedBox(height: NpSpace.md),
            ],
          ),
        ),
      ),
    );
  }

  Widget _list() {
    if (_loading && _rows.isEmpty) return const AsyncState.loading(message: 'Memuat interface…');
    if (_error != null && _rows.isEmpty) return AsyncState.error(message: _error!, onRetry: () => _load(reset: true));
    if (_rows.isEmpty) {
      return AsyncState.empty(message: 'Tidak ada interface yang cocok.', icon: Icons.search_off_rounded, onRetry: _refresh);
    }

    return SectionCard(
      clip: true,
      child: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView.builder(
          controller: _scrollCtrl,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.zero,
          itemCount: _rows.length + (_hasMore ? 1 : 0),
          itemBuilder: (context, i) {
            if (i >= _rows.length) {
              return Padding(
                padding: const EdgeInsets.all(NpSpace.lg),
                child: Center(
                  child: _loadingMore
                      ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: context.np.accent))
                      : const SizedBox.shrink(),
                ),
              );
            }
            return _InterfaceRowTile(
              row: _rows[i],
              thresholds: _thresholds,
              first: i == 0,
              onTap: () => _openDetail(_rows[i]),
            );
          },
        ),
      ),
    );
  }
}

// ── Filter ───────────────────────────────────────────────────────────────────

class _DeviceChips extends StatelessWidget {
  const _DeviceChips({required this.devices, required this.selectedId, required this.onSelect});

  final List<InterfaceDevice> devices;
  final int selectedId;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 32,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: devices.length + 1,
        separatorBuilder: (_, _) => const SizedBox(width: NpSpace.sm),
        itemBuilder: (context, i) {
          final id = i == 0 ? 0 : devices[i - 1].id;
          final name = i == 0 ? 'Semua perangkat' : (devices[i - 1].name.isNotEmpty ? devices[i - 1].name : 'Perangkat $id');
          return _Chip(label: name, selected: id == selectedId, onTap: () => onSelect(id));
        },
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(NpRadius.pill),
        onTap: onTap,
        child: AnimatedContainer(
          duration: NpMotion.fast,
          height: 32,
          padding: const EdgeInsets.symmetric(horizontal: NpSpace.md),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? k.accent : k.surface,
            borderRadius: BorderRadius.circular(NpRadius.pill),
            border: Border.all(color: selected ? k.accent : k.border),
          ),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(color: selected ? k.onAccent : k.ink2),
          ),
        ),
      ),
    );
  }
}

class _Segmented extends StatelessWidget {
  const _Segmented({required this.options, required this.value, required this.onChanged});

  final List<(String, String)> options;
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: k.surface2,
        borderRadius: BorderRadius.circular(NpRadius.control),
        border: Border.all(color: k.border),
      ),
      child: Row(
        children: [
          for (final (key, label) in options)
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => onChanged(key),
                child: AnimatedContainer(
                  duration: NpMotion.fast,
                  height: 32,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: key == value ? k.surface : Colors.transparent,
                    borderRadius: BorderRadius.circular(9),
                    boxShadow: key == value ? k.shadow : null,
                  ),
                  child: Text(
                    label,
                    style: t.labelMedium?.copyWith(color: key == value ? k.ink : k.ink2),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// ── Baris ────────────────────────────────────────────────────────────────────

class _InterfaceRowTile extends StatelessWidget {
  const _InterfaceRowTile({required this.row, required this.thresholds, required this.first, required this.onTap});

  final InterfaceRow row;
  final RxThresholds thresholds;
  final bool first;
  final VoidCallback onTap;

  static String formatSpeed(int? bps) {
    if (bps == null || bps <= 0) return '—';
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    var v = bps.toDouble();
    var i = 0;
    while (v >= 1000 && i < units.length - 1) {
      v /= 1000;
      i++;
    }
    final s = v == v.roundToDouble() ? v.toStringAsFixed(0) : v.toStringAsFixed(1);
    return '$s ${units[i]}';
  }

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;
    final status = thresholds.statusOf(rx: row.rxPower, operStatus: row.operStatus);
    final alias = row.description.trim();

    return InkWell(
      onTap: onTap,
      child: Container(
        padding: NpSpace.row,
        decoration: BoxDecoration(
          border: first ? null : Border(top: BorderSide(color: k.border)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(row.deviceName ?? 'Perangkat ${row.deviceId}', style: t.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 2),
                      Text.rich(
                        TextSpan(
                          children: [
                            TextSpan(
                              text: row.ifName ?? 'if${row.ifIndex}',
                              style: NpText.mono(size: 11.5, weight: FontWeight.w500, color: k.ink2),
                            ),
                            TextSpan(text: alias.isNotEmpty ? ' · $alias' : ' · tanpa alias'),
                          ],
                        ),
                        style: t.bodySmall,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                StatusBadge(status),
              ],
            ),
            const SizedBox(height: NpSpace.sm),
            Row(
              children: [
                Expanded(child: HeartbeatBar(row.history24h)),
                const SizedBox(width: NpSpace.md),
                RxValue(rx: row.rxPower, operStatus: row.operStatus, thresholds: thresholds, showMeter: false),
                const SizedBox(width: NpSpace.sm),
                Text(formatSpeed(row.ifSpeed), style: t.bodySmall?.copyWith(color: k.ink3, fontSize: 11.5)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
