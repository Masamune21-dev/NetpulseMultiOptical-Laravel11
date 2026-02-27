let chart = null;
let currentRange = '1h';
let autoRefreshTimer = null;
let autoRefreshPaused = false;

const deviceSelect = document.getElementById('deviceSelect');
const interfaceSelect = document.getElementById('interfaceSelect');
const rangeSelectMobile = document.getElementById('rangeSelectMobile');

/* ======================================================
   INIT
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    initChart();
    loadDevices();

    document.querySelectorAll('.btn-range').forEach(btn => {
        btn.addEventListener('click', () => {
            setRangeUI(btn.dataset.range);
        });
    });

    if (rangeSelectMobile) {
        rangeSelectMobile.value = currentRange;
        rangeSelectMobile.addEventListener('change', () => {
            setRangeUI(rangeSelectMobile.value);
        });
    }
});

/* ======================================================
   AUTO REFRESH
====================================================== */
function startAutoRefresh() {
    stopAutoRefresh();

    const refreshIntervals = {
        '1h': 10000,
        '1d': 30000,
        '3d': 60000,
        '7d': 120000,
        '30d': 300000,
        '1y': 600000
    };

    autoRefreshTimer = setInterval(
        loadChart,
        refreshIntervals[currentRange] || 10000
    );
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}

/* ======================================================
   LOAD DEVICES & INTERFACES
====================================================== */
function loadDevices() {
    fetch('api/monitoring_devices')
        .then(r => r.json())
        .then(devices => {
            deviceSelect.innerHTML =
                '<option value="">-- Pilih Device --</option>';

            devices.forEach(d => {
                deviceSelect.innerHTML += `
                    <option value="${d.id}">
                        ${d.device_name}
                    </option>`;
            });
        });
}

deviceSelect.addEventListener('change', () => {
    stopAutoRefresh();
    interfaceSelect.innerHTML =
        '<option value="">-- Pilih Interface --</option>';

    if (!deviceSelect.value) return;

    fetch(`api/monitoring_interfaces?device_id=${deviceSelect.value}`)
        .then(r => r.json())
        .then(ifs => {
            ifs.forEach(i => {
                interfaceSelect.innerHTML += `
                    <option value="${i.if_index}"
                            data-name="${i.if_name}"
                            data-alias="${i.if_alias || ''}">
                        ${i.if_name}${i.if_alias ? ' — ' + i.if_alias : ''}
                    </option>`;
            });
        });
});

interfaceSelect.addEventListener('change', () => {
    const opt = interfaceSelect.selectedOptions[0];
    if (!opt) return;

    const name = opt.dataset.name;
    const alias = opt.dataset.alias;

    document.getElementById('ifaceInfo').innerHTML = alias
        ? `Interface: <b>${name}</b> <span style="color:#64748b">(${alias})</span>`
        : `Interface: <b>${name}</b>`;

    loadChart();
    startAutoRefresh();
});

// Pause polling when tab is hidden (reduce load)
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        if (autoRefreshTimer) {
            autoRefreshPaused = true;
            stopAutoRefresh();
        }
        return;
    }

    if (autoRefreshPaused && interfaceSelect.value) {
        autoRefreshPaused = false;
        startAutoRefresh();
    }
});

/* ======================================================
   RANGE
====================================================== */
function setRange(r) {
    currentRange = r;
    loadChart();
    startAutoRefresh();
}

function setRangeUI(r) {
    currentRange = r;

    // Sync buttons
    document.querySelectorAll('.btn-range')
        .forEach(b => b.classList.remove('active'));
    const activeBtn = document.querySelector(`.btn-range[data-range="${CSS.escape(r)}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    // Sync mobile dropdown
    if (rangeSelectMobile) {
        rangeSelectMobile.value = r;
    }

    loadChart();
    startAutoRefresh();
}

/* ======================================================
   INIT CHART (TIME SCALE, 24 JAM WIB)
====================================================== */
function initChart() {
    const ctx = document.getElementById('opticalChart').getContext('2d');

    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                data: [],
                borderColor: '#6366f1',
                backgroundColor: 'rgba(255,255,255,0.06)',
                borderWidth: 2,
                tension: 0.25,
                pointRadius: 0,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.parsed.y.toFixed(2)} dBm`
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        tooltipFormat: 'dd MMM yyyy HH:mm' // 24 JAM
                    },
                    ticks: {
                        maxTicksLimit: 10,
                        callback: (value) => {
                            const d = new Date(value);
                            return new Intl.DateTimeFormat('id-ID', {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false 
                            }).format(d);
                        }

                    },
                    grid: {
                        color: 'rgba(255,255,255,0.05)'
                    }
                },
                y: {
                    ticks: {
                        callback: v => v.toFixed(2) + ' dBm'
                    }
                }
            }
        }
    });
}


