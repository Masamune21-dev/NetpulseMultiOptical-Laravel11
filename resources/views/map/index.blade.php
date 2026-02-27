@extends('layouts.app')

@section('content')
    <div class="topbar">
        <div class="topbar-content">
            <h1><i class="fas fa-map-marked-alt"></i> Network Map</h1>
            <div class="topbar-actions">
                <button class="btn btn-outline" id="lockBtn">
                    <i class="fas fa-lock-open"></i> Unlocked
                </button>
                <button class="btn btn-outline" onclick="openAddLinkModal()">
                    <i class="fas fa-link"></i> Connection
                </button>
                <button class="btn btn-outline" id="lineEditBtn">
                    <i class="fas fa-bezier-curve"></i> Edit Lines
                </button>
                <button class="btn action-create" onclick="openAddNodeModal()">
                    <i class="fas fa-plus"></i> Add Node
                </button>
                <button class="btn btn-outline" onclick="refreshMap()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>
    </div>

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

        <div id="networkMap"></div>

        <div class="node-sidebar" id="nodeSidebar">
            <div class="sidebar-header">
                <h3>Node Details</h3>
                <button class="sidebar-close" onclick="closeNodeSidebar()">&times;</button>
            </div>
            <div class="sidebar-content" id="nodeDetailContent">
                <div class="node-info" id="nodeInfo">
                    <div class="node-header">
                        <div class="node-icon-large router">
                            <i class="fas fa-router"></i>
                        </div>
                        <div class="node-title">
                            <h4 id="sidebarNodeName">-</h4>
                            <div class="node-subtitle" id="sidebarNodeType">-</div>
                        </div>
                        <button class="btn btn-outline" onclick="openEditModal()">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <div class="node-details" id="nodeDetails"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addNodeModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3><i class="fas fa-plus"></i> Add Node</h3>

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

@endsection

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
            @media (max-width: 768px) {
                /* Map topbar: make action buttons a 3-column grid on phones. */
                .topbar .topbar-content {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 10px;
                }

                .topbar .topbar-actions {
                    width: 100%;
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 8px;
                }

                .topbar .topbar-actions .btn {
                    width: 100%;
                    justify-content: center;
                    white-space: nowrap;
                }
            }

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

        .connection-line {
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .connection-line:not(.editable) {
            animation: connection-dash 0.35s linear infinite;
        }

        .connection-line.editable {
            animation: none;
        }

        @keyframes connection-dash {
            from {
                stroke-dashoffset: 12;
            }
            to {
                stroke-dashoffset: 0;
            }
        }

        .power-good {
            color: #16a34a;
            font-weight: 600;
        }

        .power-warning {
            color: #f59e0b;
            font-weight: 700;
        }

        .power-critical {
            color: #ef4444;
            font-weight: 700;
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
            width: 52px;
            height: 52px;
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
            padding: 10px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .node-actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }

        .node-actions .btn {
            width: 100%;
            margin: 0 !important;
            padding: 10px 8px !important;
            min-height: 64px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
            white-space: normal;
            line-height: 1.1;
        }

        .node-actions .btn i {
            width: auto;
            height: auto;
            margin: 0;
            font-size: 16px;
        }

        .custom-node-marker {
            background: transparent;
            border: none;
        }

        .node-marker {
            position: relative;
            width: 46px;
            height: 46px;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
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
            color: #fff;
            font-size: 16px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            will-change: transform;
        }

        .node-icon::after {
            content: "";
            position: absolute;
            inset: -2px;
            border-radius: 50% 50% 50% 0;
            border: 2px solid #fff;
            pointer-events: none;
        }

        .node-icon i {
            /* Cancel the container rotation so the icon stays upright */
            transform: rotate(45deg);
        }

        .node-marker:hover .node-icon {
            transform: rotate(-45deg) scale(1.1);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.28);
        }

        .node-marker.highlighted .node-icon {
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.35), 0 14px 28px rgba(0, 0, 0, 0.25);
        }

        .node-status {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            border: 2px solid #fff;
            /* Cancel the container rotation so the dot stays aligned */
            transform: rotate(45deg);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .node-status.up {
            background: #22c55e;
        }

        .node-status.down {
            background: #ef4444;
        }

        .node-status.warning {
            background: #f59e0b;
        }

        .leaflet-node-label {
            background: rgba(15, 23, 42, 0.9);
            color: #fff;
            border-radius: 10px;
            padding: 6px 10px;
            font-size: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .leaflet-node-label::before {
            border-top-color: rgba(15, 23, 42, 0.9) !important;
        }

        /* Mobile fixes: sidebar width was fixed 460px which breaks on phones */
        @media (max-width: 768px) {
            .map-controls {
                top: 12px;
                right: 12px;
                width: calc(100vw - 24px);
                max-width: 320px;
            }

            .map-control-btn {
                top: 12px;
                right: 12px;
            }

            .leaflet-top.leaflet-right {
                top: 58px;
            }

            .node-sidebar {
                top: 0;
                right: -100%;
                width: 100%;
                height: 100vh;
                border-left: none;
                border-radius: 0;
            }

            .node-sidebar.open {
                right: 0;
            }

            .sidebar-header {
                padding: 18px 18px 12px;
            }

            .sidebar-content {
                padding: 18px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .node-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .node-actions .btn {
                min-height: 56px;
                padding: 9px 8px !important;
                font-size: 12px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('assets/js/map.js') }}?v={{ filemtime(public_path('assets/js/map.js')) }}"></script>
@endpush
