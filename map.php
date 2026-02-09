<?php
require_once 'includes/layout_start.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'technician', 'viewer'])) {
    echo '<div class="alert error">Access denied</div>';
    require_once 'includes/layout_end.php';
    exit;
}
?>

<div class="topbar">
    <div class="topbar-content">
        <h1><i class="fas fa-map-marked-alt"></i> Network Map</h1>
        <div class="topbar-actions">
            <button class="btn btn-outline" id="lockBtn">
                <i class="fas fa-lock-open"></i> Unlocked
            </button>
            <button class="btn btn-outline" onclick="openAddLinkModal()">
                <i class="fas fa-link"></i> Add Connection
            </button>
            <button class="btn btn-outline" id="lineEditBtn">
                <i class="fas fa-bezier-curve"></i> Edit Lines
            </button>
            <button class="btn action-create" onclick="openAddNodeModal()">
                <i class="fas fa-plus"></i> Add Node
            </button>
            <button class="btn btn-primary" onclick="refreshMap()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- Modal Add Connection -->
<div class="modal" id="addLinkModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeLinkModal()">&times;</button>
        <h3><i class="fas fa-link"></i> Add Connection</h3>

        <div class="form-group">
            <label>Node A</label>
            <select id="linkNodeA" class="monitoring-select"></select>
        </div>

        <div class="form-group">
            <label>Interface A</label>
            <select id="linkInterfaceA" class="monitoring-select"></select>
        </div>

        <div class="form-group">
            <label>Node B</label>
            <select id="linkNodeB" class="monitoring-select"></select>
        </div>

        <div class="form-group">
            <label>Interface B</label>
            <select id="linkInterfaceB" class="monitoring-select"></select>
        </div>

        <div class="form-group">
            <label>Attenuation (dB)</label>
            <input type="number" step="0.01" id="linkAttenuation" placeholder="e.g. -18.50">
        </div>

        <div class="form-group">
            <label>Notes</label>
            <input type="text" id="linkNotes" placeholder="Optional">
        </div>

        <div class="modal-actions">
            <button class="btn btn-primary" onclick="addConnection()">
                <i class="fas fa-link"></i> Save Connection
            </button>
            <button class="btn btn-outline" onclick="closeLinkModal()">
                Cancel
            </button>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label>Existing Connections</label>
            <div id="linkList" class="connection-list"></div>
        </div>
    </div>
</div>

<div class="map-container">
    <!-- Map Controls -->
    <button id="mapControlToggle" class="map-control-btn">
        <i class="fas fa-sliders-h"></i>
    </button>
    <div class="map-controls">
        <div class="control-group">
            <div class="control-title">Map Layers</div>
            <div class="control-item">
                <input type="checkbox" id="showGrid">
                <label for="showGrid">Show Grid</label>
            </div>
            <div class="control-item">
                <input type="checkbox" id="showConnections" checked>
                <label for="showConnections">Connections</label>
            </div>
        </div>

        <div class="control-group">
            <div class="control-title">Node Filters</div>
            <div class="control-item">
                <select id="statusFilter" onchange="filterNodes()">
                    <option value="all">All Status</option>
                    <option value="up">Up Only</option>
                    <option value="down">Down Only</option>
                </select>
            </div>
            <div class="control-item">
                <select id="deviceFilter" onchange="filterNodes()">
                    <option value="all">All Devices</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Map Area -->
    <div id="networkMap"></div>
    <div id="gridOverlay" class="grid-layer" aria-hidden="true"></div>
</div>

<!-- Node Detail Sidebar -->
<div id="nodeSidebar" class="node-sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-server"></i> Node Details</h3>
        <button class="sidebar-close" onclick="closeNodeSidebar()">&times;</button>
    </div>
    <div class="sidebar-content" id="nodeDetailContent">
        <div class="sidebar-placeholder">
            <i class="fas fa-mouse-pointer fa-2x"></i>
            <p>Click on a node to view details</p>
        </div>
    </div>
</div>

<!-- Modal Add Node -->
<div class="modal" id="addNodeModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3><i class="fas fa-map-pin"></i> Add Network Node</h3>

        <div class="form-group">
            <label>Select Device</label>
            <select id="nodeDeviceSelect" class="monitoring-select">
                <option value="">-- Select Device --</option>
            </select>
        </div>

        <div class="form-group">
            <label>Node Name</label>
            <input type="text" id="nodeName" placeholder="Node Display Name">
        </div>

        <div class="form-group">
            <label>Node Type</label>
            <select id="nodeType">
                <option value="router">Router</option>
                <option value="switch">Switch</option>
                <option value="firewall">Firewall</option>
                <option value="ap">Access Point</option>
                <option value="server">Server</option>
                <option value="client">Client</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>X Position</label>
                <input type="number" id="nodeX" placeholder="0" min="0" max="1000">
            </div>
            <div class="form-group">
                <label>Y Position</label>
                <input type="number" id="nodeY" placeholder="0" min="0" max="800">
            </div>
        </div>

        <div class="form-group">
            <label>Custom Icon</label>
            <select id="nodeIcon">
                <option value="router">Router Icon</option>
                <option value="switch">Switch Icon</option>
                <option value="server">Server Icon</option>
                <option value="cloud">Cloud Icon</option>
                <option value="ap">AP Icon</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn btn-primary" onclick="addNode()">
                <i class="fas fa-plus"></i> Add Node
            </button>
            <button class="btn btn-outline" onclick="closeModal()">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Edit Node Modal -->
