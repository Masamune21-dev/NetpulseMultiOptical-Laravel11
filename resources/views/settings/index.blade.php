@extends('layouts.app')

@section('content')
<div class="topbar">
    <h1>
        <i class="fas fa-gear"></i>
        Settings
    </h1>
</div>

<div class="tabs">
    <button class="tab active" onclick="openTab('telegram')">
        <i class="fab fa-telegram"></i> Telegram Bot
    </button>
    <button class="tab" onclick="openTab('alert')">
        <i class="fas fa-bell"></i> Alert
    </button>
    <button class="tab" onclick="openTab('theme')">
        <i class="fas fa-palette"></i> Theme
    </button>
    <button class="tab" onclick="openTab('logs')">
        <i class="fas fa-file-lines"></i> Logs
    </button>
</div>

<div class="tab-content active" id="telegram">
    <div class="card">
        <h3>
            <i class="fab fa-telegram"></i>
            Telegram Bot Settings
        </h3>

        <div class="form-group">
            <label>Bot Token</label>
            <input id="bot_token" placeholder="123456:ABC-DEF...">
        </div>

        <div class="form-group">
            <label>Chat ID</label>
            <input id="chat_id" placeholder="-100xxxxxxxx">
        </div>

        <div class="modal-actions">
            <button class="btn action-edit" onclick="saveTelegram()">
                <i class="fas fa-save"></i> Save
            </button>
            <button class="btn action-edit" style="background:#10b981" onclick="testTelegram()">
                <i class="fas fa-paper-plane"></i> Test Bot
            </button>
        </div>
    </div>
</div>

<div class="tab-content" id="alert">
    <div class="card">
        <h3>
            <i class="fas fa-bell"></i>
            Alert Settings
        </h3>

        <div class="alert-grid">
            <div class="alert-section">
                <div class="alert-section-title">Channels</div>

                <label class="switch-row">
                    <div class="switch-label">
                        <div class="switch-title">Telegram Alert</div>
                        <div class="switch-sub">Kirim alert ke Telegram (bot token & chat id).</div>
                    </div>
                    <div class="switch">
                        <input type="checkbox" id="alert_telegram_enabled">
                        <span class="slider"></span>
                    </div>
                </label>

                <label class="switch-row">
                    <div class="switch-label">
                        <div class="switch-title">Web UI Alert Log</div>
                        <div class="switch-sub">Simpan semua event alert ke log di Web UI.</div>
                    </div>
                    <div class="switch">
                        <input type="checkbox" id="alert_webui_enabled">
                        <span class="slider"></span>
                    </div>
                </label>
            </div>

            <div class="alert-section">
                <div class="alert-section-title">Event Types</div>

                <div class="pill-grid">
                    <label class="pill-check">
                        <input type="checkbox" id="alert_interface_down">
                        <span><i class="fas fa-link-slash"></i> Interface Down</span>
                    </label>
                    <label class="pill-check">
                        <input type="checkbox" id="alert_interface_up">
                        <span><i class="fas fa-link"></i> Interface Up</span>
                    </label>
                    <label class="pill-check">
                        <input type="checkbox" id="alert_interface_warning">
                        <span><i class="fas fa-triangle-exclamation"></i> RX Warning</span>
                    </label>
                    <label class="pill-check">
                        <input type="checkbox" id="alert_device_down">
                        <span><i class="fas fa-server"></i> Device Down</span>
                    </label>
                    <label class="pill-check">
                        <input type="checkbox" id="alert_device_up">
                        <span><i class="fas fa-arrow-up"></i> Device Up</span>
                    </label>
                </div>
            </div>

            <div class="alert-section alert-section-wide">
                <div class="alert-section-title">Threshold (Redaman / RX Power)</div>

                <div class="threshold-grid">
                    <div class="threshold-item">
                        <label>Warning High (dBm)</label>
                        <input type="number" step="0.1" id="alert_rx_warning_high" placeholder="-18.0">
                        <div class="help">Mulai warning jika RX lebih kecil atau sama dari nilai ini.</div>
                    </div>
                    <div class="threshold-item">
                        <label>Warning Low (dBm)</label>
                        <input type="number" step="0.1" id="alert_rx_warning_low" placeholder="-25.0">
                        <div class="help">Batas bawah warning (range -18 sampai -25).</div>
                    </div>
                    <div class="threshold-item">
                        <label>Down Threshold (dBm)</label>
                        <input type="number" step="0.1" id="alert_rx_down_threshold" placeholder="-40.0">
                        <div class="help">Jika RX lebih kecil atau sama dari ini, dianggap down.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn action-edit" onclick="saveAlerts()">
                <i class="fas fa-save"></i> Save Alerts
            </button>
            <button class="btn btn-outline" onclick="refreshAlertLogs()">
                <i class="fas fa-rotate"></i> Refresh Log
            </button>
        </div>
    </div>

    <div class="card" style="margin-top: 16px;">
        <h3>
            <i class="fas fa-list"></i>
            Alert Log (Web UI)
        </h3>

        <div class="form-group">
            <label>Latest Entries</label>
            <div class="log-toolbar">
                <input type="text" id="alertLogFilter" placeholder="Filter keyword (device, interface, message)...">
                <select id="alertTypeFilter">
                    <option value="all">All Types</option>
                    <option value="device_down">device_down</option>
                    <option value="device_up">device_up</option>
                    <option value="interface_down">interface_down</option>
                    <option value="interface_up">interface_up</option>
                    <option value="interface_warning">interface_warning</option>
                </select>
                <select id="alertSeverityFilter">
                    <option value="all">All Severity</option>
                    <option value="info">info</option>
                    <option value="warning">warning</option>
                    <option value="critical">critical</option>
                </select>
            </div>

            <div class="alert-log-box" id="alertLogBox">Loading...</div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-outline" onclick="refreshAlertLogs()">
                <i class="fas fa-rotate"></i> Refresh
            </button>
            <button class="btn btn-danger action-delete" onclick="clearAlertLogs()">
                <i class="fas fa-trash"></i> Clear Log
            </button>
        </div>
    </div>
