import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../location/location_service.dart';

class LocationScreen extends StatefulWidget {
  const LocationScreen({super.key});

  @override
  State<LocationScreen> createState() => _LocationScreenState();
}

class _LocationScreenState extends State<LocationScreen> {
  bool _busy = false;
  String? _status;

  Future<void> _sendNow() async {
    setState(() {
      _busy = true;
      _status = null;
    });

    final api = ApiClient(SessionStore.instance);
    final loc = LocationService(api);

    try {
      await loc.sendCurrentLocation();
      setState(() => _status = 'Lokasi terkirim.');
    } on ApiException catch (e) {
      setState(() => _status = 'Gagal: ${e.message}');
    } catch (e) {
      setState(() => _status = 'Gagal: $e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lokasi'),
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
                    'Kirim lokasi sekarang',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'App akan meminta izin lokasi, lalu kirim koordinat ke server.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: scheme.onSurface.withOpacity(0.7),
                        ),
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: FilledButton.icon(
                      onPressed: _busy ? null : _sendNow,
                      icon: _busy
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.send),
                      label: const Text('Send'),
                    ),
                  ),
                  if (_status != null) ...[
                    const SizedBox(height: 12),
                    Text(_status!),
                  ],
                ],
              ),
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.security_outlined),
              title: const Text('Catatan'),
              subtitle: const Text(
                'Tracking background butuh izin khusus dan alasan Play Store. '
                'Versi ini fokus tracking foreground dulu.',
              ),
            ),
          ),
        ],
      ),
    );
  }
}
