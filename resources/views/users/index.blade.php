@extends('layouts.app')

@section('content')
    <div class="topbar">
        <div class="topbar-content">
            <h1>Users</h1>
            <button class="btn action-create" onclick="openAddModal()">
                <i class="fas fa-user-plus"></i>
                Add User
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="userTable">
            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th width="160">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Filled by users.js -->
            </tbody>
        </table>
    </div>

    <div id="userModal" class="modal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>

            <h3 id="modalTitle">
                <i class="fas fa-user"></i>
                Add User
            </h3>

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
                <input id="password" type="password" placeholder="Password">
                <div class="password-strength">
                    <div class="password-strength-meter"></div>
                </div>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select id="role" required>
                    <option value="">Select Role</option>
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
                <button class="btn btn-danger" onclick="closeModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/users.js') }}?v={{ filemtime(public_path('assets/js/users.js')) }}"></script>
@endpush
