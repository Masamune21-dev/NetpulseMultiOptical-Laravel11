@extends('layouts.app')

@section('content')
    <div class="topbar">
        <h1>
            <i class="fas fa-wave-square"></i>
            Optical Monitoring
        </h1>
    </div>

    <div class="card monitoring-card">
        <div class="monitoring-filters">
            <div class="form-group">
                <label>Device</label>
                <select id="deviceSelect" class="monitoring-select">
                    <option value="">-- Pilih Device --</option>
                </select>
            </div>

            <div class="form-group">
                <label>Interface (SFP)</label>
                <select id="interfaceSelect" class="monitoring-select">
                    <option value="">-- Pilih Interface --</option>
                </select>
            </div>
        </div>

        <div class="range-select-mobile">
            <div class="form-group">
                <label>Range Waktu</label>
                <select id="rangeSelectMobile" class="monitoring-select">
                    <option value="1h">1 Jam</option>
                    <option value="1d">1 Hari</option>
                    <option value="3d">3 Hari</option>
                    <option value="7d">1 Minggu</option>
                    <option value="30d">1 Bulan</option>
                    <option value="1y">1 Tahun</option>
                </select>
            </div>
        </div>

        <div class="range-buttons">
            <button class="btn btn-range active" data-range="1h">
                <i class="fas fa-clock"></i> 1 Jam
            </button>
            <button class="btn btn-range" data-range="1d">
                <i class="fas fa-sun"></i> 1 Hari
            </button>
            <button class="btn btn-range" data-range="3d">
                <i class="fas fa-calendar-days"></i> 3 Hari
            </button>
            <button class="btn btn-range" data-range="7d">
                <i class="fas fa-calendar-week"></i> 1 Minggu
            </button>
            <button class="btn btn-range" data-range="30d">
                <i class="fas fa-calendar"></i> 1 Bulan
            </button>
            <button class="btn btn-range" data-range="1y">
                <i class="fas fa-calendar-alt"></i> 1 Tahun
            </button>
        </div>

        <div id="ifaceInfo" class="interface-info">
            Interface: -
        </div>

        <div id="rxStats" class="rx-stats">
            Now: - dBm | Avg: - dBm | Min: - dBm | Max: - dBm
        </div>

        <div class="chart-container">
            <canvas id="opticalChart"></canvas>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        /* Mobile: replace range buttons with a compact dropdown */
        .range-select-mobile { display: none; }
        @media (max-width: 768px) {
            .range-buttons { display: none !important; }
            .range-select-mobile { display: block; margin-top: 0.75rem; }

            /* Mobile: make chart feel full-width inside the card */
            .monitoring-card .chart-container {
                margin-left: -16px;
                margin-right: -16px;
                border-left: none;
                border-right: none;
                border-radius: 0;
                height: 360px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="{{ asset('assets/js/monitoring.js') }}?v={{ filemtime(public_path('assets/js/monitoring.js')) }}"></script>
@endpush
