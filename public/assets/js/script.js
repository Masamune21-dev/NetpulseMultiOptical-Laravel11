/**
 * Main JavaScript file for Mikrotik CRS Monitor
 */

// Utility functions
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatBps(bps) {
    if (bps === 0) return '0 bps';
    const k = 1000;
    const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    const i = Math.floor(Math.log(bps) / Math.log(k));
    return parseFloat((bps / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatTimeAgo(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - timestamp;
    
    if (diff < 60) return diff + ' seconds ago';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' days ago';
    if (diff < 31536000) return Math.floor(diff / 2592000) + ' months ago';
    return Math.floor(diff / 31536000) + ' years ago';
}

// Modal management
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const inputs = form.querySelectorAll('[required]');
    let valid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            valid = false;
        } else {
            input.classList.remove('is-invalid');
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.classList.add('is-invalid');
                valid = false;
            }
        }
        
        // IP validation
        if (input.name === 'ip_address' && input.value) {
            const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            if (!ipRegex.test(input.value)) {
                input.classList.add('is-invalid');
                valid = false;
            }
        }
        
        // Password confirmation
        if (input.name === 'confirm_password' && input.value) {
            const password = form.querySelector('[name="password"]');
            if (password && password.value !== input.value) {
                input.classList.add('is-invalid');
                valid = false;
            }
        }
    });
    
    return valid;
}

// AJAX helper
function ajaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        
        if (method === 'POST' && data) {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(xhr.statusText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        if (data && method === 'POST') {
            const formData = new URLSearchParams();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            xhr.send(formData);
        } else {
            xhr.send();
        }
    });
}

// Notification system
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
        </div>
        <div class="toast-body">
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => toast.remove());
    }

    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            toast.classList.add('toast-hide');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}

function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-circle';
        case 'warning': return 'exclamation-triangle';
        default: return 'info-circle';
    }
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Real-time updates
class RealTimeUpdater {
    constructor(options = {}) {
        this.options = {
            url: 'api/data',
            interval: 10000, // 10 seconds
            ...options
        };
        this.intervalId = null;
        this.callbacks = [];
    }
    
    start() {
        if (this.intervalId) return;
        this.intervalId = setInterval(() => this.update(), this.options.interval);
        this.update(); // Initial update
    }
    
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
    
    update() {
        ajaxRequest(this.options.url)
            .then(data => {
                this.callbacks.forEach(callback => callback(data));
            })
            .catch(error => {
                console.error('Real-time update failed:', error);
            });
    }
    
    onUpdate(callback) {
        this.callbacks.push(callback);
    }
    
    removeCallback(callback) {
        this.callbacks = this.callbacks.filter(cb => cb !== callback);
    }
}

// Chart utilities
class ChartManager {
    constructor(chartId, options = {}) {
        this.chartId = chartId;
        this.chart = null;
        this.options = {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                }
            },
            ...options
        };
    }
    
    init() {
        const ctx = document.getElementById(this.chartId).getContext('2d');
        this.chart = new Chart(ctx, this.options);
        return this;
    }
    
    updateData(data) {
        if (!this.chart) return;
        
        this.chart.data.labels = data.labels || [];
        this.chart.data.datasets = data.datasets || [];
        this.chart.update();
    }
    
    addDataset(label, data, color = null) {
        if (!this.chart) return;
        
        const dataset = {
            label: label,
            data: data,
            borderColor: color || this.getRandomColor(),
            backgroundColor: this.hexToRgba(color || this.getRandomColor(), 0.1),
            borderWidth: 2,
            fill: true,
            tension: 0.1
        };
        
        this.chart.data.datasets.push(dataset);
        this.chart.update();
    }
    
    getRandomColor() {
        const colors = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
            '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6610f2'
        ];
        return colors[Math.floor(Math.random() * colors.length)];
    }
    
    hexToRgba(hex, alpha = 1) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}

