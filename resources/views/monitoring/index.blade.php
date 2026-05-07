@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/monitoring.css') }}?v={{ filemtime(public_path('assets/css/pages/monitoring.css')) }}">
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
