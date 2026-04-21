<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Admin Dashboard — ShopAI</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #0f172a;
    --sidebar:   #1e293b;
    --surface:   #1e293b;
    --surface2:  #273449;
    --border:    #334155;
    --accent:    #06b6d4;
    --accent2:   #0ea5e9;
    --text:      #f1f5f9;
    --muted:     #94a3b8;
    --danger:    #ef4444;
    --success:   #22c55e;
    --warning:   #f59e0b;
    --purple:    #a855f7;
}

html, body { height: 100%; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    height: 100vh;
    overflow: hidden;
}

/* ── SIDEBAR ─────────────────────────────────────────────────────────────── */
.sidebar {
    width: 240px; min-width: 240px;
    background: var(--sidebar);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    padding: 24px 0;
}
.sidebar-logo {
    display: flex; align-items: center; gap: 11px;
    padding: 0 20px 28px;
    border-bottom: 1px solid var(--border);
}
.sidebar-logo-icon {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.sidebar-logo-text { font-size: 1.1rem; font-weight: 700; }
.sidebar-logo-sub  { font-size: 0.7rem; color: var(--muted); }

.sidebar-nav { padding: 20px 12px; flex: 1; }
.nav-label { font-size: 0.65rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; padding: 0 8px; margin-bottom: 8px; margin-top: 16px; }
.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: 9px; cursor: pointer;
    font-size: 0.875rem; font-weight: 500; color: var(--muted);
    transition: background .15s, color .15s;
    text-decoration: none;
}
.nav-item:hover { background: rgba(255,255,255,.05); color: var(--text); }
.nav-item.active { background: rgba(6,182,212,.12); color: var(--accent); }
.nav-item .icon { font-size: 16px; width: 20px; text-align: center; }

.sidebar-footer {
    padding: 16px 12px 0;
    border-top: 1px solid var(--border);
}
.admin-badge {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 10px;
}
.admin-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--purple));
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.admin-name  { font-size: 0.83rem; font-weight: 600; }
.admin-role  { font-size: 0.7rem; color: var(--muted); }
.logout-btn {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 10px; border-radius: 9px; cursor: pointer;
    font-size: 0.85rem; color: var(--danger); width: 100%;
    background: none; border: none; transition: background .15s;
    font-family: inherit;
}
.logout-btn:hover { background: rgba(239,68,68,.1); }

/* ── MAIN CONTENT ─────────────────────────────────────────────────────────── */
.main {
    flex: 1; overflow-y: auto;
    display: flex; flex-direction: column;
    background: var(--bg);
}

.topbar {
    padding: 22px 32px 0;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.page-title { font-size: 1.5rem; font-weight: 700; }
.page-sub   { font-size: 0.83rem; color: var(--muted); margin-top: 3px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.refresh-btn {
    padding: 8px 14px; border-radius: 9px;
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--muted); font-size: 0.82rem; cursor: pointer;
    display: flex; align-items: center; gap: 6px; font-family: inherit;
    transition: color .15s, border-color .15s;
}
.refresh-btn:hover { color: var(--text); border-color: var(--accent); }

/* ── STATS CARDS ─────────────────────────────────────────────────────────── */
.stats-row {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 16px; padding: 24px 32px 0;
}
.stat-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; padding: 20px 22px;
    display: flex; align-items: flex-start; gap: 14px;
    transition: border-color .2s, transform .15s;
}
.stat-card:hover { border-color: rgba(6,182,212,.4); transform: translateY(-2px); }
.stat-icon {
    width: 44px; height: 44px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.stat-icon.blue   { background: rgba(6,182,212,.15); }
.stat-icon.green  { background: rgba(34,197,94,.15); }
.stat-icon.purple { background: rgba(168,85,247,.15); }
.stat-icon.amber  { background: rgba(245,158,11,.15); }
.stat-val   { font-size: 1.8rem; font-weight: 700; line-height: 1; }
.stat-label { font-size: 0.78rem; color: var(--muted); margin-top: 5px; }
.stat-delta { font-size: 0.72rem; color: var(--success); margin-top: 4px; }

/* ── CLIENTS TABLE ───────────────────────────────────────────────────────── */
.section {
    margin: 24px 32px 32px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden; flex: 1;
}
.section-head {
    padding: 18px 22px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
}
.section-title { font-size: 1rem; font-weight: 700; }
.section-sub   { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

.search-wrap {
    display: flex; align-items: center; gap: 8px;
}
.search-box {
    padding: 8px 13px; border-radius: 9px;
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); font-size: 0.85rem; width: 230px; outline: none;
    font-family: inherit; transition: border-color .2s;
}
.search-box:focus { border-color: var(--accent); }
.search-box::placeholder { color: var(--muted); }

table { width: 100%; border-collapse: collapse; }
thead th {
    padding: 11px 18px; text-align: left;
    font-size: 0.72rem; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid var(--border);
    background: rgba(255,255,255,.02);
}
tbody tr {
    border-bottom: 1px solid rgba(51,65,85,.5);
    transition: background .12s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,.03); }
