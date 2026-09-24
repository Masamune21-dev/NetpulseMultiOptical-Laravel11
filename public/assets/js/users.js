// ===============================
// INITIAL LOAD
// ===============================
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    initializeEventListeners();

    // Global auto-refresh: keep users list updated.
    if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
        window.netpulseRefresh.register('users', () => {
            if (!location.pathname.startsWith('/users')) return;
            if (document.hidden) return;
            const modalOpen = document.getElementById('userModal')?.style?.display === 'flex';
            if (modalOpen) return;
            loadUsers(true);
        }, { minIntervalMs: 30000 });
    }
});

function requireAdmin() {
    const role = document.body?.dataset?.role || 'viewer';
    if (role !== 'admin') {
        if (typeof showNotification === 'function') {
            showNotification('Akses ditolak', 'warning');
        } else {
            showAlert('warning', 'Akses ditolak');
        }
        return false;
    }
    return true;
}

// ===============================
// INITIALIZE EVENT LISTENERS
// ===============================
function initializeEventListeners() {
    // Add enter key support in modal
    document.getElementById('userModal').addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            saveUser();
        }
    });
}

// ===============================
// LOAD USERS
// ===============================
function loadUsers(silent = false) {
    const tbody = document.querySelector('#userTable tbody');
    if (!tbody) {
        console.error('User table body not found');
        return;
    }

    if (!silent && !tbody.dataset.loaded) {
        tbody.innerHTML = '<tr><td colspan="6" class="loading">Loading users...</td></tr>';
    }

    fetch('api/users')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                if (!silent) showAlert('error', data.error);
                if (!tbody.dataset.loaded) {
                    tbody.innerHTML = '<tr><td colspan="6" class="error">Error loading users</td></tr>';
                }
                return;
            }

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty">No users found</td></tr>';
                tbody.dataset.loaded = '1';
                return;
            }

            renderUsersTable(data);
            tbody.dataset.loaded = '1';
        })
        .catch(error => {
            console.error('Error loading users:', error);
            if (!tbody.dataset.loaded) {
                tbody.innerHTML = `<tr><td colspan="6" class="error">Error: ${escHtml(error.message)}</td></tr>`;
            }
            if (!silent) showAlert('error', `Failed to load users: ${error.message}`);
        });
}