<div class="modal" id="editNodeModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeEditModal()">&times;</button>
        <h3><i class="fas fa-edit"></i> Edit Node</h3>

        <input type="hidden" id="editNodeId">

        <div class="form-group">
            <label>Node Name</label>
            <input type="text" id="editNodeName">
        </div>

        <div class="form-group">
            <label>Node Type</label>
            <select id="editNodeType">
                <option value="router">Router</option>
                <option value="switch">Switch</option>
                <option value="firewall">Firewall</option>
                <option value="ap">Access Point</option>
                <option value="server">Server</option>
                <option value="client">Client</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>X Position</label>
                <input type="number" step="any" id="editNodeX">
            </div>
            <div class="form-group">
                <label>Y Position</label>
                <input type="number" step="any" id="editNodeY">
            </div>
        </div>

        <div class="form-group">
            <label>Lock Position</label>
            <select id="editNodeLocked">
                <option value="0">Unlocked</option>
                <option value="1">Locked</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn btn-primary" onclick="updateNode()">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button class="btn btn-danger" onclick="deleteNode()">
                <i class="fas fa-trash"></i> Delete Node
            </button>
            <button class="btn btn-outline" onclick="closeEditModal()">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .map-container {
        position: relative;
        height: calc(100vh - 350px);
        width: 100%;
        min-height: 500px;
        background: var(--bg-secondary);
    }

    #networkMap {
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    .map-controls {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 1000;

        background: rgba(255, 255, 255, .95);
        backdrop-filter: blur(12px);
        border-radius: 14px;
        padding: 18px;
        width: 260px;

        box-shadow: 0 8px 30px rgba(0, 0, 0, .12);
        border: 1px solid rgba(255, 255, 255, .3);

        opacity: 0;
        pointer-events: none;
        transform: translateY(-10px) scale(.95);
        transition: .25s ease;
    }

    .leaflet-top.leaflet-right {
        top: 70px;
    }

    .map-controls.open {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }

    .map-control-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 1001;

        width: 44px;
        height: 44px;
        border-radius: 12px;

        background: white;
        border: none;
        cursor: pointer;

        box-shadow: 0 6px 20px rgba(0, 0, 0, .15);

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 18px;
        color: #6366f1;

        transition: .2s;
    }

    .map-control-btn:hover {
        transform: scale(1.1);
    }

    .leaflet-control-fullscreen {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #6366f1;
    }

    .leaflet-control-fullscreen:hover {
        transform: scale(1.06);
    }

    .control-group {
        margin-bottom: 25px;
    }

    .control-group:last-child {
        margin-bottom: 0;
    }

    .control-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .control-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        padding: 8px 12px;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        transition: background 0.3s;
    }

    .control-item:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .control-item label {
        margin-left: 10px;
        cursor: pointer;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .control-item select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 6px;
        background: white;
        font-size: 14px;
    }

    .node-sidebar {
        position: fixed;
        right: -460px;
        top: 80px;
        width: 460px;
        height: calc(100vh - 100px);
        background: rgba(255, 255, 255, 0.98);
        border-left: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: -5px 0 30px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
    }

    .node-sidebar.open {
        right: 0;
    }

    .sidebar-header {
        padding: 25px 25px 15px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-header h3 {
        margin: 0;
        font-size: 20px;
        color: var(--text-primary);
    }

    .sidebar-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s;
    }

    .sidebar-close:hover {
        background: rgba(0, 0, 0, 0.1);
        color: var(--text-primary);
    }

    .sidebar-content {
        padding: 28px;
    }

    .node-info {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .node-header {
        display: grid;
        grid-template-columns: 52px 1fr auto;
        align-items: center;
        gap: 12px;
    }

    .node-icon-large {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    }

    .node-icon-large.router,
    .node-icon-large.switch,
    .node-icon-large.firewall,
    .node-icon-large.ap,
    .node-icon-large.server,
    .node-icon-large.client,
    .node-icon-large.cloud {
        background: var(--primary-gradient);
    }

    .node-title h4 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--text-primary);
    }

    .node-subtitle {
        margin: 2px 0 0;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .node-details {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .detail-item {
        background: rgba(0, 0, 0, 0.03);
        border-radius: 12px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .detail-item label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .detail-item span {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9rem;
        word-break: break-word;
    }

    .node-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .interfaces-section h4 {
        margin: 0 0 10px;
        font-size: 1rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .interfaces-section .table-responsive {
        padding: 12px;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .interfaces-section .table {
        margin: 0;
    }

    .interfaces-section .table {
        font-size: 0.85rem;
    }

    .connection-list {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 10px;
        max-height: 220px;
        overflow: auto;
        padding: 8px;
        background: rgba(0, 0, 0, 0.02);
    }

    .connection-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.06);
        margin-bottom: 8px;
    }

    .connection-item:last-child {
        margin-bottom: 0;
    }

    .connection-actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .connection-item small {
        color: var(--text-secondary);
    }

    .connection-line.editable {
        cursor: pointer;
    }

    .line-handle {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #ffffff;
        border: 2px solid #6366f1;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .interfaces-section code {
        background: rgba(99, 102, 241, 0.1);
        padding: 2px 6px;
        border-radius: 6px;
        color: #4f46e5;
    }

    .iface-name.power-good {
        color: var(--success);
        background: rgba(16, 185, 129, 0.1);
    }

    .iface-name.power-warning {
        color: var(--warning);
        background: rgba(245, 158, 11, 0.12);
    }

    .iface-name.power-critical {
        color: var(--danger);
        background: rgba(239, 68, 68, 0.12);
    }

    .power-value.power-good {
        color: var(--success);
    }

    .power-value.power-warning {
        color: var(--warning);
    }

    .power-value.power-critical {
        color: var(--danger);
    }

    @media (max-width: 1200px) {
        .node-sidebar {
            width: 420px;
            right: -420px;
        }
    }

    .sidebar-placeholder {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-tertiary);
    }

    .sidebar-placeholder i {
        color: var(--primary);
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .node-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        transform-origin: center center !important;
        will-change: transform;
        cursor: move;
        transition: all 0.3s;
        z-index: 100;
    }

    .node-marker:hover {
        z-index: 10000 !important;
    }

    .node-marker.locked {
        cursor: default;
    }

    .leaflet-node-label {
        background: rgba(0, 0, 0, .75);
        color: #fff;
        border-radius: 6px;
        padding: 4px 8px;
        border: none;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .3);
    }

    .leaflet-tooltip-top:before {
        border-top-color: rgba(0, 0, 0, .75);
    }

    .node-icon {
        width: 32px;
        height: 32px;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
        transform-origin: center center;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: white;
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
        transition: all 0.2s ease;
        position: relative;
    }

    .node-icon::after {
        content: "";
        position: absolute;
        inset: -2px;
        border-radius: 50% 50% 50% 0;
        border: 2px solid white;
        pointer-events: none;
    }

    .node-icon i {
        transform: rotate(45deg);
    }

    .node-icon.router,
    .node-icon.switch,
    .node-icon.firewall,
    .node-icon.ap,
    .node-icon.server,
    .node-icon.client,
    .node-icon.cloud {
        background: var(--primary-gradient);
    }

    .node-icon:hover {
        transform: rotate(-45deg) scale(1.1);
    }

    .node-status {
        position: absolute;
        top: -6px;
        right: -6px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid white;
        transform: rotate(45deg);
    }

    .node-status.up {
        background: #10b981;
    }

    .node-status.down {
        background: #ef4444;
    }

    .node-status.warning {
        background: #f59e0b;
    }

    .node-label {
        position: absolute;
        top: 42px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
        z-index: 9999;
    }

    .node-marker:hover .node-label {
        opacity: 1;
    }

    .node-marker.show-label .node-label {
        opacity: 1;
    }

    .node-marker.highlighted .node-icon {
        transform: scale(1.1);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.35), 0 10px 24px rgba(99, 102, 241, 0.35);
    }

    .connection-line {
        stroke-width: 3;
        stroke-dasharray: 6 6;
        animation: dash 20s linear infinite;
    }

    @keyframes dash {
        to {
            stroke-dashoffset: -1000;
        }
    }

    .grid-layer {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 2;
        background-image:
            linear-gradient(rgba(0, 0, 0, 0.1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0, 0, 0, 0.1) 1px, transparent 1px);
        background-size: 50px 50px;
    }

    @media (max-width: 768px) {
        .map-controls {
            width: 250px;
            top: 10px;
            right: 10px;
            padding: 15px;
        }

        .node-sidebar {
            width: 100%;
            right: -100%;
        }

        .node-header {
            grid-template-columns: 44px 1fr;
        }

        .node-header .badge {
            grid-column: 1 / -1;
            justify-self: flex-start;
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/map.js"></script>

<?php
require_once 'includes/layout_end.php';
?>