</div>

<div class="tab-content" id="theme">
    <div class="card theme-card">
        <h3>
            <i class="fas fa-palette"></i>
            Theme
        </h3>

        <div class="theme-grid">
            <div class="theme-section">
                <div class="theme-section-title">Mode</div>
                <label class="theme-mode-card">
                    <input type="radio" name="theme" value="light">
                    <div class="mode-preview mode-light">
                        <div class="mode-bar"></div>
                        <div class="mode-lines"></div>
                    </div>
                    <div class="mode-label">Light</div>
                </label>
                <label class="theme-mode-card">
                    <input type="radio" name="theme" value="dark">
                    <div class="mode-preview mode-dark">
                        <div class="mode-bar"></div>
                        <div class="mode-lines"></div>
                    </div>
                    <div class="mode-label">Dark</div>
                </label>
            </div>

            <div class="theme-section">
                <div class="theme-section-title">Gradient Colors</div>
                <div class="theme-color-row">
                    <div class="theme-color-picker">
                        <label>Primary</label>
                        <div class="picker-field">
                            <input id="primary_color" type="color" value="#6366f1">
                            <span id="primaryColorHex">#6366f1</span>
                        </div>
                    </div>
                    <div class="theme-color-picker">
                        <label>Secondary</label>
                        <div class="picker-field">
                            <input id="primary_soft" type="color" value="#8b5cf6">
                            <span id="primarySoftHex">#8b5cf6</span>
                        </div>
                    </div>
                </div>

                <div class="theme-preview">
                    <div class="preview-header">
                        <div class="preview-title">Preview</div>
                        <div class="preview-sub">Buttons, sidebar & accents</div>
                    </div>
                    <div id="themeGradientPreview" class="preview-gradient"></div>
                    <div class="preview-samples">
                        <div class="preview-pill">Primary</div>
                        <div class="preview-pill outline">Outline</div>
                        <div class="preview-chip">Sidebar</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn action-edit" onclick="saveTheme()">
                <i class="fas fa-save"></i> Save Theme
            </button>
            <button class="btn btn-outline" onclick="resetThemeColors()">
                <i class="fas fa-rotate-left"></i> Reset Colors
            </button>
        </div>
    </div>
</div>

