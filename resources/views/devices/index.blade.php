@extends('layouts.app')


@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/devices.css') }}?v={{ filemtime(public_path('assets/css/pages/devices.css')) }}">
@endpush

@section('content')

{{-- Tabs --}}
<div class="dev-tabs">
    <button class="dev-tab active" onclick="openTab('snmp')">
        <i class="fas fa-sliders-h"></i> SNMP Config
    </button>
    <button class="dev-tab" onclick="openTab('monitoring')">
        <i class="fas fa-wave-square"></i> Interface Discovery
    </button>
</div>

{{-- SNMP Tab --}}
<div id="snmp" class="tab-content active">
    <div class="dev-card">
        <div class="dev-card-head">
            <h3><i class="fas fa-server"></i> SNMP Devices</h3>
            <button class="btn action-create" onclick="openAddDevice()" title="Add Device" style="width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:9px;flex-shrink:0">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="dev-table-wrap">
            <table class="table" id="deviceTable">
                <thead>
                    <tr>
                        <th style="width:30%">Device</th>
                        <th style="width:14%">IP Address</th>
                        <th style="width:8%;text-align:center">SNMP</th>
                        <th style="width:14%;text-align:center">Auth</th>
                        <th style="width:12%;text-align:center">Status</th>
                        <th style="width:22%;text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- filled by devices.js -->
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Discovery Tab --}}
<div id="monitoring" class="tab-content">
    <div class="mon-discover-card">
        <div class="mon-discover-head">
            <h3><i class="fas fa-circle-nodes"></i> Interface Discovery</h3>
            <p>Pilih device untuk melihat data SFP & traffic interface</p>
        </div>
        <div class="mon-discover-controls">
            <div class="form-group">
                <label><i class="fas fa-server"></i> &nbsp;Select Device</label>
                <select id="monitorDeviceSelect" class="monitoring-select" onchange="selectMonitoringDevice()">
                    <option value="">— Pilih Device —</option>
                </select>
            </div>
            <button class="btn btn-theme-gradient discover-btn" onclick="discoverSelectedInterfaces()">
                <i class="fas fa-magnifying-glass"></i>
                Discover Interfaces
            </button>
        </div>
        <div id="monitoringContent" class="monitoring-content">
            <div class="monitoring-placeholder">
                <i class="fas fa-circle-nodes"></i>
                <p>Pilih device untuk melihat data SFP / Interface</p>
            </div>
        </div>
    </div>
</div>

{{-- Add/Edit Device Modal --}}
<div class="modal" id="deviceModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3><i class="fas fa-server" style="color:var(--primary)"></i> <span id="deviceTitle">Add Device</span></h3>

        <input id="device_id" type="hidden">

        <div class="form-group">
            <label>Device Name</label>
            <input id="device_name" placeholder="CRS-326-24G-2S+RM">
        </div>
        <div class="form-group">
            <label>IP Address</label>
            <input id="ip_address" placeholder="192.168.1.1">
        </div>
        <div class="form-group">
            <label>SNMP Version</label>
            <select id="snmp_version">
                <option value="2c">SNMP v2c</option>
                <option value="3">SNMP v3</option>
            </select>
        </div>
        <div class="form-group">
            <label>Community (v2c)</label>
            <input id="community" placeholder="public">
        </div>
        <div class="form-group">
            <label>User (v3)</label>
            <input id="snmp_user" placeholder="snmpuser">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="is_active">
                <option value="1">Active</option>
                <option value="0">Disabled</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn" onclick="saveDevice()">
                <i class="fas fa-save"></i> Save
            </button>
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/devices.js') }}?v={{ filemtime(public_path('assets/js/devices.js')) }}"></script>
<script>
// Override tab function to use new .dev-tab styling
(function () {
    var _origOpen = window.openTab;
    window.openTab = function (id) {
        // Toggle tab-content
        document.querySelectorAll('.tab-content').forEach(function (el) {
            el.classList.toggle('active', el.id === id);
        });
        // Toggle dev-tab active
        document.querySelectorAll('.dev-tab').forEach(function (btn) {
            var matches = btn.getAttribute('onclick') && btn.getAttribute('onclick').includes("'" + id + "'");
            btn.classList.toggle('active', matches);
        });
    };
})();
</script>
@endpush
