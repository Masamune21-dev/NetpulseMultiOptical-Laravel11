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
                var primary = localStorage.getItem('primary_color') || '#ffe14a';
                var soft = localStorage.getItem('primary_soft') || '#ff5c8a';
                document.documentElement.style.setProperty('--primary', primary);
                document.documentElement.style.setProperty('--primary-soft', soft);
                document.documentElement.style.setProperty('--primary-gradient',
                    'linear-gradient(135deg, ' + primary + ' 0%, ' + soft + ' 100%)');
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">


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
        {{-- Mobile top header --}}
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
            <a href="/logout" class="mobile-logout" title="Logout" aria-label="Logout">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </a>
        </header>

        {{-- Desktop sidebar --}}
        <aside class="sidebar">
            <div class="sidebar-nav">
                <h2>
                    <i class="fas fa-wave-square"></i>
                    NetPulse MultiOptical
                </h2>
                <ul>
                    <li class="{{ request()->is('dashboard*') ? 'active' : '' }}">
                        <a href="/dashboard">
                            <i class="fas fa-gauge-high"></i><span>Dashboard</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('monitoring*') ? 'active' : '' }}">
                        <a href="/monitoring">
                            <i class="fas fa-signal"></i><span>Monitoring</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('devices*') ? 'active' : '' }}">
                        <a href="/devices">
                            <i class="fas fa-server"></i><span>Devices</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('interfaces*') ? 'active' : '' }}">
                        <a href="/interfaces">
                            <i class="fas fa-ethernet"></i><span>Interfaces</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('map*') ? 'active' : '' }}">
                        <a href="/map">
                            <i class="fas fa-map-location-dot"></i><span>Map</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('users*') ? 'active' : '' }}">
                        <a href="/users">
                            <i class="fas fa-users-gear"></i><span>Users</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('settings*') ? 'active' : '' }}">
                        <a href="/settings">
                            <i class="fas fa-sliders"></i><span>Settings</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Sidebar user info + logout --}}
            <div class="sidebar-user">
                <div class="sidebar-user__card">
                    <div class="sidebar-user__avatar">
                        {{ strtoupper(substr($currentUser['username'] ?? 'U', 0, 2)) }}
                    </div>
                    <div class="sidebar-user__meta">
                        <div class="sidebar-user__name">{{ $currentUser['username'] ?? '-' }}</div>
                        <div class="sidebar-user__role">{{ $currentUser['role'] ?? '' }}</div>
                    </div>
                    <a href="/logout" class="sidebar-user__logout" title="Logout">
                        <i class="fas fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
                <div class="sidebar-version">v2.0 · NetPulse MultiOptical</div>
            </div>
        </aside>

        <main class="content">
            <div class="topbar">
                <h1>{{ $pageTitle ?? 'Dashboard' }}</h1>
                <div class="topbar-right-group">
                    <div class="topbar-actions-slot">
                        @stack('topbar-actions')
                    </div>
                    <div class="header-clock" data-live-clock></div>
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        {{ $currentUser['username'] ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="content-body">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Mobile bottom navigation --}}
    <nav class="mobile-bottom-nav">
        <div class="nav-items">
            <a href="/dashboard" class="{{ request()->is('dashboard*') ? 'active' : '' }}">
                <i class="fas fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
            <a href="/monitoring" class="{{ request()->is('monitoring*') ? 'active' : '' }}">
                <i class="fas fa-signal"></i>
                <span>Monitor</span>
            </a>
            <a href="/devices" class="{{ request()->is('devices*') ? 'active' : '' }}">
                <i class="fas fa-server"></i>
                <span>Devices</span>
            </a>
            <a href="/map" class="{{ request()->is('map*') ? 'active' : '' }}">
                <i class="fas fa-map-location-dot"></i>
                <span>Map</span>
            </a>
            <a href="/settings" class="{{ request()->is('settings*') ? 'active' : '' }}">
                <i class="fas fa-sliders"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    {{-- Confirm delete modal --}}
    <div class="modal" id="confirmDeleteModal">
        <div class="modal-box" style="max-width:420px;">
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            <h3><i class="fas fa-triangle-exclamation" style="color:#ef4444"></i> Konfirmasi Hapus</h3>
            <p id="confirmDeleteMessage" style="margin:1rem 0; color:var(--text-soft);">
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
        <span>© {{ date('Y') }} NetpulseMultiOptical &nbsp;·&nbsp; Dibuat oleh <strong>Masamune</strong></span>
    </footer>

    <script src="{{ asset('assets/js/script.js') }}?v={{ filemtime(public_path('assets/js/script.js')) }}"></script>
    @stack('scripts')
</body>
</html>