<div class="tab-content" id="logs">
    <div class="card">
        <h3>
            <i class="fas fa-file-lines"></i>
            Security Logs
        </h3>

        <div class="form-group">
            <label>Latest Entries</label>
            <div class="log-toolbar">
                <input type="text" id="logFilter" placeholder="Filter keyword (username, IP, event)...">
                <select id="logLevelFilter">
                    <option value="all">All</option>
                    <option value="LOGIN_SUCCESS">LOGIN_SUCCESS</option>
                    <option value="LOGIN_FAILED">LOGIN_FAILED</option>
                    <option value="LOGIN_RATE_LIMIT">LOGIN_RATE_LIMIT</option>
                    <option value="SESSION_HIJACK">SESSION_HIJACK</option>
                </select>
            </div>
            <div class="log-box" id="securityLogBox">Loading...</div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-outline" onclick="refreshSecurityLogs()">
                <i class="fas fa-rotate"></i> Refresh
            </button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .theme-card {
        position: relative;
    }

    .theme-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
        gap: 24px;
        margin-top: 16px;
    }

    .theme-section {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .theme-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-soft);
    }

    .theme-mode-card {
        display: grid;
        grid-template-columns: 72px 1fr;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface-glass);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .theme-mode-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .theme-mode-card .mode-label {
        font-weight: 600;
        color: var(--text);
    }

    .theme-mode-card .mode-preview {
        width: 72px;
        height: 48px;
        border-radius: 10px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }

    .theme-mode-card .mode-bar {
        height: 12px;
        background: var(--primary-gradient);
    }

    .theme-mode-card .mode-lines {
        display: grid;
        gap: 6px;
        padding: 8px;
    }

    .theme-mode-card .mode-lines::before,
    .theme-mode-card .mode-lines::after {
        content: '';
        height: 6px;
        border-radius: 6px;
        background: rgba(148, 163, 184, 0.35);
    }

    .theme-mode-card .mode-dark {
        background: #0f172a;
    }

    .theme-mode-card .mode-dark .mode-lines::before,
    .theme-mode-card .mode-dark .mode-lines::after {
        background: rgba(148, 163, 184, 0.25);
    }

    .theme-mode-card .mode-light {
        background: #f8fafc;
    }

    .theme-mode-card:has(input:checked) {
        border-color: var(--primary);
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.18);
        transform: translateY(-2px);
    }

    .theme-color-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 16px;
    }

    .theme-color-picker label {
        display: block;
        font-size: 0.85rem;
        color: var(--text-soft);
        margin-bottom: 6px;
    }

    .picker-field {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--surface-glass);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 8px 10px;
    }

    .picker-field input[type="color"] {
        width: 44px;
        height: 36px;
        border: none;
        background: transparent;
        padding: 0;
        cursor: pointer;
    }

    .picker-field span {
        font-weight: 600;
        color: var(--text);
        font-size: 0.9rem;
    }

    .theme-preview {
        margin-top: 6px;
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 14px 16px;
        display: grid;
        gap: 12px;
    }

    .preview-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 12px;
    }

    .preview-title {
        font-weight: 700;
        color: var(--text);
    }

    .preview-sub {
        font-size: 0.75rem;
        color: var(--text-soft);
    }

    .preview-gradient {
        height: 40px;
        border-radius: 12px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    .preview-samples {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .preview-pill {
        padding: 6px 14px;
        border-radius: 999px;
        background: var(--primary-gradient);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .preview-pill.outline {
        background: transparent;
        color: var(--primary);
        border: 1.5px solid var(--primary);
    }

    .preview-chip {
        padding: 6px 12px;
        border-radius: 10px;
        color: var(--sidebar-title);
        background: var(--sidebar);
        font-size: 0.75rem;
        font-weight: 600;
    }

    @media (max-width: 900px) {
        .theme-grid {
            grid-template-columns: 1fr;
        }
    }

    .log-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px;
        min-height: 160px;
        max-height: 300px;
        overflow: auto;
        font-size: 0.85rem;
    }

    .log-row {
        display: grid;
        grid-template-columns: 140px 120px 140px 1fr;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
    }

    .log-row:last-child {
        border-bottom: none;
    }

    .log-event.success {
        color: #22c55e;
        font-weight: 600;
    }

    .log-event.error {
        color: #ef4444;
        font-weight: 600;
    }

    .log-event.warn {
        color: #f59e0b;
        font-weight: 600;
    }

    .log-event.info {
        color: #3b82f6;
        font-weight: 600;
    }

    .log-toolbar {
        display: flex;
        gap: 10px;
        margin-bottom: 12px;
    }

    .log-toolbar input,
    .log-toolbar select {
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--surface);
        color: var(--text);
    }

    .alert-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 18px;
        margin-top: 16px;
    }

    .alert-section {
        border: 1px solid var(--border);
        border-radius: 16px;
        background: var(--surface-glass);
        padding: 14px 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .alert-section-wide {
        grid-column: 1 / -1;
    }

    .alert-section-title {
        font-size: 0.85rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-soft);
    }

    .switch-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: center;
        padding: 12px 12px;
        border-radius: 14px;
        background: var(--surface);
        border: 1px solid var(--border);
        cursor: pointer;
    }

    .switch-title {
        font-weight: 700;
        color: var(--text);
    }

    .switch-sub {
        font-size: 0.82rem;
        color: var(--text-soft);
        margin-top: 3px;
    }

    .switch {
        position: relative;
        width: 48px;
        height: 28px;
        flex: 0 0 auto;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .switch .slider {
        position: absolute;
        inset: 0;
        background: rgba(148, 163, 184, 0.35);
        border-radius: 999px;
        transition: 0.2s ease;
        border: 1px solid rgba(148, 163, 184, 0.25);
    }

    .switch .slider::before {
        content: '';
        position: absolute;
        height: 22px;
        width: 22px;
        left: 3px;
        top: 3px;
        background: #fff;
        border-radius: 999px;
        transition: 0.2s ease;
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15);
    }

    .switch input:checked + .slider {
        background: var(--primary-gradient);
        border-color: rgba(99, 102, 241, 0.35);
    }

    .switch input:checked + .slider::before {
        transform: translateX(20px);
    }

    .pill-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .pill-check {
        position: relative;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 10px 12px;
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: var(--text);
        transition: 0.2s ease;
    }

    .pill-check input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .pill-check span {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
    }

    .pill-check:has(input:checked) {
        border-color: rgba(99, 102, 241, 0.35);
        box-shadow: 0 10px 26px rgba(99, 102, 241, 0.14);
        transform: translateY(-1px);
    }

    .threshold-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .threshold-item {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
    }

    .threshold-item label {
        display: block;
        font-size: 0.85rem;
        color: var(--text-soft);
        margin-bottom: 6px;
        font-weight: 700;
    }

    .threshold-item input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface-glass);
        color: var(--text);
        font-weight: 800;
        letter-spacing: 0.02em;
    }

    .threshold-item .help {
        font-size: 0.78rem;
        color: var(--text-soft);
        margin-top: 6px;
    }

    .alert-log-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0;
        min-height: 180px;
        max-height: 420px;
        overflow: auto;
        font-size: 0.85rem;
    }

    .alert-log-header,
    .alert-log-row {
        display: grid;
        grid-template-columns: 170px 220px minmax(0, 1.25fr) minmax(0, 1.55fr);
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
        align-items: center;
    }

    .alert-log-row:last-child {
        border-bottom: none;
    }

    .alert-log-header {
        position: sticky;
        top: 0;
        z-index: 5;
        background: color-mix(in srgb, var(--surface) 80%, transparent);
        backdrop-filter: blur(8px);
        font-weight: 800;
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--text-soft);
    }

    .alert-log-row {
        transition: background 0.15s ease;
    }

    .alert-log-row:nth-child(even) {
        background: color-mix(in srgb, var(--surface) 92%, rgba(99, 102, 241, 0.06));
    }

    .alert-log-row:hover {
        background: color-mix(in srgb, var(--surface) 86%, rgba(99, 102, 241, 0.12));
    }

    /* Prevent long text from breaking the grid and overlapping columns */
    .alert-log-header > *,
    .alert-log-row > * {
        min-width: 0;
    }

    .alert-log-time {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-variant-numeric: tabular-nums;
        color: var(--text-soft);
        white-space: nowrap;
    }

    .alert-log-device {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .alert-log-device .name {
        font-weight: 900;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .alert-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        color: var(--text-soft);
        font-size: 0.8rem;
        margin-top: 0;
        line-height: 1.3;
    }

    .alert-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: var(--surface-glass);
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .alert-chip code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.78rem;
    }

    .alert-log-message {
        color: var(--text);
        line-height: 1.35;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }

    .alert-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.76rem;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: var(--surface-glass);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .alert-badge.warning { color: #d97706; }
    .alert-badge.critical { color: #dc2626; }
    .alert-badge.info { color: #0ea5e9; }

    @media (max-width: 900px) {
        .alert-grid {
            grid-template-columns: 1fr;
        }
        .threshold-grid {
            grid-template-columns: 1fr;
        }
        .pill-grid {
            grid-template-columns: 1fr;
        }
        .alert-log-row {
            grid-template-columns: 1fr;
        }
        .alert-log-header {
            display: none;
        }
        .alert-log-message {
            -webkit-line-clamp: 5;
        }
    }

    /* Remove black border from color picker */
    input[type="color"] {
        -webkit-appearance: none;
        appearance: none;
        border: none;
        outline: none;
        background: transparent;
        padding: 0;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        cursor: pointer;
    }

    input[type="color"]::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    input[type="color"]::-webkit-color-swatch {
        border: none;
        border-radius: 8px;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/js/settings.js') }}?v={{ filemtime(public_path('assets/js/settings.js')) }}"></script>
@endpush
