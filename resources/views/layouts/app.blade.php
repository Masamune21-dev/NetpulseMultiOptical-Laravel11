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
                var primary = localStorage.getItem('primary_color') || '#111111';
                var soft = localStorage.getItem('primary_soft') || '#3d3d3d';
                document.documentElement.style.setProperty('--primary', primary);
                document.documentElement.style.setProperty('--primary-soft', soft);
                document.documentElement.style.setProperty('--primary-gradient',
                    'linear-gradient(135deg, ' + primary + ' 0%, ' + soft + ' 100%)');
                document.documentElement.style.setProperty('--sidebar',
                    'linear-gradient(160deg, ' + primary + ' 0%, ' + soft + ' 55%, ' + primary + ' 100%)');
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('assets/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════════════
           GLOBAL CYBER ENHANCEMENTS — layout-level only
        ═══════════════════════════════════════════════════════ */

        /* Comic halftone grid on page */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(var(--ink-1, rgba(0,0,0,.03)) 1px, transparent 1px),
                linear-gradient(90deg, var(--ink-1, rgba(0,0,0,.03)) 1px, transparent 1px);
            background-size: 44px 44px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Sidebar ──────────────────────────────────────── */
        .sidebar {
            border-right: 1px solid var(--ink-3, rgba(0,0,0,.11)) !important;
            box-shadow: 3px 0 18px rgba(0,0,0,.12) !important;
        }
        @media (min-width: 769px) {
            .sidebar {
                bottom: 0;
                height: 100vh;
                height: 100dvh;
                display: grid;
                grid-template-rows: minmax(0, 1fr) auto;
                overflow: hidden;
            }
        }

        /* Logo glow */
        .sidebar h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }
        .sidebar h2 i {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.15);
            border-radius: 10px;
            box-shadow: 0 0 16px rgba(255,255,255,.2);
            flex-shrink: 0;
        }
        .sidebar ul li {
            margin-bottom: 4px !important;
        }
        .sidebar ul li > a {
            gap: 10px !important;
            padding: 8px 12px !important;
            border-radius: 10px !important;
            font-size: 0.84rem !important;
            min-height: 42px;
        }
        .sidebar ul li > a i {
            width: 18px !important;
            font-size: 0.92rem !important;
        }

        /* Active nav item — neon glow bar on left */
        .sidebar ul li.active > a {
            position: relative;
            background: rgba(255,255,255,.15) !important;
            box-shadow: inset 0 0 20px rgba(255,255,255,.06) !important;
        }
        .sidebar ul li.active > a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            height: 60%;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: #fff;
            box-shadow: 0 0 10px rgba(255,255,255,.8);
        }

        /* Hover glow */
        .sidebar ul li > a:hover {
            background: rgba(255,255,255,.1) !important;
        }

        /* Sidebar bottom version tag */
        .sidebar-version {
            font-size: 0.6rem;
            color: rgba(255,255,255,.25);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-align: center;
            padding: 6px 0 0;
        }

        /* Sidebar nav wrapper — row 1 (1fr), scrollable */
        .sidebar-nav {
            overflow-y: auto;
            min-height: 0;
            scrollbar-width: none;
        }
        .sidebar-nav::-webkit-scrollbar { display: none; }

        /* Sidebar user card */
        .sidebar-user {
            border-top: 1px solid rgba(255,255,255,.08);
            padding: 12px 0 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sidebar-user__card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-user__avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--primary-gradient, rgba(99,102,241,.6));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        .sidebar-user__meta {
            flex: 1;
            min-width: 0;
        }
        .sidebar-user__name {
            font-size: 0.8rem;
            font-weight: 700;
            color: rgba(255,255,255,.9);
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sidebar-user__role {
            font-size: 0.65rem;
            color: rgba(255,255,255,.4);
            letter-spacing: 0.03em;
        }
        .sidebar-user__logout {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.08);
            color: rgba(255,255,255,.45);
            font-size: 0.9rem;
            text-decoration: none;
            transition: all .2s;
        }
        .sidebar-user__logout:hover {
            background: rgba(239,68,68,.2);
            border-color: rgba(239,68,68,.35);
            color: #fca5a5;
        }

        /* ── Topbar ───────────────────────────────────────── */
        .topbar {
            border-bottom: 1.5px solid var(--border) !important;
            position: relative;
        }
        .topbar::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 120px;
            height: 2px;
            background: var(--primary-gradient);
            border-radius: 0 2px 2px 0;
        }

        /* Topbar right section with actions + clock + user */
        .topbar-right-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }

        /* Topbar action buttons from pages */
        .topbar-actions-slot {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Clock mono */
        .header-clock {
            font-family: 'Space Mono', monospace !important;
            font-size: 0.78rem !important;
            color: var(--text-soft) !important;
            background: var(--ink-1, rgba(0,0,0,.04));
            padding: 4px 10px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            white-space: nowrap;
        }

        /* User chip */
        .user-info {
            background: var(--ink-2, rgba(0,0,0,.06)) !important;
            border: 1.5px solid var(--border) !important;
            border-radius: 999px !important;
            padding: 4px 12px 4px 8px !important;
            font-size: 0.78rem !important;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .user-info i {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--btn-text, #fff);
            font-size: 0.7rem;
        }

        /* ── Mobile bottom nav ────────────────────────────── */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 900;
            background: var(--surface);
            border-top: 1.5px solid var(--border);
            box-shadow: 0 -2px 12px rgba(0,0,0,.1);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 6px 0 env(safe-area-inset-bottom, 0);
        }
        .mobile-bottom-nav .nav-items {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        .mobile-bottom-nav a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 6px 4px;
            text-decoration: none;
            color: var(--text-muted, #64748b);
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            transition: color .15s;
            position: relative;
        }
        .mobile-bottom-nav a i {
            font-size: 1.1rem;
            line-height: 1;
        }
        .mobile-bottom-nav a.active {
            color: var(--primary);
        }
        .mobile-bottom-nav a.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 25%;
            right: 25%;
            height: 2.5px;
            border-radius: 0 0 4px 4px;
            background: var(--primary-gradient);
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none !important;
            }

            .mobile-bottom-nav { display: block; }

            /* Extra bottom padding so content doesn't hide behind nav */
            .content {
                margin-left: 0 !important;
                max-width: 100% !important;
                padding-bottom: 82px !important;
            }
            .content-body { padding-bottom: 0 !important; }

            .topbar {
                align-items: stretch !important;
            }
            .topbar-right-group {
                width: 100%;
                margin-left: 0;
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
            }
            .topbar-actions-slot {
                width: 100%;
                justify-content: center;
            }
            .header-clock,
            .user-info {
                width: auto !important;
                max-width: 100%;
                margin-left: 0 !important;
            }

            /* Mobile tabs: 3-column grid */
            .tabs {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 8px !important;
                padding: 10px !important;
            }
            .tab {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                text-align: center !important;
                gap: 5px !important;
                padding: 10px 8px !important;
                font-size: 0.7rem !important;
                line-height: 1.1 !important;
                min-height: 68px;
            }
            .tab i { font-size: 1.2rem !important; }

            /* Toast placement */
            .toast-container {
                top: 12px !important;
                bottom: auto !important;
                right: 12px !important;
                left: 12px !important;
                max-width: none !important;
            }
        }

        /* ── Delete modal polish ──────────────────────────── */
        #confirmDeleteModal .modal-box {
            border: 1px solid rgba(239,68,68,.25);
            box-shadow: 0 0 40px rgba(239,68,68,.12);
        }

        /* ── Footer ───────────────────────────────────────── */
        .app-footer {
            border-top: 1px solid var(--border);
            font-size: 0.72rem;
            color: var(--text-soft);
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
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
            <a href="/monitoring" class="{{ request()->is('monitoring*') ? 'active' : '' }}">
                <i class="fas fa-heartbeat"></i>
                <span>Monitor</span>
            </a>
            <a href="/devices" class="{{ request()->is('devices*') ? 'active' : '' }}">
                <i class="fas fa-network-wired"></i>
                <span>Devices</span>
            </a>
            <a href="/map" class="{{ request()->is('map*') ? 'active' : '' }}">
                <i class="fas fa-map-marked-alt"></i>
                <span>Map</span>
            </a>
            <a href="/settings" class="{{ request()->is('settings*') ? 'active' : '' }}">
                <i class="fas fa-sliders-h"></i>
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
