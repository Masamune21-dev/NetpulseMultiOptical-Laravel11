@extends('layouts.app')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════════════════
   DASHBOARD CYBER — local styles (scoped to .db-* classes)
══════════════════════════════════════════════════════════════ */

/* ── Page wrapper ─────────────────────────────────────────── */
.db-wrap {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 24px;
    position: relative;
}

/* Grid overlay */
.db-wrap::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(var(--ink-1) 1px, transparent 1px),
        linear-gradient(90deg, var(--ink-1) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
}

/* ── Section headings ─────────────────────────────────────── */
.db-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-soft);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.db-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, var(--ink-4) 0%, transparent 100%);
}

/* ── KPI Cards ────────────────────────────────────────────── */
.db-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    position: relative;
    z-index: 1;
}

.db-kpi {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    padding: 20px 18px 16px;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s, transform .15s;
    cursor: default;
}
.db-kpi::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--ink-2) 0%, transparent 60%);
    border-radius: inherit;
    pointer-events: none;
}
.db-kpi:hover {
    border-color: var(--ink-5);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.db-kpi.db-kpi--danger {
    border-color: rgba(239,68,68,.45);
    box-shadow: 0 0 18px rgba(239,68,68,.15);
    animation: db-danger-pulse 2.4s ease-in-out infinite;
}
.db-kpi.db-kpi--danger::before {
    background: linear-gradient(135deg, rgba(239,68,68,.1) 0%, transparent 60%);
}

@keyframes db-danger-pulse {
    0%, 100% { box-shadow: 0 0 14px rgba(239,68,68,.15); }
    50%       { box-shadow: 0 0 30px rgba(239,68,68,.35); }
}

.db-kpi__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    margin-bottom: 14px;
    background: var(--primary-gradient);
    color: var(--btn-text, #fff);
    box-shadow: var(--shadow-sm);
    position: relative;
    z-index: 1;
}
.db-kpi--danger .db-kpi__icon {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    box-shadow: 2px 2px 0px rgba(220,38,38,.35);
}
.db-kpi--success .db-kpi__icon {
    background: linear-gradient(135deg, #15803d, #166534);
    box-shadow: 2px 2px 0px rgba(21,128,61,.3);
}
.db-kpi--cyan .db-kpi__icon {
    background: linear-gradient(135deg, #374151, #1f2937);
    box-shadow: var(--shadow-sm);
}
.db-kpi--amber .db-kpi__icon {
    background: linear-gradient(135deg, #b45309, #92400e);
    box-shadow: 2px 2px 0px rgba(180,83,9,.3);
}

.db-kpi__val {
    font-family: inherit;
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1;
    color: var(--text, #f1f5f9);
    letter-spacing: -0.02em;
    position: relative;
    z-index: 1;
}
.db-kpi--danger .db-kpi__val { color: #ef4444; }

.db-kpi__label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text, #f1f5f9);
    margin-top: 4px;
    position: relative;
    z-index: 1;
}
.db-kpi__sub {
    font-size: 0.7rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
    position: relative;
    z-index: 1;
}

/* Live pulse dot */
.db-live-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    position: relative;
    margin-left: 6px;
    vertical-align: middle;
}
.db-live-dot::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: rgba(16,185,129,.3);
    animation: db-live 1.6s ease-in-out infinite;
}
@keyframes db-live {
    0%, 100% { transform: scale(1); opacity: .6; }
    50%       { transform: scale(1.8); opacity: 0; }
}

/* ── Charts Row ───────────────────────────────────────────── */
.db-charts-row {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 14px;
    position: relative;
    z-index: 1;
}

.db-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
}
.db-card:hover { border-color: var(--ink-5); box-shadow: var(--shadow-md); }
.db-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--primary-gradient);
    border-radius: 14px 14px 0 0;
}

