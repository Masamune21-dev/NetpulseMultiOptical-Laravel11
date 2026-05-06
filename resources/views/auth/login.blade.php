<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — NetPulse MultiOptical</title>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                if (theme) document.documentElement.setAttribute('data-theme', theme);
                var primary = localStorage.getItem('primary_color') || '#111111';
                var soft = localStorage.getItem('primary_soft') || '#3d3d3d';
                document.documentElement.style.setProperty('--primary', primary);
                document.documentElement.style.setProperty('--primary-soft', soft);
                document.documentElement.style.setProperty('--primary-gradient',
                    'linear-gradient(135deg, ' + primary + ' 0%, ' + soft + ' 100%)');
                document.documentElement.style.setProperty('--sidebar',
                    'linear-gradient(160deg, ' + primary + ' 0%, ' + soft + ' 55%, ' + primary + ' 100%)');
            } catch (e) { }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════════════
           LOGIN PAGE — CYBER NEON THEME
        ══════════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body.login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            position: relative;
        }

        /* ── Left panel ─────────────────────────────────── */
        .login-left {
            background: var(--sidebar);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 4rem;
            position: relative;
            overflow: hidden;
        }

        /* Cyber dot-grid overlay */
        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle, rgba(255,255,255,.12) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
            z-index: 1;
        }

        /* Scanline effect */
        .login-left::after {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 3px,
                rgba(0,0,0,.04) 3px,
                rgba(0,0,0,.04) 4px
            );
            pointer-events: none;
            z-index: 1;
        }

        /* Floating glow orbs */
        .login-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
            animation: orb-float 8s ease-in-out infinite;
        }
        .login-orb-1 {
            width: 300px; height: 300px;
            background: rgba(255,255,255,.08);
            top: -80px; left: -80px;
        }
        .login-orb-2 {
            width: 200px; height: 200px;
            background: rgba(255,255,255,.06);
            bottom: 60px; right: -40px;
            animation-delay: -4s;
        }
        @keyframes orb-float {
            0%,100% { transform: translateY(0) scale(1); }
            50%      { transform: translateY(-20px) scale(1.05); }
        }

        .brand-container {
            position: relative;
            z-index: 2;
            margin-bottom: 2.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }
        .logo-icon {
            width: 46px; height: 46px;
            background: rgba(255,255,255,.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 0 20px rgba(255,255,255,.2), inset 0 1px 0 rgba(255,255,255,.3);
            border: 1px solid rgba(255,255,255,.2);
        }
        .logo-text {
            font-family: 'Space Mono', monospace;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .logo-sub {
            font-size: 0.7rem;
            opacity: .6;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .login-left h1 {
            position: relative;
            z-index: 2;
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #fff, rgba(255,255,255,.8));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .login-left > p {
            position: relative;
            z-index: 2;
            font-size: 0.95rem;
            line-height: 1.6;
            opacity: .85;
            max-width: 420px;
        }

        .features {
            position: relative;
            z-index: 2;
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }
        .feature-icon {
            width: 28px; height: 28px;
            background: rgba(255,255,255,.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,.1);
        }

        /* Status bar at bottom of left panel */
        .login-status-bar {
            position: absolute;
            z-index: 2;
            bottom: 24px;
            left: 4rem;
            right: 4rem;
            display: flex;
            gap: 20px;
            opacity: .65;
        }
        .login-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .login-stat__val {
            font-family: 'Space Mono', monospace;
            font-size: 1.1rem;
            font-weight: 700;
        }
        .login-stat__label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: .8;
        }

        /* ── Right panel ─────────────────────────────────── */
        .login-right {
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            position: relative;
        }

        /* Subtle cyber grid on right panel */
        .login-right::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,0,0,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,.025) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            background: var(--surface, rgba(255,255,255,.04));
            padding: 2.4rem;
            border-radius: 22px;
            border: 1px solid var(--border);
            box-shadow:
                0 0 0 1px var(--ink-2),
                0 24px 48px rgba(0,0,0,.15),
                0 0 80px var(--ink-2);
        }

        /* Top accent line on card */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--ink-5), transparent);
            border-radius: 0 0 99px 99px;
        }

        .login-header {
            margin-bottom: 1.8rem;
        }
        .login-header h2 {
            font-size: 1.7rem;
            font-weight: 800;
            margin-bottom: 0.3rem;
        }
        .login-header p {
            color: var(--text-soft, #94a3b8);
            font-size: 0.88rem;
        }

        /* Alerts */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: rgba(239,68,68,.08);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.2);
        }
        .alert-success {
            background: rgba(34,197,94,.08);
            color: #22c55e;
            border: 1px solid rgba(34,197,94,.2);
        }

        /* Form elements */
        .form-group { margin-bottom: 1.1rem; }
        .form-label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.83rem;
            font-weight: 600;
            color: var(--text, #f1f5f9);
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-soft, #64748b);
            font-size: 0.85rem;
            z-index: 1;
        }
        .form-control {
            width: 100%;
            padding: 0.85rem 0.9rem 0.85rem 2.8rem !important;
            border-radius: 12px;
            border: 1px solid rgba(99,102,241,.2);
            background: var(--ink-1);
            color: var(--text, #f1f5f9);
            outline: none;
            font-size: 0.9rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: var(--ink-5);
            box-shadow: 0 0 0 3px var(--ink-3);
            background: var(--ink-2);
        }
        .form-control::placeholder { color: var(--text-soft, #64748b); }

        .password-field { padding-right: 2.8rem !important; }
        .password-toggle {
            position: absolute;
            right: 12px;
            border: none;
            background: transparent;
            color: var(--text-soft, #64748b);
            cursor: pointer;
            font-size: 0.85rem;
            transition: color .15s;
        }
        .password-toggle:hover { color: var(--primary); }

        /* Login button */
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            border-radius: 12px;
            border: none;
            background: var(--primary-gradient);
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 20px var(--shadow-sm);
            margin-top: 4px;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 28px var(--shadow-md);
        }
        .btn-login:disabled {
            opacity: .7;
            cursor: not-allowed;
            transform: none;
        }
        .btn-login .btn-icon {
            display: inline-flex;
            align-items: center;
            width: 1.2em; height: 1.2em;
        }

        /* Footer */
        .login-footer {
            margin-top: 1.6rem;
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-soft, #64748b);
            line-height: 1.8;
        }
        .login-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        .login-footer a:hover { text-decoration: underline; }

        /* Divider */
        .login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 1.2rem 0;
            color: var(--text-soft, #64748b);
            font-size: 0.75rem;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Responsive */
        @media (max-width: 900px) {
            body.login-page { grid-template-columns: 1fr; }
            .login-left { display: none; }
            .login-right { padding: 1.5rem; min-height: 100vh; }
        }
    </style>
</head>
<body class="login-page">

    {{-- Left Panel --}}
    <div class="login-left">
        <div class="login-orb login-orb-1"></div>
        <div class="login-orb login-orb-2"></div>

        <div class="brand-container">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-wave-square"></i></div>
                <div>
                    <div class="logo-text">NetPulse</div>
                    <div class="logo-sub">MultiOptical</div>
                </div>
            </div>
        </div>

        <h1>Network Optical<br>Monitoring System</h1>
        <p>Monitoring jaringan optik dengan status real-time, alert otomatis, dan informasi lengkap perangkat Anda.</p>

        <div class="features">
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-circle-nodes"></i></div>
                <span>SFP monitoring dengan pembacaan TX/RX power optik</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-triangle-exclamation"></i></div>
                <span>Alert otomatis untuk deteksi redaman & port down</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-map-marked-alt"></i></div>
                <span>Network topology map interaktif real-time</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-user-shield"></i></div>
                <span>Manajemen user & akses berbasis role (admin / tech / viewer)</span>
            </div>
        </div>

        <div class="login-status-bar">
            <div class="login-stat">
                <div class="login-stat__val">24/7</div>
                <div class="login-stat__label">Monitoring</div>
            </div>
            <div class="login-stat">
                <div class="login-stat__val">SNMP</div>
                <div class="login-stat__label">v2c / v3</div>
            </div>
            <div class="login-stat">
                <div class="login-stat__val">dBm</div>
                <div class="login-stat__label">Optical Power</div>
            </div>
        </div>
    </div>

    {{-- Right Panel --}}
    <div class="login-right">
        <div class="login-container">
            <div class="login-header">
                <h2>Selamat Datang</h2>
                <p>Masuk untuk membuka dashboard monitoring</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" id="loginForm" action="/login">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="username" name="username" class="form-control"
                            placeholder="Masukkan username" value="{{ old('username') }}" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control password-field"
                            placeholder="Masukkan password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Tampilkan password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginButton">
                    <span id="buttonText">Masuk</span>
                    <span class="btn-icon" id="buttonIcon"><i class="fas fa-arrow-right"></i></span>
                </button>
            </form>

            <div class="login-divider">atau</div>

            <div class="login-footer">
                <p>Butuh bantuan? <a href="mailto:masamunekazuto21@gmail.com">Hubungi support</a></p>
                <p>© {{ date('Y') }} NetPulse MultiOptical &nbsp;·&nbsp; by Masamune</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const inp = document.getElementById('password');
            const ico = document.querySelector('.password-toggle i');
            if (inp.type === 'password') {
                inp.type = 'text';
                ico.className = 'fas fa-eye-slash';
            } else {
                inp.type = 'password';
                ico.className = 'fas fa-eye';
            }
            inp.focus();
        }

        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginButton');
            const txt = document.getElementById('buttonText');
            const ico = document.getElementById('buttonIcon').querySelector('i');
            btn.disabled = true;
            txt.textContent = 'Signing in...';
            ico.className = 'fas fa-spinner fa-spin';
        });

        document.addEventListener('DOMContentLoaded', function () {
            const u = document.getElementById('username');
            if (u) setTimeout(() => u.focus(), 200);

            document.addEventListener('keydown', function (e) {
                if (e.ctrlKey && e.key === '/') { e.preventDefault(); document.getElementById('username').focus(); }
                if (e.ctrlKey && e.key === '.') { e.preventDefault(); document.getElementById('password').focus(); }
                if (e.ctrlKey && e.shiftKey && e.key === 'P') { e.preventDefault(); togglePassword(); }
            });
        });

        document.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const f = document.activeElement;
                if (f.tagName !== 'TEXTAREA' && f.type !== 'button') {
                    e.preventDefault();
                    if (!document.getElementById('loginButton').disabled) {
                        document.getElementById('loginForm').submit();
                    }
                }
            }
        });

        // Auto-dismiss error alert on typing
        ['username','password'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', function () {
                const err = document.querySelector('.alert-error');
                if (err) { err.style.opacity = '0'; err.style.transition = 'opacity .25s'; setTimeout(() => err.remove(), 280); }
            });
        });
    </script>
</body>
</html>
