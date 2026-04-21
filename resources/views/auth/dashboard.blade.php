<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — ShopAI Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:       #2563EB;
            --accent-h:     #1D4ED8;
            --accent-dim:   rgba(37,99,235,0.10);
            --accent-glow:  rgba(37,99,235,0.22);
            --text:         #111827;
            --text-2:       #374151;
            --muted:        #6B7280;
            --border:       rgba(17,24,39,0.09);
            --border2:      rgba(17,24,39,0.14);
            --surface:      #FFFFFF;
            --surface2:     #F9FAFB;
            --bg:           #F3F4F6;
            --success:      #059669;
            --success-bg:   #ECFDF5;
            --warn:         #D97706;
            --warn-bg:      #FFFBEB;
            --danger:       #DC2626;
            --code-bg:      #1E293B;
            --code-text:    #E2E8F0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── Topbar ── */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            box-shadow: 0 3px 10px rgba(37,99,235,0.30);
        }
        .brand-name {
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.4px;
            background: linear-gradient(135deg, #1E40AF, #7C3AED);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 99px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-2);
        }
        .user-pill .avatar {
            width: 26px; height: 26px;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
        }
        .btn-logout {
            padding: 7px 16px;
            background: none;
            border: 1.5px solid var(--border2);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-logout:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: #FEF2F2;
        }

        /* ── Page layout ── */
        .page {
            max-width: 880px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }
        .page-header { margin-bottom: 36px; }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
        }
        .page-header p {
            color: var(--muted);
            font-size: 14.5px;
            font-weight: 500;
            margin-top: 5px;
        }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border-radius: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .card-header {
            padding: 22px 28px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-header-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }
        .icon-blue   { background: rgba(37,99,235,0.10); }
        .icon-purple { background: rgba(124,58,237,0.10); }
        .icon-green  { background: rgba(5,150,105,0.10); }
        .icon-amber  { background: rgba(217,119,6,0.10); }

        .card-header-text h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }
        .card-header-text p {
            font-size: 13px;
            color: var(--muted);
            font-weight: 500;
            margin-top: 2px;
        }
        .card-body { padding: 20px 28px 28px; }

        /* ── Stat row ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.5px;
        }
        .stat-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
            font-weight: 500;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: 11.5px;
            font-weight: 700;
        }
        .badge-green  { background: var(--success-bg); color: var(--success); }
        .badge-blue   { background: var(--accent-dim); color: var(--accent); }

        /* ── API Key section ── */
        .key-row {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--code-bg);
            border-radius: 12px;
            padding: 14px 18px;
        }
        .key-value {
            flex: 1;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13.5px;
            color: var(--code-text);
            word-break: break-all;
            letter-spacing: 0.5px;
            user-select: all;
        }
        .key-value.masked { filter: blur(6px); user-select: none; transition: filter 0.2s; }
        .key-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        .btn-icon {
            padding: 7px 13px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn-reveal {
            background: rgba(255,255,255,0.10);
            color: #CBD5E1;
        }
        .btn-reveal:hover { background: rgba(255,255,255,0.18); color: #fff; }
        .btn-copy {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 2px 8px rgba(37,99,235,0.40);
        }
        .btn-copy:hover { background: var(--accent-h); }
        .btn-copy.copied { background: var(--success); box-shadow: 0 2px 8px rgba(5,150,105,0.35); }

        .warn-box {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: var(--warn-bg);
            border: 1px solid rgba(217,119,6,0.25);
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 14px;
            font-size: 13px;
            color: #92400E;
            font-weight: 500;
        }
        .warn-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }

        /* ── Info field ── */
        .info-field {
            margin-bottom: 16px;
        }
        .info-field:last-child { margin-bottom: 0; }
        .info-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 10px 14px;
            font-family: 'Courier New', Courier, monospace;
            word-break: break-all;
        }

        /* ── Code block ── */
        .code-block {
            background: var(--code-bg);
            border-radius: 12px;
            padding: 20px;
            overflow-x: auto;
            position: relative;
        }
        .code-block pre {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: var(--code-text);
            line-height: 1.7;
            white-space: pre;
        }
        .code-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .hl-key    { color: #93C5FD; }
        .hl-str    { color: #86EFAC; }
        .hl-cmt    { color: #64748B; font-style: italic; }
        .hl-fn     { color: #FCD34D; }
        .hl-kw     { color: #C4B5FD; }

        /* ── WordPress download card ── */
        .wp-card {
            background: linear-gradient(135deg, #1E1B4B 0%, #312E81 50%, #4C1D95 100%);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.10);
            box-shadow: 0 4px 24px rgba(76,29,149,0.30), 0 1px 3px rgba(0,0,0,0.10);
            overflow: hidden;
            margin-bottom: 24px;
            position: relative;
        }
        .wp-card::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(167,139,250,0.22), transparent 70%);
            pointer-events: none;
        }
        .wp-card-inner {
            padding: 28px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .wp-card-left { display: flex; align-items: center; gap: 18px; }
        .wp-card-icon {
            width: 54px; height: 54px;
            background: rgba(255,255,255,0.13);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.18);
        }
        .wp-card-text h2 {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
            letter-spacing: -0.3px;
        }
        .wp-card-text p {
            font-size: 13.5px;
            color: rgba(255,255,255,0.70);
            font-weight: 500;
            line-height: 1.5;
            max-width: 380px;
        }
        .wp-card-pills {
            display: flex;
            gap: 7px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .wp-pill {
            padding: 3px 10px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 99px;
            font-size: 11.5px;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
        }
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 26px;
            background: #fff;
            color: #312E81;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14.5px;
            font-weight: 800;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0,0,0,0.20);
            transition: all 0.18s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.28);
            background: #F5F3FF;
        }
        .btn-download:active { transform: translateY(0); }
        .btn-download svg { width: 18px; height: 18px; flex-shrink: 0; }

        /* ── Steps ── */
        .steps { list-style: none; counter-reset: steps; }
        .steps li {
            counter-increment: steps;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text-2);
            font-weight: 500;
        }
        .steps li:last-child { border-bottom: none; padding-bottom: 0; }
        .steps li::before {
            content: counter(steps);
            width: 26px; height: 26px;
            background: var(--accent-dim);
            color: var(--accent);
            border-radius: 50%;
            font-size: 12px;
            font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── Connection status ── */
        .conn-dot {
            display: inline-block;
            width: 9px; height: 9px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }
        .conn-connected    { background: var(--success); box-shadow: 0 0 0 3px rgba(5,150,105,0.18); }
        .conn-disconnected { background: var(--danger); }
        .conn-not          { background: var(--muted); }

        /* ── Domain setup overlay ── */
        .setup-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .setup-card {
            background: var(--surface);
            border-radius: 22px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04), 0 20px 60px rgba(0,0,0,0.09);
            padding: 44px 48px;
            width: 100%; max-width: 500px;
            border: 1px solid var(--border);
        }
        .setup-card h2 { font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 6px; letter-spacing: -0.4px; }
        .setup-card p  { font-size: 14px; color: var(--muted); font-weight: 500; margin-bottom: 28px; line-height: 1.5; }
        .setup-steps { display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px; }
        .setup-step {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 14px; border-radius: 10px;
            background: var(--surface2); border: 1px solid var(--border);
            font-size: 13.5px; font-weight: 500; color: var(--text-2);
        }
        .setup-step-num {
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--accent-dim); color: var(--accent);
            font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .domain-input-wrap { margin-bottom: 16px; }
        .domain-input-wrap label { display: block; font-size: 13px; font-weight: 600; color: var(--text-2); margin-bottom: 7px; }
        .domain-input-wrap input {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-family: inherit; font-size: 14px; font-weight: 500;
            color: var(--text); background: #FAFAFA; outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .domain-input-wrap input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3.5px var(--accent-dim);
            background: var(--surface);
        }
        .btn-setup {
            width: 100%; padding: 13px; border: none; border-radius: 11px;
            font-family: inherit; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: all 0.18s;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #fff; box-shadow: 0 4px 14px rgba(37,99,235,0.38);
        }
        .btn-setup:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.46); }
        .btn-setup:disabled { opacity: 0.6; cursor: not-allowed; }
        .setup-alert { border-radius: 10px; padding: 12px 14px; font-size: 13px; font-weight: 500; margin-bottom: 16px; display: none; }
        .setup-alert-error   { background: #FEF2F2; color: #DC2626; border: 1px solid rgba(220,38,38,.2); }
        .setup-alert-success { background: #ECFDF5; color: #059669; border: 1px solid rgba(5,150,105,.2); }

        /* ── Domain update section ── */
        .domain-row {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .domain-row .info-value { flex: 1; min-width: 0; margin-bottom: 0; }
        .btn-update-domain {
            padding: 7px 14px; border: 1.5px solid var(--border); border-radius: 8px;
            font-family: inherit; font-size: 12.5px; font-weight: 700;
            color: var(--accent); background: var(--accent-dim);
            cursor: pointer; white-space: nowrap; transition: all 0.15s;
            flex-shrink: 0;
        }
        .btn-update-domain:hover { background: rgba(37,99,235,0.18); }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .stat-row { grid-template-columns: 1fr 1fr; }
            .card-body, .card-header { padding-left: 20px; padding-right: 20px; }
            .topbar { padding: 0 16px; }
            .page { padding: 24px 16px 48px; }
            .key-row { flex-direction: column; align-items: stretch; }
            .key-actions { justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <header class="topbar">
        <div class="brand">
            <div class="brand-icon">🛍️</div>
            <span class="brand-name">ShopAI Platform</span>
        </div>
        <div class="topbar-right">
            <div class="user-pill">
                <div class="avatar" id="userAvatar">?</div>
                <span id="userName">Loading…</span>
            </div>
            <button class="btn-logout" onclick="logout()">Sign out</button>
        </div>
    </header>

    <main class="page">
        <!-- Header -->
        <div class="page-header">
            <h1>Client Dashboard</h1>
            <p>Manage your API key, sync products, and integrate the AI chat into your storefront.</p>
        </div>

        <!-- Stats row -->
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-label">Account Status</div>
                <div class="stat-value" id="acctStatusVal" style="font-size:16px;padding-top:4px;"><span class="badge badge-green">● Live</span></div>
                <div class="stat-sub">Platform access</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Client ID</div>
                <div class="stat-value" style="font-size:13px;font-family:monospace;letter-spacing:0;padding-top:4px;" id="clientIdShort">—</div>
                <div class="stat-sub">Unique tenant identifier</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Connection Status</div>
                <div class="stat-value" id="connStatusVal" style="font-size:15px;padding-top:4px;">—</div>
                <div class="stat-sub" id="connStatusSub">Checking…</div>
            </div>
        </div>

        <!-- API Key card -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon icon-blue">🔑</div>
                <div class="card-header-text">
                    <h2>Your API Key</h2>
                    <p>Use this key in the Authorization header of every API request.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="key-row">
                    <span class="key-value masked" id="apiKeyDisplay">Loading…</span>
                    <div class="key-actions">
                        <button class="btn-icon btn-reveal" id="revealBtn" onclick="toggleReveal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Show
                        </button>
                        <button class="btn-icon btn-copy" id="copyBtn" onclick="copyKey()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            Copy
                        </button>
                    </div>
                </div>
                <div class="warn-box">
                    <span class="warn-icon">⚠️</span>
                    <span>Keep this key private. Do not expose it in public client-side code. Rotate it immediately if compromised.</span>
                </div>
            </div>
        </div>

        <!-- Account Details card -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon icon-purple">👤</div>
                <div class="card-header-text">
                    <h2>Account Details</h2>
                    <p>Your account information and tenant identifiers.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="info-field">
                    <div class="info-label">Full Name</div>
                    <div class="info-value" id="detailName">—</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Email Address</div>
                    <div class="info-value" id="detailEmail">—</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Client ID (Tenant UUID)</div>
                    <div class="info-value" id="detailClientId">—</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Registered Domain</div>
                    <div class="domain-row">
                        <div class="info-value" id="detailDomain" style="flex:1;min-width:0;">—</div>
                        <button class="btn-update-domain" onclick="updateDomain()">✏️ Update</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- WordPress plugin download card -->
        <div class="wp-card">
            <div class="wp-card-inner">
                <div class="wp-card-left">
                    <div class="wp-card-icon">🔌</div>
                    <div class="wp-card-text">
                        <h2>WordPress / WooCommerce Plugin</h2>
                        <p>Install our plugin on any WooCommerce store to sync products and embed the AI chat widget — no coding required.</p>
                        <div class="wp-card-pills">
                            <span class="wp-pill">WooCommerce</span>
                            <span class="wp-pill">Auto Product Sync</span>
                            <span class="wp-pill">AI Chat Widget</span>
                            <span class="wp-pill">Plug &amp; Play</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('download.plugin') }}" class="btn-download" id="downloadPluginBtn">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Download Plugin (.zip)
                </a>
            </div>
        </div>

        <!-- Integration guide card -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon icon-green">⚡</div>
                <div class="card-header-text">
                    <h2>Quick Integration Guide</h2>
                    <p>Three steps to add AI-powered shopping to your storefront.</p>
                </div>
            </div>
            <div class="card-body">
                <ol class="steps">
                    <li>Copy your API key from the card above and store it securely (e.g. in an environment variable).</li>
                    <li>Sync your product catalogue to the platform via <code style="background:var(--accent-dim);color:var(--accent);padding:2px 6px;border-radius:5px;font-size:12.5px;font-weight:700;">POST /api/sync-products</code> — embeddings are auto-generated.</li>
                    <li>Embed the chat widget on your storefront and pass the API key in the <code style="background:var(--accent-dim);color:var(--accent);padding:2px 6px;border-radius:5px;font-size:12.5px;font-weight:700;">Authorization</code> header.</li>
                </ol>
            </div>
        </div>

        <!-- Code example card -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon icon-amber">💻</div>
                <div class="card-header-text">
                    <h2>Code Examples</h2>
                    <p>Copy-paste snippets to get started immediately.</p>
                </div>
            </div>
            <div class="card-body">

                <div class="code-label">1 — Sync your products</div>
                <div class="code-block" style="margin-bottom:20px;">
                    <pre id="syncCode">
<span class="hl-fn">fetch</span>(<span class="hl-str">'{{ url("/api/sync-products") }}'</span>, {
  <span class="hl-key">method</span>: <span class="hl-str">'POST'</span>,
  <span class="hl-key">headers</span>: {
    <span class="hl-str">'Authorization'</span>: <span class="hl-str">'Bearer &lt;YOUR_API_KEY&gt;'</span>,
    <span class="hl-str">'Content-Type'</span>: <span class="hl-str">'application/json'</span>,
  },
  <span class="hl-key">body</span>: <span class="hl-fn">JSON.stringify</span>({
    <span class="hl-key">replace_all</span>: <span class="hl-kw">false</span>,
    <span class="hl-key">products</span>: [
      {
        <span class="hl-key">name</span>:        <span class="hl-str">'Classic Black Hoodie'</span>,
        <span class="hl-key">description</span>: <span class="hl-str">'Warm cotton pullover hoodie'</span>,
        <span class="hl-key">category</span>:    <span class="hl-str">'clothing'</span>,
        <span class="hl-key">price</span>:       <span class="hl-kw">39.99</span>,
        <span class="hl-key">attributes</span>:  { <span class="hl-key">color</span>: <span class="hl-str">'black'</span>, <span class="hl-key">size</span>: <span class="hl-str">'L'</span> },
        <span class="hl-key">image_url</span>:   <span class="hl-str">'https://example.com/hoodie.jpg'</span>,
      }
    ],
  }),
});</pre>
                </div>

                <div class="code-label">2 — Chat with your products</div>
                <div class="code-block">
                    <pre>
<span class="hl-fn">fetch</span>(<span class="hl-str">'{{ url("/api/chat") }}'</span>, {
  <span class="hl-key">method</span>: <span class="hl-str">'POST'</span>,
  <span class="hl-key">headers</span>: {
    <span class="hl-str">'Authorization'</span>: <span class="hl-str">'Bearer &lt;YOUR_API_KEY&gt;'</span>,
    <span class="hl-str">'Content-Type'</span>: <span class="hl-str">'application/json'</span>,
  },
  <span class="hl-key">body</span>: <span class="hl-fn">JSON.stringify</span>({ <span class="hl-key">message</span>: <span class="hl-str">'show me black hoodies in large'</span> }),
})
  .<span class="hl-fn">then</span>(r => r.<span class="hl-fn">json</span>())
  .<span class="hl-fn">then</span>(data => {
    <span class="hl-fn">console.log</span>(data.reply);    <span class="hl-cmt">// AI response text</span>
    <span class="hl-fn">console.log</span>(data.products); <span class="hl-cmt">// Matched products array</span>
  });</pre>
                </div>

            </div>
        </div>

    </main>

    <!-- Domain Setup Overlay (shown when api_key not yet generated) -->
    <div class="setup-overlay" id="setupOverlay" style="display:none;">
        <div class="setup-card">
            <div class="brand" style="margin-bottom:24px;">
                <div class="brand-icon">🛍️</div>
                <span class="brand-name">ShopAI Platform</span>
            </div>
            <h2>One last step — register your domain</h2>
            <p>Your API key will be generated and bound exclusively to this domain. Only requests from this domain will be accepted.</p>

            <div class="setup-steps">
                <div class="setup-step"><div class="setup-step-num">1</div><span><strong>Enter your website URL</strong> — e.g. <code style="background:var(--accent-dim);color:var(--accent);padding:1px 5px;border-radius:4px;font-size:12px;">mystore.com</code></span></div>
                <div class="setup-step"><div class="setup-step-num">2</div><span><strong>Your API key is generated</strong> — bound only to that domain</span></div>
                <div class="setup-step"><div class="setup-step-num">3</div><span><strong>Install the WordPress plugin</strong> or use the API directly</span></div>
            </div>

            <div class="setup-alert setup-alert-error" id="setupError"></div>
            <div class="setup-alert setup-alert-success" id="setupSuccess"></div>

            <div class="domain-input-wrap">
                <label for="domainInput">Your Website URL *</label>
                <input type="text" id="domainInput" placeholder="https://mystore.com" autocomplete="url">
            </div>

            <button class="btn-setup" id="setupBtn" onclick="submitSetupDomain()">Activate My Account &amp; Generate API Key</button>

            <p style="text-align:center;font-size:12.5px;color:var(--muted);margin-top:14px;">
                Already have an API key? <a href="#" onclick="logout();return false;" style="color:var(--accent);font-weight:700;">Sign in again</a>
            </p>
        </div>
    </div>

    <script>
        const API_BASE = (function () {
            var base = '{{ rtrim(url("/api"), "/") }}';
            return window.location.origin + base.replace(/^https?:\/\/[^\/]+/, '');
        })();

        // Fix download button href to use current host (works with ngrok or localhost)
        (function () {
            var btn  = document.getElementById('downloadPluginBtn');
            if (!btn) return;
            var href = '{{ route("download.plugin") }}';
            btn.href = window.location.origin + href.replace(/^https?:\/\/[^\/]+/, '');
        })();

        // ── State ────────────────────────────────────────────────────────────
        let apiKey     = localStorage.getItem('shopai_api_key');
        let setupToken = localStorage.getItem('shopai_setup_token');
        let user       = JSON.parse(localStorage.getItem('shopai_user') || 'null');

        // ── Boot logic ───────────────────────────────────────────────────────
        if (!user && !apiKey && !setupToken) {
            window.location.href = '{{ route("login") }}';
        } else if (setupToken && !apiKey) {
            // New user: show domain setup overlay
            document.getElementById('setupOverlay').style.display = 'flex';
        } else if (apiKey && user) {
            bootDashboard();
        } else {
            window.location.href = '{{ route("login") }}';
        }

        function bootDashboard() {
            // Populate topbar
            document.getElementById('userName').textContent   = user?.name  ?? '—';
            document.getElementById('userAvatar').textContent = (user?.name ?? 'U')[0].toUpperCase();

            // Populate stat row
            const short = user?.client_id ? user.client_id.split('-')[0] + '…' : '—';
            document.getElementById('clientIdShort').textContent = short;

            // Populate API key display
            document.getElementById('apiKeyDisplay').textContent = apiKey ?? '—';

            // Populate details
            document.getElementById('detailName').textContent     = user?.name      ?? '—';
            document.getElementById('detailEmail').textContent    = user?.email     ?? '—';
            document.getElementById('detailClientId').textContent = user?.client_id ?? '—';

            // Load live profile (connection status, domain)
            loadProfile();
        }

        async function loadProfile() {
            try {
                const res  = await fetch(`${API_BASE}/auth/me`, {
                    headers: { 'Authorization': `Bearer ${apiKey}`, 'Accept': 'application/json' }
                });
                if (!res.ok) { logout(); return; }
                const data = await res.json();
                const u    = data.user;

                // Connection status
                const statusMap = {
                    'connected':      { label: 'Connected',     cls: 'conn-connected',    badge: 'badge-green' },
                    'not_connected':  { label: 'Not Connected',  cls: 'conn-not',          badge: 'badge-blue'  },
                    'never_connected':{ label: 'Never Connected',cls: 'conn-not',          badge: 'badge-blue'  },
                };
                const s = statusMap[u.connection_status] ?? { label: u.connection_status, cls: 'conn-not', badge: 'badge-blue' };
                const connEl = document.getElementById('connStatusVal');
                if (connEl) {
                    connEl.innerHTML = `<span class="conn-dot ${s.cls}"></span>${s.label}`;
                }
                const connSubEl = document.getElementById('connStatusSub');
                if (connSubEl && u.last_connected_at) {
                    const d = new Date(u.last_connected_at);
                    connSubEl.textContent = 'Last: ' + d.toLocaleString();
                }

                // Domain
                const domEl = document.getElementById('detailDomain');
                if (domEl) domEl.textContent = u.website_domain ?? '(not set)';

                // Account status badge
                const acctEl = document.getElementById('acctStatusVal');
                if (acctEl) {
                    acctEl.innerHTML = u.is_active
                        ? '<span class="badge badge-green">● Live</span>'
                        : '<span class="badge" style="background:#FEF2F2;color:#DC2626;">● Disabled</span>';
                }
            } catch(e) { /* offline */ }
        }

        // ── Domain Setup ─────────────────────────────────────────────────────
        async function submitSetupDomain() {
            const domain = document.getElementById('domainInput').value.trim();
            const btn    = document.getElementById('setupBtn');
            const errEl  = document.getElementById('setupError');
            const okEl   = document.getElementById('setupSuccess');

            errEl.style.display = 'none';
            okEl.style.display  = 'none';

            if (!domain) {
                errEl.textContent = 'Please enter your website URL.';
                errEl.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Setting up…';

            try {
                const res  = await fetch(`${API_BASE}/auth/setup-domain`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({ setup_token: setupToken, website_domain: domain }),
                });
                const data = await res.json();

                if (!res.ok) {
                    errEl.textContent = data.error || 'Setup failed. Please try again.';
                    errEl.style.display = 'block';
                    return;
                }

                // Store api_key, update user, clear setup_token
                localStorage.setItem('shopai_api_key', data.api_key);
                if (data.user) localStorage.setItem('shopai_user', JSON.stringify({
                    ...JSON.parse(localStorage.getItem('shopai_user') || '{}'),
                    ...data.user,
                    website_domain: data.website_domain,
                }));
                localStorage.removeItem('shopai_setup_token');

                okEl.textContent = 'Account activated! Loading your dashboard…';
                okEl.style.display = 'block';

                setTimeout(() => window.location.reload(), 1200);

            } catch(e) {
                errEl.textContent = 'Network error. Please try again.';
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Activate My Account & Generate API Key';
            }
        }

        // ── Update domain (after already active) ─────────────────────────────
        async function updateDomain() {
            const newDomain = prompt('Enter new website domain (e.g. mystore.com):');
            if (!newDomain) return;

            try {
                const res  = await fetch(`${API_BASE}/auth/domain`, {
                    method:  'PUT',
                    headers: { 'Authorization': `Bearer ${apiKey}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({ website_domain: newDomain }),
                });
                const data = await res.json();
                if (!res.ok) { alert(data.error || 'Update failed'); return; }
                const domEl = document.getElementById('detailDomain');
                if (domEl) domEl.textContent = data.website_domain;
            } catch(e) { alert('Network error.'); }
        }

        // ── Reveal / hide key ─────────────────────────────────────────────────
        let revealed = false;
        function toggleReveal() {
            revealed = !revealed;
            const el  = document.getElementById('apiKeyDisplay');
            const btn = document.getElementById('revealBtn');
            if (revealed) {
                el.classList.remove('masked');
                btn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg> Hide`;
            } else {
                el.classList.add('masked');
                btn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Show`;
            }
        }

        // ── Logout ────────────────────────────────────────────────────────────
        function logout() {
            localStorage.removeItem('shopai_api_key');
            localStorage.removeItem('shopai_user');
            localStorage.removeItem('shopai_setup_token');
            window.location.href = '{{ route("login") }}';
        }
    </script>
</body>
</html>
