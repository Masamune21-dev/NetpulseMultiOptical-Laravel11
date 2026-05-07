@extends('layouts.app')


@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/users.css') }}?v={{ filemtime(public_path('assets/css/pages/users.css')) }}">
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
