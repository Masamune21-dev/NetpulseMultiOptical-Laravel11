<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $pageTitle ?? 'NetPulse' }}</title>

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                if (theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                    document.body && document.body.setAttribute('data-theme', theme);
                }
                var primary = localStorage.getItem('primary_color') || '#6366f1';
                var soft = localStorage.getItem('primary_soft') || '#8b5cf6';
                document.documentElement.style.setProperty('--primary', primary);
                document.documentElement.style.setProperty('--primary-soft', soft);
                document.documentElement.style.setProperty(
                    '--primary-gradient',
                    'linear-gradient(135deg, ' + primary + ' 0%, ' + soft + ' 100%)'
                );
                document.documentElement.style.setProperty(
                    '--sidebar',
                    'linear-gradient(160deg, ' + primary + ' 0%, ' + soft + ' 55%, ' + primary + ' 100%)'
                );
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('assets/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">

    <style>
        /* Override global mobile toast placement (keep toasts at the top on phones). */
        @media (max-width: 768px) {
            .toast-container {
                top: 12px !important;
                bottom: auto !important;
                right: 12px !important;
                left: 12px !important;
                max-width: none !important;
            }
        }
    </style>

    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}"></script>
    @stack('styles')
</head>
<body data-role="{{ $currentUser['role'] ?? 'viewer' }}">
    <script>
        (function () {
            try {
                var theme = document.documentElement.getAttribute('data-theme');
                if (theme) document.body.setAttribute('data-theme', theme);
            } catch (e) {}
        })();
    </script>

    <div class="layout">
        <header class="mobile-header">
            <div class="mobile-brand">
                <div class="mobile-logo">
                    <i class="fas fa-wave-square"></i>
                </div>
                <div class="mobile-titles">
                    <div class="mobile-title">NetPulse MultiOptical</div>
                    <div class="mobile-subtitle">Network Optical Monitoring</div>
                </div>
            </div>
        </header>

        <aside class="sidebar">
            <h2>
                <i class="fas fa-wave-square"></i>
                NetPulse MultiOptical
            </h2>
            <ul>
                <li class="{{ request()->is('dashboard*') ? 'active' : '' }}">
                    <a href="/dashboard">
                        <i class="fas fa-chart-pie"></i><span>Dashboard</span>
                    </a>
                </li>
                <li class="{{ request()->is('monitoring*') ? 'active' : '' }}">
                    <a href="/monitoring">
                        <i class="fas fa-heartbeat"></i><span>Monitoring</span>
                    </a>
                </li>
                <li class="{{ request()->is('devices*') ? 'active' : '' }}">
                    <a href="/devices">
                        <i class="fas fa-network-wired"></i><span>Devices</span>
                    </a>
                </li>
                <li class="{{ request()->is('map*') ? 'active' : '' }}">
                    <a href="/map">
                        <i class="fas fa-map-marked-alt"></i><span>Map</span>
                    </a>
                </li>
                <li class="{{ request()->is('olt*') ? 'active' : '' }}">
                    <a href="/olt">
                        <i class="fas fa-server"></i><span>OLT</span>
                    </a>
                </li>
                <li class="{{ request()->is('users*') ? 'active' : '' }}">
                    <a href="/users">
                        <i class="fas fa-user-shield"></i><span>Users</span>
                    </a>
                </li>
                <li class="{{ request()->is('settings*') ? 'active' : '' }}">
                    <a href="/settings">
                        <i class="fas fa-sliders-h"></i><span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="/logout">
                        <i class="fas fa-power-off"></i><span>Logout</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="content">
            <div class="topbar">
                <h1>{{ $pageTitle ?? 'Dashboard' }}</h1>
                <div class="header-clock" data-live-clock></div>
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    {{ $currentUser['username'] ?? '-' }}
                </div>
            </div>

            <div class="content-body">
                @yield('content')
            </div>
        </main>
    </div>

    <div class="modal" id="confirmDeleteModal">
        <div class="modal-box" style="max-width:420px;">
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            <h3><i class="fas fa-triangle-exclamation"></i> Konfirmasi Hapus</h3>
            <p id="confirmDeleteMessage" style="margin: 1rem 0; color: var(--text-soft);">
                Yakin ingin menghapus data ini?
            </p>
            <div class="modal-actions">
                <button class="btn btn-danger" id="confirmDeleteYes">
                    <i class="fas fa-trash"></i> Hapus
                </button>
                <button class="btn btn-outline" onclick="closeDeleteModal()">
                    Batal
                </button>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <span>© {{ date('Y') }} NetpulseMultiOptical. Web ini dibuat oleh Masamune.</span>
    </footer>

    <script src="{{ asset('assets/js/script.js') }}?v={{ filemtime(public_path('assets/js/script.js')) }}"></script>
    @stack('scripts')
</body>
</html>
