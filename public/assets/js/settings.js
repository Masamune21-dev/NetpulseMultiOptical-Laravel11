document.addEventListener('DOMContentLoaded', loadSettings);

// Global auto-refresh: keep alert/security logs updated while the tab is open.
document.addEventListener('DOMContentLoaded', () => {
    if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
        window.netpulseRefresh.register('settings-logs', () => {
            if (!location.pathname.startsWith('/settings')) return;
            if (document.hidden) return;

            if (document.getElementById('alert')?.classList?.contains('active')) {
                refreshAlertLogs();
                refreshMobileDevices();
                refreshMobilePushTargets();
            }
            if (document.getElementById('logs')?.classList?.contains('active')) {
                refreshSecurityLogs();
            }
        }, { minIntervalMs: 15000 });
    }
});

function openTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    document.querySelector(`[onclick="openTab('${id}')"]`).classList.add('active');
    document.getElementById(id).classList.add('active');

    if (id === 'alert') {
        refreshAlertLogs();
        refreshMobileDevices();
        refreshMobilePushTargets();
    }
    if (id === 'logs') {
        refreshSecurityLogs();
    }
}

function loadSettings() {
    fetch('api/settings')
        .then(r => r.json())
        .then(d => {

            // Telegram
            if (typeof bot_token !== 'undefined') {
                bot_token.value = d.bot_token || '';
            }
            if (typeof chat_id !== 'undefined') {
                chat_id.value = d.chat_id || '';
            }

            // Alerts (defaults keep backward compatibility)
            const setCheckbox = (id, val) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.checked = String(val ?? '1') === '1';
            };
            const setNumber = (id, val, fallback) => {
                const el = document.getElementById(id);
                if (!el) return;
                const v = (val === null || val === undefined || val === '') ? fallback : val;
                el.value = String(v);
            };

            setCheckbox('alert_telegram_enabled', d.alert_telegram_enabled);
            setCheckbox('alert_webui_enabled', d.alert_webui_enabled);
            setCheckbox('alert_mobile_enabled', d.alert_mobile_enabled);
            setCheckbox('alert_interface_down', d.alert_interface_down);
            setCheckbox('alert_interface_up', d.alert_interface_up);
            setCheckbox('alert_interface_warning', d.alert_interface_warning);
            setCheckbox('alert_device_down', d.alert_device_down);
            setCheckbox('alert_device_up', d.alert_device_up);

            setNumber('alert_rx_warning_high', d.alert_rx_warning_high, -18.0);
            setNumber('alert_rx_warning_low', d.alert_rx_warning_low, -25.0);
            setNumber('alert_rx_down_threshold', d.alert_rx_down_threshold, -40.0);

            // Theme
            const theme = d.theme || 'light';
            document.body.dataset.theme = theme;

            const radio = document.querySelector(
                `input[name="theme"][value="${theme}"]`
            );
            if (radio) radio.checked = true;

            // Theme colors
            THEME_COLORS.forEach(({ key, cssVar, default: dflt }) => {
                const value = normalizeHex(d[key]) || dflt;
                const swatch = document.querySelector(`.theme-color-swatch[data-key="${key}"]`);
                const hex = document.querySelector(`.theme-color-hex[data-key="${key}"]`);
                if (swatch) swatch.value = value;
                if (hex) hex.value = value;
                document.documentElement.style.setProperty(cssVar, value);
                try { localStorage.setItem(key, value); } catch (e) {}
            });
            syncPrimaryGradient();
            attachThemeColorListeners();

            // Live theme preview on radio change
            document.querySelectorAll('input[name="theme"]').forEach(input => {
                input.addEventListener('change', () => {
                    document.body.dataset.theme = input.value;
                    document.documentElement.dataset.theme = input.value;
                });
            });
        });
}

function saveTelegram() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    fetch('api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bot_token: bot_token.value,
            chat_id: chat_id.value
        })
    })
    .then(() => showNotification('Telegram settings saved', 'success'));
}

function getCheckboxVal(id, fallback = '1') {
    const el = document.getElementById(id);
    if (!el) return fallback;
    return el.checked ? '1' : '0';
}

function getNumberVal(id, fallback) {
    const el = document.getElementById(id);
    if (!el) return fallback;
    const v = parseFloat(el.value);
    return Number.isFinite(v) ? v : fallback;
}

