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
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
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
              _NetworkHealthSection(health: counts.deviceHealth),
              const SizedBox(height: 14),
              _ActionPanel(counts: counts),
              const SizedBox(height: 14),
              _WorstPortsSection(ports: counts.worstPorts),
              const SizedBox(height: 14),
              _RecentAlertsSection(alerts: counts.recentAlerts),
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

// ─────────────────────────────────────────────────────────────────────────────
// Hero Panel
// ─────────────────────────────────────────────────────────────────────────────

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

// ─────────────────────────────────────────────────────────────────────────────
// Quick KPI Band
// ─────────────────────────────────────────────────────────────────────────────

class _QuickBand extends StatelessWidget {
  const _QuickBand({required this.counts});

  final DashboardCounts counts;

  @override
  Widget build(BuildContext context) {
    final deviceSub = counts.deviceTotal > 0
        ? 'dari ${counts.deviceTotal} terdaftar'
        : 'device aktif';
    final ifSub = (counts.ifUpCount + counts.ifDownCount) > 0
        ? '${counts.ifUpCount} up · ${counts.ifDownCount} down'
        : 'total interface';

    return GridView.count(
      crossAxisCount: 2,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 1.85,
      children: [
        _QuickMetric(
          label: 'Device Aktif',
          value: counts.deviceCount,
          sub: deviceSub,
          icon: Icons.router_outlined,
          tone: const Color(0xFF0369A1),
        ),
        _QuickMetric(
          label: 'Interface',
          value: counts.interfaceCount,
          sub: ifSub,
          icon: Icons.cable_outlined,
          tone: const Color(0xFF0F766E),
        ),
        _QuickMetric(
          label: 'SFP Aktif',
          value: counts.sfpCount,
          sub: 'port optik terdeteksi',
          icon: Icons.fiber_smart_record_outlined,
          tone: const Color(0xFF15803D),
        ),
        _QuickMetric(
          label: 'Optical Critical',
          value: counts.badOpticalCount,
          sub: counts.badOpticalCount > 0 ? 'port bermasalah' : 'semua port normal',
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
    required this.sub,
    required this.icon,
    required this.tone,
  });

  final String label;
  final int value;
  final String sub;
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
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: tone.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 20, color: tone),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '$value',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
                Text(
                  label,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: const Color(0xFF334155),
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  sub,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: const Color(0xFF94A3B8),
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Network Health Section
// ─────────────────────────────────────────────────────────────────────────────

class _NetworkHealthSection extends StatelessWidget {
  const _NetworkHealthSection({required this.health});

  final DeviceHealth health;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      icon: Icons.monitor_heart_outlined,
      iconColor: const Color(0xFF15803D),
      title: 'Network Health',
      badge: 'Live',
      badgeColor: const Color(0xFF15803D),
      child: Column(
        children: [
          Row(
            children: [
              _HealthPill(
                label: 'Active',
                value: health.active,
                color: const Color(0xFF10B981),
              ),
              const SizedBox(width: 8),
              _HealthPill(
                label: 'Failed',
                value: health.failed,
                color: const Color(0xFFEF4444),
              ),
              const SizedBox(width: 8),
              _HealthPill(
                label: 'Inactive',
                value: health.inactive,
                color: const Color(0xFF94A3B8),
              ),
            ],
          ),
          if (health.total > 0) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: SizedBox(
                height: 8,
                child: Row(
                  children: [
                    if (health.active > 0)
                      Flexible(
                        flex: health.active,
                        child: Container(color: const Color(0xFF10B981)),
                      ),
                    if (health.failed > 0)
                      Flexible(
                        flex: health.failed,
                        child: Container(color: const Color(0xFFEF4444)),
                      ),
                    if (health.inactive > 0)
                      Flexible(
                        flex: health.inactive,
                        child: Container(color: const Color(0xFFCBD5E1)),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.centerRight,
              child: Text(
                '${health.total} device terdaftar',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: const Color(0xFF94A3B8),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _HealthPill extends StatelessWidget {
  const _HealthPill({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withValues(alpha: 0.25)),
        ),
        child: Column(
          children: [
            Text(
              '$value',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: color,
              ),
            ),
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: color.withValues(alpha: 0.85),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Action Panel
// ─────────────────────────────────────────────────────────────────────────────

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
        border: Border.all(color: color.withValues(alpha: 0.3)),
        boxShadow: [
          BoxShadow(
            color: color.withValues(alpha: 0.08),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
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
                Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: const Color(0xFF475569),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Worst SFP Ports Section
// ─────────────────────────────────────────────────────────────────────────────

class _WorstPortsSection extends StatelessWidget {
  const _WorstPortsSection({required this.ports});

  final List<WorstPort> ports;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      icon: Icons.arrow_downward_rounded,
      iconColor: const Color(0xFFF87171),
      title: 'Worst SFP Ports',
      badge: 'Top 6 RX Terendah',
      badgeColor: const Color(0xFFF59E0B),
      child: ports.isEmpty
          ? _emptyState(
              icon: Icons.check_circle_outline,
              color: const Color(0xFF10B981),
              text: 'Semua port optik dalam kondisi normal',
            )
          : Column(
              children: ports
                  .map((p) => _PortRow(port: p))
                  .toList(),
            ),
    );
  }
}

class _PortRow extends StatelessWidget {
  const _PortRow({required this.port});

  final WorstPort port;

  Color _rxColor(double rx) {
    if (rx >= -25) return const Color(0xFF10B981);
    if (rx >= -30) return const Color(0xFFF59E0B);
    return const Color(0xFFEF4444);
  }

  @override
  Widget build(BuildContext context) {
    final rxColor = _rxColor(port.rxPower);
    final barPct = ((port.rxPower + 40) / 30).clamp(0.0, 1.0);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Expanded(
            flex: 5,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  port.ifName,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF1E293B),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
                if (port.ifAlias?.isNotEmpty == true)
                  Text(
                    port.ifAlias!,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: const Color(0xFF94A3B8),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                Text(
                  port.deviceName,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: const Color(0xFF64748B),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            flex: 4,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '${port.rxPower.toStringAsFixed(2)} dBm',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: rxColor,
                  ),
                ),
                const SizedBox(height: 3),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: barPct,
                    minHeight: 5,
                    backgroundColor: const Color(0xFFE2E8F0),
                    valueColor: AlwaysStoppedAnimation<Color>(rxColor),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'TX: ${port.txPower.toStringAsFixed(2)} dBm',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: const Color(0xFF94A3B8),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Recent Alerts Section
// ─────────────────────────────────────────────────────────────────────────────

class _RecentAlertsSection extends StatelessWidget {
  const _RecentAlertsSection({required this.alerts});

  final List<RecentAlert> alerts;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      icon: Icons.bolt_rounded,
      iconColor: const Color(0xFFFBBF24),
      title: 'Recent Alerts',
      badge: 'Live Feed',
      badgeColor: const Color(0xFF6366F1),
      child: alerts.isEmpty
          ? _emptyState(
              icon: Icons.inbox_outlined,
              color: const Color(0xFF94A3B8),
              text: 'Belum ada alert tercatat',
            )
          : Column(
              children: alerts
                  .map((a) => _AlertRow(alert: a))
                  .toList(),
            ),
    );
  }
}

class _AlertRow extends StatelessWidget {
  const _AlertRow({required this.alert});

  final RecentAlert alert;

  Color _sevColor(String sev) {
    switch (sev.toLowerCase()) {
      case 'critical':
        return const Color(0xFFEF4444);
      case 'warning':
        return const Color(0xFFF59E0B);
      default:
        return const Color(0xFF6366F1);
    }
  }

  IconData _eventIcon(String evType) {
    if (evType.contains('down')) return Icons.arrow_downward_rounded;
    if (evType.contains('up')) return Icons.arrow_upward_rounded;
    if (evType.contains('warning')) return Icons.warning_amber_rounded;
    return Icons.info_outline_rounded;
  }

  String _timeAgo(String createdAt) {
    try {
      final dt = DateTime.parse(createdAt);
      final diff = DateTime.now().difference(dt);
      if (diff.inSeconds < 60) return '${diff.inSeconds}d lalu';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m lalu';
      if (diff.inHours < 24) return '${diff.inHours}j lalu';
      return '${diff.inDays}h lalu';
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = _sevColor(alert.severity);
    final icon = _eventIcon(alert.eventType);
    final timeAgo = _timeAgo(alert.createdAt);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, size: 16, color: color),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  alert.message,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: const Color(0xFF1E293B),
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  [alert.deviceName, if (alert.ifName?.isNotEmpty == true) alert.ifName!].join(' · '),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: const Color(0xFF64748B),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          Text(
            timeAgo,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: const Color(0xFF94A3B8),
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared helpers
// ─────────────────────────────────────────────────────────────────────────────

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.badge,
    required this.badgeColor,
    required this.child,
  });

  final IconData icon;
  final Color iconColor;
  final String title;
  final String badge;
  final Color badgeColor;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 16, color: iconColor),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: badgeColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: badgeColor.withValues(alpha: 0.3)),
                ),
                child: Text(
                  badge,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: badgeColor,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

Widget _emptyState({
  required IconData icon,
  required Color color,
  required String text,
}) {
  return Padding(
    padding: const EdgeInsets.symmetric(vertical: 20),
    child: Center(
      child: Column(
        children: [
          Icon(icon, size: 32, color: color),
          const SizedBox(height: 8),
          Text(
            text,
            style: TextStyle(
              fontSize: 12,
              color: const Color(0xFF94A3B8),
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard Skeleton
// ─────────────────────────────────────────────────────────────────────────────

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _skeletonBox(height: 90)),
            const SizedBox(width: 10),
            Expanded(child: _skeletonBox(height: 90)),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(child: _skeletonBox(height: 90)),
            const SizedBox(width: 10),
            Expanded(child: _skeletonBox(height: 90)),
          ],
        ),
        const SizedBox(height: 10),
        _skeletonBox(height: 100),
        const SizedBox(height: 10),
        _skeletonBox(height: 60),
        const SizedBox(height: 10),
        _skeletonBox(height: 200),
        const SizedBox(height: 10),
        _skeletonBox(height: 200),
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

// ─────────────────────────────────────────────────────────────────────────────
// Health helpers
// ─────────────────────────────────────────────────────────────────────────────

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
  final sfp = counts.sfpCount;
  final bad = counts.badOpticalCount;

  // Proporsional: setiap 1% port bermasalah mengurangi 3 poin.
  // 0% bad → 100, 5% bad → 85 (Stable batas), 12% bad → 64 (Watch batas).
  final int score;
  if (sfp <= 0) {
    score = 100;
  } else {
    final percentBad = (bad / sfp) * 100;
    score = (100 - percentBad * 3).clamp(0, 100).toInt();
  }

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
