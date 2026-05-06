@extends('layouts.app')


@push('styles')
<style>
/* ══════════════════════════════════════════
   DEVICES PAGE — Cyber Theme
══════════════════════════════════════════ */

/* ── Tabs ─────────────────────────────────── */
.dev-tabs {
    display: flex;
    gap: 8px;
    padding: 4px 0 0;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.dev-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 10px 10px 0 0;
    border: 1px solid transparent;
    border-bottom: none;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    font-size: 0.83rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    position: relative;
    bottom: -1px;
}
.dev-tab:hover {
    background: var(--ink-2);
    color: var(--text, #f1f5f9);
}
.dev-tab.active {
    background: var(--surface);
    border-color: var(--border);
    border-bottom-color: var(--surface, var(--bg, #fff));
    color: var(--primary);
    box-shadow: inset 0 -2px 0 0 var(--primary);
}

/* Tab content */
.tab-content { display: none; }
.tab-content.active { display: block; }

/* ── Device Table Card ────────────────────── */
.dev-card {
    background: var(--surface, rgba(255,255,255,.04));
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
}
.dev-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--primary-gradient);
}

.dev-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 12px;
    border-bottom: 1px solid rgba(99,102,241,.1);
}
.dev-card-head h3 {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    display: flex;
    align-items: center;
    gap: 8px;
}
.dev-card-head h3 i { color: var(--primary); }

/* ── Table enhancements ───────────────────── */
.dev-table-wrap { overflow-x: auto; }

.table th {
    font-size: 0.67rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.1em !important;
    text-transform: uppercase !important;
    color: var(--text-muted, #94a3b8) !important;
    padding: 10px 14px !important;
    border-bottom: 1px solid var(--ink-3) !important;
}
.table td {
    padding: 11px 14px !important;
    vertical-align: middle !important;
    border-bottom: 1px solid var(--ink-2) !important;
    font-size: 0.83rem;
}
.table tbody tr:last-child td { border-bottom: none !important; }
.table tbody tr:hover td {
    background: var(--ink-1) !important;
}

/* Device name with icon */
.dev-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.dev-icon {
    width: 32px; height: 32px;
    border-radius: 9px;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.75rem;
    flex-shrink: 0;
    box-shadow: 0 4px 10px var(--ink-4);
}
.dev-name { font-weight: 700; color: var(--text, #f1f5f9); font-size: 0.85rem; }
.dev-ip   { font-size: 0.72rem; color: var(--text-muted, #94a3b8); font-family: 'Space Mono', monospace; margin-top: 1px; }

/* SNMP version badge */
.snmp-badge {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 3px 8px;
    border-radius: 6px;
    font-family: 'Space Mono', monospace;
}
.snmp-v2c { background: rgba(6,182,212,.12); color: #22d3ee; border: 1px solid rgba(6,182,212,.25); }
.snmp-v3  { background: rgba(139,92,246,.12); color: #a78bfa; border: 1px solid rgba(139,92,246,.25); }

/* Status badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    padding: 4px 10px;
    border-radius: 99px;
}
.status-badge::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-ok {
    background: rgba(16,185,129,.1);
    color: #10b981;
    border: 1px solid rgba(16,185,129,.25);
}
.status-ok::before { background: #10b981; box-shadow: 0 0 6px #10b981; }

.status-failed {
    background: rgba(239,68,68,.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,.25);
    animation: status-pulse 2s ease-in-out infinite;
}
.status-failed::before { background: #ef4444; box-shadow: 0 0 6px #ef4444; }

.status-inactive {
    background: rgba(100,116,139,.1);
    color: #64748b;
    border: 1px solid rgba(100,116,139,.2);
}
.status-inactive::before { background: #64748b; }

@keyframes status-pulse {
    0%,100% { box-shadow: none; }
    50% { box-shadow: 0 0 0 3px rgba(239,68,68,.15); }
}

/* ── Monitoring / Discovery Tab ───────────── */
.mon-discover-card {
    background: var(--surface, rgba(255,255,255,.04));
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
}
.mon-discover-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--primary), #34d399);
}
.mon-discover-head {
    padding: 16px 20px 14px;
    border-bottom: 1px solid rgba(99,102,241,.1);
}
.mon-discover-head h3 {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    display: flex; align-items: center; gap: 8px;
}
.mon-discover-head h3 i { color: #34d399; }
.mon-discover-head p {
    font-size: 0.78rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 4px;
}

.mon-discover-controls {
    padding: 16px 20px;
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.mon-discover-controls .form-group { flex: 1; min-width: 200px; margin-bottom: 0; }
.mon-discover-controls .form-group label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted, #94a3b8);
    display: block;
    margin-bottom: 6px;
}

.discover-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.monitoring-placeholder {
    text-align: center;
    padding: 48px 24px;
    color: var(--text-muted, #94a3b8);
}
.monitoring-placeholder i { font-size: 2.5rem; margin-bottom: 12px; display: block; opacity: .4; }
.monitoring-placeholder p { font-size: 0.85rem; }

/* ── Modal polish ─────────────────────────── */
.modal-box h3 {
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.modal-box .form-group label {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted, #94a3b8);
    display: block;
    margin-bottom: 5px;
}

/* ── Mobile ───────────────────────────────── */
@media (max-width: 768px) {
    .dev-tabs { gap: 4px; padding: 0; }
    .dev-tab  { padding: 8px 12px; font-size: 0.78rem; }
    .mon-discover-controls { flex-direction: column; }
    .mon-discover-controls .form-group { min-width: 100%; }
}
</style>
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
