@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/monitoring.css') }}?v={{ filemtime(public_path('assets/css/pages/monitoring.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/interfaces.css') }}?v={{ filemtime(public_path('assets/css/pages/interfaces.css')) }}">
@endpush

@section('content')
<div class="mon-wrap if-wrap">

    {{-- Filter Card --}}
    <div class="mon-filter-card">
        <div class="mon-filter-row if-filter-row">
            <div class="form-group">
                <label><i class="fas fa-server"></i> &nbsp;Device</label>
                <select id="ifFilterDevice" class="monitoring-select">
                    <option value="">All devices</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-signal"></i> &nbsp;Status</label>
                <select id="ifFilterStatus" class="monitoring-select">
                    <option value="all">All</option>
                    <option value="up">Up</option>
                    <option value="down">Down</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-magnifying-glass"></i> &nbsp;Search</label>
                <input type="text" id="ifFilterSearch" class="monitoring-select" placeholder="Name, alias, description...">
            </div>
            <div class="form-group if-filter-reset-group">
                <label>&nbsp;</label>
                <button class="btn btn-outline if-filter-reset" id="ifFilterReset" type="button">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="mon-chart-card if-table-card">
        <div class="mon-chart-card__head">
            <div class="mon-chart-card__title">
                <i class="fas fa-ethernet" style="color:var(--primary)"></i>
                Interface List
            </div>
            <span class="mon-refresh-badge if-count-badge" id="ifCount">0 interfaces</span>
        </div>

        <div class="if-table-wrap">
            <table class="table" id="ifTable">
                <thead>
                    <tr>
                        <th style="width:13%">Device</th>
                        <th style="width:11%">Interface</th>
                        <th style="width:15%">Description</th>
                        <th style="width:8%">RX (dBm)</th>
                        <th style="width:8%">TX (dBm)</th>
                        <th style="width:8%">Status</th>
                        <th style="width:8%">Speed</th>
                        <th style="width:13%">Traffic In/Out</th>
                        <th style="width:12%">Last seen</th>
                        <th style="width:5%;text-align:center">Action</th>
                    </tr>
                </thead>
                <tbody id="ifTableBody">
                    <tr>
                        <td colspan="10" class="if-empty">
                            <i class="fas fa-circle-notch fa-spin"></i> Loading interfaces...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="if-paginator">
            <div class="if-paginator-left">
                <label for="ifPerPage">Per page</label>
                <select id="ifPerPage">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                </select>
                <span class="if-meta" id="ifMeta">—</span>
            </div>
            <div class="if-paginator-right" id="ifPagerButtons">
                {{-- buttons injected by JS --}}
            </div>
        </div>
    </div>

</div>

{{-- Traffic History Modal --}}
<div class="modal if-traffic-modal" id="ifTrafficModal">
    <div class="modal-box if-traffic-modal-box">
        <button class="modal-close" onclick="ifCloseTrafficModal()">&times;</button>

        <div class="if-tm-head">
            <div class="if-tm-title">
                <i class="fas fa-circle-nodes"></i>
                <span id="ifTmIfName">—</span>
                <span class="if-tm-speed" id="ifTmSpeed">—</span>
            </div>
            <span class="if-tm-status" id="ifTmStatus">—</span>
        </div>
        <div class="if-tm-sub">
            <span id="ifTmAlias">—</span>
            <span class="if-tm-device" id="ifTmDevice"></span>
        </div>

        <div class="if-tm-ranges">
            <button type="button" class="if-tm-range active" data-range="1d">1d</button>
            <button type="button" class="if-tm-range" data-range="7d">7d</button>
            <button type="button" class="if-tm-range" data-range="30d">30d</button>
        </div>

        <div class="if-tm-chart-wrap">
            <canvas id="ifTrafficChart"></canvas>
            <div class="if-tm-loading" id="ifTmLoading">
                <i class="fas fa-circle-notch fa-spin"></i> Loading...
            </div>
        </div>

        <div class="if-tm-summary">
            <div class="if-tm-sum-row if-tm-sum-in">
                <span class="if-tm-sum-label"><i class="fas fa-arrow-down"></i> In</span>
                <span class="if-tm-sum-pair"><span class="if-tm-sum-key">Cur</span> <span id="ifTmInCur">—</span></span>
                <span class="if-tm-sum-pair"><span class="if-tm-sum-key">Avg</span> <span id="ifTmInAvg">—</span></span>
                <span class="if-tm-sum-pair"><span class="if-tm-sum-key">Max</span> <span id="ifTmInMax">—</span></span>
            </div>
            <div class="if-tm-sum-row if-tm-sum-out">
                <span class="if-tm-sum-label"><i class="fas fa-arrow-up"></i> Out</span>
                <span class="if-tm-sum-pair"><span class="if-tm-sum-key">Cur</span> <span id="ifTmOutCur">—</span></span>
                <span class="if-tm-sum-pair"><span class="if-tm-sum-key">Avg</span> <span id="ifTmOutAvg">—</span></span>
                <span class="if-tm-sum-pair"><span class="if-tm-sum-key">Max</span> <span id="ifTmOutMax">—</span></span>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="{{ asset('assets/js/interfaces.js') }}?v={{ filemtime(public_path('assets/js/interfaces.js')) }}"></script>
@endpush