td { padding: 13px 18px; font-size: 0.86rem; vertical-align: middle; }

.client-cell { display: flex; align-items: center; gap: 11px; }
.client-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff;
}
.client-name  { font-weight: 600; }
.client-email { font-size: 0.77rem; color: var(--muted); margin-top: 2px; }

.badge {
    display: inline-flex; align-items: center;
    padding: 3px 9px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.badge-blue   { background: rgba(6,182,212,.15); color: var(--accent); }
.badge-green  { background: rgba(34,197,94,.15);  color: var(--success); }
.badge-gray   { background: rgba(148,163,184,.1); color: var(--muted); }

.monospace { font-family: 'Courier New', monospace; font-size: 0.8rem; color: var(--muted); }

.action-btns { display: flex; align-items: center; gap: 6px; }
.action-btn {
    width: 30px; height: 30px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: 1px solid var(--border);
    background: none; font-size: 14px; transition: background .15s, border-color .15s;
}
.action-btn:hover       { background: var(--surface2); border-color: var(--accent); }
.action-btn.danger:hover{ background: rgba(239,68,68,.1); border-color: var(--danger); }

.empty-row td {
    text-align: center; padding: 48px;
    color: var(--muted); font-size: 0.9rem;
}

/* ── DETAIL PANEL ────────────────────────────────────────────────────────── */
.overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 50; opacity: 0; pointer-events: none;
    transition: opacity .25s;
}
.overlay.open { opacity: 1; pointer-events: all; }

.detail-panel {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: 460px; background: var(--surface);
    border-left: 1px solid var(--border);
    z-index: 51; display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}
.detail-panel.open { transform: translateX(0); }

.panel-head {
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.panel-title  { font-size: 1rem; font-weight: 700; }
.close-btn {
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--muted); cursor: pointer; font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    transition: color .15s, border-color .15s;
}
.close-btn:hover { color: var(--text); border-color: var(--accent); }

.panel-body { flex: 1; overflow-y: auto; padding: 22px; }

.panel-client-header {
    display: flex; align-items: center; gap: 14px;
    padding: 16px; border-radius: 12px;
    background: var(--surface2); border: 1px solid var(--border);
    margin-bottom: 20px;
}
.panel-avatar {
    width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: 700; color: #fff;
}
.panel-name  { font-size: 1.05rem; font-weight: 700; }
.panel-email { font-size: 0.82rem; color: var(--muted); margin-top: 3px; }
.panel-reg   { font-size: 0.75rem; color: var(--muted); margin-top: 4px; }

.panel-stats-row {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 10px; margin-bottom: 20px;
}
.mini-stat {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 14px; text-align: center;
}
.mini-stat-val   { font-size: 1.4rem; font-weight: 700; }
.mini-stat-label { font-size: 0.7rem; color: var(--muted); margin-top: 3px; }

