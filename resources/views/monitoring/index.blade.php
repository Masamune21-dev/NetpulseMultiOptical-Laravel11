@extends('layouts.app')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════
   MONITORING PAGE — Cyber Theme
══════════════════════════════════════════ */
.mon-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    z-index: 1;
}

/* ── Filter Card ──────────────────────────── */
.mon-filter-card {
    background: var(--surface, rgba(255,255,255,.04));
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
}
.mon-filter-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--primary-gradient);
    border-radius: 18px 18px 0 0;
}

.mon-filter-row {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.mon-filter-row .form-group {
    flex: 1;
    min-width: 160px;
    margin-bottom: 0;
}
.mon-filter-row .form-group label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted, #94a3b8);
    display: block;
    margin-bottom: 6px;
}


/* ── Stats Cards Row ──────────────────────── */
.mon-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
.mon-stat {
    background: var(--surface, rgba(255,255,255,.04));
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
}
.mon-stat::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--ink-1) 0%, transparent 70%);
    pointer-events: none;
}
.mon-stat__label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 4px;
}
.mon-stat__val {
    font-family: 'Space Mono', monospace;
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    line-height: 1;
    white-space: nowrap;
}
.mon-stat__unit {
    font-size: 0.7rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
}

/* Color accents per stat */
.mon-stat--now  .mon-stat__val { color: var(--primary); }
.mon-stat--avg  .mon-stat__val { color: var(--success); }
.mon-stat--min  .mon-stat__val { color: var(--danger); }
.mon-stat--max  .mon-stat__val { color: var(--warning); }

/* ── Interface Info Banner ────────────────── */
.mon-iface-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--ink-2);
    border-radius: 10px;
    border: 1px solid var(--ink-3);
    font-size: 0.82rem;
    color: var(--text-muted, #94a3b8);
    transition: all .2s;
}
.mon-iface-banner i { color: var(--primary); }
.mon-iface-banner span { font-weight: 600; color: var(--text, #f1f5f9); }
.mon-iface-banner.has-data {
    border-color: rgba(99,102,241,.3);
    background: var(--ink-2);
}

/* ── Chart Card ───────────────────────────── */
.mon-chart-card {
    background: var(--surface, rgba(255,255,255,.04));
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}
.mon-chart-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--primary-gradient);
    background-size: 200% auto;
    animation: chart-bar-anim 4s linear infinite;
    border-radius: 18px 18px 0 0;
}
@keyframes chart-bar-anim {
    0%   { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
}

.mon-chart-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.mon-chart-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    display: flex;
    align-items: center;
    gap: 8px;
}
.mon-refresh-badge {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 99px;
    background: rgba(52,211,153,.1);
    color: #34d399;
    border: 1px solid rgba(52,211,153,.2);
    display: flex;
    align-items: center;
    gap: 4px;
}

.chart-container {
    position: relative;
    height: 320px;
}

/* ── Responsive ───────────────────────────── */
@media (max-width: 900px) {
    .mon-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .mon-filter-row { flex-direction: column; gap: 10px; }
    .mon-filter-row .form-group { min-width: 100%; }
    .mon-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .chart-container {
        height: 280px;
        margin-left: -20px;
        margin-right: -20px;
        border-radius: 0;
    }
}
@media (max-width: 480px) {
    .mon-stats-grid { grid-template-columns: 1fr 1fr; }
}
</style>
@endpush

