/**
 * Pengaturan → Vendor & Optik (khusus admin).
 *
 * Semua nilai dari server/perangkat (nama perangkat, IP, sysDescr, nama profil, ifName)
 * WAJIB lewat escHtml() sebelum masuk innerHTML — sysDescr dan ifName bisa diisi siapa pun
 * yang punya akses konfigurasi switch. Aksi memakai data-* + satu event listener, bukan
 * onclick berisi data.
 */
(function () {
    let state = { devices: [], profiles: [], units: {}, index_types: {}, overrides: {} };

    function esc(v) { return window.escHtml(v); }

    async function api(url, options = {}) {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            ...options,
        });
        let body = {};
        try { body = await res.json(); } catch (e) { /* bukan JSON */ }
        if (res.status === 429) throw new Error('Terlalu sering. Tunggu sebentar lalu coba lagi.');
        if (!res.ok || body.success === false) {
            const err = new Error(body.error || body.message || ('HTTP ' + res.status));
            err.errors = body.errors || null;
            throw err;
        }
        return body;
    }

    function notify(msg, type) {
        if (typeof showNotification === 'function') showNotification(msg, type);
    }

    function driverOptions(current) {
        const opts = Object.entries(state.overrides).map(([k, label]) =>
            `<option value="${esc(k)}" ${(current || 'auto') === k ? 'selected' : ''}>${esc(label)}</option>`);
        state.profiles.filter(p => p.tested_at).forEach(p => {
            const k = 'profile:' + p.id;
            opts.push(`<option value="${esc(k)}" ${current === k ? 'selected' : ''}>Profil: ${esc(p.name)}</option>`);
        });
        return opts.join('');
    }

    function renderDevices() {
        const tbody = document.querySelector('#opticalDeviceTable tbody');
        if (!state.devices.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty">Belum ada perangkat</td></tr>';
            return;
        }
        tbody.innerHTML = state.devices.map(d => `
            <tr>
                <td data-label="Perangkat"><b>${esc(d.device_name)}</b><br><small>${esc(d.ip_address)}</small></td>
                <td data-label="Vendor terdeteksi">${esc(d.vendor)}${d.sys_descr ? `<br><small title="${esc(d.sys_descr)}">${esc(d.sys_descr.slice(0, 60))}</small>` : ''}</td>
                <td data-label="sysObjectID"><small>${esc(d.sys_object_id || '—')}</small></td>
                <td data-label="Driver optik"><select data-action="override" data-device="${esc(d.id)}">${driverOptions(d.driver_override)}</select></td>
                <td class="optical-actions"><button class="btn btn-outline" data-action="redetect" data-device="${esc(d.id)}" title="Deteksi ulang vendor" aria-label="Deteksi ulang vendor"><i class="fas fa-rotate"></i></button></td>
            </tr>`).join('');
    }

    function renderProfiles() {
        const tbody = document.querySelector('#opticalProfileTable tbody');
        if (!state.profiles.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="empty">Belum ada profil</td></tr>';
            return;
        }
        const deviceSelect = state.devices.filter(d => d.is_active)
            .map(d => `<option value="${esc(d.id)}">${esc(d.device_name)}</option>`).join('');
        tbody.innerHTML = state.profiles.map(p => {
            let status;
            if (p.is_active) status = '<span class="badge badge-success">Aktif</span>';
            else if (p.tested_at) status = '<span class="badge badge-info">Lulus uji, nonaktif</span>';
            else status = `<span class="badge badge-warning">${p.is_template ? 'Templat — belum diuji' : 'Draf — belum diuji'}</span>`;
            const match = [p.match_sys_object_id, p.match_sys_descr ? '/' + p.match_sys_descr + '/' : null].filter(Boolean).join('<br>');
            return `
            <tr>
                <td data-label="Profil"><b>${esc(p.name)}</b>${p.notes ? `<br><small>${esc(p.notes)}</small>` : ''}
                    <div class="optical-sub"><small>Cocok: ${match ? match.split('<br>').map(esc).join(' · ') : '— (override saja)'}</small></div></td>
                <td data-label="OID RX / TX"><small class="optical-oid">RX ${esc(p.rx_oid)}<br>TX ${esc(p.tx_oid || '—')}</small>
                    <div class="optical-sub"><small>${esc(state.units[p.value_unit] || p.value_unit)} · ${esc(state.index_types[p.index_type] || p.index_type)}</small></div></td>
                <td data-label="Status">${status}</td>
                <td class="optical-actions">
                    <select data-role="test-device" data-profile="${esc(p.id)}" aria-label="Perangkat untuk uji">${deviceSelect}</select>
                    <button class="btn btn-outline" data-action="test" data-profile="${esc(p.id)}" title="Uji pada perangkat terpilih" aria-label="Uji profil"><i class="fas fa-vial"></i></button>
                    <button class="btn btn-outline" data-action="toggle" data-profile="${esc(p.id)}" title="${p.is_active ? 'Nonaktifkan' : 'Aktifkan'}" aria-label="${p.is_active ? 'Nonaktifkan' : 'Aktifkan'}" ${!p.tested_at && !p.is_active ? 'disabled' : ''}><i class="fas fa-power-off"></i></button>
                    <button class="btn btn-outline" data-action="edit" data-profile="${esc(p.id)}" title="Sunting" aria-label="Sunting profil"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-outline" data-action="delete" data-profile="${esc(p.id)}" title="Hapus" aria-label="Hapus profil"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderTest(profile, result) {
        document.getElementById('opticalTestCard').style.display = '';
        document.getElementById('opticalTestTitle').textContent = '— ' + profile.name;
        const s = result.summary || {};
        document.getElementById('opticalTestSummary').innerHTML = result.ok
            ? `✅ ${esc(s.interfaces)} interface terbaca dari ${esc(s.rows)} baris di ${esc(s.device_name)}. Profil boleh diaktifkan.`
            : `❌ Tidak ada interface yang terbaca (${esc(s.rows)} baris). Periksa OID, indeks, dan satuan.`;
        const rows = result.rows || [];
        document.querySelector('#opticalTestTable tbody').innerHTML = rows.length
            ? rows.map(r => `<tr><td data-label="Arah">${esc(r.dir.toUpperCase())}</td><td data-label="Indeks">${esc(r.index)}</td><td data-label="ifIndex">${esc(r.if_index ?? '—')}</td><td data-label="ifName">${esc(r.if_name ?? '(tak terpetakan)')}</td><td data-label="Mentah">${esc(r.raw ?? '—')}</td><td data-label="dBm"><b>${r.dbm === null ? '—' : esc(r.dbm.toFixed(2))}</b></td></tr>`).join('')
              + (result.truncated ? '<tr><td colspan="6" class="empty">… dipotong 200 baris pertama</td></tr>' : '')
            : '<tr><td colspan="6" class="empty">OID tidak mengembalikan data</td></tr>';
    }

    window.opticalLoad = async function () {
        try {
            state = await api('api/optical/profiles');
            renderDevices();
            renderProfiles();
        } catch (e) {
            notify('Gagal memuat data optik: ' + e.message, 'error');
        }
    };

    function fillSelect(id, map, value) {
        document.getElementById(id).innerHTML = Object.entries(map)
            .map(([k, label]) => `<option value="${esc(k)}" ${k === value ? 'selected' : ''}>${esc(label)}</option>`).join('');
    }

    window.opticalOpenProfile = function (profile) {
        const p = profile || {};
        document.getElementById('opticalProfileTitle').textContent = p.id ? 'Sunting profil' : 'Profil baru';
        document.getElementById('op_id').value = p.id || '';
        document.getElementById('op_name').value = p.name || '';
        document.getElementById('op_match_oid').value = p.match_sys_object_id || '';
        document.getElementById('op_match_descr').value = p.match_sys_descr || '';
        document.getElementById('op_rx').value = p.rx_oid || '';
        document.getElementById('op_tx').value = p.tx_oid || '';
        document.getElementById('op_invalid').value = p.invalid_values || '';
        document.getElementById('op_notes').value = p.notes || '';
        fillSelect('op_index', state.index_types, p.index_type || 'if_index');
        fillSelect('op_unit', state.units, p.value_unit || 'dbm_0_01');
        document.getElementById('op_errors').textContent = '';
        const modal = document.getElementById('opticalProfileModal');
        // Modal dirender di dalam wadah konten, yang membentuk konteks tumpukan sendiri:
        // z-index 2000-nya kalah dari footer & navigasi bawah HP. Pindahkan ke <body> sekali.
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
        modal.style.display = 'flex';
    };

    window.opticalCloseProfile = function () {
        document.getElementById('opticalProfileModal').style.display = 'none';
    };

    window.opticalSaveProfile = async function () {
        const val = id => document.getElementById(id).value.trim();
        const payload = {
            id: val('op_id') || null, name: val('op_name'), match_sys_object_id: val('op_match_oid'),
            match_sys_descr: val('op_match_descr'), rx_oid: val('op_rx'), tx_oid: val('op_tx'),
            index_type: val('op_index'), value_unit: val('op_unit'), invalid_values: val('op_invalid'), notes: val('op_notes'),
        };
        try {
            await api('api/optical/profiles', { method: 'POST', body: JSON.stringify(payload) });
            opticalCloseProfile();
            notify('Profil disimpan. Uji dulu sebelum diaktifkan.', 'success');
            opticalLoad();
        } catch (e) {
            document.getElementById('op_errors').textContent = e.errors
                ? Object.values(e.errors).flat().join(' ')
                : e.message;
        }
    };

    document.addEventListener('click', async function (ev) {
        const btn = ev.target.closest('#optical [data-action]');
        if (!btn || btn.tagName === 'SELECT') return;
        const action = btn.dataset.action;
        const profile = state.profiles.find(p => String(p.id) === btn.dataset.profile);
        try {
            if (action === 'edit' && profile) {
                opticalOpenProfile(profile);
            } else if (action === 'delete' && profile) {
                // Konfirmasi dua langkah tanpa window.confirm(): klik pertama mempersenjatai
                // tombol selama 4 detik, klik kedua baru menghapus.
                if (btn.dataset.armed !== '1') {
                    btn.dataset.armed = '1';
                    btn.innerHTML = 'Yakin?';
                    setTimeout(() => { btn.dataset.armed = ''; btn.innerHTML = '<i class="fas fa-trash"></i>'; }, 4000);
                    return;
                }
                await api('api/optical/profiles/' + profile.id, { method: 'DELETE' });
                notify('Profil dihapus', 'success');
                opticalLoad();
            } else if (action === 'test' && profile) {
                const sel = document.querySelector(`select[data-role="test-device"][data-profile="${profile.id}"]`);
                if (!sel || !sel.value) return notify('Pilih perangkat untuk uji', 'error');
                btn.disabled = true;
                const result = await api('api/optical/profiles/' + profile.id + '/test', {
                    method: 'POST', body: JSON.stringify({ device_id: Number(sel.value) }),
                });
                renderTest(profile, result);
                opticalLoad();
            } else if (action === 'toggle' && profile) {
                await api('api/optical/profiles/' + profile.id + '/activate', {
                    method: 'POST', body: JSON.stringify({ active: !profile.is_active }),
                });
                notify(profile.is_active ? 'Profil dinonaktifkan' : 'Profil diaktifkan', 'success');
                opticalLoad();
            } else if (action === 'redetect') {
                btn.disabled = true;
                const r = await api('api/optical/devices/' + btn.dataset.device + '/redetect', { method: 'POST' });
                notify('Vendor terdeteksi: ' + r.vendor, 'success');
                opticalLoad();
            }
        } catch (e) {
            notify(e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    document.addEventListener('change', async function (ev) {
        const sel = ev.target.closest('#optical select[data-action="override"]');
        if (!sel) return;
        try {
            await api('api/optical/devices/' + sel.dataset.device + '/override', {
                method: 'POST', body: JSON.stringify({ driver: sel.value }),
            });
            notify('Driver optik diperbarui; berlaku pada polling berikutnya', 'success');
        } catch (e) {
            notify(e.message, 'error');
            opticalLoad();
        }
    });
})();
