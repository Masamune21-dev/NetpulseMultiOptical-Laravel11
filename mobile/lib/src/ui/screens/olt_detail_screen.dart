import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/olt/olt_models.dart';
import '../../features/olt/olt_service.dart';

class OltDetailScreen extends StatefulWidget {
  const OltDetailScreen({super.key, required this.olt});

  final OltSummary olt;

  @override
  State<OltDetailScreen> createState() => _OltDetailScreenState();
}

class _OltDetailScreenState extends State<OltDetailScreen> {
  String? _pon;
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _data;
  String? _lastUpdate;

  List<Map<String, dynamic>> get _onuList {
    final data = _data;
    if (data == null) return const [];
    final list = data['onu'];
    if (list is List) {
      return list.whereType<Map>().map((e) => e.cast<String, dynamic>()).toList();
    }
    return const [];
  }

  @override
  void initState() {
    super.initState();
    if (widget.olt.pons.isNotEmpty) {
      _pon = widget.olt.pons.first;
      _load();
    }
  }

  Future<void> _load() async {
    final pon = _pon;
    if (pon == null || pon.isEmpty) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    final api = ApiClient(SessionStore.instance);
    final svc = OltService(api);

    try {
      final res = await svc.fetchOltData(oltId: widget.olt.id, pon: pon);
      setState(() {
        _data = res.data;
        _lastUpdate = res.lastUpdate;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.olt.name),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'PON',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: _pon,
                    items: widget.olt.pons
                        .map((p) =>
                            DropdownMenuItem(value: p, child: Text(p)))
                        .toList(),
                    onChanged: (v) {
                      setState(() => _pon = v);
                      _load();
                    },
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.hub_outlined),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    _lastUpdate != null ? 'Last update: $_lastUpdate' : '',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Center(
                child: SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            ),
          if (_error != null)
            Card(
              child: ListTile(
                leading: const Icon(Icons.error_outline),
                title: const Text('Gagal load data'),
                subtitle: Text(_error!),
              ),
            ),
          if (_data != null) ...[
            Card(
              child: ListTile(
                leading: const Icon(Icons.list_alt_outlined),
                title: const Text('Ringkasan'),
                subtitle: Text(
                  'Total ONU: ${_data!['total'] ?? _onuList.length}'
                  '${_lastUpdate != null ? '  |  Last update: $_lastUpdate' : ''}',
                ),
              ),
            ),
            if (_onuList.isNotEmpty)
              ..._onuList.map((onu) => _OnuTile(onu: onu))
            else
              const Card(
                child: ListTile(
                  leading: Icon(Icons.info_outline),
                  title: Text('Data ONU belum tersedia'),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _OnuTile extends StatelessWidget {
  const _OnuTile({required this.onu});

  final Map<String, dynamic> onu;

  @override
  Widget build(BuildContext context) {
    final status = (onu['status'] ?? '-').toString();
    final isUp = status.toLowerCase() == 'up';
    final signal = (onu['signal'] ?? '-').toString().toLowerCase();

    Color signalColor() {
      return switch (signal) {
        'good' => Colors.green,
        'warning' => Colors.orange,
        'critical' => Colors.redAccent,
        'offline' => Colors.blueGrey,
        _ => Colors.grey,
      };
    }

    final title = [
      onu['onu_id']?.toString() ?? '-',
      if ((onu['name'] ?? '').toString().isNotEmpty) onu['name'].toString(),
    ].join(' • ');

    final meta = [
      if ((onu['mac'] ?? '').toString().isNotEmpty) 'MAC ${onu['mac']}',
      'RX ${onu['rx_power'] ?? '-'} dBm',
      'TX ${onu['tx_power'] ?? '-'} dBm',
      if (onu['temperature'] != null) 'Temp ${onu['temperature']}°C',
      if (onu['uptime'] != null) 'Uptime ${onu['uptime']}',
    ].join('  |  ');

    return Card(
      child: ListTile(
        leading: Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isUp ? Colors.green : Colors.redAccent,
          ),
        ),
        title: Text(title),
        subtitle: Text(meta),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: signalColor().withOpacity(0.12),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            signal.isNotEmpty ? signal.toUpperCase() : '-',
            style: TextStyle(
              color: signalColor(),
              fontWeight: FontWeight.w700,
              fontSize: 11,
            ),
          ),
        ),
      ),
    );
  }
}