/* ======================================================
   LOAD CHART DATA
====================================================== */
function loadChart() {
    if (!chart || !deviceSelect.value || !interfaceSelect.value) return;

    fetch(
        `api/interface_chart?device_id=${deviceSelect.value}` +
        `&if_index=${interfaceSelect.value}&range=${currentRange}`
    )
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                // Tampilkan pesan atau chart kosong
                updateStats([-40]); // Default -40 dBm
                return;
            }

            // Filter dan map data
            const rx = data.map(d => {
                let value = Number(d.rx_power);
                // Jika null/NaN, set ke -40 (interface down)
                return isNaN(value) ? -40 : value;
            });

            let labels = data.map(d =>
                new Date(d.created_at.replace(' ', 'T'))
            );
            let rxData = rx;

            if (data.length > 100) {
                const step = Math.ceil(data.length / 120);
                labels = [];
                rxData = [];

                for (let i = 0; i < data.length; i += step) {
                    const chunk = data.slice(i, i + step);
                    if (!chunk.length) continue;

                    let min = null, max = null;
                    let minT = null, maxT = null;

                    chunk.forEach(row => {
                        const v = isNaN(Number(row.rx_power)) ? -40 : Number(row.rx_power);
                        const t = new Date(row.created_at.replace(' ', 'T'));
                        if (min === null || v < min) { min = v; minT = t; }
                        if (max === null || v > max) { max = v; maxT = t; }
                    });

                    const points = [];
                    if (minT && maxT) {
                        if (minT <= maxT) {
                            points.push([minT, min], [maxT, max]);
                        } else {
                            points.push([maxT, max], [minT, min]);
                        }
                    } else if (minT) {
                        points.push([minT, min]);
                    }

                    points.forEach(([t, v]) => {
                        labels.push(t);
                        rxData.push(v);
                    });
                }

                // Pastikan titik terakhir selalu ikut
                const lastIdx = data.length - 1;
                const lastLabel = new Date(data[lastIdx].created_at.replace(' ', 'T'));
                if (!labels.length || labels[labels.length - 1].getTime() !== lastLabel.getTime()) {
                    labels.push(lastLabel);
                    const lastValue = Number(data[lastIdx].rx_power);
                    rxData.push(isNaN(lastValue) ? -40 : lastValue);
                }
            }

            // ⏱️ UNIT WAKTU PER RANGE
            const timeUnits = {
                '1h': 'minute',
                '1d': 'hour',
                '3d': 'hour',
                '7d': 'day',
                '30d': 'day',
                '1y': 'month'
            };

            chart.options.scales.x.time.unit = timeUnits[currentRange];

            // 🔒 PAKSA FORMAT 24 JAM (TANPA AM/PM)
            chart.options.scales.x.ticks.callback = (value) => {
                const d = new Date(value);

                const formats = {
                    '1h': { hour: '2-digit', minute: '2-digit' },
                    '1d': { hour: '2-digit', minute: '2-digit' },
                    '3d': { weekday: 'short', hour: '2-digit' },
                    '7d': { weekday: 'short', day: '2-digit' },
                    '30d': { day: '2-digit', month: 'short' },
                    '1y': { month: 'short', year: '2-digit' }
                };

                return new Intl.DateTimeFormat(
                    'id-ID',
                    { ...formats[currentRange], hour12: false }
                ).format(d);
            };

            const primaryColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--primary')
                .trim() || '#6366f1';

            chart.data.labels = labels;
            chart.data.datasets[0].data = rxData;
            chart.data.datasets[0].borderColor = primaryColor;

            // Update chart warna untuk nilai -40 (interface down)
            chart.data.datasets[0].segment = {
                borderColor: ctx => {
                    return ctx.p1.parsed.y === -40 ?
                        'rgba(255, 99, 132, 0.5)' : 
                        primaryColor; 
                }
            };

            chart.update();
            updateStats(rx);
        })
        .catch(err => {
            console.error('Error loading chart:', err);
            updateStats([-40]); // Fallback ke -40 dBm
        });
}

/* ======================================================
   STATS
====================================================== */
function updateStats(rx) {
    if (!rx || rx.length === 0) {
        document.getElementById('rxStats').innerHTML = `
            Now <b>-40.00 dBm</b>
            Avg <b>-40.00 dBm</b>
            Min <b>-40.00 dBm</b>
            Max <b>-40.00 dBm</b>
            <span style="color:#ff6b6b">(Interface Down)</span>
        `;
        return;
    }

    const now = rx.at(-1);

    // Filter nilai -40 untuk perhitungan avg, min, max
    const validValues = rx.filter(v => v > -40);

    let avg, min, max;

    if (validValues.length > 0) {
        avg = validValues.reduce((a, b) => a + b, 0) / validValues.length;
        min = Math.min(...validValues);
        max = Math.max(...validValues);
    } else {
        // Semua nilai -40 (interface selalu down)
        avg = -40;
        min = -40;
        max = -40;
    }

    const downIndicator = now === -40 ?
        ' <span style="color:#ff6b6b">(Down)</span>' :
        '';

    document.getElementById('rxStats').innerHTML = `
        Now <b>${now.toFixed(2)} dBm</b>${downIndicator}
        Avg <b>${avg.toFixed(2)} dBm</b>
        Min <b>${min.toFixed(2)} dBm</b>
        Max <b>${max.toFixed(2)} dBm</b>
    `;
}
