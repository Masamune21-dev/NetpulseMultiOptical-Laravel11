import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import '../../theme/tokens.dart';

class AboutScreen extends StatefulWidget {
  const AboutScreen({super.key});

  @override
  State<AboutScreen> createState() => _AboutScreenState();
}

class _AboutScreenState extends State<AboutScreen> {
  String _version = '-';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final info = await PackageInfo.fromPlatform();
    if (!mounted) return;
    setState(() {
      _version = '${info.version}+${info.buildNumber}';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tentang')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        children: [
          Card(
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(NpRadius.card),
                color: context.np.accent,
              ),
              child: ListTile(
                leading: Icon(Icons.new_releases_rounded, color: context.np.onAccent),
                title: Text(
                  'Rilis 2.1',
                  style: TextStyle(
                    color: context.np.onAccent,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                subtitle: Text(
                  'Desain ulang: token seragam, riwayat 24 jam, ambang RX dari server',
                  style: TextStyle(color: context.np.onAccent),
                ),
              ),
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.apps),
              title: const Text('Netpulse Mobile'),
              subtitle: Text('Versi $_version'),
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.person_outline),
              title: const Text('Dibuat oleh'),
              subtitle: const Text('Masamune'),
            ),
          ),
        ],
      ),
    );
  }
}
