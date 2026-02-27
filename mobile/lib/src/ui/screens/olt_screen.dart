import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/olt/olt_models.dart';
import '../../features/olt/olt_service.dart';
import 'olt_detail_screen.dart';

class OltScreen extends StatefulWidget {
  const OltScreen({super.key});

  @override
  State<OltScreen> createState() => _OltScreenState();
}

class _OltScreenState extends State<OltScreen> {
  bool _loading = true;
  String? _error;
  List<OltSummary> _olts = const [];

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

    final api = ApiClient(SessionStore.instance);
    final svc = OltService(api);

    try {
      final olts = await svc.listOlts();
      setState(() => _olts = olts);
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
        title: const Text('OLT'),
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
          if (_error != null)
            Card(
              child: ListTile(
                leading: const Icon(Icons.error_outline),
                title: const Text('Gagal load data'),
                subtitle: Text(_error!),
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
          ..._olts.map(_oltTile),
        ],
      ),
    );
  }

  Widget _oltTile(OltSummary o) {
    final ponCount = o.ponCount ?? o.pons.length;
    return Card(
      child: ListTile(
        leading: const Icon(Icons.storage_outlined),
        title: Text(o.name.isNotEmpty ? o.name : o.id),
        subtitle: Text(
          '$ponCount PON'
          '${o.lastPoll != null ? '  |  ${o.lastPoll}' : ''}',
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => OltDetailScreen(olt: o),
            ),
          );
        },
      ),
    );
  }
}
