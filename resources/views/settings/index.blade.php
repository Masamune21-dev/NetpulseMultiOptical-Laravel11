@extends('layouts.app')

@section('content')
<div class="dev-tabs">
    <button class="dev-tab active" onclick="openTab('telegram')">
        <i class="fab fa-telegram"></i> Telegram Bot
    </button>
    <button class="dev-tab" onclick="openTab('alert')">
        <i class="fas fa-bell"></i> Alert
    </button>
    <button class="dev-tab" onclick="openTab('theme')">
        <i class="fas fa-palette"></i> Theme
    </button>
    <button class="dev-tab" onclick="openTab('logs')">
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
            <div style="position:relative;display:flex;align-items:center">
                <input id="bot_token" type="password" placeholder="123456:ABC-DEF..." style="padding-right:42px;width:100%">
                <button type="button" id="toggleBotToken"
                    onclick="(function(){var i=document.getElementById('bot_token'),b=document.getElementById('toggleBotToken');i.type=i.type==='password'?'text':'password';b.innerHTML=i.type==='password'?'<i class=\'fas fa-eye\'></i>':'<i class=\'fas fa-eye-slash\'></i>';})()"
                    style="position:absolute;right:10px;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:0.85rem;padding:4px;line-height:1">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
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

                <label class="switch-row">
                    <div class="switch-label">
                        <div class="switch-title">Mobile Push Alert</div>
                        <div class="switch-sub">Kirim push notification ke aplikasi mobile yang terdaftar (override preferensi per-user saat OFF).</div>
                    </div>
                    <div class="switch">
                        <input type="checkbox" id="alert_mobile_enabled">
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

    <div class="card" style="margin-top: 16px;">
        <h3>
            <i class="fas fa-paper-plane"></i>
            Send Push Notification
        </h3>

        <div class="form-group">
            <label>Target</label>
            <select id="pushTarget">
                <option value="all" data-i18n="all-devices">All Devices (0)</option>
            </select>
            <div class="help" id="pushTargetHelp">Pilih ke siapa pesan ini akan dikirim.</div>
        </div>

        <div class="form-group">
            <label>Title</label>
            <input id="pushTitle" type="text" maxlength="120" placeholder="Judul notifikasi (max 120 char)">
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea id="pushBody" rows="3" maxlength="1000" placeholder="Isi pesan yang akan muncul di notifikasi mobile..."></textarea>
        </div>

        <div class="modal-actions">
            <button class="btn action-edit" onclick="sendMobilePush()">
                <i class="fas fa-paper-plane"></i> Send Notification
            </button>
            <button class="btn btn-outline" onclick="refreshMobilePushTargets()">
                <i class="fas fa-rotate"></i> Refresh Targets
            </button>
        </div>
    </div>

    <div class="card" style="margin-top: 16px;">
        <h3>
            <i class="fas fa-mobile-screen-button"></i>
            Mobile Devices (Push Targets)
        </h3>

        <div class="form-group">
            <label>Aplikasi mobile yang terdaftar untuk menerima alert</label>
            <div class="alert-log-box" id="mobileDeviceBox">Loading...</div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-outline" onclick="refreshMobileDevices()">
                <i class="fas fa-rotate"></i> Refresh
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
                <div class="theme-section-title">Colors</div>
                <div class="theme-color-grid" id="themeColorGrid">
                    @php
                        $themeColors = [
                            ['key' => 'primary_color',  'label' => 'Primary',      'default' => '#ffe14a'],
                            ['key' => 'primary_soft',   'label' => 'Primary Soft', 'default' => '#ff5c8a'],
                            ['key' => 'accent_color',   'label' => 'Accent',       'default' => '#00d1ff'],
                            ['key' => 'accent_2_color', 'label' => 'Accent 2',     'default' => '#70f570'],
                            ['key' => 'danger_color',   'label' => 'Danger',       'default' => '#ef4444'],
                            ['key' => 'warning_color',  'label' => 'Warning',      'default' => '#f59e0b'],
                        ];
                    @endphp
                    @foreach ($themeColors as $c)
                        <div class="theme-color-picker">
                            <label>{{ $c['label'] }}</label>
                            <div class="picker-field">
                                <input type="color"
                                       class="theme-color-swatch"
                                       data-key="{{ $c['key'] }}"
                                       value="{{ $c['default'] }}">
                                <input type="text"
                                       class="theme-color-hex"
                                       data-key="{{ $c['key'] }}"
                                       value="{{ $c['default'] }}"
                                       maxlength="7"
                                       spellcheck="false"
                                       placeholder="#rrggbb">
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="theme-preview">
                    <div class="preview-header">
                        <div class="preview-title">Preview</div>
                        <div class="preview-sub">Buttons, sidebar & accents</div>
                    </div>
                    <div class="preview-samples">
                        <div class="preview-pill">Primary</div>
                        <div class="preview-pill outline">Outline</div>
                        <div class="preview-chip">Sidebar</div>
                        <div class="preview-chip preview-chip-accent2">Up</div>
                        <div class="preview-chip preview-chip-danger">Down</div>
                        <div class="preview-chip preview-chip-warning">Warning</div>
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
<link rel="stylesheet" href="{{ asset('assets/css/pages/settings.css') }}?v={{ filemtime(public_path('assets/css/pages/settings.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('assets/js/settings.js') }}?v={{ filemtime(public_path('assets/js/settings.js')) }}"></script>
<script>
// Override openTab to work with .dev-tab styling
(function () {
    window.openTab = function (id) {
        document.querySelectorAll('.tab-content').forEach(function (el) {
            el.classList.toggle('active', el.id === id);
        });
        document.querySelectorAll('.dev-tab').forEach(function (btn) {
            var matches = btn.getAttribute('onclick') && btn.getAttribute('onclick').includes("'" + id + "'");
            btn.classList.toggle('active', matches);
        });

        if (id === 'alert') {
            if (typeof refreshAlertLogs === 'function') refreshAlertLogs();
            if (typeof refreshMobileDevices === 'function') refreshMobileDevices();
            if (typeof refreshMobilePushTargets === 'function') refreshMobilePushTargets();
        }
        if (id === 'logs' && typeof refreshSecurityLogs === 'function') {
            refreshSecurityLogs();
        }
    };

    // Prefetch logs on initial page load so the tabs are populated the moment
    // the user opens them (no more lingering "Loading..." until the first
    // 15s auto-refresh tick or a manual click).
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof refreshAlertLogs === 'function') refreshAlertLogs();
        if (typeof refreshSecurityLogs === 'function') refreshSecurityLogs();
        if (typeof refreshMobileDevices === 'function') refreshMobileDevices();
        if (typeof refreshMobilePushTargets === 'function') refreshMobilePushTargets();
    });
})();
</script>
@endpush
