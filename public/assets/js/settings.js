document.addEventListener('DOMContentLoaded', loadSettings);

// Global auto-refresh: keep alert/security logs updated while the tab is open.
document.addEventListener('DOMContentLoaded', () => {
    if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
        window.netpulseRefresh.register('settings-logs', () => {
            if (!location.pathname.startsWith('/settings')) return;
            if (document.hidden) return;

            if (document.getElementById('alert')?.classList?.contains('active')) {
                refreshAlertLogs();
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
            const primaryInput = document.getElementById('primary_color');
            const softInput = document.getElementById('primary_soft');
            const preview = document.getElementById('themeGradientPreview');
            const primaryHex = document.getElementById('primaryColorHex');
            const softHex = document.getElementById('primarySoftHex');

            if (primaryInput) primaryInput.value = d.primary_color || '#6366f1';
            if (softInput) softInput.value = d.primary_soft || '#8b5cf6';
            if (primaryHex) primaryHex.textContent = (primaryInput && primaryInput.value) || '#6366f1';
            if (softHex) softHex.textContent = (softInput && softInput.value) || '#8b5cf6';

            if (primaryInput && softInput) {
                applyThemeColors(primaryInput.value, softInput.value, preview);
                try {
                    localStorage.setItem('primary_color', primaryInput.value);
                    localStorage.setItem('primary_soft', softInput.value);
                } catch (e) {}
                primaryInput.addEventListener('input', () => {
                    if (primaryHex) primaryHex.textContent = primaryInput.value;
                    applyThemeColors(primaryInput.value, softInput.value, preview);
                });
                softInput.addEventListener('input', () => {
                    if (softHex) softHex.textContent = softInput.value;
                    applyThemeColors(primaryInput.value, softInput.value, preview);
                });
            }

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

function escapeHtml(str) {
    return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function saveTheme() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    const theme = document.querySelector('input[name="theme"]:checked').value;
    const primaryInput = document.getElementById('primary_color');
    const softInput = document.getElementById('primary_soft');
    const primaryColor = primaryInput ? primaryInput.value : '#6366f1';
    const primarySoft = softInput ? softInput.value : '#8b5cf6';

    document.body.dataset.theme = theme; // 🔥 langsung apply
    applyThemeColors(primaryColor, primarySoft, document.getElementById('themeGradientPreview'));

    fetch('api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            theme,
            primary_color: primaryColor,
            primary_soft: primarySoft
        })
    }).then(() => {
        try {
            localStorage.setItem('primary_color', primaryColor);
            localStorage.setItem('primary_soft', primarySoft);
        } catch (e) {}
        showNotification('Theme saved', 'success');
    });
}

function applyThemeColors(primaryColor, primarySoft, previewEl) {
    document.documentElement.style.setProperty('--primary', primaryColor);
    document.documentElement.style.setProperty('--primary-soft', primarySoft);
    document.documentElement.style.setProperty(
        '--primary-gradient',
        `linear-gradient(135deg, ${primaryColor} 0%, ${primarySoft} 100%)`
    );
    document.documentElement.style.setProperty(
        '--sidebar',
        `linear-gradient(160deg, ${primaryColor} 0%, ${primarySoft} 55%, ${primaryColor} 100%)`
    );
    if (previewEl) {
        previewEl.style.background = `linear-gradient(135deg, ${primaryColor}, ${primarySoft})`;
    }
}

function resetThemeColors() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    const primary = '#6366f1';
    const soft = '#8b5cf6';
    const primaryInput = document.getElementById('primary_color');
    const softInput = document.getElementById('primary_soft');
    if (primaryInput) primaryInput.value = primary;
    if (softInput) softInput.value = soft;
    applyThemeColors(primary, soft, document.getElementById('themeGradientPreview'));
    fetch('api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            primary_color: primary,
            primary_soft: soft
        })
    }).then(() => {
        try {
            localStorage.setItem('primary_color', primary);
            localStorage.setItem('primary_soft', soft);
        } catch (e) {}
        showNotification('Theme colors reset', 'success');
    });
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
