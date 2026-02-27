import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/alerts/alert_models.dart';
import '../../features/alerts/alerts_service.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _loadingAlerts = true;
  String? _alertsError;
  List<AlertLogItem> _alertItems = const [];

  String _severity = 'all';
  String _type = 'all';
  final _alertQuery = TextEditingController();

  AlertsService get _alerts => AlertsService(ApiClient(SessionStore.instance));

  @override
  void initState() {
    super.initState();
    _loadAlerts();
  }

  @override
  void dispose() {
    _alertQuery.dispose();
    super.dispose();
  }

  Future<void> _loadAlerts() async {
    setState(() {
      _loadingAlerts = true;
      _alertsError = null;
    });
    try {
      final items = await _alerts.list(
        limit: 200,
        type: _type,
        severity: _severity,
        q: _alertQuery.text.trim(),
      );
      setState(() => _alertItems = items);
    } catch (e) {
      setState(() => _alertsError = e.toString());
    } finally {
      if (mounted) setState(() => _loadingAlerts = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Alert Log'),
      ),
      body: _alertTab(context),
    );
  }

  Widget _alertTab(BuildContext context) {
    return ListView(
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
                          _loadAlerts();
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
                          _loadAlerts();
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
                  controller: _alertQuery,
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.search),
                    hintText: 'Search message/device/interface',
                    suffixIcon: IconButton(
                      onPressed: _loadingAlerts ? null : _loadAlerts,
                      icon: const Icon(Icons.arrow_forward),
                    ),
                  ),
                  onSubmitted: (_) => _loadAlerts(),
                ),
              ],
            ),
          ),
        ),
        if (_loadingAlerts)
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
        if (_alertsError != null)
          Card(
            child: ListTile(
              leading: const Icon(Icons.error_outline),
              title: const Text('Error'),
              subtitle: Text(_alertsError!),
            ),
          ),
        ..._alertItems.map(_alertTile),
      ],
    );
  }

  Widget _alertTile(AlertLogItem a) {
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