.info-row {
    display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;
}
.info-item {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 14px;
}
.info-label { font-size: 0.7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
.info-value { font-size: 0.83rem; word-break: break-all; }
.copy-row { display: flex; align-items: center; gap: 8px; }
.copy-value { font-family: 'Courier New', monospace; font-size: 0.78rem; flex: 1; word-break: break-all; }
.copy-btn {
    padding: 5px 10px; border-radius: 7px; font-size: 0.75rem; font-weight: 600;
    background: rgba(6,182,212,.12); border: 1px solid rgba(6,182,212,.3);
    color: var(--accent); cursor: pointer; white-space: nowrap; font-family: inherit;
    flex-shrink: 0; transition: background .15s;
}
.copy-btn:hover { background: rgba(6,182,212,.22); }

.products-section-title {
    font-size: 0.78rem; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px;
}
.product-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px; border-radius: 8px;
    background: var(--surface2); border: 1px solid var(--border);
    margin-bottom: 7px;
}
.product-row:last-child { margin-bottom: 0; }
.product-row-name { font-size: 0.83rem; font-weight: 600; }
.product-row-cat  { font-size: 0.73rem; color: var(--muted); margin-top: 2px; }
.product-row-right { text-align: right; }
.product-row-price { font-size: 0.85rem; font-weight: 700; color: var(--accent); }
.product-row-stock { font-size: 0.72rem; margin-top: 2px; }
.in-stock    { color: var(--success); }
.out-of-stock{ color: var(--danger); }