function saveAlerts() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;

    let warnHigh = getNumberVal('alert_rx_warning_high', -18.0);
    let warnLow = getNumberVal('alert_rx_warning_low', -25.0);
    let down = getNumberVal('alert_rx_down_threshold', -40.0);

    // Normalize: warnLow should be <= warnHigh (e.g. -25 <= -18)
    if (warnLow > warnHigh) {
        const tmp = warnLow;
        warnLow = warnHigh;
        warnHigh = tmp;
    }
    if (down > warnLow) {
        // Keep down more negative than warning low by default.
        down = warnLow - 15.0;
    }

    const payload = {
        alert_telegram_enabled: getCheckboxVal('alert_telegram_enabled', '1'),
        alert_webui_enabled: getCheckboxVal('alert_webui_enabled', '1'),
        alert_mobile_enabled: getCheckboxVal('alert_mobile_enabled', '1'),
        alert_interface_down: getCheckboxVal('alert_interface_down', '1'),
        alert_interface_up: getCheckboxVal('alert_interface_up', '1'),
        alert_interface_warning: getCheckboxVal('alert_interface_warning', '1'),
        alert_device_down: getCheckboxVal('alert_device_down', '1'),
        alert_device_up: getCheckboxVal('alert_device_up', '1'),
        alert_rx_warning_high: warnHigh,
        alert_rx_warning_low: warnLow,
        alert_rx_down_threshold: down,
    };

    fetch('api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(r => r.json().catch(() => ({})))
        .then(d => {
            if (d && d.error) {
                showNotification(d.error, 'error');
                return;
            }
            showNotification('Alert settings saved', 'success');
            loadSettings();
        })
        .catch(() => showNotification('Failed to save alert settings', 'error'));
}

let _alertLogCache = '';

function refreshAlertLogs() {
    const type = document.getElementById('alertTypeFilter')?.value || 'all';
    const severity = document.getElementById('alertSeverityFilter')?.value || 'all';
    const q = document.getElementById('alertLogFilter')?.value || '';

    const params = new URLSearchParams();
    params.set('limit', '250');
    if (type && type !== 'all') params.set('type', type);
    if (severity && severity !== 'all') params.set('severity', severity);
    if (q) params.set('q', q);

    fetch('api/alert_logs?' + params.toString())
        .then(r => r.json())
        .then(d => {
            const box = document.getElementById('alertLogBox');
            if (!box) return;
            if (!d || !d.success) {
                box.textContent = d && d.error ? d.error : 'Failed to load alert logs';
                return;
            }
            const rows = d.data || [];
            _alertLogCache = JSON.stringify(rows);
            renderAlertLogs(rows);
        })
        .catch(() => {
            const box = document.getElementById('alertLogBox');
            if (box) box.textContent = 'Failed to load alert logs';
        });
}

function renderAlertLogs(rows) {
    const box = document.getElementById('alertLogBox');
    if (!box) return;

    if (!Array.isArray(rows) || rows.length === 0) {
        box.textContent = 'No alerts';
        return;
    }

    const header = `
        <div class="alert-log-header">
            <div>Time</div>
            <div>Type</div>
            <div>Device / Interface</div>
            <div>Message</div>
        </div>
    `;

    const html = rows.map(r => {
        const sev = (r.severity || 'info').toLowerCase();
        const badgeCls = sev === 'critical' ? 'critical' : (sev === 'warning' ? 'warning' : 'info');

        const dev = (r.device_name || 'Device') + (r.device_ip ? ` (${r.device_ip})` : '');
        const iface = r.if_name ? r.if_name + (r.if_alias ? ` (${r.if_alias})` : '') : '';
        const rx = (r.rx_power !== null && r.rx_power !== undefined) ? `${r.rx_power.toFixed(2)} dBm` : 'N/A';
        const tx = (r.tx_power !== null && r.tx_power !== undefined) ? `${r.tx_power.toFixed(2)} dBm` : 'N/A';

        const meta = [
            iface ? `<span class="alert-chip" title="${escapeHtml(iface)}"><i class="fas fa-plug"></i><code>${escapeHtml(iface)}</code></span>` : '',
            `<span class="alert-chip"><i class="fas fa-signal"></i><span><strong>RX</strong> ${escapeHtml(rx)}</span></span>`,
            `<span class="alert-chip"><i class="fas fa-tower-broadcast"></i><span><strong>TX</strong> ${escapeHtml(tx)}</span></span>`,
        ].filter(Boolean).join('');

        return `
            <div class="alert-log-row">
                <div class="alert-log-time">${escapeHtml(r.created_at || '')}</div>
                <div><span class="alert-badge ${badgeCls}" title="${escapeHtml(r.event_type || '')}">${escapeHtml(r.event_type || '')}</span></div>
                <div class="alert-log-device">
                    <div class="name" title="${escapeHtml(dev)}">${escapeHtml(dev)}</div>
                    <div class="alert-meta">${meta}</div>
                </div>
                <div class="alert-log-message" title="${escapeHtml(r.message || '')}">${escapeHtml(r.message || '')}</div>
            </div>
        `;
    }).join('');

    box.innerHTML = header + html;
}

