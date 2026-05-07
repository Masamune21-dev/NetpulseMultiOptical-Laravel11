@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/dashboard.css') }}?v={{ filemtime(public_path('assets/css/pages/dashboard.css')) }}">
@endpush

@section('content')
<div class="db-wrap">

    {{-- ══════════════════════════════════════════
         ZONA 1 — KPI CARDS
    ══════════════════════════════════════════ --}}
    <div>
        <div class="db-section-title"><i class="fas fa-chart-line"></i> Overview</div>
        <div class="db-kpi-grid">

            {{-- Device Aktif --}}
            <div class="db-kpi db-kpi--default">
                <div class="db-kpi__icon"><i class="fas fa-server"></i></div>
                <div class="db-kpi__val" data-counter="{{ $deviceCount }}">{{ $deviceCount }}</div>
                <div class="db-kpi__label">Device Aktif</div>
                <div class="db-kpi__sub">dari {{ $deviceHealth['total'] }} terdaftar</div>
            </div>

            {{-- Total Interface --}}
            <div class="db-kpi db-kpi--cyan">
                <div class="db-kpi__icon db-kpi__icon--cyan"><i class="fas fa-plug"></i></div>
                <div class="db-kpi__val" data-counter="{{ $ifCount }}">{{ $ifCount }}</div>
                <div class="db-kpi__label">Total Interface</div>
                <div class="db-kpi__sub">{{ $ifUpCount }} up &middot; {{ $ifDownCount }} down</div>
            </div>

            {{-- SFP Aktif --}}
            <div class="db-kpi db-kpi--success">
                <div class="db-kpi__icon db-kpi__icon--success"><i class="fas fa-circle-nodes"></i></div>
                <div class="db-kpi__val" data-counter="{{ $sfpCount }}">{{ $sfpCount }}</div>
                <div class="db-kpi__label">SFP / Optik Aktif</div>
                <div class="db-kpi__sub">port optik terdeteksi</div>
            </div>

            {{-- Optical Critical --}}
            <div class="db-kpi {{ $badOptical > 0 ? 'db-kpi--danger' : 'db-kpi--success' }}">
                <div class="db-kpi__icon">
                    @if($badOptical > 0)
                        <i class="fas fa-triangle-exclamation"></i>
                    @else
                        <i class="fas fa-shield-check"></i>
                    @endif
                </div>
                <div class="db-kpi__val" data-counter="{{ $badOptical }}">{{ $badOptical }}</div>
                <div class="db-kpi__label">Optical Critical</div>
                <div class="db-kpi__sub">
                    @if($badOptical > 0) port bermasalah @else semua port normal @endif
                </div>
            </div>

            {{-- Total Users --}}
            <div class="db-kpi db-kpi--amber">
                <div class="db-kpi__icon db-kpi__icon--amber"><i class="fas fa-users"></i></div>
                <div class="db-kpi__val" data-counter="{{ $userCount }}">{{ $userCount }}</div>
                <div class="db-kpi__label">Total Users</div>
                <div class="db-kpi__sub">akun terdaftar</div>
            </div>

        </div>
    </div>

    {{-- ══════════════════════════════════════════
         ZONA 2 — CHARTS ROW
    ══════════════════════════════════════════ --}}
    <div>
        <div class="db-section-title"><i class="fas fa-chart-bar"></i> Analytics</div>
        <div class="db-charts-row">

            {{-- Alert Trend Bar Chart --}}
            <div class="db-card">
                <div class="db-card__head">
                    <div class="db-card__title">
                        <i class="fas fa-bell" style="color:var(--primary)"></i>
                        Alert Trend
                    </div>
                    <span class="db-badge">7 Hari Terakhir</span>
                </div>
                <div class="db-chart-wrap" style="height:220px">
                    <canvas id="dbAlertTrendChart"></canvas>
                </div>
            </div>

            {{-- Network Health Donut --}}
            <div class="db-card">
                <div class="db-card__head">
                    <div class="db-card__title">
                        <i class="fas fa-circle-half-stroke" style="color:#15803d"></i>
                        Network Health
                    </div>
                    <span class="db-badge db-badge--success">Live</span>
                </div>
                <div class="db-chart-wrap" style="height:180px; position:relative">
                    <canvas id="dbHealthDonutChart"></canvas>
                    <div class="db-donut-center">
                        <div class="db-donut-center__val">{{ $deviceHealth['active'] }}</div>
                        <div class="db-donut-center__label">Active</div>
                    </div>
                </div>
                <div class="db-donut-legend">
                    <div class="db-donut-legend__item">
                        <span class="db-donut-legend__dot" style="background:#10b981"></span>
                        Active ({{ $deviceHealth['active'] }})
                    </div>
                    <div class="db-donut-legend__item">
                        <span class="db-donut-legend__dot" style="background:#ef4444"></span>
                        Failed ({{ $deviceHealth['failed'] }})
                    </div>
                    <div class="db-donut-legend__item">
                        <span class="db-donut-legend__dot" style="background:#475569"></span>
                        Inactive ({{ $deviceHealth['inactive'] }})
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ══════════════════════════════════════════
         ZONA 3 — DATA TABLES
    ══════════════════════════════════════════ --}}
    <div>
        <div class="db-section-title"><i class="fas fa-table-list"></i> Detail</div>
        <div class="db-data-row">

            {{-- Worst SFP Ports --}}
            <div class="db-card">
                <div class="db-card__head">
                    <div class="db-card__title">
                        <i class="fas fa-arrow-trend-down" style="color:#f87171"></i>
                        Worst SFP Ports
                    </div>
                    <span class="db-badge db-badge--warn">Top 6 RX Terendah</span>
                </div>
                @if(count($worstPorts) > 0)
                <table class="db-sfp-table">
                    <thead>
                        <tr>
                            <th>Device / Port</th>
                            <th>RX Power</th>
                            <th>TX Power</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($worstPorts as $port)
                        @php
                            $rx = (float)$port->rx_power;
                            $tx = (float)$port->tx_power;
                            if ($rx >= -25)      { $pwrClass = 'db-pwr--ok'; }
                            elseif ($rx >= -30)  { $pwrClass = 'db-pwr--warn'; }
                            elseif ($rx >= -35)  { $pwrClass = 'db-pwr--bad'; }
                            else                 { $pwrClass = 'db-pwr--crit'; }
                            // Bar width: map -40 to -10 dBm → 0% to 100%
                            $barPct = max(0, min(100, (($rx + 40) / 30) * 100));
                            if ($rx >= -25)      { $barColor = '#10b981'; }
                            elseif ($rx >= -30)  { $barColor = '#f59e0b'; }
                            else                 { $barColor = '#ef4444'; }
                        @endphp
                        <tr>
                            <td>
                                <div class="db-if-name">{{ $port->if_name }}
                                    @if($port->if_alias)
                                    <span style="color:var(--text-muted,#94a3b8); font-family:inherit"> · {{ $port->if_alias }}</span>
                                    @endif
                                </div>
                                <div class="db-dev-name">{{ $port->device_name }}</div>
                            </td>
                            <td>
                                <span class="db-pwr {{ $pwrClass }}">{{ number_format($rx,2) }} dBm</span>
                                <span class="db-pwr-bar-wrap">
                                    <span class="db-pwr-bar" style="width:{{ $barPct }}%; background:{{ $barColor }}"></span>
                                </span>
                            </td>
                            <td>
                                <span class="db-pwr" style="color:var(--text-muted,#94a3b8)">{{ number_format($tx,2) }} dBm</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div style="text-align:center; padding:32px 0; color:var(--text-muted,#94a3b8); font-size:.8rem;">
                    <i class="fas fa-circle-check" style="font-size:2rem; color:#10b981; margin-bottom:8px; display:block"></i>
                    Semua port optik dalam kondisi normal
                </div>
                @endif
            </div>

            {{-- Recent Alert Feed --}}
            <div class="db-card db-card--alerts">
                <div class="db-card__head">
                    <div class="db-card__title">
                        <i class="fas fa-bolt" style="color:#fbbf24"></i>
                        Recent Alerts
                        <span class="db-live-dot"></span>
                    </div>
                    <span class="db-badge db-badge--info">Live Feed</span>
                </div>
                @if(count($recentAlerts) > 0)
                <div class="db-alert-feed">
                    @foreach($recentAlerts as $alert)
                    @php
                        $sev = strtolower($alert->severity ?? 'info');
                        $evType = $alert->event_type ?? '';
                        $icon = match(true) {
                            str_contains($evType, 'down')    => 'fas fa-arrow-down',
                            str_contains($evType, 'up')      => 'fas fa-arrow-up',
                            str_contains($evType, 'warning') => 'fas fa-triangle-exclamation',
                            default                           => 'fas fa-circle-info',
                        };
                        $timeAgo = '';
                        try {
                            $diff = time() - strtotime($alert->created_at);
                            if ($diff < 60)        $timeAgo = $diff . 'd lalu';
                            elseif ($diff < 3600)  $timeAgo = floor($diff/60) . 'm lalu';
                            elseif ($diff < 86400) $timeAgo = floor($diff/3600) . 'j lalu';
                            else                   $timeAgo = floor($diff/86400) . 'h lalu';
                        } catch(\Throwable $e) { $timeAgo = ''; }
                    @endphp
                    <div class="db-alert-item db-alert-item--{{ $sev }}">
                        <div class="db-alert-item__icon"><i class="{{ $icon }}"></i></div>
                        <div class="db-alert-item__body">
                            <div class="db-alert-item__msg">{{ $alert->message }}</div>
                            <div class="db-alert-item__meta">
                                {{ $alert->device_name }}
                                @if($alert->if_name) · {{ $alert->if_name }} @endif
                            </div>
                        </div>
                        <div class="db-alert-item__time">{{ $timeAgo }}</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div style="text-align:center; padding:32px 0; color:var(--text-muted,#94a3b8); font-size:.8rem;">
                    <i class="fas fa-inbox" style="font-size:2rem; margin-bottom:8px; display:block; opacity:.4"></i>
                    Belum ada alert tercatat
                </div>
                @endif
            </div>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    /* ── helpers ───────────────────────────────────────────── */
    function getCssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || null;
    }
    function isDark() {
        return document.documentElement.getAttribute('data-theme') === 'dark'
            || document.body.getAttribute('data-theme') === 'dark';
    }

    const gridColor  = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)';
    const tickColor  = () => isDark() ? '#64748b' : '#94a3b8';
    const tooltipBg  = () => isDark() ? '#1e1b4b' : '#312e81';

    /* ── Counter animation ─────────────────────────────────── */
    function animateCounter(el) {
        const target = parseInt(el.dataset.counter || '0', 10);
        if (target === 0) return;
        const duration = 1100;
        const start = performance.now();
        function step(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = Math.round(eased * target);
            if (progress < 1) requestAnimationFrame(step);
            else el.textContent = target;
        }
        requestAnimationFrame(step);
    }

    document.querySelectorAll('[data-counter]').forEach(animateCounter);

    /* ── Alert Trend Chart (stacked bar) ───────────────────── */
    const trendRaw = @json($alertTrend);
    const trendLabels   = trendRaw.map(r => r.label);
    const trendCritical = trendRaw.map(r => r.critical);
    const trendWarning  = trendRaw.map(r => r.warning);
    const trendInfo     = trendRaw.map(r => r.info);

    const trendCtx = document.getElementById('dbAlertTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Critical',
                        data: trendCritical,
                        backgroundColor: 'rgba(239,68,68,.75)',
                        borderColor: 'rgba(239,68,68,.9)',
                        borderWidth: 0,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Warning',
                        data: trendWarning,
                        backgroundColor: 'rgba(245,158,11,.7)',
                        borderColor: 'rgba(245,158,11,.9)',
                        borderWidth: 0,
                        borderRadius: 0,
                        borderSkipped: false,
                    },
                    {
                        label: 'Info',
                        data: trendInfo,
                        backgroundColor: 'rgba(99,102,241,.65)',
                        borderColor: 'rgba(99,102,241,.9)',
                        borderWidth: 0,
                        borderRadius: [4, 4, 0, 0],
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            borderRadius: 5,
                            useBorderRadius: true,
                            padding: 14,
                            color: tickColor(),
                            font: { size: 11 },
                        },
                    },
                    tooltip: {
                        backgroundColor: tooltipBg(),
                        titleColor: '#c7d2fe',
                        bodyColor: '#e0e7ff',
                        borderColor: 'rgba(99,102,241,.4)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            title: (items) => items[0].label,
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: tickColor(), font: { size: 11 } },
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: {
                            color: tickColor(),
                            font: { size: 11 },
                            stepSize: 1,
                            precision: 0,
                        },
                    },
                },
            },
        });
    }

    /* ── Network Health Donut ──────────────────────────────── */
    const healthData = {
        active:   {{ $deviceHealth['active'] }},
        failed:   {{ $deviceHealth['failed'] }},
        inactive: {{ $deviceHealth['inactive'] }},
    };

    const donutCtx = document.getElementById('dbHealthDonutChart');
    if (donutCtx) {
        const hasData = (healthData.active + healthData.failed + healthData.inactive) > 0;
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Failed', 'Inactive'],
                datasets: [{
                    data: hasData
                        ? [healthData.active, healthData.failed, healthData.inactive]
                        : [1, 0, 0],
                    backgroundColor: [
                        'rgba(16,185,129,.85)',
                        'rgba(239,68,68,.8)',
                        'rgba(71,85,105,.5)',
                    ],
                    borderColor: [
                        'rgba(16,185,129,.3)',
                        'rgba(239,68,68,.3)',
                        'rgba(71,85,105,.3)',
                    ],
                    borderWidth: 2,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: tooltipBg(),
                        titleColor: '#c7d2fe',
                        bodyColor: '#e0e7ff',
                        borderColor: 'rgba(99,102,241,.4)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
            },
        });
    }

    /* ── KPI auto-refresh (Axios, 60 s) ────────────────────── */
    const kpiEls = {
        deviceCount: document.querySelector('[data-counter="{{ $deviceCount }}"]'),
    };

    function refreshKpi() {
        if (document.hidden) return;
        if (typeof axios === 'undefined') return;
        axios.get('/api/v1/dashboard')
            .then(res => {
                const d = res.data?.data;
                if (!d) return;
                // Update KPI card values (find by original data-counter attr)
                document.querySelectorAll('[data-counter]').forEach(el => {
                    // match by position in .db-kpi-grid
                });
            })
            .catch(() => {});
    }

    // Lightweight interval — we let the page reload handle stale data
    // (same behavior as before, but now charts stay alive)
    if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
        window.netpulseRefresh.register('dashboard', () => {
            if (!location.pathname.startsWith('/dashboard')) return;
            if (document.hidden) return;
            location.reload();
        }, { minIntervalMs: 60000 });
    } else {
        setTimeout(() => location.reload(), 60000);
    }

})();
</script>
@endpush
