/*
 * Port "tidak dipakai" — dipakai halaman Interfaces, Monitoring, Devices, dan SLA.
 *
 * Port yang ditandai tidak dipakai (interfaces.is_monitored = 0) tetap dibaca poller,
 * tetapi tanpa alert, tanpa kejadian SLA, dan tanpa statistik; ia juga keluar dari
 * laporan SLA dan hitungan dashboard. Mengubahnya khusus admin (dicek ulang di server).
 *
 *   portMonitoring.open({ items: [{device_id, if_index, label}], monitored: false, onDone })
 *   portMonitoring.badge(row)   -> HTML lencana "Tidak dipakai" / "Aktif kembali" (sudah di-escape)
 */
(function () {
    const esc = (v) => window.escHtml(v);
    let current = null;

    function modal() {
        let el = document.getElementById('portMonitoringModal');
        if (el) return el;

        el = document.createElement('div');
        el.id = 'portMonitoringModal';
        el.className = 'modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.innerHTML = `
            <div class="modal-box pm-box">
                <button type="button" class="modal-close" data-pm="close" aria-label="Tutup">&times;</button>
                <h3 id="pmTitle"></h3>
                <p class="pm-lead" id="pmLead"></p>
                <ul class="pm-list" id="pmList"></ul>
                <div class="form-group" id="pmReasonGroup">
                    <label for="pmReason">Alasan (opsional, tampil di daftar port)</label>
                    <textarea id="pmReason" rows="3" maxlength="255"
                        placeholder="mis. pelanggan berhenti, link dipindah ke port lain"></textarea>
                </div>
                <p class="pm-error" id="pmError" role="alert" hidden></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" data-pm="close">Batal</button>
                    <button type="button" class="btn" id="pmSubmit"></button>
                </div>
            </div>`;
        // Di <body>, supaya tidak kalah tumpukan dengan footer & navigasi bawah HP.
        document.body.appendChild(el);

        el.addEventListener('click', (e) => {
            if (e.target === el || e.target.closest('[data-pm="close"]')) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && el.style.display === 'flex') close();
        });
        el.querySelector('#pmSubmit').addEventListener('click', submit);
        return el;
    }

    function close() {
        const el = document.getElementById('portMonitoringModal');
        if (el) el.style.display = 'none';
        current = null;
    }

    function open(opts) {
        if (!window.roleUtils || !window.roleUtils.requireAdmin('Hanya admin yang bisa mengubah status pantau port')) return;
        const items = (opts.items || []).filter((i) => i && i.device_id && i.if_index);
        if (!items.length) return;

        current = { items, monitored: !!opts.monitored, onDone: opts.onDone };
        const el = modal();
        const off = !current.monitored;

        el.querySelector('#pmTitle').innerHTML = off
            ? '<i class="fas fa-eye-slash"></i> Tandai tidak dipakai'
            : '<i class="fas fa-eye"></i> Pantau lagi';
        el.querySelector('#pmLead').textContent = off
            ? 'Port ini berhenti menghasilkan alert, kejadian SLA, dan statistik, serta keluar dari laporan SLA dan dashboard. '
              + 'Kejadian down yang masih terbuka ditutup sekarang. Status & RX tetap dibaca, jadi port yang hidup lagi akan diberi tanda "Aktif kembali".'
            : 'Port ini kembali dipantau penuh mulai siklus polling berikutnya (alert, SLA, statistik).';
        el.querySelector('#pmList').innerHTML = items.slice(0, 8)
            .map((i) => `<li>${esc(i.label || ('ifIndex ' + i.if_index))}</li>`).join('')
            + (items.length > 8 ? `<li>… dan ${esc(items.length - 8)} port lain</li>` : '');
        el.querySelector('#pmReasonGroup').hidden = !off;
        el.querySelector('#pmReason').value = '';
        el.querySelector('#pmError').hidden = true;

        const btn = el.querySelector('#pmSubmit');
        btn.disabled = false;
        btn.className = off ? 'btn btn-danger' : 'btn';
        btn.textContent = off ? `Tandai tidak dipakai (${items.length})` : `Pantau lagi (${items.length})`;

        el.style.display = 'flex';
        setTimeout(() => (off ? el.querySelector('#pmReason') : btn).focus(), 30);
    }

    async function submit() {
        if (!current) return;
        const el = modal();
        const btn = el.querySelector('#pmSubmit');
        const err = el.querySelector('#pmError');
        btn.disabled = true;

        try {
            const res = await fetch('/api/interfaces/monitoring', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    items: current.items.map((i) => ({ device_id: i.device_id, if_index: i.if_index })),
                    monitored: current.monitored,
                    reason: el.querySelector('#pmReason').value.trim(),
                }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) throw new Error(body.error || `Gagal (${res.status})`);

            const done = current.onDone;
            close();
            window.showNotification(
                body.monitored
                    ? `${body.changed} port dipantau lagi`
                    : `${body.changed} port ditandai tidak dipakai` + (body.closed_events ? ` · ${body.closed_events} kejadian SLA ditutup` : ''),
                'success'
            );
            if (typeof done === 'function') done(body);
        } catch (e) {
            err.textContent = e.message;
            err.hidden = false;
            btn.disabled = false;
        }
    }

    function badge(row) {
        if (!row || row.is_monitored !== false) return '';
        const why = row.unmonitored_reason ? ` title="${esc(row.unmonitored_reason)}"` : '';
        let html = `<span class="pm-badge pm-badge-off"${why}><i class="fas fa-eye-slash"></i> Tidak dipakai</span>`;
        if (row.active_again) {
            html += ' <span class="pm-badge pm-badge-again" title="Port ini up dengan sinyal — mungkin dipakai lagi"><i class="fas fa-bolt"></i> Aktif kembali</span>';
        }
        return html;
    }

    window.portMonitoring = { open, close, badge };
})();