function clearAlertLogs() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    if (!confirm('Clear ALL alert logs?')) return;

    fetch('api/alert_logs', { method: 'DELETE' })
        .then(r => r.json().catch(() => ({})))
        .then(d => {
            if (d && d.error) {
                showNotification(d.error, 'error');
                return;
            }
            showNotification('Alert logs cleared', 'success');
            refreshAlertLogs();
        })
        .catch(() => showNotification('Failed to clear alert logs', 'error'));
}

function refreshMobileDevices() {
    const box = document.getElementById('mobileDeviceBox');
    if (!box) return;

    fetch('api/mobile_devices')
        .then(r => r.json())
        .then(d => {
            if (!d || !d.success) {
                box.textContent = (d && d.error) ? d.error : 'Failed to load devices';
                return;
            }
            renderMobileDevices(d.data || []);
        })
        .catch(() => {
            box.textContent = 'Failed to load devices';
        });
}

function renderMobileDevices(rows) {
    const box = document.getElementById('mobileDeviceBox');
    if (!box) return;

    if (!Array.isArray(rows) || rows.length === 0) {
        box.textContent = 'No mobile devices registered';
        return;
    }

    const header = `
        <div class="alert-log-header">
            <div>User</div>
            <div>Platform</div>
            <div>Device / Token</div>
            <div>Last Seen</div>
        </div>
    `;

    const html = rows.map(r => {
        const user = r.user_name
            ? `${escapeHtml(r.user_name)} <span style="opacity:.6">#${r.user_id}</span>`
            : `<span style="opacity:.6">User #${r.user_id}</span>`;
        const platform = `<span class="alert-badge info">${escapeHtml(r.platform || '-')}</span>`;
        const deviceName = r.device_name ? escapeHtml(r.device_name) : '<em>unknown device</em>';
        const tokenPreview = r.token_preview
            ? `<div class="alert-meta"><span class="alert-chip"><i class="fas fa-key"></i><code>${escapeHtml(r.token_preview)}</code></span></div>`
            : '';
        const lastSeen = r.last_seen_at || r.created_at || '-';

        return `
            <div class="alert-log-row">
                <div class="alert-log-device"><div class="name">${user}</div></div>
                <div>${platform}</div>
                <div class="alert-log-device">
                    <div class="name" title="${escapeHtml(r.device_name || '')}">${deviceName}</div>
                    ${tokenPreview}
                </div>
                <div class="alert-log-time">
                    ${escapeHtml(lastSeen)}
                    <div style="margin-top:6px">
                        <button class="btn btn-danger action-delete" style="padding:4px 10px;font-size:.75rem"
                                onclick="revokeMobileDevice(${r.id})">
                            <i class="fas fa-trash"></i> Revoke
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    box.innerHTML = header + html;
}

function refreshMobilePushTargets() {
    const sel = document.getElementById('pushTarget');
    if (!sel) return;

    fetch('api/mobile_push_targets')
        .then(r => r.json())
        .then(d => {
            if (!d || !d.success) {
                sel.innerHTML = `<option value="all">All Devices (0)</option>`;
                return;
            }
            const total = (d.data && d.data.total_devices) || 0;
            const users = (d.data && d.data.users) || [];

            const prev = sel.value;
            const opts = [`<option value="all">All Devices (${total})</option>`];
            users.forEach(u => {
                const label = u.name ? `${escapeHtml(u.name)} (user #${u.id})` : `User #${u.id}`;
                opts.push(`<option value="user:${u.id}">${label}</option>`);
            });
            sel.innerHTML = opts.join('');

            if (prev) sel.value = prev;
            if (!sel.value) sel.value = 'all';
        })
        .catch(() => {
            sel.innerHTML = `<option value="all">All Devices</option>`;
        });
}

function previewPushImage(input) {
    const file = input && input.files && input.files[0];
    const wrap = document.getElementById('pushImagePreviewWrap');
    const img = document.getElementById('pushImagePreview');
    if (!file) {
        clearPushImage();
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showNotification('Ukuran gambar maksimal 2 MB', 'error');
        clearPushImage();
        return;
    }
    if (img && wrap) {
        img.src = URL.createObjectURL(file);
        wrap.style.display = 'block';
    }
}

function clearPushImage() {
    const input = document.getElementById('pushImage');
    const wrap = document.getElementById('pushImagePreviewWrap');
    const img = document.getElementById('pushImagePreview');
    if (input) input.value = '';
    if (img && img.src) { try { URL.revokeObjectURL(img.src); } catch (e) {} img.src = ''; }
    if (wrap) wrap.style.display = 'none';
}

