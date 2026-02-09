// ===============================
// PAGE LOAD
// ===============================
document.addEventListener('DOMContentLoaded', () => {
    loadDevices();
    loadMonitoringDevices();
    startAutoDiscover();

    // Global auto-refresh: keep device status and monitoring list fresh.
    if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
        window.netpulseRefresh.register('devices', () => {
            if (!location.pathname.startsWith('/devices')) return;
            if (document.hidden) return;

            const modalOpen = document.getElementById('deviceModal')?.style?.display === 'flex';
            if (!modalOpen) {
                loadDevices();
                loadMonitoringDevices();
            }

            // If monitoring tab is open and a device is selected, refresh interface list.
            const monitoringTabActive = document.getElementById('monitoring')?.classList?.contains('active');
            const id = document.getElementById('monitorDeviceSelect')?.value;
            if (monitoringTabActive && id) {
                loadInterfaces(id);
            }
        }, { minIntervalMs: 15000 });
    }
});

// ===============================
// TAB HANDLER
// ===============================
function openTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    document.querySelector(`.tab[onclick*="${id}"]`).classList.add('active');
    document.getElementById(id).classList.add('active');

    if (id === 'monitoring') {
        startAutoDiscover();
    } else {
        stopAutoDiscover();
    }
}

// ===============================
// SNMP DEVICE LIST
// ===============================
function loadDevices() {
    fetch('api/devices')
        .then(r => r.json())
        .then(data => {
            const tb = document.querySelector('#deviceTable tbody');
            tb.innerHTML = '';

            data.forEach(d => {
                tb.innerHTML += `
                <tr>
                    <td>${d.device_name}</td>
                    <td>${d.ip_address}</td>
                    <td>v${d.snmp_version}</td>
                    <td>${d.snmp_version === '2c' ? d.community : (d.snmp_user || '-')}</td>
                    <td>${d.last_status ?? '-'}</td>
                    <td class="actions-cell">
                        <div class="action-buttons">
                            <button class="btn btn-icon btn-edit action-edit" onclick='editDevice(${JSON.stringify(d)})'>
                            <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-info" onclick="testSNMP(${d.id})">
                            <i class="fas fa-plug"></i>
                            </button>
                            <button class="btn btn-icon btn-danger action-delete" onclick="deleteDevice(${d.id})">
                            <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
        });
}


// ===============================
// MODAL
// ===============================
function openAddDevice() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    document.getElementById('deviceModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('deviceModal').style.display = 'none';
}

function editDevice(d) {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    openAddDevice();
    device_id.value = d.id;
    device_name.value = d.device_name;
    ip_address.value = d.ip_address;
    snmp_version.value = d.snmp_version;
    community.value = d.community;
    snmp_user.value = d.snmp_user;
    is_active.value = d.is_active;
}

// ===============================
// CRUD
// ===============================
function saveDevice() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    fetch('api/devices', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: device_id.value || null,
            device_name: device_name.value,
            ip_address: ip_address.value,
            snmp_version: snmp_version.value,
            community: community.value,
            snmp_user: snmp_user.value,
            is_active: is_active.value
        })
    }).then(() => {
        closeModal();
        loadDevices();
        loadMonitoringDevices();
    });
}

function deleteDevice(id) {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    confirmDelete('Hapus device ini?', () => {
        fetch('api/devices?id=' + id, { method: 'DELETE' })
            .then(() => {
                loadDevices();
                loadMonitoringDevices();
            });
    });
}

// ===============================
// SNMP TEST
// ===============================
window.testSNMP = function (id) {
    fetch('api/devices?test=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.status === 'OK') {
                showNotification(`SNMP OK: ${d.response}`, 'success');
            } else {
                showNotification(`SNMP FAILED: ${d.error}`, 'error');
            }
            loadDevices();
        })
        .catch(() => showNotification('SNMP error', 'error'));
};

// ===============================
// MONITORING TAB
// ===============================
function loadMonitoringDevices() {
    fetch('api/devices')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('monitorDeviceSelect');
            if (!sel) return;

            sel.innerHTML = '<option value="">-- Pilih Device --</option>';

            data.forEach(d => {
                if (d.is_active == 1) {
                    sel.innerHTML += `
                        <option value="${d.id}">
                            ${d.device_name} (${d.ip_address})
                        </option>`;
                }
            });
        });
}

function selectMonitoringDevice() {

    const id = document.getElementById('monitorDeviceSelect').value;
    if (!id) return;

    discoverSelectedInterfaces();
    startAutoDiscover();
}

// ===============================
// DISCOVER INTERFACES
// ===============================
let autoDiscoverTimer = null;
let autoDiscoverBusy = false;

function startAutoDiscover() {
    stopAutoDiscover();
    autoDiscoverTimer = setInterval(() => {
        const tabActive = document.getElementById('monitoring')?.classList.contains('active');
        const id = document.getElementById('monitorDeviceSelect')?.value;
        if (!tabActive || !id) return;
        discoverSelectedInterfaces(true);
    }, 60000);
}

function stopAutoDiscover() {
    if (autoDiscoverTimer) {
        clearInterval(autoDiscoverTimer);
        autoDiscoverTimer = null;
    }
}

async function discoverSelectedInterfaces(silent = false) {

    const sel = document.getElementById('monitorDeviceSelect');
    const id = sel.value;

    if (!id) return showNotification('Pilih device dulu', 'warning');

    const name = sel.selectedOptions[0].text.toLowerCase();

    const vendor = name.includes('sekarjalak') || name.includes('huawei')
        ? 'huawei'
        : 'mikrotik';

    const api = vendor === 'huawei'
        ? 'api/huawei_discover_optics'
        : 'api/discover_interfaces';

    const box = document.getElementById('monitoringContent');

    if (!silent) {
        box.innerHTML = `<div class="alert info">🔍 Discovering (${vendor})...</div>`;
    }

    try {
        if (autoDiscoverBusy) return;
        autoDiscoverBusy = true;

        const r = await fetch(`${api}?device_id=${id}&_=${Date.now()}`);
        const raw = await r.text();

        console.log(raw);

        let data = {};

        try {
            data = JSON.parse(raw);
        } catch {
            console.warn('Non JSON Huawei output OK');
        }

        setTimeout(() => loadInterfaces(id), 2000);

    } catch (e) {

        console.error(e);
        if (!silent) showNotification('Discovery failed', 'error');

    } finally {
        autoDiscoverBusy = false;
    }
}

function loadInterfaces(deviceId) {
    const box = document.getElementById('monitoringContent');
    box.innerHTML = '<div class="alert info">📡 Loading interface data...</div>';

    fetch(`api/interfaces?device_id=${deviceId}&_=${Date.now()}`)
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {

            if (!Array.isArray(data) || data.length === 0) {
                box.innerHTML = `
                    <div class="alert warning">
                        <strong>No interfaces found</strong><br>
                        Click <b>Discover Interface</b> first.
                    </div>`;
                return;
            }

            const sfp = data.filter(i => Number(i.is_sfp) === 1)
                .sort((a, b) => a.if_index - b.if_index);
            const other = data.filter(i => Number(i.is_sfp) === 0);

            let html = `
<div class="table-responsive">
<h3>🔦 SFP / QSFP Interfaces (${sfp.length})</h3>
<table class="table">
<thead>
<tr>
    <th>Idx</th>
    <th>Interface</th>
    <th>Type</th>
    <th>TX (dBm)</th>
    <th>RX (dBm)</th>
    <th>Loss</th>
    <th>Status</th>
</tr>
</thead>
<tbody>`;

            sfp.forEach(i => {

                /* ===== FORCE NUMBER ===== */
                const tx = Number(i.tx_power);
                const rx = Number(i.rx_power);

                let txHtml = '-';
                let rxHtml = '-';
                let lossHtml = '-';
                let status = 'Unknown';
                let statusClass = 'badge-unknown';

                /* ===== TX ===== */
                if (Number.isFinite(tx)) {
                    let c = tx < -10 ? '#dc2626'
                        : tx < 0 ? '#f97316'
                            : tx < 5 ? '#16a34a'
                                : '#dc2626';
                    txHtml = `<b style="color:${c}">${tx.toFixed(2)}</b>`;
                }

                /* ===== RX ===== */
                if (Number.isFinite(rx)) {
                    let c = rx < -30 ? '#dc2626'
                        : rx < -20 ? '#f97316'
                            : rx < 0 ? '#16a34a'
                                : rx < 5 ? '#2563eb'
                                    : '#dc2626';
                    rxHtml = `<b style="color:${c}">${rx.toFixed(2)}</b>`;
                }

                /* ===== ATTENUATION ===== */
                if (Number.isFinite(tx) && Number.isFinite(rx)) {
                    const loss = tx - rx;
                    let c = '#16a34a';

                    if (loss < 0) {
                        c = '#dc2626';
                        status = 'Invalid';
                        statusClass = 'badge-invalid';
                    } else if (loss > 30) {
                        c = '#dc2626';
                        status = 'Critical';
                        statusClass = 'badge-critical';
                    } else if (loss > 15) {
                        c = '#f97316';
                        status = 'Warning';
                        statusClass = 'badge-warning';
                    } else if (loss > 5) {
                        c = '#eab308';
                        status = 'Moderate';
                        statusClass = 'badge-moderate';
                    } else {
                        status = 'Good';
                        statusClass = 'badge-good';
                    }

                    lossHtml = `<b style="color:${c}">${loss.toFixed(2)}</b>`;
                }

                html += `
<tr>
    <td class="text-center"><code>${i.if_index}</code></td>
    <td>
        <b>${i.if_name}</b>
        ${i.if_alias ? `<div class="iface-comment">${i.if_alias}</div>` : ''}
    </td>
    <td class="text-center">
    <span class="interface-badge ${i.interface_type === 'QSFP+' ? 'badge-qsfp' : 'badge-sfp'}">
        ${i.interface_type}
    </span>
    </td>
    <td class="text-center">${txHtml}</td>
    <td class="text-center">${rxHtml}</td>
    <td class="text-center">${lossHtml}</td>
    <td class="text-center">
        <span class="status-badge ${statusClass}">${status}</span>
    </td>
</tr>`;
            });

            html += `</tbody></table></div>`;

            /* ===== NON SFP ===== */
            if (other.length) {
                html += `
<h3 style="margin-top:20px">📶 Other Interfaces</h3>
<table class="table">
<thead><tr><th>Idx</th><th>Name</th><th>Comment</th><th>Type</th></tr></thead><tbody>`;
                other.forEach(i => {
                    html += `
<tr>
<td class="text-center">${i.if_index}</td>
<td>${i.if_name}</td>
<td class="text-center">${i.if_alias || '-'}</td>
<td class="text-center">${i.interface_type}</td>
</tr>`;
                });
                html += `</tbody></table>`;
            }

            box.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            box.innerHTML = `<div class="alert error">❌ ${err.message}</div>`;
        });
}