.db-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.db-card__title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Badge pills */
.db-badge {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 6px;
    background: var(--ink-2);
    color: var(--text-soft);
    border: 1px solid var(--border);
}
.db-badge--warn    { background: rgba(180,83,9,.1);  color: #b45309; border-color: rgba(180,83,9,.25); }
.db-badge--danger  { background: rgba(220,38,38,.1); color: #dc2626; border-color: rgba(220,38,38,.25); }
.db-badge--success { background: rgba(21,128,61,.1); color: #15803d; border-color: rgba(21,128,61,.25); }
.db-badge--info    { background: var(--ink-2); color: var(--text-soft); border-color: var(--border); }

/* Chart canvas */
.db-chart-wrap {
    position: relative;
    width: 100%;
}
.db-chart-wrap canvas {
    max-width: 100%;
}

/* Donut center text */
.db-donut-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
}
.db-donut-center__val {
    font-family: 'Space Mono', monospace;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    line-height: 1;
}
.db-donut-center__label {
    font-size: 0.65rem;
    color: var(--text-muted, #94a3b8);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

/* Donut legend below chart */
.db-donut-legend {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 14px;
    flex-wrap: wrap;
}
.db-donut-legend__item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
}
.db-donut-legend__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Data Row ─────────────────────────────────────────────── */
.db-data-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    position: relative;
    z-index: 1;
}

/* SFP Power Table */
.db-sfp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}
.db-sfp-table th {
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-soft);
    padding: 0 8px 8px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.db-sfp-table td {
    padding: 9px 8px;
    color: var(--text);
    border-bottom: 1px solid var(--ink-2);
    vertical-align: middle;
    white-space: nowrap;
}
.db-sfp-table tr:last-child td { border-bottom: none; }
.db-sfp-table tr:hover td { background: var(--ink-1); }

.db-sfp-table .db-dev-name {
    font-size: 0.7rem;
    color: var(--text-muted, #94a3b8);
}
.db-sfp-table .db-if-name {
    font-family: 'Space Mono', monospace;
    font-size: 0.72rem;
}

/* Power coloring */
.db-pwr { font-family: 'Space Mono', monospace; font-size: 0.78rem; font-weight: 700; }
.db-pwr--ok   { color: #10b981; }
.db-pwr--warn { color: #f59e0b; }
.db-pwr--bad  { color: #ef4444; }
.db-pwr--crit { color: #dc2626; text-shadow: 0 0 8px rgba(220,38,38,.5); }

/* Power bar */
.db-pwr-bar-wrap {
    width: 60px;
    height: 5px;
    background: var(--ink-3);
    border-radius: 99px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
    margin-left: 6px;
}
.db-pwr-bar {
    height: 100%;
    border-radius: 99px;
    transition: width .4s ease;
}

/* ── Alert Feed ───────────────────────────────────────────── */
.db-alert-feed {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 320px;
    overflow-y: auto;
    padding-right: 4px;
}
.db-alert-feed::-webkit-scrollbar { width: 4px; }
.db-alert-feed::-webkit-scrollbar-track { background: transparent; }
.db-alert-feed::-webkit-scrollbar-thumb { background: var(--ink-4); border-radius: 99px; }

.db-alert-item {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid transparent;
    background: var(--ink-1);
    transition: background .15s;
}
.db-alert-item:hover { background: var(--ink-2); }
.db-alert-item--critical { border-left: 3px solid #dc2626 !important; background: rgba(220,38,38,.05); }
.db-alert-item--warning  { border-left: 3px solid #d97706 !important; background: rgba(217,119,6,.04); }
.db-alert-item--info     { border-left: 3px solid var(--primary) !important; }

.db-alert-item__icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
}
.db-alert-item--critical .db-alert-item__icon { background: rgba(220,38,38,.12); color: #dc2626; }
.db-alert-item--warning  .db-alert-item__icon { background: rgba(217,119,6,.12); color: #d97706; }
.db-alert-item--info     .db-alert-item__icon { background: var(--ink-3); color: var(--primary); }

.db-alert-item__body { flex: 1; min-width: 0; }
.db-alert-item__msg {
    font-size: 0.75rem;
    color: var(--text, #f1f5f9);
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.db-alert-item__meta {
    font-size: 0.65rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.db-alert-item__time {
    font-size: 0.62rem;
    color: var(--text-muted, #94a3b8);
    white-space: nowrap;
    margin-top: 2px;
}

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1100px) {
    .db-kpi-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .db-kpi-grid    { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .db-charts-row  { grid-template-columns: 1fr; }
    .db-data-row    { grid-template-columns: 1fr; }
    .db-kpi__val    { font-size: 1.8rem; }
    .db-pwr-bar-wrap { display: none; }
}
@media (max-width: 480px) {
    .db-kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>
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
            <div class="db-card">
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
