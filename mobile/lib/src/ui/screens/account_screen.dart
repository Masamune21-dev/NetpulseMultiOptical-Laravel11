import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/auth_service.dart';
import '../../auth/session_store.dart';
import '../../push/fcm_service.dart';
import '../gate.dart';
import 'about_screen.dart';
import 'settings_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool _busy = false;
  bool _loadingPref = false;
  bool _pushEnabled = true;
  String _severityMin = 'info';

  @override
  void initState() {
    super.initState();
    _loadPref();
  }

  Future<void> _loadPref() async {
    setState(() => _loadingPref = true);
    final api = ApiClient(SessionStore.instance);
    try {
      final json = await api.getJson('/api/v1/alert-preferences');
      final data = (json['data'] as Map?)?.cast<String, dynamic>() ?? const {};
      final incoming = (data['severity_min'] ?? 'info').toString();
      setState(() {
        _pushEnabled = data['push_enabled'] == true;
        _severityMin = 'info';
      });
      if (incoming != 'info') {
        await _savePref();
      }
    } catch (_) {
      // keep defaults
    } finally {
      if (mounted) setState(() => _loadingPref = false);
    }
  }

  Future<void> _savePref() async {
    final api = ApiClient(SessionStore.instance);
    await api.postJson('/api/v1/alert-preferences', body: {
      'push_enabled': _pushEnabled,
      'severity_min': _severityMin,
    });
  }

  Future<void> _testPush() async {
    setState(() => _busy = true);

    final session = SessionStore.instance;
    final api = ApiClient(session);

    // Ensure the latest token is registered (best-effort).
    await FcmService.instance.syncToken();

    try {
      await api.postJson('/api/v1/push/test', body: {
        'title': 'Netpulse Test',
        'body': 'Push test berhasil.',
      });

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Push test terkirim. Cek notifikasi.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal: $e')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _logout() async {
    setState(() => _busy = true);
    final session = SessionStore.instance;
    final api = ApiClient(session);
    final auth = AuthService(api, session);
    await auth.logout();

    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const Gate()),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = SessionStore.instance.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Akun'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        children: [
          Card(
            child: ListTile(
              leading: const CircleAvatar(child: Icon(Icons.person)),
              title: Text(user?.fullName.isNotEmpty == true ? user!.fullName : '-'),
              subtitle: Text('${user?.username ?? '-'}  |  ${user?.role ?? '-'}'),
            ),
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: SizedBox(
                width: double.infinity,
                height: 46,
                child: FilledButton.icon(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const SettingsScreen(),
                      ),
                    );
                  },
                  icon: const Icon(Icons.notifications_outlined),
                  label: const Text('Alert Log'),
                ),
              ),
            ),
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Alert Notifications',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 10),
                  if (_loadingPref) const LinearProgressIndicator(minHeight: 2),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Push alert'),
                    subtitle: const Text('Terima notifikasi ketika ada alert baru'),
                    value: _pushEnabled,
                    onChanged: _busy
                        ? null
                        : (v) async {
                            setState(() => _pushEnabled = v);
                            await _savePref();
                          },
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Semua tingkat alert (info, warning, critical) akan dikirim.',
                  ),
                ],
              ),
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.notifications_active_outlined),
              title: const Text('Test Push'),
              subtitle: const Text('Kirim notifikasi test ke device ini'),
              trailing: const Icon(Icons.chevron_right),
              onTap: _busy ? null : _testPush,
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.link),
              title: const Text('API Base URL'),
              subtitle: Text(SessionStore.instance.apiBaseUrl),
              trailing: const Icon(Icons.chevron_right),
              onTap: _busy
                  ? null
                  : () async {
                      final next = await _editBaseUrl(context);
                      if (next == null) return;
                      await SessionStore.instance.setApiBaseUrl(next);
                      if (mounted) setState(() {});
                    },
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.info_outline),
              title: const Text('About'),
              subtitle: const Text('Info aplikasi'),
              trailing: const Icon(Icons.chevron_right),
              onTap: _busy
                  ? null
                  : () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const AboutScreen()),
                      );
                    },
            ),
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: SizedBox(
                width: double.infinity,
                height: 48,
                child: FilledButton.tonalIcon(
                  onPressed: _busy ? null : _logout,
                  icon: _busy
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.logout),
                  label: const Text('Logout'),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<String?> _editBaseUrl(BuildContext context) async {
    final ctrl = TextEditingController(text: SessionStore.instance.apiBaseUrl);
    final res = await showDialog<String>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('API Base URL'),
          content: TextField(
            controller: ctrl,
            decoration: const InputDecoration(
              hintText: 'https://example.com',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(null),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(ctrl.text.trim()),
              child: const Text('Save'),
            ),
          ],
        );
      },
    );
    ctrl.dispose();
    return res;
  }
}