.panel-footer {
    padding: 16px 22px;
    border-top: 1px solid var(--border);
    display: flex; gap: 10px; flex-shrink: 0;
}
.btn {
    flex: 1; padding: 10px; border-radius: 9px;
    font-size: 0.875rem; font-weight: 600; cursor: pointer;
    border: none; font-family: inherit; transition: opacity .15s, transform .1s;
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.btn:active { transform: scale(.97); }
.btn-danger  { background: var(--danger); color: #fff; }
.btn-warning { background: linear-gradient(135deg, var(--warning), #ea580c); color: #fff; }
.btn-success { background: var(--success); color: #fff; }
.btn-muted   { background: var(--surface2); border: 1px solid var(--border); color: var(--muted); }
.btn-danger:hover  { opacity: .85; }
.btn-warning:hover { opacity: .9; }
.btn-success:hover { opacity: .85; }
.btn-muted:hover   { border-color: var(--accent); color: var(--text); }

/* ── CONFIRM MODAL ───────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,.65);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity .2s;
    padding: 24px;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 28px 28px 24px;
    max-width: 400px; width: 100%;
    transform: scale(.95); transition: transform .2s;
}
.modal-overlay.open .modal { transform: scale(1); }
.modal-icon { font-size: 36px; margin-bottom: 14px; }
.modal-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; }
.modal-text  { font-size: 0.875rem; color: var(--muted); margin-bottom: 22px; line-height: 1.55; }
.modal-btns  { display: flex; gap: 10px; }
.modal-btns .btn { flex: 1; }
.btn-cancel {
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); font-size: 0.875rem; font-weight: 600;
    padding: 10px; border-radius: 9px; cursor: pointer;
    font-family: inherit; flex: 1; transition: border-color .15s;
}
.btn-cancel:hover { border-color: var(--accent); }

/* ── TOAST ───────────────────────────────────────────────────────────────── */
.toast-wrap {
    position: fixed; bottom: 24px; right: 24px; z-index: 200;
    display: flex; flex-direction: column; gap: 10px;
}
.toast {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 500;
    box-shadow: 0 8px 24px rgba(0,0,0,.4); min-width: 260px;
    animation: slideIn .25s ease;
}
.toast.success { background: rgba(34,197,94,.15); border: 1px solid rgba(34,197,94,.3); color: #86efac; }
.toast.error   { background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; }
.toast.info    { background: rgba(6,182,212,.15);  border: 1px solid rgba(6,182,212,.3);  color: #67e8f9; }
@keyframes slideIn { from { transform: translateX(40px); opacity: 0; } to { transform: none; opacity: 1; } }

/* ── LOADING SPINNER ─────────────────────────────────────────────────────── */
.spinner {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,.2); border-top-color: var(--accent);
    animation: spin .65s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── SKELETON ────────────────────────────────────────────────────────────── */
.skeleton {
    background: linear-gradient(90deg, var(--surface2) 25%, rgba(71,85,105,.4) 50%, var(--surface2) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite; border-radius: 6px;
}
@keyframes shimmer { to { background-position: -200% 0; } }
</style>
</head>
<body>

<!-- ── SIDEBAR ─────────────────────────────────────────────────────────── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">🛍️</div>
        <div>
            <div class="sidebar-logo-text">ShopAI</div>
            <div class="sidebar-logo-sub">Admin Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Overview</div>
        <a class="nav-item active" href="#">
            <span class="icon">📊</span> Dashboard
        </a>
        <div class="nav-label">Management</div>
        <a class="nav-item" href="#" onclick="focusSearch(); return false;">
            <span class="icon">👥</span> Clients
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-badge">
            <div class="admin-avatar" id="adminInitial">A</div>
            <div>
                <div class="admin-name" id="adminName">Admin</div>
                <div class="admin-role">Super Admin</div>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin-top:6px;">
            @csrf
            <button type="submit" class="logout-btn">
                <span>🚪</span> Sign Out
            </button>
        </form>
    </div>
</aside>

<!-- ── MAIN ────────────────────────────────────────────────────────────── -->
<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="page-sub" id="dateLabel"></div>
        </div>
        <div class="topbar-right">
            <button class="refresh-btn" onclick="loadAll()">
                <span>↻</span> Refresh
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue">👥</div>
            <div>
                <div class="stat-val" id="s-clients"><span class="skeleton" style="width:40px;height:32px;display:block"></span></div>
                <div class="stat-label">Total Clients</div>
                <div class="stat-delta" id="s-new"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🛍️</div>
            <div>
                <div class="stat-val" id="s-products"><span class="skeleton" style="width:40px;height:32px;display:block"></span></div>
                <div class="stat-label">Total Products</div>
                <div class="stat-delta" id="s-embed"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">🧠</div>
            <div>
                <div class="stat-val" id="s-embedded"><span class="skeleton" style="width:40px;height:32px;display:block"></span></div>
                <div class="stat-label">With Embeddings</div>
                <div class="stat-delta" style="color:var(--muted)">AI-ready products</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">✨</div>
            <div>
                <div class="stat-val" id="s-month"><span class="skeleton" style="width:40px;height:32px;display:block"></span></div>
                <div class="stat-label">New This Month</div>
                <div class="stat-delta" style="color:var(--muted)">Registered clients</div>
            </div>
        </div>
    </div>

    <!-- Clients section -->
    <div class="section">
        <div class="section-head">
            <div>
                <div class="section-title">Client Accounts</div>
                <div class="section-sub" id="clientCountLabel">Loading...</div>
            </div>
            <div class="search-wrap">
                <input id="searchInput" class="search-box" type="text"
                       placeholder="🔍  Search name, email, ID…"
                       oninput="debounceSearch(this.value)">
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Client ID</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Products</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="clientsTableBody">
                    <tr><td colspan="7" style="text-align:center;padding:36px;">
                        <div class="spinner" style="margin:0 auto 10px;"></div>
                        <div style="color:var(--muted);font-size:.85rem;">Loading clients…</div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ── DETAIL PANEL ─────────────────────────────────────────────────────── -->
<div class="overlay" id="overlay" onclick="closePanel()"></div>

<div class="detail-panel" id="detailPanel">
    <div class="panel-head">
        <div class="panel-title">Client Details</div>
        <button class="close-btn" onclick="closePanel()">✕</button>
    </div>
    <div class="panel-body" id="panelBody">
        <div style="text-align:center;padding:40px 0;color:var(--muted);">
            <div class="spinner" style="margin:0 auto 12px;"></div>
            Loading…
        </div>
    </div>
    <div class="panel-footer" style="flex-wrap:wrap;gap:8px;">
        <button class="btn btn-muted" id="toggleBtn" onclick="toggleActive()">
            🚫 Disable Client
        </button>
        <button class="btn btn-warning" id="regenBtn" onclick="regenKey()">
            🔑 Regenerate Key
        </button>
        <button class="btn btn-danger" id="deleteBtn" onclick="confirmDelete()">
            🗑️ Delete
        </button>
    </div>
</div>

<!-- ── CONFIRM MODAL ────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon">⚠️</div>
        <div class="modal-title">Delete Client?</div>
        <div class="modal-text" id="confirmText">This will permanently delete the client account and all their synced products. This action cannot be undone.</div>
        <div class="modal-btns">
            <button class="btn-cancel" onclick="closeConfirm()">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn" onclick="doDelete()">Delete</button>
        </div>
    </div>
</div>

<!-- ── TOAST CONTAINER ──────────────────────────────────────────────────── -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ── SCRIPTS ──────────────────────────────────────────────────────────── -->
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let activeClientId = null;
let searchTimer    = null;

// ── Helpers ────────────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = `toast ${type}`;
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    el.innerHTML = `<span>${icons[type] || 'ℹ'}</span> ${msg}`;
    wrap.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 3500);
}

async function api(method, url, body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    };
    if (body) opts.body = JSON.stringify(body);
    const res  = await fetch(url, opts);
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
    return data;
}

function avatarColor(name) {
    const colors = ['#06b6d4','#0ea5e9','#a855f7','#22c55e','#f59e0b','#ef4444','#ec4899','#8b5cf6'];
    let hash = 0;
    for (const c of name) hash = c.charCodeAt(0) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
}
function initials(name) {
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// ── Load stats ─────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const d = await api('GET', '/admin/api/stats');
        document.getElementById('s-clients').textContent  = d.total_clients;
        document.getElementById('s-products').textContent = d.total_products;
        document.getElementById('s-embedded').textContent = d.products_with_embed;
        document.getElementById('s-month').textContent    = d.new_this_month;
        document.getElementById('s-new').textContent = `+${d.new_this_month} this month`;
        document.getElementById('s-embed').textContent = `${d.products_with_embed} embedded`;
    } catch (e) { toast('Failed to load stats', 'error'); }
}

// ── Load clients table ─────────────────────────────────────────────────────
async function loadClients(search = '') {
    const tbody = document.getElementById('clientsTableBody');
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:28px;">
        <div class="spinner" style="margin:0 auto 8px;"></div>
        <div style="color:var(--muted);font-size:.82rem;">Loading…</div>
    </td></tr>`;

    try {
        const d = await api('GET', `/admin/api/clients?search=${encodeURIComponent(search)}`);
        const clients = d.clients;
        const lbl = document.getElementById('clientCountLabel');
        lbl.textContent = `${clients.length} client${clients.length !== 1 ? 's' : ''} ${search ? 'found' : 'registered'}`;

        if (!clients.length) {
            tbody.innerHTML = `<tr class="empty-row"><td colspan="8">
                <div style="font-size:2rem;margin-bottom:10px;">🔍</div>
                No clients found${search ? ' for "' + search + '"' : ''}
            </td></tr>`;
            return;
        }

        tbody.innerHTML = clients.map((c, i) => `
            <tr style="opacity:${c.is_active ? 1 : 0.55}">
                <td style="color:var(--muted);font-size:.8rem;">${i + 1}</td>
                <td>
                    <div class="client-cell">
                        <div class="client-avatar" style="background:${avatarColor(c.name)}">${initials(c.name)}</div>
                        <div>
                            <div class="client-name">${esc(c.name)}</div>
                            <div class="client-email">${esc(c.email)}</div>
                        </div>
                    </div>
                </td>
                <td><span class="monospace">${c.client_id.substring(0,8)}…</span></td>
                <td style="font-size:.8rem;color:var(--muted);">${c.website_domain ? esc(c.website_domain) : '<span style="color:var(--warning)">Not set</span>'}</td>
                <td>
                    <span class="badge ${c.is_active ? 'badge-green' : 'badge-gray'}">
                        ${c.is_active ? '● Active' : '● Disabled'}
                    </span>
                </td>
                <td>
                    <span class="badge ${c.products_count > 0 ? 'badge-blue' : 'badge-gray'}">
                        ${c.products_count} product${c.products_count !== 1 ? 's' : ''}
                    </span>
                </td>
                <td style="color:var(--muted);font-size:.82rem;" title="${esc(c.registered_ago)}">${esc(c.registered_at)}</td>
                <td>
                    <div class="action-btns">
                        <button class="action-btn" title="View Details" onclick="openPanel(${c.id})">👁️</button>
                        <button class="action-btn ${c.is_active ? 'danger' : ''}" title="${c.is_active ? 'Disable' : 'Enable'}" onclick="quickToggle(${c.id}, this)">${c.is_active ? '🚫' : '✅'}</button>
                        <button class="action-btn danger" title="Delete" onclick="confirmDeleteById(${c.id}, '${esc(c.name)}')">🗑️</button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">⚠️ Failed to load clients. ${e.message}</td></tr>`;
        toast('Failed to load clients', 'error');
    }
}

function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function debounceSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadClients(val), 320);
}

function focusSearch() {
    document.getElementById('searchInput').focus();
}

// ── Detail panel ───────────────────────────────────────────────────────────
async function openPanel(clientId) {
    activeClientId = clientId;
    document.getElementById('overlay').classList.add('open');
    document.getElementById('detailPanel').classList.add('open');
    document.getElementById('regenBtn').disabled  = true;
    document.getElementById('deleteBtn').disabled = true;

    const body = document.getElementById('panelBody');
    body.innerHTML = `<div style="text-align:center;padding:40px 0;color:var(--muted);">
        <div class="spinner" style="margin:0 auto 12px;"></div> Loading…
    </div>`;

    try {
        const d = await api('GET', `/admin/api/clients/${clientId}`);
        const c = d.client;
        const ps = d.recent_products;

        document.getElementById('regenBtn').disabled  = false;
        document.getElementById('deleteBtn').disabled = false;

        const color = avatarColor(c.name);

        const connColor = {'connected':'var(--success)','not_connected':'var(--muted)','never_connected':'var(--muted)'}[c.connection_status] ?? 'var(--muted)';
        const connLabel = {'connected':'Connected','not_connected':'Not Connected','never_connected':'Never Connected'}[c.connection_status] ?? c.connection_status;

        // Update toggle button label
        const toggleBtn = document.getElementById('toggleBtn');
        if (toggleBtn) {
            toggleBtn.innerHTML = c.is_active ? '🚫 Disable Client' : '✅ Enable Client';
            toggleBtn.className = 'btn ' + (c.is_active ? 'btn-muted' : 'btn-success');
        }

        body.innerHTML = `
            <div class="panel-client-header">
                <div class="panel-avatar" style="background:${color}">${initials(c.name)}</div>
                <div>
                    <div class="panel-name">${esc(c.name)}</div>
                    <div class="panel-email">${esc(c.email)}</div>
                    <div class="panel-reg">Joined ${esc(c.registered_at)} · ${esc(c.registered_ago)}</div>
                    <div style="margin-top:5px">
                        <span class="badge ${c.is_active ? 'badge-green' : 'badge-gray'}">${c.is_active ? '● Active' : '● Disabled'}</span>
                        &nbsp;
                        <span style="font-size:.75rem;color:${connColor}">⬤ ${connLabel}</span>
                    </div>
                </div>
            </div>

            <div class="panel-stats-row">
                <div class="mini-stat">
                    <div class="mini-stat-val">${c.products_count}</div>
                    <div class="mini-stat-label">Total Products</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-val" style="color:var(--accent)">${c.embedded_count}</div>
                    <div class="mini-stat-label">Embedded</div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-val" style="color:var(--success)">${c.in_stock_count}</div>
                    <div class="mini-stat-label">In Stock</div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Client ID</div>
                    <div class="copy-row">
                        <span class="copy-value">${esc(c.client_id)}</span>
                        <button class="copy-btn" onclick="copyText('${esc(c.client_id)}')">Copy</button>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Registered Domain</div>
                    <div class="info-value" style="font-size:.82rem;">${esc(c.website_domain)}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Connected</div>
                    <div class="info-value" style="font-size:.82rem;color:${connColor}">${esc(c.last_connected_at)}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">API Key</div>
                    <div class="copy-row">
                        <span class="copy-value" id="panelApiKey">${esc(c.api_key)}</span>
                        <button class="copy-btn" onclick="copyText(document.getElementById('panelApiKey').textContent)">Copy</button>
                    </div>
                </div>
            </div>

            ${ps.length ? `
            <div class="products-section-title">Recent Products (${ps.length})</div>
            ${ps.map(p => `
                <div class="product-row">
                    <div>
                        <div class="product-row-name">${esc(p.name)}</div>
                        <div class="product-row-cat">${esc(p.category)} · Added ${esc(p.added)}</div>
                    </div>
                    <div class="product-row-right">
                        <div class="product-row-price">$${esc(p.price)}</div>
                        <div class="product-row-stock ${p.in_stock ? 'in-stock' : 'out-of-stock'}">
                            ${p.in_stock ? '● In Stock' : '● Out of Stock'}
                        </div>
                    </div>
                </div>
            `).join('')}
            ` : `<div style="color:var(--muted);font-size:.85rem;text-align:center;padding:20px 0;">No products synced yet</div>`}
        `;
    } catch (e) {
        body.innerHTML = `<div style="color:var(--danger);text-align:center;padding:30px;">⚠️ ${esc(e.message)}</div>`;
        toast('Failed to load client details', 'error');
    }
}

function closePanel() {
    document.getElementById('overlay').classList.remove('open');
    document.getElementById('detailPanel').classList.remove('open');
    activeClientId = null;
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        toast('Copied to clipboard!', 'success');
    } catch { toast('Copy failed', 'error'); }
}

