<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — ShopAI Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:      #2563EB;
            --accent-h:    #1D4ED8;
            --accent-dim:  rgba(37,99,235,0.10);
            --accent-glow: rgba(37,99,235,0.22);
            --text:        #111827;
            --text-2:      #374151;
            --muted:       #6B7280;
            --border:      rgba(17,24,39,0.10);
            --surface:     #FFFFFF;
            --bg:          #F3F4F6;
            --error:       #DC2626;
            --error-bg:    #FEF2F2;
            --success:     #059669;
            --radius:      14px;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        /* Animated blobs */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.45;
            pointer-events: none;
        }
        body::before {
            width: 520px; height: 520px;
            background: radial-gradient(circle, #BFDBFE, #93C5FD);
            top: -140px; left: -140px;
            animation: drift 9s ease-in-out infinite alternate;
        }
        body::after {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #DDD6FE, #C4B5FD);
            bottom: -100px; right: -100px;
            animation: drift 11s ease-in-out infinite alternate-reverse;
        }
        @keyframes drift {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(30px, 20px) scale(1.06); }
        }

        .card {
            background: var(--surface);
            border-radius: 22px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04), 0 20px 60px rgba(0,0,0,0.09);
            padding: 44px 48px;
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
            border: 1px solid var(--border);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 32px;
        }
        .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.35);
        }
        .brand-name {
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.4px;
            background: linear-gradient(135deg, #1E40AF, #7C3AED);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
            margin-bottom: 6px;
        }
        .subtitle {
            color: var(--muted);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 32px;
        }

        .field { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 7px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
            background: #FAFAFA;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        }
        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3.5px var(--accent-dim);
            background: var(--surface);
        }
        input.error { border-color: var(--error); }
        input.error:focus { box-shadow: 0 0 0 3.5px rgba(220,38,38,0.10); }

        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 44px; }
        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; padding: 4px;
            color: var(--muted);
            display: flex; align-items: center;
            transition: color 0.15s;
        }
        .toggle-pw:hover { color: var(--accent); }

        .btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 11px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.18s;
            margin-top: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #fff;
            box-shadow: 0 4px 14px rgba(37,99,235,0.38);
        }
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #1D4ED8, #1E40AF);
            box-shadow: 0 6px 20px rgba(37,99,235,0.46);
            transform: translateY(-1px);
        }
        .btn-primary:active:not(:disabled) { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 20px;
            display: none;
        }
        .alert-error  { background: var(--error-bg); color: var(--error); border: 1px solid rgba(220,38,38,0.20); }
        .alert-success { background: #ECFDF5; color: var(--success); border: 1px solid rgba(5,150,105,0.20); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: var(--muted);
            font-size: 12.5px;
            font-weight: 500;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px; background: var(--border);
        }

        .footer-link {
            text-align: center;
            font-size: 13.5px;
            color: var(--muted);
            font-weight: 500;
        }
        .footer-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }
        .footer-link a:hover { text-decoration: underline; }

        .spinner {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2.5px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <div class="brand-icon">🛍️</div>
            <span class="brand-name">ShopAI Platform</span>
        </div>

        <h1>Welcome back</h1>
        <p class="subtitle">Sign in to access your client dashboard and API key.</p>

        <div class="alert alert-error" id="alertError"></div>

        <form id="loginForm" novalidate>
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">Sign In</button>
        </form>

        <div class="divider">or</div>

        <p class="footer-link">
            Don't have an account? <a href="{{ route('register') }}">Create one free</a>
        </p>
    </div>

    <script>
        const API_BASE = (function () {
            var base = '{{ rtrim(url("/api"), "/") }}';
            return window.location.origin + base.replace(/^https?:\/\/[^\/]+/, '');
        })();

        // Toggle password visibility
        document.getElementById('togglePw').addEventListener('click', function () {
            const pw = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const isText = pw.type === 'text';
            pw.type = isText ? 'password' : 'text';
            icon.innerHTML = isText
                ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
                : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
        });

        // Form submit
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const btn      = document.getElementById('submitBtn');
            const alert    = document.getElementById('alertError');

            alert.style.display = 'none';
            document.querySelectorAll('input').forEach(i => i.classList.remove('error'));

            if (!email || !password) {
                showError('Please fill in all fields.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Signing in…';

            try {
                const res = await fetch(`${API_BASE}/auth/login`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({ email, password }),
                });

                const data = await res.json();

                if (!res.ok) {
                    const msg = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.error || 'Login failed. Please try again.');
                    showError(msg);
                    return;
                }

                // Store credentials based on setup state
                localStorage.setItem('shopai_user', JSON.stringify(data.user));
                if (data.setup_complete) {
                    localStorage.setItem('shopai_api_key', data.api_key);
                    localStorage.removeItem('shopai_setup_token');
                } else {
                    localStorage.setItem('shopai_setup_token', data.setup_token);
                    localStorage.removeItem('shopai_api_key');
                }

                // Redirect to dashboard
                window.location.href = '{{ route("dashboard") }}';

            } catch (err) {
                showError('Network error — please check your connection and try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Sign In';
            }
        });

        function showError(msg) {
            const el = document.getElementById('alertError');
            el.textContent = msg;
            el.style.display = 'block';
        }

        // If already logged in (or pending setup), redirect to dashboard
        if (localStorage.getItem('shopai_api_key') || localStorage.getItem('shopai_setup_token')) {
            window.location.href = '{{ route("dashboard") }}';
        }
    </script>
</body>
</html>
