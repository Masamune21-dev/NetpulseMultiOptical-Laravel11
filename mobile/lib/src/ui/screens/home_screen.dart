import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../auth/session_store.dart';
import '../../features/dashboard/dashboard_models.dart';
import '../../features/dashboard/dashboard_service.dart';
import '../../theme/status.dart';
import '../../theme/tokens.dart';
import '../widgets/async_state.dart';
import '../widgets/rx_meter.dart';
import '../widgets/screen_header.dart';
import '../widgets/section_card.dart';
import '../widgets/stat_tile.dart';
import '../widgets/status_badge.dart';
import 'alerts_screen.dart';

/// Beranda: hero kesehatan jaringan, empat ubin KPI, RX terendah, alert terbaru.
/// Semua nilai visual dari token [Np]; ambang RX dari server.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  DashboardCounts? _counts;
  DateTime? _refreshedAt;

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
      if (!mounted) return;
      setState(() {
        _counts = data;
        _refreshedAt = DateTime.now();
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openAlerts() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const AlertsScreen()));
  }

  String _subtitle(DashboardCounts? c) {
    final parts = <String>[];
    final at = _refreshedAt;
    if (at != null) {
      final hh = at.hour.toString().padLeft(2, '0');
      final mm = at.minute.toString().padLeft(2, '0');
      parts.add('Pembaruan $hh.$mm');
    }
    if (c != null) parts.add('${c.deviceTotal} perangkat');
    if (parts.isEmpty) {
      final user = SessionStore.instance.user;
      parts.add(
        user?.fullName.isNotEmpty == true ? user!.fullName : 'Netpulse',
      );
    }
    return parts.join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    final c = _counts;
    final hasCritical =
        c?.recentAlerts.any((a) => a.severity.toLowerCase() == 'critical') ??
        false;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                NpSpace.lg,
                NpSpace.sm,
                NpSpace.lg,
                0,
              ),
              child: ScreenHeader(
                leading: const BrandMark(),
                title: 'Beranda',
                subtitle: _subtitle(c),
                actions: [
                  HeaderIconButton(
                    icon: Icons.notifications_none_rounded,
                    tooltip: 'Alert',
                    dot: hasCritical,
                    onTap: _openAlerts,
                  ),
                  HeaderIconButton(
                    icon: Icons.refresh_rounded,
                    tooltip: 'Muat ulang',
                    busy: _loading && c != null,
                    onTap: _load,
                  ),
                ],
              ),
            ),
            Expanded(child: _body(c)),
          ],
        ),
      ),
    );
  }

  Widget _body(DashboardCounts? c) {
    if (c == null) {
      if (_loading) {
        return const AsyncState.loading(message: 'Memuat ringkasan jaringan…');
      }
      return AsyncState.error(
        message: _error ?? 'Belum ada data dashboard.',
        onRetry: _load,
      );
    }

    final health = _healthOf(c);
    final t = c.thresholds;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: NpSpace.screen,
        children: [
          if (_error != null) ...[
            _InlineNotice(
              message:
                  'Gagal memuat data terbaru. Menampilkan data sebelumnya.',
            ),
            const SizedBox(height: NpSpace.lg),
          ],
          _HealthHero(counts: c, health: health),
          const SizedBox(height: NpSpace.lg),
          _KpiGrid(counts: c),
          const SizedBox(height: NpSpace.lg),
          SectionCard(
            title: 'RX terendah',
            child: c.worstPorts.isEmpty
                ? const _EmptyRow('Semua port optik dalam kondisi normal.')
                : Column(
                    children: [
                      for (
                        var i = 0;
                        i < c.worstPorts.length && i < 4;
                        i++
                      ) ...[
                        if (i > 0) const RowDivider(),
                        _PortRow(port: c.worstPorts[i], thresholds: t),
                      ],
                    ],
                  ),
          ),
          const SizedBox(height: NpSpace.lg),
          SectionCard(
            title: 'Alert terbaru',
            actionLabel: 'Lihat semua',
            onAction: _openAlerts,
            child: c.recentAlerts.isEmpty
                ? const _EmptyRow('Belum ada alert tercatat.')
                : Column(
                    children: [
                      for (
                        var i = 0;
                        i < c.recentAlerts.length && i < 5;
                        i++
                      ) ...[
                        if (i > 0) const RowDivider(),
                        _AlertRow(alert: c.recentAlerts[i]),
                      ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

// ── Hero ─────────────────────────────────────────────────────────────────────

class _Health {
  const _Health({
    required this.score,
    required this.status,
    required this.label,
  });
  final int score;
  final NetStatus status;
  final String label;
}

/// Skor proporsional: tiap 1% port SFP bermasalah mengurangi 3 poin.
/// ≥ 85 stabil · ≥ 65 perlu perhatian · sisanya berisiko.
_Health _healthOf(DashboardCounts c) {
  final int score;
  if (c.sfpCount <= 0) {
    score = 100;
  } else {
    final pctBad = (c.badOpticalCount / c.sfpCount) * 100;
    score = (100 - pctBad * 3).clamp(0, 100).toInt();
  }
  if (score >= 85) {
    return _Health(score: score, status: NetStatus.up, label: 'Stabil');
  }
  if (score >= 65) {
    return _Health(score: score, status: NetStatus.warn, label: 'Perlu perhatian');
  }
  return _Health(score: score, status: NetStatus.down, label: 'Berisiko');
}

class _HealthHero extends StatelessWidget {
  const _HealthHero({required this.counts, required this.health});

  final DashboardCounts counts;
  final _Health health;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;
    final dh = counts.deviceHealth;
    final bad = counts.badOpticalCount;
    final total = counts.ifUpCount + counts.ifDownCount;
    final sentence = bad > 0
        ? '${health.label}, $bad port perlu tindakan'
        : '${health.label}, tidak ada tindakan tertunda';

    return SectionCard(
      padding: NpSpace.card,
      child: Row(
        children: [
          SizedBox(
            width: 84,
            height: 84,
            child: Stack(
              fit: StackFit.expand,
              children: [
                CircularProgressIndicator(
                  value: health.score / 100,
                  strokeWidth: 8,
                  strokeCap: StrokeCap.round,
                  color: k.status(health.status).mark,
                  backgroundColor: k.track,
                ),
                Center(
                  child: Text(
                    '${health.score}%',
                    style: t.displaySmall?.copyWith(fontSize: 20),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: NpSpace.lg),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'KESEHATAN JARINGAN',
                  style: t.labelSmall?.copyWith(
                    color: k.ink2,
                    letterSpacing: 0.6,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: NpSpace.xs),
                Text(
                  sentence,
                  style: t.titleLarge?.copyWith(fontSize: 16),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: NpSpace.xs),
                Text(
                  '${counts.ifUpCount} dari $total interface SFP up · ${counts.sfpCount} SFP terbaca',
                  style: t.bodySmall,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: NpSpace.sm),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    StatusBadge(NetStatus.up, label: '${dh.active} Aktif'),
                    StatusBadge(NetStatus.down, label: '${dh.failed} Gagal'),
                    StatusBadge(
                      NetStatus.none,
                      label: '${dh.inactive} Nonaktif',
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ── KPI ──────────────────────────────────────────────────────────────────────

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.counts});

  final DashboardCounts counts;

  @override
  Widget build(BuildContext context) {
    final c = counts;
    final total = c.ifUpCount + c.ifDownCount;
    final dh = c.deviceHealth;

    // IntrinsicHeight: di dalam ListView tinggi tidak terbatas, jadi `stretch`
    // tanpa pembungkus ini memaksa tinggi tak hingga dan seluruh sisa halaman
    // gagal digambar (Beranda tampak kosong di rilis 2.1.0+6).
    return Column(
      children: [
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: StatTile(
                  label: 'Interface up',
                  value: '${c.ifUpCount}',
                  suffix: '/ $total',
                  foot: c.ifDownCount > 0
                      ? '${c.ifDownCount} down'
                      : 'Semua up',
                  footStatus: c.ifDownCount > 0 ? NetStatus.down : NetStatus.up,
                ),
              ),
              const SizedBox(width: NpSpace.md),
              Expanded(
                child: StatTile(
                  label: 'SFP aktif',
                  value: '${c.sfpCount}',
                  foot: 'Terbaca DDM',
                  footStatus: NetStatus.up,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: NpSpace.md),
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: StatTile(
                  label: 'Optik bermasalah',
                  value: '${c.badOpticalCount}',
                  foot: c.badOpticalCount > 0
                      ? 'Down dan pernah alert'
                      : 'Tidak ada',
                  footStatus: c.badOpticalCount > 0
                      ? NetStatus.down
                      : NetStatus.up,
                ),
              ),
              const SizedBox(width: NpSpace.md),
              Expanded(
                child: StatTile(
                  label: 'Perangkat',
                  value: '${dh.active}',
                  suffix: '/ ${dh.total}',
                  foot: dh.failed > 0
                      ? '${dh.failed} gagal polling'
                      : 'Semua terjangkau',
                  footStatus: dh.failed > 0 ? NetStatus.down : NetStatus.up,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

// ── Baris ────────────────────────────────────────────────────────────────────

class _PortRow extends StatelessWidget {
  const _PortRow({required this.port, required this.thresholds});

  final WorstPort port;
  final RxThresholds thresholds;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;
    final alias = port.ifAlias?.trim() ?? '';

    return Padding(
      padding: NpSpace.row,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  port.deviceName,
                  style: t.titleSmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(
                        text: port.ifName,
                        style: NpText.mono(
                          size: 11.5,
                          weight: FontWeight.w500,
                          color: k.ink2,
                        ),
                      ),
                      if (alias.isNotEmpty) TextSpan(text: ' · $alias'),
                    ],
                  ),
                  style: t.bodySmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: NpSpace.md),
          RxValue(rx: port.rxPower, thresholds: thresholds),
        ],
      ),
    );
  }
}

class _AlertRow extends StatelessWidget {
  const _AlertRow({required this.alert});

  final RecentAlert alert;

  static String timeAgo(String createdAt) {
    try {
      final dt = DateTime.parse(createdAt);
      final diff = DateTime.now().difference(dt);
      if (diff.inSeconds < 60) return 'baru saja';
      if (diff.inMinutes < 60) return '${diff.inMinutes} mnt';
      if (diff.inHours < 24) return '${diff.inHours} jam';
      return '${diff.inDays} hr';
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    final t = Theme.of(context).textTheme;
    final sev = k.severity(alert.severity);
    final where = [
      alert.deviceName,
      if (alert.ifName?.isNotEmpty == true) alert.ifName!,
    ].join(' · ');

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        13,
        NpSpace.md,
        NpSpace.lg,
        NpSpace.md,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 3,
            height: 34,
            margin: const EdgeInsets.only(top: 2),
            decoration: BoxDecoration(
              color: sev.mark,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: NpSpace.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  alert.message,
                  style: t.titleSmall,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  where,
                  style: t.bodySmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: NpSpace.md),
          Text(
            timeAgo(alert.createdAt),
            style: t.bodySmall?.copyWith(color: k.ink3, fontSize: 11.5),
          ),
        ],
      ),
    );
  }
}

class _EmptyRow extends StatelessWidget {
  const _EmptyRow(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: NpSpace.row,
    child: Text(text, style: Theme.of(context).textTheme.bodySmall),
  );
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final k = context.np;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: NpSpace.md, vertical: 10),
      decoration: BoxDecoration(
        color: k.warnBg,
        borderRadius: BorderRadius.circular(NpRadius.control),
      ),
      child: Row(
        children: [
          Icon(Icons.wifi_off_rounded, size: 16, color: k.warnText),
          const SizedBox(width: NpSpace.sm),
          Expanded(
            child: Text(
              message,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: k.warnText),
            ),
          ),
        ],
      ),
    );
  }
}
