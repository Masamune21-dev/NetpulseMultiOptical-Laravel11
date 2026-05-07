@extends('layouts.app')

@section('content')

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

    {{-- Map Toolbar Card --}}
    <div class="map-toolbar-card">
        <span class="map-toolbar-label">
            <i class="fas fa-map-marked-alt"></i> Map Controls
        </span>
        <div class="map-toolbar-actions">
            <button class="btn btn-outline" id="lockBtn" style="white-space:nowrap">
                <i class="fas fa-lock-open"></i> Unlocked
            </button>
            <div class="map-toolbar-divider"></div>
            <button class="btn btn-outline" onclick="openAddLinkModal()" style="white-space:nowrap">
                <i class="fas fa-link"></i> Connection
            </button>
            <button class="btn btn-outline" id="lineEditBtn" style="white-space:nowrap">
                <i class="fas fa-bezier-curve"></i> Edit Lines
            </button>
            <div class="map-toolbar-divider"></div>
            <button class="btn action-create" onclick="openAddNodeModal()" style="white-space:nowrap">
                <i class="fas fa-plus"></i> Add Node
            </button>
            <button class="btn btn-outline" onclick="refreshMap()" style="white-space:nowrap">
                <i class="fas fa-sync"></i> Refresh
            </button>
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
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="{{ asset('assets/css/pages/map.css') }}?v={{ filemtime(public_path('assets/css/pages/map.css')) }}">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('assets/js/map.js') }}?v={{ filemtime(public_path('assets/js/map.js')) }}"></script>
@endpush