@section('content')
<div class="mon-wrap">

    {{-- Filter Card --}}
    <div class="mon-filter-card">
        <div class="mon-filter-row">
            <div class="form-group">
                <label><i class="fas fa-server"></i> &nbsp;Device</label>
                <select id="deviceSelect" class="monitoring-select">
                    <option value="">— Pilih Device —</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-circle-nodes"></i> &nbsp;Interface (SFP)</label>
                <select id="interfaceSelect" class="monitoring-select">
                    <option value="">— Pilih Interface —</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-clock"></i> &nbsp;Range Waktu</label>
                <select id="rangeSelect" class="monitoring-select">
                    <option value="1h" selected>1 Jam</option>
                    <option value="1d">1 Hari</option>
                    <option value="3d">3 Hari</option>
                    <option value="7d">1 Minggu</option>
                    <option value="30d">1 Bulan</option>
                    <option value="1y">1 Tahun</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Interface Info Banner --}}
    <div id="ifaceInfo" class="mon-iface-banner">
        <i class="fas fa-circle-nodes"></i>
        <span>Pilih device dan interface untuk melihat data optik</span>
    </div>

    {{-- Stats Grid --}}
    <div class="mon-stats-grid">
        <div class="mon-stat mon-stat--now">
            <div class="mon-stat__label">Sekarang (RX)</div>
            <div class="mon-stat__val" id="statNow">—</div>
            <div class="mon-stat__unit">dBm</div>
        </div>
        <div class="mon-stat mon-stat--avg">
            <div class="mon-stat__label">Rata-rata</div>
            <div class="mon-stat__val" id="statAvg">—</div>
            <div class="mon-stat__unit">dBm</div>
        </div>
        <div class="mon-stat mon-stat--min">
            <div class="mon-stat__label">Minimum</div>
            <div class="mon-stat__val" id="statMin">—</div>
            <div class="mon-stat__unit">dBm</div>
        </div>
        <div class="mon-stat mon-stat--max">
            <div class="mon-stat__label">Maximum</div>
            <div class="mon-stat__val" id="statMax">—</div>
            <div class="mon-stat__unit">dBm</div>
        </div>
    </div>

    {{-- Chart Card --}}
    <div class="mon-chart-card">
        <div class="mon-chart-card__head">
            <div class="mon-chart-card__title">
                <i class="fas fa-chart-line" style="color:var(--primary)"></i>
                RX / TX Optical Power
            </div>
            <div class="mon-refresh-badge">
                <i class="fas fa-circle" style="font-size:.5rem"></i>
                Live
            </div>
        </div>
        <div class="chart-container">
            <canvas id="opticalChart"></canvas>
        </div>
    </div>

</div>

{{-- Hidden rx-stats div kept for monitoring.js compatibility --}}
<div id="rxStats" style="display:none"></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="{{ asset('assets/js/monitoring.js') }}?v={{ filemtime(public_path('assets/js/monitoring.js')) }}"></script>
<script>
// Bridge: map new stat card IDs ← monitoring.js uses #rxStats text
// Override monitoring.js's stat display to update the new cards instead
(function () {
    'use strict';
    var _orig = window._updateRxStats;

    // Polling approach: intercept changes to #rxStats and mirror to new cards
    var rxStatsEl = document.getElementById('rxStats');
    if (!rxStatsEl) return;

    var observer = new MutationObserver(function () {
        var txt = rxStatsEl.textContent || '';
        // format: "Now: X dBm | Avg: X dBm | Min: X dBm | Max: X dBm"
        var parts = {};
        txt.split('|').forEach(function (part) {
            var m = part.trim().match(/^(\w+):\s*([\-\d.]+|-)/) ;
            if (m) parts[m[1].toLowerCase()] = m[2];
        });
        if (parts.now)  document.getElementById('statNow').textContent  = parts.now  === '-' ? '—' : parts.now;
        if (parts.avg)  document.getElementById('statAvg').textContent  = parts.avg  === '-' ? '—' : parts.avg;
        if (parts.min)  document.getElementById('statMin').textContent  = parts.min  === '-' ? '—' : parts.min;
        if (parts.max)  document.getElementById('statMax').textContent  = parts.max  === '-' ? '—' : parts.max;
    });
    observer.observe(rxStatsEl, { childList: true, subtree: true, characterData: true });

    // Also mirror interface info
    var ifaceEl = document.getElementById('ifaceInfo');
    // monitoring.js updates #ifaceInfo directly — add class when data changes
    if (ifaceEl) {
        new MutationObserver(function () {
            var txt = ifaceEl.textContent.trim();
            if (txt && txt !== 'Interface: -') {
                ifaceEl.classList.add('has-data');
                // Wrap content nicely
                if (!ifaceEl.querySelector('i')) return;
            }
        }).observe(ifaceEl, { childList: true, subtree: true, characterData: true });
    }


})();
</script>
@endpush
