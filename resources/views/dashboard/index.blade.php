@extends('layouts.app')

@section('content')
    <div class="cards">
        <div class="card">
            <h3><i class="fas fa-server"></i> Device Aktif</h3>
            <p><strong>{{ $deviceCount }}</strong> device dimonitor</p>
        </div>

        <div class="card">
            <h3><i class="fas fa-plug"></i> Total Interface</h3>
            <p><strong>{{ $ifCount }}</strong> interface</p>
        </div>

        <div class="card">
            <h3><i class="fas fa-fiber-optic"></i> SFP Aktif</h3>
            <p><strong>{{ $sfpCount }}</strong> port optik</p>
        </div>

        <div class="card {{ $badOptical ? 'danger' : '' }}">
            <h3><i class="fas fa-triangle-exclamation"></i> Optical Critical</h3>
            <p><strong>{{ $badOptical }}</strong> port bermasalah</p>
        </div>

        <div class="card">
            <h3><i class="fas fa-users"></i> Total Users</h3>
            <p><strong>{{ $userCount }}</strong> user terdaftar</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Dashboard is server-rendered; simplest way to keep it fresh is a periodic reload.
        if (window.netpulseRefresh && typeof window.netpulseRefresh.register === 'function') {
            window.netpulseRefresh.register('dashboard', () => {
                if (!location.pathname.startsWith('/dashboard')) return;
                if (document.hidden) return;
                location.reload();
            }, { minIntervalMs: 60000 });
        } else {
            setTimeout(() => location.reload(), 60000);
        }
    </script>
@endpush