function sendMobilePush() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;

    const title = (document.getElementById('pushTitle')?.value || '').trim();
    const body = (document.getElementById('pushBody')?.value || '').trim();
    const targetRaw = document.getElementById('pushTarget')?.value || 'all';
    const imageFile = document.getElementById('pushImage')?.files?.[0] || null;

    if (!title || !body) {
        showNotification('Title dan message wajib diisi', 'error');
        return;
    }
    if (imageFile && imageFile.size > 2 * 1024 * 1024) {
        showNotification('Ukuran gambar maksimal 2 MB', 'error');
        return;
    }

    const form = new FormData();
    form.append('title', title);
    form.append('body', body);
    if (targetRaw.startsWith('user:')) {
        form.append('target', 'user');
        form.append('user_id', String(parseInt(targetRaw.slice(5), 10)));
    } else {
        form.append('target', 'all');
    }
    if (imageFile) form.append('image', imageFile);

    if (!confirm(`Kirim push ke target: ${targetRaw === 'all' ? 'All Devices' : targetRaw}?`)) return;

    // Note: no Content-Type header — the browser sets the multipart boundary itself.
    fetch('api/mobile_push_send', {
        method: 'POST',
        body: form,
    })
        .then(r => r.json().catch(() => ({})))
        .then(d => {
            if (!d || (!d.success && !d.sent)) {
                const err = d && (d.error || (d.errors && d.errors[0])) || 'Failed to send';
                showNotification(err, 'error');
                return;
            }
            let msg = `Sent ${d.sent || 0} push`;
            if (d.failed) msg += ` (${d.failed} failed)`;
            showNotification(msg, d.failed ? 'warning' : 'success');

            const bodyEl = document.getElementById('pushBody');
            if (bodyEl) bodyEl.value = '';
            clearPushImage();
        })
        .catch(() => showNotification('Failed to send push', 'error'));
}

function revokeMobileDevice(id) {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    if (!confirm('Cabut device ini? Aplikasi mobile harus login ulang untuk menerima alert lagi.')) return;

    fetch('api/mobile_devices/' + encodeURIComponent(id), { method: 'DELETE' })
        .then(r => r.json().catch(() => ({})))
        .then(d => {
            if (!d || !d.success) {
                showNotification((d && d.error) ? d.error : 'Failed to revoke device', 'error');
                return;
            }
            showNotification('Mobile device revoked', 'success');
            refreshMobileDevices();
        })
        .catch(() => showNotification('Failed to revoke device', 'error'));
}

