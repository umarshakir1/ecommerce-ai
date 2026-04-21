<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — ShopAI</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #0f172a;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .bg-grid {
        position: fixed; inset: 0; z-index: 0;
        background-image:
            linear-gradient(rgba(6,182,212,.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(6,182,212,.04) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    .card {
        position: relative; z-index: 1;
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 20px;
        padding: 44px 40px;
        width: 100%; max-width: 420px;
        box-shadow: 0 25px 60px rgba(0,0,0,.5);
    }

    .logo {
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 32px;
    }
    .logo-icon {
        width: 44px; height: 44px; border-radius: 12px;
        background: linear-gradient(135deg, #06b6d4, #0ea5e9);
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
    }
    .logo-text { font-size: 1.3rem; font-weight: 700; color: #f1f5f9; }
    .logo-sub  { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

    h1 { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin-bottom: 6px; }
    .subtitle { font-size: 0.875rem; color: #64748b; margin-bottom: 28px; }

    .form-group { margin-bottom: 18px; }
    label { display: block; font-size: 0.8rem; font-weight: 600; color: #94a3b8; margin-bottom: 7px; letter-spacing: .04em; text-transform: uppercase; }
    input[type="email"], input[type="password"] {
        width: 100%; padding: 11px 14px;
        background: #0f172a; border: 1px solid #334155; border-radius: 10px;
        color: #f1f5f9; font-size: 0.9rem;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
    }
    input:focus { border-color: #06b6d4; box-shadow: 0 0 0 3px rgba(6,182,212,.15); }

    .remember {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 24px;
    }
    .remember input[type="checkbox"] { accent-color: #06b6d4; width: 15px; height: 15px; cursor: pointer; }
    .remember label { margin: 0; text-transform: none; letter-spacing: 0; font-size: 0.85rem; color: #94a3b8; cursor: pointer; }

    .btn {
        width: 100%; padding: 12px;
        background: linear-gradient(135deg, #06b6d4, #0ea5e9);
        color: #fff; font-size: 0.95rem; font-weight: 600;
        border: none; border-radius: 10px; cursor: pointer;
        transition: opacity .2s, transform .1s;
    }
    .btn:hover   { opacity: .9; }
    .btn:active  { transform: scale(.98); }

    .error-box {
        background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3);
        border-radius: 10px; padding: 12px 14px; margin-bottom: 20px;
        font-size: 0.85rem; color: #fca5a5;
    }
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🛍️</div>
        <div>
            <div class="logo-text">ShopAI</div>
            <div class="logo-sub">Admin Panel</div>
        </div>
    </div>

    <h1>Welcome back</h1>
    <p class="subtitle">Sign in to your admin account to continue</p>

    @if ($errors->any())
        <div class="error-box">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.login.post') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   value="{{ old('email') }}"
                   placeholder="admin@example.com" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required>
        </div>

        <div class="remember">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn">Sign In</button>
    </form>
</div>
</body>
</html>
