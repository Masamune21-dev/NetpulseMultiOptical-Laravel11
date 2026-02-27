import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/dashboard/dashboard_models.dart';
import '../../features/dashboard/dashboard_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  DashboardCounts? _counts;

  DashboardService get _svc =>
      DashboardService(ApiClient(SessionStore.instance));

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

    try {
      final data = await _svc.counts();
      setState(() => _counts = data);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = SessionStore.instance.user;
    final counts = _counts;
    final health = counts != null ? _healthFromCounts(counts) : null;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
          children: [
            _HeroPanel(
              name: user?.fullName.isNotEmpty == true
                  ? user!.fullName
                  : 'Operator',
              role: user?.role ?? '-',
              health: health,
            ),
            const SizedBox(height: 14),
            if (_error != null)
              Card(
                margin: EdgeInsets.zero,
                child: ListTile(
                  leading: const Icon(Icons.error_outline),
                  title: const Text('Gagal load data'),
                  subtitle: Text(_error!),
                ),
              ),
            if (_loading) const _DashboardSkeleton(),
            if (counts != null) ...[
              _QuickBand(counts: counts),
              const SizedBox(height: 14),
              _SectionTitle(
                title: 'Operations Snapshot',
                subtitle: 'Ringkasan kapasitas dan perangkat inti',
              ),
              const SizedBox(height: 10),
              _StatGrid(
                items: [
                  _StatItem(
                    'Total OLT',
                    counts.oltCount,
                    Icons.storage_outlined,
                  ),
                  _StatItem('Total PON', counts.ponCount, Icons.hub_outlined),
                  _StatItem(
                    'Total ONU',
                    counts.onuCount,
                    Icons.device_hub_outlined,
                  ),
                  _StatItem(
                    'Total User',
                    counts.userCount,
                    Icons.people_alt_outlined,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _ActionPanel(counts: counts),
            ],
            if (!_loading && counts == null && _error == null)
              Card(
                margin: EdgeInsets.zero,
                child: const ListTile(
                  leading: Icon(Icons.info_outline),
                  title: Text('Belum ada data dashboard.'),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _HeroPanel extends StatelessWidget {
  const _HeroPanel({
    required this.name,
    required this.role,
    required this.health,
  });

  final String name;
  final String role;
  final _HealthSnapshot? health;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final now = DateTime.now();
    final ts =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')} '
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          colors: [scheme.primary, scheme.tertiary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: scheme.primary.withValues(alpha: 0.26),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Control Center',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: Colors.white.withValues(alpha: 0.88),
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  name,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Role: $role',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Colors.white.withValues(alpha: 0.92),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Last refresh: $ts',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.78),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          _ScoreBadge(health: health),
        ],
      ),
    );
  }
}

class _ScoreBadge extends StatelessWidget {
  const _ScoreBadge({required this.health});

  final _HealthSnapshot? health;

  @override
  Widget build(BuildContext context) {
    final score = health?.score ?? 0;
    final label = health?.label ?? 'Loading';
    final color = health?.color ?? Colors.blueGrey;

    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white,
        border: Border.all(color: color.withValues(alpha: 0.8), width: 3),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            '$score',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickBand extends StatelessWidget {
  const _QuickBand({required this.counts});

  final DashboardCounts counts;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 2.2,
      children: [
        _QuickMetric(
          label: 'Device',
          value: counts.deviceCount,
          icon: Icons.router_outlined,
          tone: const Color(0xFF0369A1),
        ),
        _QuickMetric(
          label: 'Interface',
          value: counts.interfaceCount,
          icon: Icons.cable_outlined,
          tone: const Color(0xFF0F766E),
        ),
        _QuickMetric(
          label: 'SFP Aktif',
          value: counts.sfpCount,
          icon: Icons.fiber_smart_record_outlined,
          tone: const Color(0xFF15803D),
        ),
        _QuickMetric(
          label: 'Critical Optical',
          value: counts.badOpticalCount,
          icon: Icons.warning_amber_outlined,
          tone: counts.badOpticalCount > 0
              ? const Color(0xFFDC2626)
              : const Color(0xFF334155),
        ),
      ],
    );
  }
}

class _QuickMetric extends StatelessWidget {
  const _QuickMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.tone,
  });

  final String label;
  final int value;
  final IconData icon;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: tone.withValues(alpha: 0.2)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: tone.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 20, color: tone),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '$value',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              Text(
                label,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: const Color(0xFF475569),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.counts});

  final DashboardCounts counts;

  @override
  Widget build(BuildContext context) {
    final hasIssue = counts.badOpticalCount > 0;
    final color = hasIssue ? const Color(0xFFDC2626) : const Color(0xFF0F766E);
    final title = hasIssue ? 'Action Required' : 'Network Stable';
    final subtitle = hasIssue
        ? '${counts.badOpticalCount} interface optical critical perlu ditinjau.'
        : 'Tidak ada anomali optical critical saat ini.';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              hasIssue ? Icons.priority_high_rounded : Icons.verified_rounded,
              color: color,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: color,
                  ),
                ),
                const SizedBox(height: 4),
                Text(subtitle),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 3),
        Text(
          subtitle,
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
        ),
      ],
    );
  }
}

class _StatItem {
  const _StatItem(this.label, this.value, this.icon);

  final String label;
  final int value;
  final IconData icon;
}

class _StatGrid extends StatelessWidget {
  const _StatGrid({required this.items});

  final List<_StatItem> items;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return GridView.count(
      crossAxisCount: 2,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 1.15,
      children: items.map((item) {
        return Container(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: scheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(item.icon, color: scheme.primary),
              ),
              const Spacer(),
              Text(
                '${item.value}',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                item.label,
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: const Color(0xFF475569)),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _skeletonBox(height: 86)),
            const SizedBox(width: 10),
            Expanded(child: _skeletonBox(height: 86)),
          ],
        ),
        const SizedBox(height: 10),
        _skeletonBox(height: 160),
      ],
    );
  }

  static Widget _skeletonBox({required double height}) {
    return Container(
      height: height,
      decoration: BoxDecoration(
        color: const Color(0xFFE2E8F0),
        borderRadius: BorderRadius.circular(16),
      ),
    );
  }
}

class _HealthSnapshot {
  const _HealthSnapshot({
    required this.score,
    required this.label,
    required this.color,
  });

  final int score;
  final String label;
  final Color color;
}

_HealthSnapshot _healthFromCounts(DashboardCounts counts) {
  final risk = counts.badOpticalCount * 12;
  final score = (100 - risk).clamp(20, 100);

  if (score >= 85) {
    return _HealthSnapshot(
      score: score,
      label: 'Stable',
      color: const Color(0xFF16A34A),
    );
  }
  if (score >= 65) {
    return _HealthSnapshot(
      score: score,
      label: 'Watch',
      color: const Color(0xFFF59E0B),
    );
  }
  return _HealthSnapshot(
    score: score,
    label: 'Risk',
    color: const Color(0xFFDC2626),
  );
}