function escapeHtml(str) {
    return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

const THEME_COLORS = [
    { key: 'primary_color',  cssVar: '--primary',      default: '#ffe14a' },
    { key: 'primary_soft',   cssVar: '--primary-soft', default: '#ff5c8a' },
    { key: 'accent_color',   cssVar: '--accent',       default: '#00d1ff' },
    { key: 'accent_2_color', cssVar: '--accent-2',     default: '#70f570' },
    { key: 'danger_color',   cssVar: '--danger',       default: '#ef4444' },
    { key: 'warning_color',  cssVar: '--warning',      default: '#f59e0b' },
];

function normalizeHex(v) {
    if (typeof v !== 'string') return '';
    const s = v.trim().toLowerCase();
    return /^#[0-9a-f]{6}$/.test(s) ? s : '';
}

function syncPrimaryGradient() {
    const root = document.documentElement.style;
    const p = root.getPropertyValue('--primary').trim() || '#ffe14a';
    const s = root.getPropertyValue('--primary-soft').trim() || '#ff5c8a';
    root.setProperty('--primary-gradient', `linear-gradient(135deg, ${p} 0%, ${s} 100%)`);
}

function attachThemeColorListeners() {
    THEME_COLORS.forEach(({ key, cssVar }) => {
        const swatch = document.querySelector(`.theme-color-swatch[data-key="${key}"]`);
        const hex = document.querySelector(`.theme-color-hex[data-key="${key}"]`);
        if (!swatch || !hex) return;
        if (swatch.dataset.bound === '1') return;
        swatch.dataset.bound = '1';

        swatch.addEventListener('input', () => {
            const v = swatch.value.toLowerCase();
            hex.value = v;
            hex.classList.remove('invalid');
            document.documentElement.style.setProperty(cssVar, v);
            if (key === 'primary_color' || key === 'primary_soft') syncPrimaryGradient();
        });
        hex.addEventListener('input', () => {
            const v = normalizeHex(hex.value);
            if (!v) {
                hex.classList.add('invalid');
                return;
            }
            hex.classList.remove('invalid');
            swatch.value = v;
            document.documentElement.style.setProperty(cssVar, v);
            if (key === 'primary_color' || key === 'primary_soft') syncPrimaryGradient();
        });
        hex.addEventListener('blur', () => {
            if (!normalizeHex(hex.value)) {
                hex.value = swatch.value;
                hex.classList.remove('invalid');
            }
        });
    });
}

function saveTheme() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    const theme = document.querySelector('input[name="theme"]:checked').value;
    document.body.dataset.theme = theme;
    document.documentElement.dataset.theme = theme;

    const payload = { theme };
    THEME_COLORS.forEach(({ key, cssVar, default: dflt }) => {
        const swatch = document.querySelector(`.theme-color-swatch[data-key="${key}"]`);
        const value = (swatch && normalizeHex(swatch.value)) || dflt;
        payload[key] = value;
        document.documentElement.style.setProperty(cssVar, value);
        try { localStorage.setItem(key, value); } catch (e) {}
    });
    syncPrimaryGradient();

    fetch('api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }).then(() => showNotification('Theme saved', 'success'));
}

function resetThemeColors() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    const payload = {};
    THEME_COLORS.forEach(({ key, cssVar, default: dflt }) => {
        const swatch = document.querySelector(`.theme-color-swatch[data-key="${key}"]`);
        const hex = document.querySelector(`.theme-color-hex[data-key="${key}"]`);
        if (swatch) swatch.value = dflt;
        if (hex) { hex.value = dflt; hex.classList.remove('invalid'); }
        document.documentElement.style.setProperty(cssVar, dflt);
        payload[key] = dflt;
        try { localStorage.setItem(key, dflt); } catch (e) {}
    });
    syncPrimaryGradient();

    fetch('api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }).then(() => showNotification('Theme colors reset', 'success'));
}


function testTelegram() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    fetch('api/telegram_test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.success) {
            showNotification('Test message sent', 'success');
        } else {
            showNotification(d.error || 'Failed to send test message', 'error');
        }
    })
    .catch(() => {
        showNotification('Failed to send test message', 'error');
    });
}

function refreshSecurityLogs() {
    fetch('api/logs?type=security')
        .then(r => r.json())
        .then(d => {
            const box = document.getElementById('securityLogBox');
            if (!box) return;
            if (!d || !d.success) {
                box.textContent = d && d.error ? d.error : 'Failed to load logs';
                return;
            }
            const raw = d.data || '';
            renderLogs(raw);
        })
        .catch(() => {
            const box = document.getElementById('securityLogBox');
            if (box) box.textContent = 'Failed to load logs';
        });
}

function renderLogs(raw) {
    const box = document.getElementById('securityLogBox');
    if (!box) return;

    const filterInput = document.getElementById('logFilter');
    const levelSelect = document.getElementById('logLevelFilter');
    const keyword = (filterInput ? filterInput.value : '').toLowerCase();
    const level = levelSelect ? levelSelect.value : 'all';

    const lines = raw.split('\n').filter(Boolean);
    const rows = [];

    lines.forEach(line => {
        // format: [time] [ip] [event] details [User-Agent: ...]
        const match = line.match(/^\[(.+?)\]\s+\[(.+?)\]\s+\[(.+?)\]\s+(.*)$/);
        if (!match) return;
        const time = match[1];
        const ip = match[2];
        const event = match[3];
        const details = match[4];

        if (level !== 'all' && event !== level) return;
        const hay = (line + ' ' + details).toLowerCase();
        if (keyword && !hay.includes(keyword)) return;

        const cls =
            event === 'LOGIN_SUCCESS' ? 'success' :
            event === 'LOGIN_FAILED' ? 'error' :
            event === 'LOGIN_RATE_LIMIT' ? 'warn' :
            event === 'SESSION_HIJACK' ? 'error' : 'info';

        rows.push(`
            <div class="log-row">
                <div class="log-time">${time}</div>
                <div class="log-ip">${ip}</div>
                <div class="log-event ${cls}">${event}</div>
                <div class="log-details">${details}</div>
            </div>
        `);
    });

    box.innerHTML = rows.length ? rows.join('') : 'No logs';
}

document.addEventListener('input', (e) => {
    if (e.target && (e.target.id === 'logFilter' || e.target.id === 'logLevelFilter')) {
        refreshSecurityLogs();
    }
    if (e.target && (e.target.id === 'alertLogFilter' || e.target.id === 'alertTypeFilter' || e.target.id === 'alertSeverityFilter')) {
        refreshAlertLogs();
    }
});