// ── Toggle active ──────────────────────────────────────────────────────────
async function toggleActive() {
    if (!activeClientId) return;
    const btn = document.getElementById('toggleBtn');
    btn.disabled = true;
    try {
        const d = await api('POST', `/admin/api/clients/${activeClientId}/toggle-active`);
        toast(d.message, 'success');
        openPanel(activeClientId); // refresh panel
        loadClients(document.getElementById('searchInput').value);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

async function quickToggle(clientId, btn) {
    btn.disabled = true;
    try {
        const d = await api('POST', `/admin/api/clients/${clientId}/toggle-active`);
        toast(d.message, 'success');
        loadClients(document.getElementById('searchInput').value);
    } catch (e) {
        toast(e.message, 'error');
        btn.disabled = false;
    }
}

// ── Regenerate key ─────────────────────────────────────────────────────────
async function regenKey() {
    if (!activeClientId) return;
    const btn = document.getElementById('regenBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Regenerating…';
    try {
        const d = await api('POST', `/admin/api/clients/${activeClientId}/regenerate-key`);
        const keyEl = document.getElementById('panelApiKey');
        if (keyEl) keyEl.textContent = d.api_key;
        toast('API key regenerated!', 'success');
        loadClients(document.getElementById('searchInput').value);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '🔑 Regenerate Key';
    }
}

// ── Delete ─────────────────────────────────────────────────────────────────
let deleteTargetId = null;

function confirmDelete() {
    if (!activeClientId) return;
    deleteTargetId = activeClientId;
    document.getElementById('confirmModal').classList.add('open');
}

function confirmDeleteById(id, name) {
    deleteTargetId = id;
    document.getElementById('confirmText').textContent =
        `This will permanently delete "${name}" and all their synced products. This cannot be undone.`;
    document.getElementById('confirmModal').classList.add('open');
}

function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    deleteTargetId = null;
}

async function doDelete() {
    if (!deleteTargetId) return;
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div>';
    try {
        await api('DELETE', `/admin/api/clients/${deleteTargetId}`);
        toast('Client deleted successfully', 'success');
        closeConfirm();
        closePanel();
        loadAll();
    } catch (e) {
        toast(e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = 'Delete';
    }
}

// ── Init ───────────────────────────────────────────────────────────────────
function loadAll() {
    loadStats();
    loadClients(document.getElementById('searchInput').value);
}

document.getElementById('dateLabel').textContent =
    new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

// Set admin name from server
document.getElementById('adminName').textContent = @json(Auth::user()->name ?? 'Admin');
document.getElementById('adminInitial').textContent = @json(strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)));

loadAll();
</script>

</body>
</html>
