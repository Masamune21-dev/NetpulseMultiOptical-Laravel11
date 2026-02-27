@extends('layouts.app')

@section('content')
    <div class="topbar">
        <div class="topbar-content">
            <h1>Data Devices</h1>
            <button class="btn action-create" onclick="openAddDevice()">
                <i class="fas fa-plus"></i> Add Device
            </button>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="openTab('snmp')">
            <i class="fas fa-sliders-h"></i> SNMP Configuration
        </button>
        <button class="tab" onclick="openTab('monitoring')">
            <i class="fas fa-wave-square"></i> Interfaces Discovery
        </button>
    </div>

    <div id="snmp" class="tab-content active">
        <div class="card">
            <h3>
                <i class="fas fa-sliders-h"></i>
                SNMP Devices
            </h3>
            <div class="table-responsive">
                <table class="table" id="deviceTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>IP</th>
                            <th>SNMP</th>
                            <th>Auth</th>
                            <th>Status</th>
                            <th width="180">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="monitoring" class="tab-content">
        <div class="card monitoring-card">
            <div class="monitoring-header">
                <h3>
                    <i class="fas fa-fiber-optic"></i>
                    Interfaces Discovery
                </h3>
                <p class="monitoring-subtitle">Pilih device untuk melihat data SFP & traffic</p>
            </div>

            <div class="monitoring-controls">
                <div class="form-group">
                    <label>Select Device</label>
                    <select id="monitorDeviceSelect" class="monitoring-select" onchange="selectMonitoringDevice()">
                        <option value="">-- Pilih Device --</option>
                    </select>
                </div>

                <button class="btn btn-theme-gradient discover-btn" onclick="discoverSelectedInterfaces()">
                    <i class="fas fa-magnifying-glass"></i>
                    Discover Interfaces
                </button>
            </div>

            <div id="monitoringContent" class="monitoring-content">
                <div class="monitoring-placeholder">
                    <i class="fas fa-fiber-optic fa-2x"></i>
                    <p>Select a device to view SFP/Interface data</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="deviceModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>

            <h3 id="deviceTitle">Add Device</h3>

            <input id="device_id" type="hidden">

            <div class="form-group">
                <label>Device Name</label>
                <input id="device_name" placeholder="CRS-326">
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
                <button class="btn btn-outline" onclick="closeModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/devices.js') }}?v={{ filemtime(public_path('assets/js/devices.js')) }}"></script>
@endpush