// ===============================
// RENDER USERS TABLE
// ===============================
function renderUsersTable(users) {
    const tbody = document.querySelector('#userTable tbody');
    if (!tbody) return;

    users.sort((a, b) => parseInt(a.id) - parseInt(b.id));

    const html = users.map(u => {
        const roleClass = u.role === 'admin' ? 'role-admin'
            : u.role === 'technician' ? 'role-technician'
            : 'role-viewer';

        const roleIcon = u.role === 'admin' ? 'fa-shield-halved'
            : u.role === 'technician' ? 'fa-screwdriver-wrench'
            : 'fa-eye';

        const avatarClass = u.role === 'admin' ? 'usr-avatar--admin'
            : u.role === 'technician' ? 'usr-avatar--tech'
            : 'usr-avatar--viewer';

        const initials = (u.full_name || u.username || '?')
            .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

        const statusDotClass = u.is_active == 1 ? 'usr-status-dot--on' : 'usr-status-dot--off';
        const statusTextClass = u.is_active == 1 ? 'usr-status--active' : 'usr-status--inactive';
        const statusLabel = u.is_active == 1 ? 'Active' : 'Disabled';

        const youBadge = u.is_current
            ? `<span style="font-size:0.6rem;font-weight:700;letter-spacing:.05em;padding:2px 7px;border-radius:99px;background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3);margin-left:6px">YOU</span>`
            : '';

        const rowStyle = u.is_current ? ' style="background:rgba(99,102,241,.05)"' : '';

        return `
            <tr${rowStyle}>
                <td style="text-align:center"><code>${escHtml(u.id)}</code></td>
                <td>
                    <div class="usr-cell">
                        <div class="usr-avatar ${avatarClass}">${escHtml(initials)}</div>
                        <div>
                            <div class="usr-name">${escapeHtml(u.username)}${youBadge}</div>
                            <div class="usr-fullname">${escapeHtml(u.full_name || '')}</div>
                        </div>
                    </div>
                </td>
                <td style="text-align:center">
                    <span class="role-badge ${roleClass}">
                        <i class="fas ${roleIcon}"></i> ${escHtml(u.role)}
                    </span>
                </td>
                <td style="text-align:center">
                    <span class="usr-status ${statusTextClass}">
                        <span class="usr-status-dot ${statusDotClass}"></span>
                        ${statusLabel}
                    </span>
                </td>
                <td class="actions-cell" style="text-align:center">
                    <div class="action-buttons">
                        <button class="btn btn-icon btn-edit action-edit" data-edit-user="${escHtml(u.id)}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-icon btn-danger action-delete"
                            data-delete-user="${escHtml(u.id)}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Data baris disimpan di peta; tombol hanya membawa id. JSON/username di atribut onclick
    // pecah oleh tanda kutip dan membuka celah XSS (escapeHtml berbasis textContent tidak
    // meng-escape kutip).
    userRowsById = new Map(users.map(u => [String(u.id), u]));
    tbody.innerHTML = html;
    bindUserTableActions(tbody);
}

let userRowsById = new Map();
let userTableBound = false;

function bindUserTableActions(tbody) {
    if (userTableBound) return;
    userTableBound = true;
    tbody.addEventListener('click', (e) => {
        const edit = e.target.closest('[data-edit-user]');
        if (edit) {
            const u = userRowsById.get(edit.dataset.editUser);
            if (u) editUser(u);
            return;
        }
        const del = e.target.closest('[data-delete-user]');
        if (del) {
            const u = userRowsById.get(del.dataset.deleteUser);
            if (u) deleteUser(Number(u.id), u.username);
        }
    });
}

// ===============================
// MODAL FUNCTIONS
// ===============================
function openAddModal() {
    if (!requireAdmin()) return;
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('modalTitle');
    
    if (!modal || !modalTitle) {
        showAlert('error', 'Modal elements not found');
        return;
    }
    
    modalTitle.textContent = 'Add New User';
    document.getElementById('userId').value = '';
    
    // Clear all form fields
    ['username', 'full_name', 'password', 'role', 'is_active'].forEach(field => {
        const element = document.getElementById(field);
        if (element) {
            if (field === 'password') {
                element.value = '';
                element.placeholder = 'Password';
            } else if (field === 'role') {
                element.value = 'viewer';
            } else if (field === 'is_active') {
                element.value = '1';
            } else {
                element.value = '';
            }
        }
    });
    
    // Show modal
    modal.style.display = 'flex';
    
    // Focus on username field
    setTimeout(() => {
        const usernameField = document.getElementById('username');
        if (usernameField) usernameField.focus();
    }, 100);
}

function editUser(user) {
    if (!requireAdmin()) return;
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('modalTitle');
    
    if (!modal || !modalTitle) {
        showAlert('error', 'Modal elements not found');
        return;
    }
    
    modalTitle.textContent = 'Edit User';
    
    // Fill form with user data
    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('password').value = '';
    document.getElementById('password').placeholder = 'Leave blank to keep current password';
    document.getElementById('role').value = user.role;
    document.getElementById('is_active').value = user.is_active;
    
    // Show modal
    modal.style.display = 'flex';
    
    // Focus on first field
    setTimeout(() => {
        const usernameField = document.getElementById('username');
        if (usernameField) usernameField.focus();
    }, 100);
}

function closeModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// ===============================
// SAVE USER
// ===============================
function saveUser() {
    if (!requireAdmin()) return;
    const userId = document.getElementById('userId').value;
    const username = document.getElementById('username')?.value.trim();
    const fullName = document.getElementById('full_name')?.value.trim();
    const password = document.getElementById('password')?.value;
    const role = document.getElementById('role')?.value;
    const isActive = document.getElementById('is_active')?.value;

    // Validation
    if (!username || !fullName || !role) {
        showAlert('warning', 'Please fill in all required fields');
        return;
    }

    if (!userId && !password) {
        showAlert('warning', 'Password is required for new user');
        return;
    }

    // Prepare data
    const userData = {
        id: userId || null,
        username: username,
        full_name: fullName,
        password: password,
        role: role,
        is_active: isActive
    };

    // Show loading
    const modal = document.getElementById('userModal');
    const saveBtn = modal?.querySelector('.btn');
    if (saveBtn) {
        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;

        // Send request
        fetch('api/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message || 'User saved successfully');
                closeModal();
                loadUsers();
            } else {
                showAlert('error', data.error || 'Error saving user');
            }
        })
        .catch(error => {
            console.error('Error saving user:', error);
            showAlert('error', 'Error saving user: ' + error.message);
        })
        .finally(() => {
            if (saveBtn) {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            }
        });
    }
}

// ===============================
// DELETE USER
// ===============================
function deleteUser(id, username) {
    if (!requireAdmin()) return;
    confirmDelete(`Hapus user "${username}"?`, () => {

        // Get the button that was clicked
        const deleteBtn = event.target;
        const originalText = deleteBtn.textContent;
        deleteBtn.textContent = 'Deleting...';
        deleteBtn.disabled = true;

        fetch(`api/users?id=${id}`, { 
            method: 'DELETE' 
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message || 'User deleted successfully');
                loadUsers();
            } else {
                showAlert('error', data.error || 'Error deleting user');
            }
        })
        .catch(error => {
            console.error('Error deleting user:', error);
            showAlert('error', 'Error deleting user: ' + error.message);
        })
        .finally(() => {
            deleteBtn.textContent = originalText;
            deleteBtn.disabled = false;
        });
    });
}

// ===============================
// HELPER FUNCTIONS
// ===============================
function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(alert => alert.remove());

    // Create alert
    const alert = document.createElement('div');
    alert.className = `alert-message alert-${type}`;
    alert.innerHTML = `
        <span>${escapeHtml(message)}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;

    // Find a safe place to insert the alert
    const content = document.querySelector('.content');
    const topbar = document.querySelector('.topbar');
    
    if (content && topbar && topbar.parentElement === content) {
        // Insert after topbar
        content.insertBefore(alert, topbar.nextSibling);
    } else if (content) {
        // Insert at beginning of content
        content.insertBefore(alert, content.firstChild);
    } else {
        // Insert at beginning of body
        document.body.insertBefore(alert, document.body.firstChild);
    }

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===============================
// GLOBAL EVENT LISTENERS
// ===============================
// Close modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = document.getElementById('userModal');
    if (modal && modal.style.display === 'flex' && e.target === modal) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        loadUsers();
        initializeEventListeners();
    });
} else {
    loadUsers();
    initializeEventListeners();
}
