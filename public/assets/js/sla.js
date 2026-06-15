(function () {
    'use strict';

    const state = { days: 30, deviceId: '', q: '', expanded: new Set() };
    let searchTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadDevices();
        bindToolbar();
        load();
    });

    function bindToolbar() {
        document.querySelectorAll('.sla-range').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.sla-range').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.days = parseInt(btn.dataset.days, 10);
                const lbl = document.getElementById('slaPeriodLabel');
                if (lbl) lbl.textContent = `last ${state.days} days`;
                state.expanded.clear();
                load();
            });
        });

        const dev = document.getElementById('slaDevice');
        if (dev) dev.addEventListener('change', () => { state.deviceId = dev.value; state.expanded.clear(); load(); });

        const search = document.getElementById('slaSearch');
        if (search) search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { state.q = search.value.trim(); state.expanded.clear(); load(); }, 300);
        });

        const exportBtn = document.getElementById('slaExport');
        if (exportBtn) exportBtn.addEventListener('click', () => {
            const params = new URLSearchParams({ days: state.days });
            if (state.deviceId) params.set('device_id', state.deviceId);
            if (state.q) params.set('q', state.q);
            window.location = `/api/sla/export?${params.toString()}`;
        });
    }

    function loadDevices() {
        fetch('/api/monitoring_devices', { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : [])
            .then(list => {
                const sel = document.getElementById('slaDevice');
                if (!sel || !Array.isArray(list)) return;
                list.forEach(d => {
                    const o = document.createElement('option');
                    o.value = d.id;
                    o.textContent = d.device_name;
                    sel.appendChild(o);
                });
            })
            .catch(() => {});
    }

    function load() {
        const tbody = document.getElementById('slaTableBody');
        tbody.innerHTML = `<tr><td colspan="7" class="if-empty"><i class="fas fa-circle-notch fa-spin"></i> Loading…</td></tr>`;

        const params = new URLSearchParams({ days: state.days });
        if (state.deviceId) params.set('device_id', state.deviceId);
        if (state.q) params.set('q', state.q);

        fetch(`/api/sla?${params.toString()}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (!json || json.success === false) throw new Error('Failed to load');
                renderStats(json.totals);
                renderRows(json.interfaces || []);
            })
            .catch(() => {
                tbody.innerHTML = `<tr><td colspan="7" class="if-empty if-empty-err"><i class="fas fa-triangle-exclamation"></i> Failed to load SLA data</td></tr>`;
            });
    }

    function renderStats(t) {
        t = t || {};
        document.getElementById('slaStatIfaces').textContent = t.interfaces ?? 0;
        document.getElementById('slaStatEvents').textContent = t.down_events ?? 0;
        document.getElementById('slaStatDowntime').textContent = fmtDur(t.down_sec || 0);
    }

    function renderRows(rows) {
        const tbody = document.getElementById('slaTableBody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="if-empty"><i class="fas fa-circle-check"></i> No interface downs in this period 🎉</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const key = `${r.device_id}:${r.if_index}`;
            const name = esc(r.if_alias || r.if_name || ('ifIndex ' + r.if_index));
            const sub = r.if_alias ? `<span class="sla-ifsub">${esc(r.if_name || '')}</span>` : '';
            const avail = r.availability !== null ? r.availability.toFixed(3) + '%' : '—';
            const availCls = r.availability === null ? '' : (r.availability >= 99.9 ? 'sla-ok' : (r.availability >= 99 ? 'sla-warn' : 'sla-bad'));
            const status = r.still_down ? '<span class="sla-badge sla-badge-down">DOWN now</span>' : '';
            return `
                <tr class="sla-row" data-key="${key}" data-device="${r.device_id}" data-ifindex="${r.if_index}">
                    <td>${esc(r.device_name || '')}</td>
                    <td><div class="sla-ifname">${name} ${status}</div>${sub}</td>
                    <td style="text-align:center"><b>${r.down_count}</b></td>
                    <td style="text-align:center">${fmtDur(r.down_sec)}</td>
                    <td style="text-align:center" class="${availCls}">${avail}</td>
                    <td class="if-time">${esc(r.last_down_at || '—')}</td>
                    <td style="text-align:center"><button class="if-action-btn sla-expand" title="Show each down"><i class="fas fa-chevron-down"></i></button></td>
                </tr>
                <tr class="sla-detail" data-detail="${key}" style="display:none"><td colspan="7"><div class="sla-detail-box">Loading…</div></td></tr>
            `;
        }).join('');

        tbody.querySelectorAll('.sla-row').forEach(row => {
            row.querySelector('.sla-expand').addEventListener('click', () => toggleDetail(row));
        });
    }

    function toggleDetail(row) {
        const key = row.dataset.key;
        const detail = document.querySelector(`tr.sla-detail[data-detail="${key}"]`);
        if (!detail) return;
        const icon = row.querySelector('.sla-expand i');
        const open = detail.style.display !== 'none';
        if (open) {
            detail.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
            return;
        }
        detail.style.display = '';
        icon.className = 'fas fa-chevron-up';

        const box = detail.querySelector('.sla-detail-box');
        const params = new URLSearchParams({ days: state.days, device_id: row.dataset.device, if_index: row.dataset.ifindex });
        fetch(`/api/sla/events?${params.toString()}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                const ev = (json && json.events) || [];
                if (!ev.length) { box.innerHTML = '<div class="sla-empty">No events.</div>'; return; }
                box.innerHTML = `
                    <table class="sla-detail-table">
                        <thead><tr><th>#</th><th>Down at</th><th>Up at</th><th>Duration</th></tr></thead>
                        <tbody>
                        ${ev.map((e, i) => `
                            <tr>
                                <td>${i + 1}</td>
                                <td>${esc(e.down_at)}</td>
                                <td>${e.ongoing ? '<span class="sla-badge sla-badge-down">still down</span>' : esc(e.up_at)}</td>
                                <td>${fmtDur(e.duration_sec)}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>`;
            })
            .catch(() => { box.innerHTML = '<div class="sla-empty">Failed to load events.</div>'; });
    }

    function fmtDur(sec) {
        sec = parseInt(sec, 10) || 0;
        if (sec < 60) return sec + 's';
        const d = Math.floor(sec / 86400);
        const h = Math.floor((sec % 86400) / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const parts = [];
        if (d) parts.push(d + 'd');
        if (h) parts.push(h + 'h');
        if (m) parts.push(m + 'm');
        return parts.join(' ') || '0m';
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
})();
