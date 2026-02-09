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
            loadUsers();
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
function loadUsers() {
    const tbody = document.querySelector('#userTable tbody');
    if (!tbody) {
        console.error('User table body not found');
        return;
    }
    
    tbody.innerHTML = '<tr><td colspan="6" class="loading">Loading users...</td></tr>';

    fetch('api/users')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                showAlert('error', data.error);
                tbody.innerHTML = '<tr><td colspan="6" class="error">Error loading users</td></tr>';
                return;
            }

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty">No users found</td></tr>';
                return;
            }

            renderUsersTable(data);
        })
        .catch(error => {
            console.error('Error loading users:', error);
            tbody.innerHTML = `<tr><td colspan="6" class="error">Error: ${error.message}</td></tr>`;
            showAlert('error', `Failed to load users: ${error.message}`);
        });
}

// ===============================
// RENDER USERS TABLE
// ===============================
function renderUsersTable(users) {
    const tbody = document.querySelector('#userTable tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    // **URUTKAN BERDASARKAN ID** (tambahkan baris ini)
    users.sort((a, b) => parseInt(a.id) - parseInt(b.id));

    users.forEach(u => {
        // ... kode yang ada tetap ...
        const statusClass = u.is_active == 1 ? 'badge-success' : 'badge-secondary';
        const statusText = u.is_active == 1 ? 'Active' : 'Disabled';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><code>${u.id}</code></td>
            <td><strong>${escapeHtml(u.username)}</strong></td>
            <td>${escapeHtml(u.full_name)}</td>
            <td><span class="role-badge ${u.role}">${u.role}</span></td>
            <td><span class="badge ${statusClass}">${statusText}</span></td>
            <td class="actions-cell">
                <div class="action-buttons">
                    <button class="btn btn-icon btn-edit action-edit" onclick='editUser(${JSON.stringify(u)})'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-icon btn-danger action-delete" 
                        onclick="deleteUser(${u.id}, '${escapeHtml(u.username)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
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
