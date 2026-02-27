import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/alerts/alert_models.dart';
import '../../features/alerts/alerts_service.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  bool _loading = true;
  String? _error;
  List<AlertLogItem> _items = const [];

  String _severity = 'all';
  String _type = 'all';
  final _q = TextEditingController();

  AlertsService get _svc => AlertsService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _q.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await _svc.list(
        limit: 200,
        type: _type,
        severity: _severity,
        q: _q.text.trim(),
      );
      setState(() => _items = items);
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
        title: const Text('Alerts'),
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
                children: [
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final stacked = constraints.maxWidth < 360;
                      final fields = [
                        DropdownButtonFormField<String>(
                          isExpanded: true,
                          value: _severity,
                          items: const [
                            DropdownMenuItem(value: 'all', child: Text('All severity')),
                            DropdownMenuItem(value: 'info', child: Text('Info')),
                            DropdownMenuItem(value: 'warning', child: Text('Warning')),
                            DropdownMenuItem(value: 'critical', child: Text('Critical')),
                          ],
                          onChanged: (v) {
                            setState(() => _severity = v ?? 'all');
                            _load();
                          },
                          decoration: const InputDecoration(
                            prefixIcon: Icon(Icons.warning_amber_outlined),
                          ),
                        ),
                        DropdownButtonFormField<String>(
                          isExpanded: true,
                          value: _type,
                          items: const [
                            DropdownMenuItem(value: 'all', child: Text('All type')),
                            DropdownMenuItem(value: 'optical', child: Text('Optical')),
                            DropdownMenuItem(value: 'device', child: Text('Device')),
                            DropdownMenuItem(value: 'interface', child: Text('Interface')),
                          ],
                          onChanged: (v) {
                            setState(() => _type = v ?? 'all');
                            _load();
                          },
                          decoration: const InputDecoration(
                            prefixIcon: Icon(Icons.category_outlined),
                          ),
                        ),
                      ];

                      if (stacked) {
                        return Column(
                          children: [
                            fields[0],
                            const SizedBox(height: 12),
                            fields[1],
                          ],
                        );
                      }

                      return Row(
                        children: [
                          Expanded(child: fields[0]),
                          const SizedBox(width: 12),
                          Expanded(child: fields[1]),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _q,
                    decoration: InputDecoration(
                      prefixIcon: const Icon(Icons.search),
                      hintText: 'Search message/device/interface',
                      suffixIcon: IconButton(
                        onPressed: _loading
                            ? null
                            : () {
                                _load();
                              },
                        icon: const Icon(Icons.arrow_forward),
                      ),
                    ),
                    onSubmitted: (_) => _load(),
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
                title: const Text('Error'),
                subtitle: Text(_error!),
              ),
            ),
          ..._items.map(_tileFor),
        ],
      ),
    );
  }

  Widget _tileFor(AlertLogItem a) {
    final sev = a.severity.toLowerCase();
    final color = switch (sev) {
      'critical' => Colors.redAccent,
      'warning' => Colors.orange,
      _ => Colors.blueGrey,
    };

    final subtitleBits = <String>[
      if (a.deviceName != null && a.deviceName!.isNotEmpty) a.deviceName!,
      if (a.deviceIp != null && a.deviceIp!.isNotEmpty) a.deviceIp!,
      if (a.ifName != null && a.ifName!.isNotEmpty) a.ifName!,
    ];

    return Card(
      child: ListTile(
        leading: Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        title: Text(a.message),
        subtitle: Text('${a.createdAt}\n${subtitleBits.join(' | ')}'),
        isThreeLine: true,
      ),
    );
  }
}
