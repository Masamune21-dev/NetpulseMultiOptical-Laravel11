@extends('layouts.app')


@push('styles')
<style>
/* ══════════════════════════════════════════
   USERS PAGE — Cyber Theme
══════════════════════════════════════════ */

/* ── Users Card ───────────────────────────── */
.usr-card {
    background: var(--surface, rgba(255,255,255,.04));
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
}
.usr-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--primary-gradient);
}
.usr-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 12px;
    border-bottom: 1px solid rgba(99,102,241,.1);
}
.usr-card-head h3 {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text, #f1f5f9);
    display: flex; align-items: center; gap: 8px;
}
.usr-card-head h3 i { color: var(--primary); }

/* ── Table ────────────────────────────────── */
.usr-table-wrap { overflow-x: auto; }

.table th {
    font-size: 0.67rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.1em !important;
    text-transform: uppercase !important;
    color: var(--text-muted, #94a3b8) !important;
    padding: 10px 16px !important;
    border-bottom: 1px solid var(--ink-3) !important;
}
.table td {
    padding: 12px 16px !important;
    vertical-align: middle !important;
    border-bottom: 1px solid var(--ink-2) !important;
    font-size: 0.83rem;
}
.table tbody tr:last-child td { border-bottom: none !important; }
.table tbody tr:hover td { background: var(--ink-1) !important; }

/* ── User cell ────────────────────────────── */
.usr-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.usr-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    text-transform: uppercase;
}
.usr-avatar--admin     { background: var(--primary-gradient); box-shadow: var(--shadow-sm); }
.usr-avatar--tech      { background: linear-gradient(135deg,#374151,#1f2937); box-shadow: var(--shadow-sm); }
.usr-avatar--viewer    { background: linear-gradient(135deg,#64748b,#475569); }
.usr-name { font-weight: 700; color: var(--text, #f1f5f9); }
.usr-fullname { font-size: 0.72rem; color: var(--text-muted, #94a3b8); margin-top: 1px; }

/* ── Role badges ──────────────────────────── */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 6px;
}
.role-admin {
    background: var(--ink-3);
    color: var(--primary);
    border: 1px solid var(--ink-4);
}
.role-technician {
    background: var(--ink-2);
    color: var(--text-soft);
    border: 1px solid var(--border);
}
.role-viewer {
    background: rgba(100,116,139,.1);
    color: #94a3b8;
    border: 1px solid rgba(100,116,139,.2);
}

/* ── Status dot ───────────────────────────── */
.usr-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}
.usr-status-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.usr-status-dot--on  {
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
    animation: dot-pulse 2s ease-in-out infinite;
}
.usr-status-dot--off { background: #64748b; }
.usr-status--active   { color: #10b981; }
.usr-status--inactive { color: #64748b; }
@keyframes dot-pulse {
    0%,100% { box-shadow: 0 0 4px #10b981; }
    50%      { box-shadow: 0 0 10px #10b981; }
}

/* ── Password strength ────────────────────── */
.password-strength {
    height: 4px;
    background: rgba(99,102,241,.1);
    border-radius: 99px;
    margin-top: 6px;
    overflow: hidden;
}
.password-strength-meter {
    height: 100%;
    border-radius: 99px;
    width: 0;
    transition: width .3s, background .3s;
}

/* ── Modal form ───────────────────────────── */
.modal-box h3 {
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.modal-box .form-group label {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted, #94a3b8);
    display: block;
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .usr-fullname { display: none; }
}
</style>
@endpush

@section('content')
<div class="usr-card">
    <div class="usr-card-head">
        <h3><i class="fas fa-users"></i> Daftar Users</h3>
        <button class="btn action-create" onclick="openAddModal()" title="Add User" style="width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:9px;flex-shrink:0">
            <i class="fas fa-plus"></i>
        </button>
    </div>
    <div class="usr-table-wrap">
        <table class="table" id="userTable">
            <thead>
                <tr>
                    <th style="width:5%;text-align:center">ID</th>
                    <th style="width:40%">User</th>
                    <th style="width:15%;text-align:center">Role</th>
                    <th style="width:15%;text-align:center">Status</th>
                    <th style="width:25%;text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
                {{-- filled by users.js --}}
            </tbody>
        </table>
    </div>
</div>

{{-- Add / Edit User Modal --}}
<div id="userModal" class="modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3><i class="fas fa-user" style="color:var(--primary)"></i> <span id="modalTitle">Add User</span></h3>

        <input type="hidden" id="userId">

        <div class="form-group">
            <label>Username</label>
            <input id="username" placeholder="username" required>
        </div>
        <div class="form-group">
            <label>Full Name</label>
            <input id="full_name" placeholder="Full Name" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input id="password" type="password" placeholder="Password (kosongkan untuk tidak ganti)">
            <div class="password-strength">
                <div class="password-strength-meter"></div>
            </div>
        </div>
        <div class="form-group">
            <label>Role</label>
            <select id="role" required>
                <option value="">— Pilih Role —</option>
                <option value="admin">Admin</option>
                <option value="technician">Technician</option>
                <option value="viewer">Viewer</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="is_active">
                <option value="1">Active</option>
                <option value="0">Disabled</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn" onclick="saveUser()">
                <i class="fas fa-save"></i> Save User
            </button>
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/users.js') }}?v={{ filemtime(public_path('assets/js/users.js')) }}"></script>
@endpush
