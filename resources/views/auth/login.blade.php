<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NetPulse MultiOptical</title>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                if (theme) {
                    document.documentElement.setAttribute('data-theme', theme);
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
            } catch (e) { }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body.login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg);
            color: var(--text);
            position: relative;
            overflow-x: hidden;
        }

        .login-left {
            background: var(--sidebar);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 4rem;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
            z-index: 1;
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
            margin-bottom: 1.5rem;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            box-shadow: var(--shadow-sm);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
        }

        .tagline {
            opacity: 0.9;
        }

        .login-left h1 {
            font-size: 2.4rem;
            letter-spacing: -0.5px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #fff, rgba(255, 255, 255, 0.85));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .login-left p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
            max-width: 500px;
        }

        .features {
            margin-top: 2.2rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.9);
        }

        .login-right {
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: var(--panel);
            padding: 2.8rem;
            border-radius: 22px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
        }

        .login-header h2 {
            font-size: 1.9rem;
            margin-bottom: 0.4rem;
        }

        .login-header p {
            color: var(--text-soft);
            margin-bottom: 1.8rem;
        }

        .alert {
            padding: 0.9rem 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            width: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-soft);
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 0.9rem 0.9rem 3rem !important;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            outline: none;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        .login-page .form-control {
            padding-left: 3rem !important;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .password-field {
            padding-right: 2.8rem;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            border: none;
            background: transparent;
            color: var(--text-soft);
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 0.95rem 1.2rem;
            border-radius: 12px;
            border: none;
            background: var(--primary-gradient);
            color: white;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .login-footer {
            margin-top: 1.8rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-soft);
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        @media (max-width: 900px) {
            body.login-page {
                grid-template-columns: 1fr;
            }
            .login-left {
                display: none;
            }
            .login-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-left">
        <div class="brand-container">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-wave-square"></i></div>
                <div class="logo-text">NetPulse</div>
            </div>
            <div class="tagline">Network Optical Monitoring</div>
        </div>
        <h1>NetPulse MultiOptical Monitoring System</h1>
        <p>Monitoring jaringan optik dengan status real-time dan informasi lengkap perangkat Anda.</p>
        <div class="features">
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>SFP Aktif dengan pembacaan power optik</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>Optical Critical untuk deteksi loss</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>OLT, PON, dan ONU terintegrasi</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>Manajemen user dan akses berbasis role</span>
            </div>
        </div>
    </div>

    <div class="login-right">
        <div class="login-container">
            <div class="login-header">
                <h2>Selamat Datang</h2>
                <p>Masuk untuk membuka dashboard monitoring</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-error">
                    <span class="alert-icon"><i class="fas fa-exclamation-circle"></i></span>
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
                            placeholder="Your username" value="{{ old('username') }}" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control password-field"
                            placeholder="Your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()"
                            aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginButton">
                    <span id="buttonText">Masuk</span>
                    <span class="btn-icon" id="buttonIcon"><i class="fas fa-arrow-right"></i></span>
                </button>
            </form>

            <div class="login-footer">
                <p>Butuh bantuan? <a href="mailto:masamunekazuto21@gmeail.com">Hubungi support</a></p>
                <p>© {{ date('Y') }} NetPulse MultiOptical. Web ini dibuat oleh Masamune.</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
                passwordInput.setAttribute('data-visible', 'true');
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
                passwordInput.removeAttribute('data-visible');
            }

            passwordInput.focus();
        }

        document.getElementById('loginForm').addEventListener('submit', function () {
            const button = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const buttonIcon = document.getElementById('buttonIcon').querySelector('i');

            button.disabled = true;
            buttonText.textContent = 'Signing in...';
            buttonIcon.className = 'fas fa-spinner fa-spin';

            this.classList.add('form-loading');
        });

        document.addEventListener('DOMContentLoaded', function () {
            const usernameField = document.getElementById('username');
            if (usernameField) {
                setTimeout(() => {
                    usernameField.focus();
                }, 300);
            }

            document.addEventListener('keydown', function (e) {
                if (e.ctrlKey && e.key === '/') {
                    e.preventDefault();
                    document.getElementById('username').focus();
                }
                if (e.ctrlKey && e.key === '.') {
                    e.preventDefault();
                    document.getElementById('password').focus();
                }
            });
        });

        document.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const focused = document.activeElement;
                if (focused.tagName !== 'TEXTAREA' && focused.type !== 'button') {
                    e.preventDefault();
                    if (!document.getElementById('loginButton').disabled) {
                        document.getElementById('loginForm').submit();
                    }
                }
            }
        });

        document.getElementById('username').addEventListener('input', function () {
            const errorAlert = document.querySelector('.alert-error');
            if (errorAlert) {
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 300);
            }
        });

        document.getElementById('password').addEventListener('input', function () {
            const errorAlert = document.querySelector('.alert-error');
            if (errorAlert) {
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 300);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                togglePassword();
            }
        });
    </script>
</body>
</html>
