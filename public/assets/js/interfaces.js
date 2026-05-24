// ===============================
// INTERFACES LIST PAGE
// ===============================
(function () {
    const state = {
        page: 1,
        perPage: 25,
        deviceId: '',
        status: 'all',
        q: '',
    };

    let searchDebounceTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadDeviceOptions();
        wireFilters();
        fetchInterfaces();

        if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
            window.netpulseRefresh.register('interfaces', () => {
                if (!location.pathname.startsWith('/interfaces')) return;
                if (document.hidden) return;
                fetchInterfaces(true);
            }, { minIntervalMs: 30000 });
        }
    });

    function wireFilters() {
        const deviceEl = document.getElementById('ifFilterDevice');
        const statusEl = document.getElementById('ifFilterStatus');
        const searchEl = document.getElementById('ifFilterSearch');
        const perPageEl = document.getElementById('ifPerPage');
        const resetEl = document.getElementById('ifFilterReset');

        deviceEl.addEventListener('change', () => {
            state.deviceId = deviceEl.value;
            state.page = 1;
            fetchInterfaces();
        });

        statusEl.addEventListener('change', () => {
            state.status = statusEl.value;
            state.page = 1;
            fetchInterfaces();
        });

        searchEl.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                state.q = searchEl.value.trim();
                state.page = 1;
                fetchInterfaces();
            }, 350);
        });

        perPageEl.addEventListener('change', () => {
            const v = parseInt(perPageEl.value, 10);
            state.perPage = [10, 25, 50].includes(v) ? v : 25;
            state.page = 1;
            fetchInterfaces();
        });

        resetEl.addEventListener('click', () => {
            deviceEl.value = '';
            statusEl.value = 'all';
            searchEl.value = '';
            perPageEl.value = '25';
            state.deviceId = '';
            state.status = 'all';
            state.q = '';
            state.perPage = 25;
            state.page = 1;
            fetchInterfaces();
        });
    }

    async function loadDeviceOptions() {
        try {
            const res = await fetch('/api/monitoring_devices', { credentials: 'same-origin' });
            const json = await res.json();
            const list = Array.isArray(json) ? json : (json.data || []);
            const sel = document.getElementById('ifFilterDevice');
            list.forEach(d => {
                const opt = document.createElement('option');
                opt.value = String(d.id);
                opt.textContent = d.device_name || `Device ${d.id}`;
                sel.appendChild(opt);
            });
        } catch (e) {
            console.error('Failed to load devices', e);
        }
    }

    async function fetchInterfaces(silent = false) {
        const tbody = document.getElementById('ifTableBody');
        if (!silent && !tbody.dataset.loaded) {
            tbody.innerHTML = `<tr><td colspan="10" class="if-empty"><i class="fas fa-circle-notch fa-spin"></i> Loading...</td></tr>`;
        }

        const params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', state.perPage);
        if (state.deviceId) params.set('device_id', state.deviceId);
        if (state.status && state.status !== 'all') params.set('status', state.status);
        if (state.q) params.set('q', state.q);

        try {
            const res = await fetch(`/api/interfaces/all?${params.toString()}`, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) {
                if (!tbody.dataset.loaded) renderError(json.error || 'Failed to load');
                return;
            }
            renderRows(json.data || []);
            renderMeta(json.meta || {});
            renderPager(json.meta || {});
            tbody.dataset.loaded = '1';
        } catch (e) {
            console.error(e);
            if (!tbody.dataset.loaded) renderError('Network error');
        }
    }

    function renderRows(rows) {
        const tbody = document.getElementById('ifTableBody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="10" class="if-empty">No interfaces found</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const isUp = r.oper_status === 1;
            const statusBadge = isUp
                ? `<span class="badge badge-success status-badge"><span class="status-dot"></span>UP</span>`
                : `<span class="badge badge-danger status-badge"><span class="status-dot"></span>DOWN</span>`;

            const rx = formatDbm(r.rx_power);
            const tx = formatDbm(r.tx_power);
            const rxClass = colorForDbm(r.rx_power);
            const txClass = colorForDbm(r.tx_power);

            const speed = formatBps(r.if_speed, 0);
            const trafficIn = r.in_rate_bps != null ? formatBps(r.in_rate_bps) : '—';
            const trafficOut = r.out_rate_bps != null ? formatBps(r.out_rate_bps) : '—';

            const deviceLabel = escapeHtml(r.device_name || `Device ${r.device_id}`);
            const ifName = escapeHtml(r.if_name || `if${r.if_index}`);
            const ifAlias = escapeHtml(r.if_alias || '');
            const ifDesc = escapeHtml(r.if_description || '');
            const type = r.interface_type ? `<span class="if-type-tag">${escapeHtml(r.interface_type)}</span>` : '';
            const description = ifAlias || ifDesc || '—';

            return `
                <tr>
                    <td>
                        <div class="if-cell-device">
                            <span class="if-device-name">${deviceLabel}</span>
                            <span class="if-device-ip">${escapeHtml(r.device_ip || '')}</span>
                        </div>
                    </td>
                    <td>
                        <div class="if-cell-name">
                            <span class="if-name">${ifName}</span>
                            ${type}
                        </div>
                    </td>
                    <td class="if-desc">${description}</td>
                    <td class="if-num ${rxClass}">${rx}</td>
                    <td class="if-num ${txClass}">${tx}</td>
                    <td class="if-status-cell">${statusBadge}</td>
                    <td class="if-num">${speed}</td>
                    <td class="if-traffic">
                        <span class="if-traffic-line if-traffic-in"><i class="fas fa-arrow-down"></i> ${trafficIn}</span>
                        <span class="if-traffic-line if-traffic-out"><i class="fas fa-arrow-up"></i> ${trafficOut}</span>
                    </td>
                    <td class="if-time">${escapeHtml(r.last_seen || '—')}</td>
                    <td class="if-action-cell">
                        <button type="button" class="if-action-btn" title="View traffic history"
                                data-device="${r.device_id}" data-ifindex="${r.if_index}">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.if-action-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                openTrafficModal(parseInt(btn.dataset.device, 10), parseInt(btn.dataset.ifindex, 10));
            });
        });
    }

    function renderMeta(meta) {
        const countEl = document.getElementById('ifCount');
        const metaEl = document.getElementById('ifMeta');
        const total = meta.total ?? 0;
        const page = meta.page ?? 1;
        const perPage = meta.per_page ?? state.perPage;
        const last = meta.last_page ?? 1;
        const from = total === 0 ? 0 : (page - 1) * perPage + 1;
        const to = Math.min(page * perPage, total);

        countEl.textContent = `${total} interface${total === 1 ? '' : 's'}`;
        metaEl.textContent = total === 0 ? 'No data' : `Showing ${from}–${to} of ${total} (page ${page}/${last})`;
    }

    function renderPager(meta) {
        const wrap = document.getElementById('ifPagerButtons');
        const page = meta.page ?? 1;
        const last = meta.last_page ?? 1;

        if (last <= 1) {
            wrap.innerHTML = '';
            return;
        }

        const pages = pageWindow(page, last);
        const btnFirst = `<button class="if-pg-btn" data-page="1" ${page === 1 ? 'disabled' : ''}><i class="fas fa-angles-left"></i></button>`;
        const btnPrev = `<button class="if-pg-btn" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}><i class="fas fa-angle-left"></i></button>`;
        const btnNext = `<button class="if-pg-btn" data-page="${page + 1}" ${page === last ? 'disabled' : ''}><i class="fas fa-angle-right"></i></button>`;
        const btnLast = `<button class="if-pg-btn" data-page="${last}" ${page === last ? 'disabled' : ''}><i class="fas fa-angles-right"></i></button>`;

        const numbered = pages.map(p => {
            if (p === '...') return `<span class="if-pg-ellipsis">…</span>`;
            return `<button class="if-pg-btn ${p === page ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }).join('');

        wrap.innerHTML = `${btnFirst}${btnPrev}${numbered}${btnNext}${btnLast}`;

        wrap.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const p = parseInt(btn.dataset.page, 10);
                if (Number.isNaN(p) || btn.disabled) return;
                state.page = p;
                fetchInterfaces();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    function renderError(msg) {
        const tbody = document.getElementById('ifTableBody');
        tbody.innerHTML = `<tr><td colspan="10" class="if-empty if-empty-err"><i class="fas fa-triangle-exclamation"></i> ${escapeHtml(msg)}</td></tr>`;
    }

    function pageWindow(current, last) {
        const out = [];
        const window = 2;
        const start = Math.max(1, current - window);
        const end = Math.min(last, current + window);
        if (start > 1) {
            out.push(1);
            if (start > 2) out.push('...');
        }
        for (let i = start; i <= end; i++) out.push(i);
        if (end < last) {
            if (end < last - 1) out.push('...');
            out.push(last);
        }
        return out;
    }

    function formatDbm(v) {
        if (v === null || v === undefined) return '—';
        const num = parseFloat(v);
        if (Number.isNaN(num)) return '—';
        return num.toFixed(2);
    }

    function colorForDbm(v) {
        if (v === null || v === undefined) return '';
        const n = parseFloat(v);
        if (Number.isNaN(n)) return '';
        if (n <= -40) return 'if-rx-down';
        if (n < -25) return 'if-rx-critical';
        if (n < -18) return 'if-rx-warn';
        return 'if-rx-ok';
    }

    function formatBps(bps, decimals = 2) {
        if (bps === null || bps === undefined) return '—';
        const v = Number(bps);
        if (!Number.isFinite(v) || v < 0) return '—';
        if (v === 0) return '0 bps';
        const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        let i = 0;
        let n = v;
        while (n >= 1000 && i < units.length - 1) {
            n /= 1000;
            i++;
        }
        return `${n.toFixed(decimals)} ${units[i]}`;
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ==========================================================
       Traffic history modal + chart
       ========================================================== */
    let trafficChart = null;
    const modalState = {
        deviceId: null,
        ifIndex: null,
        range: '1d',
    };

    function openTrafficModal(deviceId, ifIndex) {
        modalState.deviceId = deviceId;
        modalState.ifIndex = ifIndex;
        modalState.range = '1d';

        const modal = document.getElementById('ifTrafficModal');
        modal.style.display = 'flex';

        document.querySelectorAll('.if-tm-range').forEach(b => {
            b.classList.toggle('active', b.dataset.range === modalState.range);
        });

        loadTrafficHistory();
    }

    function ifCloseTrafficModal() {
        const modal = document.getElementById('ifTrafficModal');
        modal.style.display = 'none';
        if (trafficChart) {
            trafficChart.destroy();
            trafficChart = null;
        }
    }
    window.ifCloseTrafficModal = ifCloseTrafficModal;

    async function loadTrafficHistory() {
        if (!modalState.deviceId || !modalState.ifIndex) return;

        const loading = document.getElementById('ifTmLoading');
        loading.style.display = 'flex';

        try {
            const url = `/api/interfaces/traffic_history?device_id=${modalState.deviceId}&if_index=${modalState.ifIndex}&range=${modalState.range}`;
            const res = await fetch(url, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) {
                loading.innerHTML = `<i class="fas fa-triangle-exclamation"></i> ${escapeHtml(json.error || 'Failed to load')}`;
                return;
            }
            renderModalMeta(json.meta || {});
            renderTrafficChart(json.data || []);
            renderSummary(json.summary || {});
            loading.style.display = 'none';
        } catch (e) {
            console.error(e);
            loading.innerHTML = `<i class="fas fa-triangle-exclamation"></i> Network error`;
        }
    }

    function renderModalMeta(meta) {
        document.getElementById('ifTmIfName').textContent = meta.if_name || '—';
        document.getElementById('ifTmSpeed').textContent = meta.if_speed ? formatBps(meta.if_speed, 0) : '—';

        const aliasEl = document.getElementById('ifTmAlias');
        const aliasText = meta.if_alias || meta.if_description || '';
        aliasEl.textContent = aliasText || '—';

        const devEl = document.getElementById('ifTmDevice');
        const devLabel = meta.device_name ? `${meta.device_name}${meta.device_ip ? ' · ' + meta.device_ip : ''}` : '';
        devEl.textContent = devLabel;

        const stEl = document.getElementById('ifTmStatus');
        const isUp = meta.oper_status === 1;
        stEl.textContent = isUp ? 'Up' : 'Down';
        stEl.classList.remove('if-tm-status--up', 'if-tm-status--down');
        stEl.classList.add(isUp ? 'if-tm-status--up' : 'if-tm-status--down');
    }

    function renderSummary(s) {
        const fmt = v => v == null ? '—' : formatBps(v);
        document.getElementById('ifTmInCur').textContent = fmt(s.in_cur);
        document.getElementById('ifTmInAvg').textContent = fmt(s.in_avg);
        document.getElementById('ifTmInMax').textContent = fmt(s.in_max);
        document.getElementById('ifTmOutCur').textContent = fmt(s.out_cur);
        document.getElementById('ifTmOutAvg').textContent = fmt(s.out_avg);
        document.getElementById('ifTmOutMax').textContent = fmt(s.out_max);
    }

    function renderTrafficChart(rows) {
        const ctx = document.getElementById('ifTrafficChart').getContext('2d');

        const labels = rows.map(r => new Date(r.created_at.replace(' ', 'T')));
        const inSeries = rows.map(r => r.in_rate_bps != null ? r.in_rate_bps / 1_000_000 : null);
        const outSeries = rows.map(r => r.out_rate_bps != null ? r.out_rate_bps / 1_000_000 : null);

        if (trafficChart) {
            trafficChart.data.labels = labels;
            trafficChart.data.datasets[0].data = inSeries;
            trafficChart.data.datasets[1].data = outSeries;
            trafficChart.update();
            return;
        }

        trafficChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'In (Mbps)',
                        data: inSeries,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.18)',
                        borderWidth: 1.5,
                        tension: 0.25,
                        pointRadius: 0,
                        fill: true,
                    },
                    {
                        label: 'Out (Mbps)',
                        data: outSeries,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.22)',
                        borderWidth: 1.5,
                        tension: 0.25,
                        pointRadius: 0,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 12, boxHeight: 12, font: { size: 11, weight: '700' } },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.dataset.label}: ${formatMbps(ctx.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        type: 'time',
                        time: { tooltipFormat: 'dd MMM yyyy HH:mm' },
                        ticks: {
                            maxTicksLimit: 8,
                            callback: (v) => {
                                const d = new Date(v);
                                return new Intl.DateTimeFormat('id-ID', {
                                    hour: '2-digit', minute: '2-digit', hour12: false,
                                }).format(d);
                            },
                        },
                        grid: { color: 'rgba(128,128,128,0.12)' },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => formatMbpsTick(v) },
                        grid: { color: 'rgba(128,128,128,0.12)' },
                    },
                },
            },
        });
    }

    function formatMbps(v) {
        if (v == null || !Number.isFinite(v)) return '—';
        if (v >= 1000) return (v / 1000).toFixed(2) + ' Gbps';
        if (v >= 1) return v.toFixed(2) + ' Mbps';
        if (v >= 0.001) return (v * 1000).toFixed(0) + ' Kbps';
        return '0 bps';
    }
    function formatMbpsTick(v) {
        if (v == null) return '';
        if (v >= 1000) return (v / 1000).toFixed(1) + ' G';
        if (v >= 1) return v.toFixed(0) + ' M';
        return v.toFixed(2);
    }

    // Range buttons
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.if-tm-range').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.if-tm-range').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                modalState.range = btn.dataset.range;
                loadTrafficHistory();
            });
        });

        // Click backdrop closes modal
        const modal = document.getElementById('ifTrafficModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) ifCloseTrafficModal();
            });
        }

        // ESC closes
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const m = document.getElementById('ifTrafficModal');
                if (m && m.style.display === 'flex') ifCloseTrafficModal();
            }
        });
    });
})();