// Device connection tester
function testDeviceConnection(deviceId) {
    showNotification('Testing connection...', 'info');
    
    ajaxRequest(`api/test_connection?id=${deviceId}`)
        .then(data => {
            if (data.success) {
                showNotification(`Connection successful: ${data.sysName}`, 'success');
            } else {
                showNotification(`Connection failed: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showNotification('Test failed: ' + error.message, 'error');
        });
}

// Export data
function exportData(format = 'csv', deviceId = null, startDate = null, endDate = null) {
    let url = `api/export?format=${format}`;
    
    if (deviceId) url += `&device_id=${deviceId}`;
    if (startDate) url += `&start=${startDate}`;
    if (endDate) url += `&end=${endDate}`;
    
    showNotification('Exporting data...', 'info');
    
    // Create temporary iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    
    setTimeout(() => {
        document.body.removeChild(iframe);
        showNotification('Export completed', 'success');
    }, 1000);
}

// Auto-refresh toggle
function initAutoRefresh() {
    const checkbox = document.getElementById('autoRefresh');
    const updater = new RealTimeUpdater();
    
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                updater.start();
                showNotification('Auto-refresh enabled', 'success');
            } else {
                updater.stop();
                showNotification('Auto-refresh disabled', 'info');
            }
        });
        
        // Start by default if checked
        if (checkbox.checked) {
            updater.start();
        }
    }
    
    return updater;
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Live clock in header
    const clockEls = document.querySelectorAll('[data-live-clock]');
    if (clockEls.length) {
        const updateClock = () => {
            const now = new Date();
            const time = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const date = now.toLocaleDateString('id-ID', {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            clockEls.forEach(el => {
                el.textContent = `${date} • ${time}`;
            });
        };
        updateClock();
        setInterval(updateClock, 1000);
    }

    // Initialize auto-refresh
    initAutoRefresh();

    // NetPulse global auto-refresh coordinator (all pages)
    // Purpose: provide a single timer and let each page register a refresh callback
    // so status/data updates become visible without manual reload.
    if (!window.netpulseRefresh) {
        window.netpulseRefresh = (function () {
            const callbacks = new Map(); // name -> { fn, minIntervalMs, lastRun }
            let intervalId = null;
            let enabled = true;

            const BASE_TICK_MS = 5000;

            function register(name, fn, options = {}) {
                if (!name || typeof fn !== 'function') return;
                const minIntervalMs = Number(options.minIntervalMs ?? 15000);
                callbacks.set(String(name), {
                    fn,
                    minIntervalMs: Number.isFinite(minIntervalMs) ? Math.max(1000, minIntervalMs) : 15000,
                    lastRun: 0
                });
            }

            function unregister(name) {
                callbacks.delete(String(name));
            }

            function tick(force = false) {
                if (!enabled) return;
                if (document.hidden) return;

                const now = Date.now();
                callbacks.forEach((c, name) => {
                    if (!force && c.lastRun && now - c.lastRun < c.minIntervalMs) {
                        return;
                    }
                    try {
                        c.lastRun = now;
                        c.fn();
                    } catch (e) {
                        console.error('netpulseRefresh callback failed:', name, e);
                    }
                });
            }

            function start() {
                if (intervalId) return;
                intervalId = setInterval(() => tick(false), BASE_TICK_MS);
                // Initial run (after a short delay to let page scripts attach).
                setTimeout(() => tick(true), 800);
            }

            function stop() {
                if (!intervalId) return;
                clearInterval(intervalId);
                intervalId = null;
            }

            function setEnabled(v) {
                enabled = !!v;
                if (enabled) start();
                else stop();
            }

            // Pause when tab hidden, resume when visible.
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) return;
                // Force a refresh when user comes back.
                tick(true);
            });

            return {
                register,
                unregister,
                start,
                stop,
                tick: () => tick(true),
                setEnabled,
                isRunning: () => !!intervalId
            };
        })();
    }
    window.netpulseRefresh.start();
    
    // Add form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this.id)) {
                e.preventDefault();
                showNotification('Please fill all required fields correctly', 'error');
            }
        });
    });
    
    // Add tooltips
    const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltips.forEach(element => {
        element.title = element.getAttribute('data-title');
    });
    
    // Add confirmation for destructive actions
    const confirmLinks = document.querySelectorAll('a[data-confirm]');
    confirmLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Initialize charts if present
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#666';
    }
});

// Delete confirmation modal
let deleteConfirmCallback = null;

window.confirmDelete = function (message, onConfirm) {
    const modal = document.getElementById('confirmDeleteModal');
    const msg = document.getElementById('confirmDeleteMessage');
    const yesBtn = document.getElementById('confirmDeleteYes');
    if (!modal || !msg || !yesBtn) {
        if (confirm(message || 'Yakin ingin menghapus data ini?')) {
            onConfirm && onConfirm();
        }
        return;
    }

    msg.textContent = message || 'Yakin ingin menghapus data ini?';
    deleteConfirmCallback = onConfirm || null;
    modal.style.display = 'flex';

    const clickHandler = () => {
        const cb = deleteConfirmCallback;
        closeDeleteModal();
        if (cb) {
            cb();
        }
    };

    yesBtn.onclick = clickHandler;
};

window.closeDeleteModal = function () {
    const modal = document.getElementById('confirmDeleteModal');
    if (modal) modal.style.display = 'none';
    deleteConfirmCallback = null;
};

document.addEventListener('click', (e) => {
    const modal = document.getElementById('confirmDeleteModal');
    if (modal && modal.style.display === 'flex' && e.target === modal) {
        closeDeleteModal();
    }
});

// Error handling
window.onerror = function(message, source, lineno, colno, error) {
    console.error('Global error:', { message, source, lineno, colno, error });
    showNotification('An error occurred. Please check console for details.', 'error');
    return false;
};

// Export functions for global use
window.mikrotikMonitor = {
    formatBytes,
    formatBps,
    formatTimeAgo,
    showNotification,
    testDeviceConnection,
    exportData,
    RealTimeUpdater,
    ChartManager
};

// Backward compatibility for direct calls
window.showNotification = showNotification;

// Role helpers
window.roleUtils = {
    getRole() {
        return document.body?.dataset?.role || 'viewer';
    },
    isAdmin() {
        return this.getRole() === 'admin';
    },
    requireAdmin(message = 'Akses ditolak') {
        if (!this.isAdmin()) {
            showNotification(message, 'warning');
            return false;
        }
        return true;
    }
};
