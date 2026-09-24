@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/monitoring.css') }}?v={{ filemtime(public_path('assets/css/pages/monitoring.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/interfaces.css') }}?v={{ filemtime(public_path('assets/css/pages/interfaces.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/sla.css') }}?v={{ filemtime(public_path('assets/css/pages/sla.css')) }}">
@endpush

@section('content')
<div class="mon-wrap if-wrap">

    <div class="sla-toolbar">
        <div class="sla-ranges">
            <button type="button" class="sla-range" data-days="7">7d</button>
            <button type="button" class="sla-range active" data-days="30">30d</button>
            <button type="button" class="sla-range" data-days="90">90d</button>
        </div>
        <select id="slaDevice" class="sla-select"><option value="">All devices</option></select>
        <input type="text" id="slaSearch" class="sla-input" placeholder="Search interface / alias…">
        <button type="button" id="slaExport" class="sla-export"><i class="fas fa-file-csv"></i> CSV</button>
        <button type="button" id="slaExportPdf" class="sla-export"><i class="fas fa-file-pdf"></i> PDF</button>
        <label class="pm-toggle sla-pm-toggle">
            <input type="checkbox" id="slaIncludeUnmonitored"> Sertakan port tidak dipakai
        </label>
    </div>

    <div class="sla-stats">
        <div class="sla-stat"><span class="sla-stat-k">Interfaces with downs</span><span class="sla-stat-v" id="slaStatIfaces">—</span></div>
        <div class="sla-stat"><span class="sla-stat-k">Total down events</span><span class="sla-stat-v" id="slaStatEvents">—</span></div>
        <div class="sla-stat"><span class="sla-stat-k">Total downtime</span><span class="sla-stat-v" id="slaStatDowntime">—</span></div>
    </div>

    <div class="mon-chart-card if-table-card">
        <div class="mon-chart-card__head">
            <div class="mon-chart-card__title">
                <i class="fas fa-chart-column" style="color:var(--primary)"></i>
                Interface Down / Uptime <span id="slaPeriodLabel" class="sla-period">last 30 days</span>
            </div>
        </div>

        <div class="if-table-wrap">
            <table class="table" id="slaTable">
                <thead>
                    <tr>
                        <th style="width:18%">Device</th>
                        <th style="width:22%">Interface</th>
                        <th style="width:9%;text-align:center">Downs</th>
                        <th style="width:14%;text-align:center">Total downtime</th>
                        <th style="width:12%;text-align:center">Availability</th>
                        <th style="width:17%">Last down</th>
                        <th style="width:8%;text-align:center"></th>
                    </tr>
                </thead>
                <tbody id="slaTableBody">
                    <tr><td colspan="7" class="if-empty"><i class="fas fa-circle-notch fa-spin"></i> Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Port yang down terus-menerus lama: kemungkinan sudah tidak dipakai --}}
    <div class="mon-chart-card if-table-card sla-candidates-card" id="slaCandidatesCard" hidden>
        <div class="mon-chart-card__head">
            <div class="mon-chart-card__title">
                <i class="fas fa-eye-slash" style="color:var(--primary)"></i>
                Kandidat tidak dipakai
                <span id="slaCandidatesLabel" class="sla-period"></span>
            </div>
            <button type="button" id="slaMarkAll" class="sla-export" hidden>
                <i class="fas fa-eye-slash"></i> Tandai semua
            </button>
        </div>
        <p class="sla-candidates-help">
            Port di bawah masih dipantau tetapi down tanpa henti cukup lama, sehingga terus menurunkan
            angka SLA. Kalau port itu memang sudah tidak dipakai, tandai supaya keluar dari laporan,
            dashboard, dan alert. Status &amp; RX-nya tetap dibaca: port yang hidup lagi akan diberi
            tanda <b>Aktif kembali</b> di halaman Interfaces.
        </p>
        <div class="if-table-wrap">
            <table class="table" id="slaCandidatesTable">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Interface</th>
                        <th style="text-align:center">Down sejak</th>
                        <th style="text-align:center">Lama</th>
                        <th style="text-align:center">Status sekarang</th>
                        <th style="text-align:center"></th>
                    </tr>
                </thead>
                <tbody id="slaCandidatesBody"></tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/sla.js') }}?v={{ filemtime(public_path('assets/js/sla.js')) }}"></script>
@endpush
