<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ShopAI — Intelligent Shopping Assistant</title>
    <meta name="theme-color" content="#F9FAFB">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:           #F9FAFB;
            --bg2:          #F3F4F6;
            --surface:      #FFFFFF;
            --surface2:     #F3F4F6;
            --surface3:     #E5E7EB;
            --border:       rgba(17,24,39,0.09);
            --border2:      rgba(17,24,39,0.15);
            --accent:       #2563EB;
            --accent2:      #3B82F6;
            --accent-glow:  rgba(37,99,235,0.18);
            --accent-dim:   rgba(37,99,235,0.08);
            --text:         #111827;
            --text-2:       #374151;
            --text-muted:   #6B7280;
            --user-grad:    linear-gradient(135deg, #2563EB, #1D4ED8);
            --ai-bg:        #F3F4F6;
            --success:      #10B981;
            --warn:         #F59E0B;
            --price:        #059669;
            --rank1:        #D97706;
            --rank2:        #6B7280;
            --rank3:        #B45309;
            --radius:       16px;
            --radius-sm:    10px;
        }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        /* ── Animated background ───────────────────────────────────────── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 10%, rgba(37,99,235,0.05) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(16,185,129,0.04) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 50% 50%, rgba(37,99,235,0.03) 0%, transparent 60%);
            pointer-events: none;
        }

        /* ── App shell ─────────────────────────────────────────────────── */
        .app {
            position: relative; z-index: 1;
            display: grid;
            grid-template-rows: auto 1fr;
            height: 100vh;
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ── Header ────────────────────────────────────────────────────── */
        header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 0 14px;
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            position: relative;
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 0 20px rgba(37,99,235,0.3);
        }
        .logo-icon::after {
            content: '';
            position: absolute; inset: -2px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(37,99,235,0.35), rgba(16,185,129,0.2));
            z-index: -1;
            filter: blur(6px);
        }
        .logo-text h1 {
            font-size: 1.15rem; font-weight: 800; letter-spacing: -0.02em;
            background: linear-gradient(135deg, #1E40AF, #2563EB);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .logo-text .tagline {
            font-size: 0.7rem; color: var(--text-muted); margin-top: 1px;
            display: flex; align-items: center; gap: 5px;
        }
        .status-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 6px var(--success);
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; } 50% { opacity: 0.4; }
        }

        .header-right { display: flex; align-items: center; gap: 10px; }
        .model-badge {
            display: flex; align-items: center; gap: 6px;
            background: var(--surface); border: 1px solid var(--border2);
            padding: 5px 11px; border-radius: 20px; font-size: 0.72rem; color: var(--text-muted);
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .model-badge span { color: var(--accent); font-weight: 600; }

        .btn-icon {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border2);
            background: var(--surface); color: var(--text-muted); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; font-size: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .btn-icon:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-dim); }

        .btn-clear {
            display: flex; align-items: center; gap: 6px;
            background: var(--surface); border: 1px solid var(--border2); color: var(--text-muted);
            padding: 7px 14px; border-radius: 10px; cursor: pointer; font-size: 0.78rem;
            font-family: inherit; transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .btn-clear:hover { border-color: #ef4444; color: #ef4444; background: rgba(239,68,68,0.06); }

        .btn-auth {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 10px; font-size: 0.78rem;
            font-family: inherit; font-weight: 600; text-decoration: none;
            transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            white-space: nowrap;
        }
        .btn-login {
            background: var(--surface); border: 1px solid var(--border2); color: var(--text-2);
        }
        .btn-login:hover {
            border-color: var(--accent); color: var(--accent); background: var(--accent-dim);
            box-shadow: 0 3px 8px rgba(37,99,235,0.12);
        }
        .btn-register {
            background: linear-gradient(135deg, #2563EB, #1D4ED8); border: 1px solid transparent;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37,99,235,0.42);
        }

        /* ── Main grid ─────────────────────────────────────────────────── */
        .main {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 16px;
            padding: 16px 0 16px;
            overflow: hidden;
            min-height: 0;
        }

        /* ── Chat Panel ────────────────────────────────────────────────── */
        .chat-panel {
            display: flex; flex-direction: column;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            min-height: 0;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06), 0 4px 24px rgba(37,99,235,0.04);
        }

        .chat-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
            background: rgba(37,99,235,0.02);
        }
        .chat-header-icon {
            width: 30px; height: 30px; border-radius: 8px;
            background: var(--accent-dim); border: 1px solid rgba(37,99,235,0.2);
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .chat-header h2 { font-size: 0.88rem; font-weight: 700; color: var(--text); }
        .chat-header p { font-size: 0.7rem; color: var(--text-muted); }

        /* Messages */
        .messages {
            flex: 1; overflow-y: auto; min-height: 0;
            display: flex; flex-direction: column; gap: 4px;
            padding: 20px 20px 12px;
            scrollbar-width: thin; scrollbar-color: rgba(17,24,39,0.1) transparent;
        }
        .messages::-webkit-scrollbar { width: 4px; }
        .messages::-webkit-scrollbar-thumb { background: rgba(17,24,39,0.1); border-radius: 4px; }

        /* ── Welcome screen ────────────────────────────────────────────── */
        .welcome {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center; padding: 40px 20px; gap: 0;
            animation: fadeUp 0.5s ease;
        }
        .welcome-orb {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(16,185,129,0.08));
            border: 1px solid rgba(37,99,235,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 34px; margin-bottom: 20px;
            box-shadow: 0 0 40px rgba(37,99,235,0.1);
            animation: float 4s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-8px); }
        }
        .welcome h2 {
            font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 10px;
            background: linear-gradient(135deg, #1E40AF, #2563EB, #0891B2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .welcome p { font-size: 0.88rem; color: var(--text-muted); max-width: 360px; line-height: 1.65; margin-bottom: 28px; }
        .welcome-features {
            display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;
        }
        .feature-pill {
            display: flex; align-items: center; gap: 6px;
            background: var(--surface2); border: 1px solid var(--border2);
            padding: 6px 12px; border-radius: 20px; font-size: 0.73rem; color: var(--text-2);
            font-weight: 500;
        }

        /* ── Message bubbles ───────────────────────────────────────────── */
        .msg {
            display: flex; gap: 10px; align-items: flex-end;
            animation: fadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .msg + .msg { margin-top: 2px; }
        .msg.user  { flex-direction: row-reverse; }
        .msg.gap   { margin-top: 12px; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .avatar {
            width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; letter-spacing: -0.5px;
        }
        .msg.user .avatar {
            background: var(--user-grad);
            box-shadow: 0 2px 10px rgba(37,99,235,0.3);
            color: #fff;
        }
        .msg.ai .avatar {
            background: var(--surface2);
            border: 1px solid var(--border2);
            font-size: 15px;
        }

        .bubble-wrap { display: flex; flex-direction: column; max-width: 78%; }
        .msg.user .bubble-wrap { align-items: flex-end; }

        .bubble {
            padding: 11px 15px; border-radius: 18px;
            font-size: 0.9rem; line-height: 1.6;
            word-break: break-word;
        }
        .msg.user .bubble {
            background: var(--user-grad);
            color: #fff;
            border-bottom-right-radius: 5px;
            box-shadow: 0 4px 15px rgba(37,99,235,0.2);
        }
        .msg.ai .bubble {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text-2);
            border-bottom-left-radius: 5px;
        }
        .bubble-meta {
            display: flex; align-items: center; gap: 6px;
            margin-top: 4px; padding: 0 2px;
        }
        .msg.user .bubble-meta { justify-content: flex-end; }
        .bubble-time { font-size: 0.67rem; color: var(--text-muted); }
        .msg.user .bubble-time { color: rgba(255,255,255,0.6); }

        /* ── Typing indicator ──────────────────────────────────────────── */
        .typing .bubble {
            padding: 13px 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .typing-label { font-size: 0.72rem; color: var(--text-muted); }
        .dots { display: flex; align-items: center; gap: 4px; }
        .dots span {
            display: block; width: 7px; height: 7px; border-radius: 50%;
            background: var(--accent); opacity: 0.7;
            animation: dotBounce 1.4s ease-in-out infinite;
        }
        .dots span:nth-child(2) { animation-delay: 0.15s; }
        .dots span:nth-child(3) { animation-delay: 0.30s; }
        @keyframes dotBounce {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
            30%            { transform: translateY(-5px); opacity: 1; }
        }

        /* ── Suggestions ───────────────────────────────────────────────── */
        .suggestions-wrap {
            padding: 10px 20px 6px;
            border-top: 1px solid var(--border);
            display: flex; gap: 7px; flex-wrap: wrap;
            background: rgba(37,99,235,0.02);
        }
        .chip {
            display: flex; align-items: center; gap: 5px;
            background: var(--surface); border: 1px solid var(--border2);
            color: var(--text-2); padding: 5px 11px; border-radius: 20px;
            font-size: 0.73rem; cursor: pointer; font-family: inherit;
            transition: all 0.2s; white-space: nowrap; font-weight: 500;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .chip:hover {
            background: var(--accent-dim); border-color: rgba(37,99,235,0.4);
            color: var(--accent); transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(37,99,235,0.12);
        }
        .chip-icon { font-size: 12px; }

        /* ── Input area ────────────────────────────────────────────────── */
        .input-wrap { padding: 12px 16px; }
        .input-box {
            display: flex; align-items: flex-end; gap: 10px;
            background: var(--surface2); border: 1px solid var(--border2);
            border-radius: 16px; padding: 10px 10px 10px 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-box:focus-within {
            border-color: rgba(37,99,235,0.5);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08), 0 4px 20px rgba(0,0,0,0.06);
            background: var(--surface);
        }
        #messageInput {
            flex: 1; background: none; border: none; outline: none;
            color: var(--text); font-size: 0.92rem; resize: none;
            max-height: 110px; line-height: 1.5; font-family: inherit;
        }
        #messageInput::placeholder { color: var(--text-muted); }
        .input-actions { display: flex; align-items: flex-end; gap: 6px; }
        .char-count {
            font-size: 0.66rem; color: var(--text-muted);
            padding-bottom: 2px; min-width: 30px; text-align: right;
        }
        .char-count.near-limit { color: var(--warn); }
        .send-btn {
            width: 38px; height: 38px; border-radius: 12px; border: none;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(37,99,235,0.35);
        }
        .send-btn:hover  { transform: scale(1.07); box-shadow: 0 6px 18px rgba(37,99,235,0.45); }
        .send-btn:active { transform: scale(0.95); }
        .send-btn:disabled {
            background: var(--surface3); box-shadow: none;
            cursor: not-allowed; opacity: 0.6; transform: none;
        }
        .send-btn svg { transition: transform 0.2s; }
        .send-btn:not(:disabled):hover svg { transform: translateX(1px) translateY(-1px); }
        .input-hint {
            text-align: center; font-size: 0.67rem; color: var(--text-muted);
            margin-top: 6px; padding: 0 4px;
        }

        /* ── Products Sidebar ──────────────────────────────────────────── */
        .products-panel {
            display: flex; flex-direction: column;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            min-height: 0;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06), 0 4px 24px rgba(16,185,129,0.04);
        }

        .panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: rgba(16,185,129,0.02);
        }
        .panel-header-top {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;
        }
        .panel-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.85rem; font-weight: 700; color: var(--text);
        }
        .panel-title-icon { font-size: 14px; }
        .product-count-badge {
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(16,185,129,0.1));
            border: 1px solid rgba(37,99,235,0.2);
            color: var(--accent); padding: 2px 9px; border-radius: 12px; font-size: 0.72rem;
            font-weight: 700; transition: all 0.3s;
        }
        .panel-subtitle { font-size: 0.69rem; color: var(--text-muted); }

        /* Intent pills row */
        .intent-row {
            display: flex; gap: 5px; flex-wrap: wrap;
            padding: 10px 18px; border-bottom: 1px solid var(--border);
            background: rgba(37,99,235,0.03);
            animation: fadeUp 0.3s ease;
        }
        .intent-pill {
            display: flex; align-items: center; gap: 4px;
            background: var(--surface); border: 1px solid var(--border2);
            padding: 3px 9px; border-radius: 10px; font-size: 0.69rem; color: var(--text-2);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .intent-pill .key { color: var(--text-muted); }
        .intent-pill .val { color: var(--accent); font-weight: 600; }

        /* Products list */
        .products-list {
            flex: 1; overflow-y: auto; min-height: 0;
            padding: 12px; display: flex; flex-direction: column; gap: 8px;
            scrollbar-width: thin; scrollbar-color: rgba(17,24,39,0.08) transparent;
        }
        .products-list::-webkit-scrollbar { width: 3px; }
        .products-list::-webkit-scrollbar-thumb { background: rgba(17,24,39,0.08); border-radius: 3px; }

        /* Product card */
        .product-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 14px;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer; position: relative; overflow: hidden;
            animation: fadeUp 0.35s ease both;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .product-card:hover {
            border-color: rgba(37,99,235,0.3);
            background: var(--surface);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37,99,235,0.1), 0 0 0 1px rgba(37,99,235,0.08);
        }
        .product-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, #2563EB, transparent);
            opacity: 0; transition: opacity 0.2s;
        }
        .product-card:hover::before { opacity: 1; }

        /* Rank badge */
        .card-rank {
            position: absolute; top: 12px; right: 12px;
            width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; font-weight: 700;
        }
        .rank-1 { background: rgba(217,119,6,0.1); border: 1px solid rgba(217,119,6,0.3); color: var(--rank1); }
        .rank-2 { background: rgba(107,114,128,0.1); border: 1px solid rgba(107,114,128,0.25); color: var(--rank2); }
        .rank-3 { background: rgba(180,83,9,0.1); border: 1px solid rgba(180,83,9,0.25); color: var(--rank3); }
        .rank-n { background: var(--surface2); border: 1px solid var(--border2); color: var(--text-muted); }

        .card-top { display: flex; align-items: flex-start; gap: 10px; padding-right: 28px; margin-bottom: 8px; }
        .card-emoji {
            width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
            background: var(--surface2); border: 1px solid var(--border2);
            display: flex; align-items: center; justify-content: center; font-size: 18px;
        }
        .card-info { flex: 1; min-width: 0; }
        .p-name {
            font-size: 0.84rem; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .p-cat { font-size: 0.68rem; color: var(--text-muted); font-weight: 500; }

        .p-desc {
            font-size: 0.74rem; color: var(--text-muted); line-height: 1.45;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; margin-bottom: 10px;
        }

        .card-attrs { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
        .attr-chip {
            display: flex; align-items: center; gap: 3px;
            padding: 2px 8px; border-radius: 8px; font-size: 0.67rem; font-weight: 500;
            border: 1px solid;
        }
        .attr-color { background: rgba(37,99,235,0.06); border-color: rgba(37,99,235,0.2); color: var(--accent); }
        .attr-size  { background: rgba(16,185,129,0.06); border-color: rgba(16,185,129,0.25); color: #059669; }
        .attr-cat   { background: rgba(17,24,39,0.04); border-color: var(--border2); color: var(--text-muted); }

        .card-footer { display: flex; align-items: center; justify-content: space-between; }
        .p-price {
            font-size: 1rem; font-weight: 800;
            background: linear-gradient(135deg, #059669, #10B981);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* Similarity arc */
        .sim-arc-wrap { display: flex; align-items: center; gap: 6px; }
        .sim-arc {
            position: relative; width: 32px; height: 32px;
        }
        .sim-arc svg { transform: rotate(-90deg); }
        .sim-arc circle { fill: none; stroke-width: 3; stroke-linecap: round; }
        .sim-arc .track { stroke: rgba(17,24,39,0.08); }
        .sim-arc .fill  { stroke: var(--accent); transition: stroke-dashoffset 0.8s ease; }
        .sim-arc-val {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.55rem; font-weight: 800; color: var(--accent);
        }

        /* Empty state */
        .empty-state {
            flex: 1; display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 10px; padding: 30px 20px; text-align: center;
        }
        .empty-state-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: var(--surface2); border: 1px solid var(--border2);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; opacity: 0.7; margin-bottom: 4px;
        }
        .empty-state h3 { font-size: 0.85rem; font-weight: 700; color: var(--text-2); }
        .empty-state p  { font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; max-width: 200px; }

        /* ── Toast notifications ────────────────────────────────────────── */
        #toast-container {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            z-index: 9999; display: flex; flex-direction: column; gap: 8px; align-items: center;
            pointer-events: none;
        }
        .toast {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 12px;
            font-size: 0.8rem; font-weight: 600; color: var(--text);
            backdrop-filter: blur(16px); pointer-events: auto;
            animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        }
        .toast.success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); }
        .toast.error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  }
        .toast.info    { background: rgba(37,99,235,0.1);  border: 1px solid rgba(37,99,235,0.3);  }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(12px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes toastOut {
            to { opacity: 0; transform: translateY(8px) scale(0.95); }
        }

        /* ── Divider between message groups ─────────────────────────────── */
        .msg-divider {
            display: flex; align-items: center; gap: 10px;
            margin: 12px 0; color: var(--text-muted); font-size: 0.68rem;
        }
        .msg-divider::before, .msg-divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }

        /* ── Streaming cursor ─────────────────────────────────────────────── */
        .streaming-cursor {
            display: inline-block;
            width: 2px; height: 1em;
            background: var(--accent);
            margin-left: 1px;
            vertical-align: text-bottom;
            animation: blink-cursor 0.7s step-end infinite;
        }
        @keyframes blink-cursor {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0; }
        }
        .bubble.streaming { min-width: 36px; }

        /* ── Responsive ─────────────────────────────────────────────────── */
        @media (max-width: 900px) {
            .main { grid-template-columns: 1fr; }
            .products-panel { display: none; }
        }
        @media (max-width: 600px) {
            .app { padding: 0 10px; }
            .model-badge { display: none; }
            .bubble { font-size: 0.86rem; }
        }
    </style>
</head>
<body>

<div id="toast-container"></div>

<div class="app">
    <!-- ── Header ── -->
    <header>
        <div class="logo">
            <div class="logo-icon">🛍️</div>
            <div class="logo-text">
                <h1>ShopAI Assistant</h1>
                <div class="tagline">
                    <span class="status-dot"></span>
                    Powered by OpenRouter · RAG Pipeline
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="model-badge">
                🤖 <span>GPT-4o-mini</span> · text-embedding-3-small
            </div>
            @guest
                <a href="{{ route('login') }}" class="btn-auth btn-login">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Login
                </a>
                <a href="{{ route('register') }}" class="btn-auth btn-register">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Register
                </a>
            @endguest
            @auth
                <a href="{{ route('dashboard') }}" class="btn-auth btn-login">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>
            @endauth
            <button class="btn-clear" onclick="clearChat()" title="Clear conversation">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                Clear
            </button>
        </div>
    </header>

    <!-- ── Main ── -->
    <div class="main">

        <!-- Chat Panel -->
        <div class="chat-panel">
            <div class="chat-header">
                <div class="chat-header-icon">💬</div>
                <div>
                    <h2>Shopping Chat</h2>
                    <p>Describe your style and I'll find the perfect match</p>
                </div>
            </div>

            <div class="messages" id="messages">
                <div class="welcome" id="welcomeMsg">
                    <div class="welcome-orb">🛍️</div>
                    <h2>What are you shopping for?</h2>
                    <p>Describe your style, size, color preference, budget, or occasion — I'll find the perfect products using AI.</p>
                    <div class="welcome-features">
                        <div class="feature-pill">✨ Smart Recommendations</div>
                        <div class="feature-pill">🧠 Intent Detection</div>
                        <div class="feature-pill">🔍 Vector Search</div>
                        <div class="feature-pill">💬 Conversational</div>
                    </div>
                </div>
            </div>

            <div class="suggestions-wrap" id="suggestionsWrap">
                <button class="chip" onclick="sendSuggestion(this)"><span class="chip-icon">👕</span> Black hoodie, large</button>
                <button class="chip" onclick="sendSuggestion(this)"><span class="chip-icon">👔</span> Formal wear male</button>
                <button class="chip" onclick="sendSuggestion(this)"><span class="chip-icon">👟</span> Casual shoes size 42</button>
                <button class="chip" onclick="sendSuggestion(this)"><span class="chip-icon">💰</span> Under $30 clothes</button>
                <button class="chip" onclick="sendSuggestion(this)"><span class="chip-icon">🎯</span> Outfit for 21yo male</button>
            </div>

            <div class="input-wrap">
                <div class="input-box">
                    <textarea id="messageInput" rows="1"
                        placeholder="e.g. I'm 21, love black streetwear, size L, budget $60…"
                        onkeydown="handleKey(event)"
                        oninput="onInputChange(this)"></textarea>
                    <div class="input-actions">
                        <span class="char-count" id="charCount"></span>
                        <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send (Enter)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="input-hint">Press <kbd style="background:var(--surface3);padding:1px 5px;border-radius:4px;border:1px solid var(--border2);font-size:0.64rem;">Enter</kbd> to send &nbsp;·&nbsp; <kbd style="background:var(--surface3);padding:1px 5px;border-radius:4px;border:1px solid var(--border2);font-size:0.64rem;">Shift+Enter</kbd> for new line</p>
            </div>
        </div>

        <!-- Products Sidebar -->
        <div class="products-panel">
            <div class="panel-header">
                <div class="panel-header-top">
                    <div class="panel-title">
                        <span class="panel-title-icon">✦</span>
                        Recommendations
                    </div>
                    <div class="product-count-badge" id="productCount">0 found</div>
                </div>
                <div class="panel-subtitle">Top matches ranked by AI similarity score</div>
            </div>

            <div class="intent-row" id="intentRow" style="display:none;"></div>

            <div class="products-list" id="productsList">
                <div class="empty-state">
                    <div class="empty-state-icon">🛒</div>
                    <h3>No recommendations yet</h3>
                    <p>Send a message to get AI-powered product recommendations tailored to you.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let isLoading   = false;
    let msgCount    = 0;

    // Category → emoji map
    const catEmoji = {
        'casual clothing': '👕', 'formal wear': '👔', 'shoes': '👟',
        'outerwear': '🧥', 'accessories': '👜', 'clothing': '👗',
    };
    function getEmoji(category = '') {
        const key = category.toLowerCase();
        for (const [k, v] of Object.entries(catEmoji)) {
            if (key.includes(k)) return v;
        }
        return '🏷️';
    }

    // ── Auto-resize textarea ─────────────────────────────────────────────
    function onInputChange(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 110) + 'px';
        const len = el.value.length;
        const cc  = document.getElementById('charCount');
        cc.textContent = len > 0 ? len : '';
        cc.className = 'char-count' + (len > 850 ? ' near-limit' : '');
    }

    function handleKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    }

    function sendSuggestion(el) {
        const input = document.getElementById('messageInput');
        input.value = el.textContent.replace(/^[^\w]+/, '').trim();
        onInputChange(input);
        sendMessage();
    }

    // ── Main send (streaming) ────────────────────────────────────────────
    async function sendMessage() {
        if (isLoading) return;
        const input   = document.getElementById('messageInput');
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        input.style.height = 'auto';
        document.getElementById('charCount').textContent = '';
        isLoading = true;
        setDisabled(true);

        const welcome = document.getElementById('welcomeMsg');
        if (welcome) {
            welcome.style.animation = 'fadeUp 0.2s ease reverse';
            setTimeout(() => welcome.remove(), 180);
        }

        const suggestions = document.getElementById('suggestionsWrap');
        if (suggestions && msgCount === 0) suggestions.style.display = 'none';
        if (msgCount > 0) appendDivider();
        msgCount++;

        appendMessage('user', message);
        scrollToBottom();

        // Create the AI bubble immediately — blinking cursor IS the loading indicator
        const aiDiv   = appendStreamingBubble();
        let   fullText = '';
        scrollToBottom();

        // Fallback: if browser doesn't support ReadableStream, use non-streaming endpoint
        if (!window.ReadableStream || !window.TextDecoder) {
            return sendMessageFallback(message, aiDiv);
        }

        try {
            const res = await fetch('/api/chat/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept':       'text/event-stream',
                },
                body: JSON.stringify({ message }),
            });

            if (!res.ok || !res.body) {
                const err = await res.json().catch(() => ({}));
                finalizeStreamingBubble(aiDiv, '⚠️ ' + (err.error || 'Something went wrong. Please try again.'));
                showToast('Request failed. Please try again.', 'error');
            } else {
                const reader    = res.body.getReader();
                const decoder   = new TextDecoder();
                let   buffer    = '';
                let   lastEvent = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const parts = buffer.split('\n');
                    buffer = parts.pop(); // keep the last incomplete line

                    for (const line of parts) {
                        if (line.startsWith('event: ')) {
                            lastEvent = line.slice(7).trim();
                        } else if (line.startsWith('data: ')) {
                            let data;
                            try { data = JSON.parse(line.slice(6)); } catch { continue; }

                            if (lastEvent === 'token') {
                                fullText += data.text;
                                updateStreamingBubble(aiDiv, fullText);
                                scrollToBottom();
                            } else if (lastEvent === 'products') {
                                renderProducts(data.products || [], {});
                            } else if (lastEvent === 'intent') {
                                renderIntentFromSSE(data);
                            } else if (lastEvent === 'toast') {
                                showToast(data.message, data.type || 'info');
                            } else if (lastEvent === 'error') {
                                finalizeStreamingBubble(aiDiv, '⚠️ ' + (data.message || 'Error'));
                            }
                        }
                    }
                }

                if (!fullText) finalizeStreamingBubble(aiDiv, 'No response generated.');
                else          finalizeStreamingBubble(aiDiv, fullText);
            }
        } catch (e) {
            finalizeStreamingBubble(aiDiv, '⚠️ Network error. Please check your connection.');
            showToast('Network error', 'error');
        }

        isLoading = false;
        setDisabled(false);
        scrollToBottom();
        input.focus();
    }

    // Fallback for browsers without ReadableStream
    async function sendMessageFallback(message, aiDiv) {
        try {
            const res = await fetch('/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ message }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                finalizeStreamingBubble(aiDiv, '⚠️ ' + (err.error || 'Something went wrong.'));
            } else {
                const data = await res.json();
                finalizeStreamingBubble(aiDiv, data.reply || 'No response generated.');
                renderProducts(data.products || [], data.intent || {});
                if ((data.products || []).length > 0) {
                    showToast(`Found ${data.products.length} matching product${data.products.length > 1 ? 's' : ''}`, 'success');
                }
            }
        } catch (e) {
            finalizeStreamingBubble(aiDiv, '⚠️ Network error.');
        }
        isLoading = false;
        setDisabled(false);
        scrollToBottom();
        document.getElementById('messageInput').focus();
    }

    // ── Message bubble ───────────────────────────────────────────────────
    function appendMessage(role, text) {
        const container = document.getElementById('messages');
        const isUser    = role === 'user';
        const time      = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const div = document.createElement('div');
        div.className = `msg ${isUser ? 'user' : 'ai'} gap`;

        const avatarContent = isUser
            ? '<span style="font-size:11px;font-weight:700;color:#fff">You</span>'
            : '🤖';

        div.innerHTML = `
            <div class="avatar">${avatarContent}</div>
            <div class="bubble-wrap">
                <div class="bubble">${escapeHtml(text).replace(/\n/g, '<br>')}</div>
                <div class="bubble-meta">
                    <span class="bubble-time">${time}</span>
                </div>
            </div>`;

        container.appendChild(div);
        return div;
    }

    // ── Streaming bubble helpers ─────────────────────────────────────────
    function appendStreamingBubble() {
        const container = document.getElementById('messages');
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const div  = document.createElement('div');
        div.className = 'msg ai gap';
        div.innerHTML = `
            <div class="avatar">🤖</div>
            <div class="bubble-wrap">
                <div class="bubble streaming"><span class="streaming-cursor"></span></div>
                <div class="bubble-meta"><span class="bubble-time">${time}</span></div>
            </div>`;
        container.appendChild(div);
        return div;
    }

    function updateStreamingBubble(div, text) {
        const bubble = div.querySelector('.bubble');
        bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>') +
                           '<span class="streaming-cursor"></span>';
    }

    function finalizeStreamingBubble(div, text) {
        const bubble = div.querySelector('.bubble');
        bubble.classList.remove('streaming');
        bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
    }

    // ── Intent pills from SSE ────────────────────────────────────────────
    function renderIntentFromSSE(data) {
        const intentRow = document.getElementById('intentRow');
        const pills = [];
        if (data.color)              pills.push({ key: '🎨 Color',  val: data.color });
        if (data.size)               pills.push({ key: '📏 Size',   val: data.size });
        if (data.category)           pills.push({ key: '🏷️ Type',   val: data.category });
        if (data.gender)             pills.push({ key: '👤 Gender', val: data.gender });
        if (data.price_range?.max)   pills.push({ key: '💰 Max',    val: '$' + data.price_range.max });

        if (pills.length) {
            intentRow.innerHTML = pills.map(p =>
                `<div class="intent-pill"><span class="key">${escapeHtml(p.key)}:</span><span class="val">${escapeHtml(p.val)}</span></div>`
            ).join('');
            intentRow.style.display = 'flex';
        }
    }

    // ── Typing indicator ─────────────────────────────────────────────────
    let typingCounter = 0;
    function appendTyping() {
        const id  = 'typing-' + (++typingCounter);
        const div = document.createElement('div');
        div.className = 'msg ai gap typing';
        div.id = id;
        div.innerHTML = `
            <div class="avatar">🤖</div>
            <div class="bubble-wrap">
                <div class="bubble">
                    <div class="dots"><span></span><span></span><span></span></div>
                    <span class="typing-label">Searching products...</span>
                </div>
            </div>`;
        document.getElementById('messages').appendChild(div);
        return id;
    }
    function removeTyping(id) { document.getElementById(id)?.remove(); }

    // ── Message divider ──────────────────────────────────────────────────
    function appendDivider() {
        const div = document.createElement('div');
        div.className = 'msg-divider';
        div.textContent = 'New search';
        document.getElementById('messages').appendChild(div);
    }

    // ── Render products ──────────────────────────────────────────────────
    function renderProducts(products, intent) {
        const list  = document.getElementById('productsList');
        const badge = document.getElementById('productCount');
        const intentRow = document.getElementById('intentRow');

        badge.textContent = products.length
            ? `${products.length} found`
            : '0 found';

        // Render intent pills
        const pills = [];
        if (intent.color)    pills.push({ key: '🎨 Color', val: intent.color });
        if (intent.size)     pills.push({ key: '📏 Size',  val: intent.size });
        if (intent.category) pills.push({ key: '🏷️ Type',  val: intent.category });
        if (intent.gender)   pills.push({ key: '👤 Gender', val: intent.gender });
        if (intent.price_range?.max) pills.push({ key: '💰 Max', val: '$' + intent.price_range.max });

        if (pills.length) {
            intentRow.innerHTML = pills.map(p =>
                `<div class="intent-pill"><span class="key">${escapeHtml(p.key)}:</span><span class="val">${escapeHtml(p.val)}</span></div>`
            ).join('');
            intentRow.style.display = 'flex';
        } else {
            intentRow.style.display = 'none';
        }

        if (!products.length) {
            list.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3>No matches found</h3>
                    <p>Try broadening your query or using different keywords.</p>
                </div>`;
            return;
        }

        const CIRCUMFERENCE = 2 * Math.PI * 13; // r=13

        list.innerHTML = products.map((p, i) => {
            const rank  = i + 1;
            const sim   = p.similarity_score ? Math.round(p.similarity_score * 100) : 0;
            const price = parseFloat(p.price || 0).toFixed(2);
            const emoji = getEmoji(p.category || '');
            const dashOffset = CIRCUMFERENCE - (sim / 100) * CIRCUMFERENCE;

            const rankClass = rank <= 3 ? `rank-${rank}` : 'rank-n';
            const rankLabel = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : `#${rank}`;

            const delay = i * 60;

            return `
                <div class="product-card" style="animation-delay:${delay}ms">
                    <div class="card-rank ${rankClass}">${rankLabel}</div>
                    <div class="card-top">
                        <div class="card-emoji">${emoji}</div>
                        <div class="card-info">
                            <div class="p-name" title="${escapeHtml(p.name || '')}">${escapeHtml(p.name || '')}</div>
                            <div class="p-cat">${escapeHtml(p.category || '')}</div>
                        </div>
                    </div>
                    <div class="p-desc">${escapeHtml(p.description || '')}</div>
                    <div class="card-attrs">
                        ${p.color    ? `<span class="attr-chip attr-color">🎨 ${escapeHtml(p.color)}</span>` : ''}
                        ${p.size     ? `<span class="attr-chip attr-size">📏 ${escapeHtml(p.size)}</span>` : ''}
                        ${p.category ? `<span class="attr-chip attr-cat">${escapeHtml(p.category)}</span>` : ''}
                    </div>
                    <div class="card-footer">
                        <span class="p-price">$${price}</span>
                        ${sim > 0 ? `
                        <div class="sim-arc-wrap" title="AI similarity: ${sim}%">
                            <div class="sim-arc">
                                <svg width="32" height="32" viewBox="0 0 32 32">
                                    <circle class="track" cx="16" cy="16" r="13" />
                                    <circle class="fill" cx="16" cy="16" r="13"
                                        stroke-dasharray="${CIRCUMFERENCE}"
                                        stroke-dashoffset="${dashOffset}" />
                                </svg>
                                <div class="sim-arc-val">${sim}%</div>
                            </div>
                        </div>` : ''}
                    </div>
                </div>`;
        }).join('');
    }

    // ── Clear chat ───────────────────────────────────────────────────────
    async function clearChat() {
        await fetch('/api/chat/history', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });

        msgCount = 0;

        document.getElementById('messages').innerHTML = `
            <div class="welcome" id="welcomeMsg">
                <div class="welcome-orb">🛍️</div>
                <h2>What are you shopping for?</h2>
                <p>Describe your style, size, color preference, budget, or occasion — I'll find the perfect products using AI.</p>
                <div class="welcome-features">
                    <div class="feature-pill">✨ Smart Recommendations</div>
                    <div class="feature-pill">🧠 Intent Detection</div>
                    <div class="feature-pill">🔍 Vector Search</div>
                    <div class="feature-pill">💬 Conversational</div>
                </div>
            </div>`;

        document.getElementById('productsList').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🛒</div>
                <h3>No recommendations yet</h3>
                <p>Send a message to get AI-powered product recommendations tailored to you.</p>
            </div>`;

        document.getElementById('productCount').textContent  = '0 found';
        document.getElementById('intentRow').style.display   = 'none';
        document.getElementById('suggestionsWrap').style.display = '';

        showToast('Conversation cleared', 'info');
    }

    // ── Toast ────────────────────────────────────────────────────────────
    function showToast(msg, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        const icons = { success: '✓', error: '✕', info: 'ℹ' };
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<span>${icons[type]}</span> ${escapeHtml(msg)}`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.25s ease forwards';
            setTimeout(() => toast.remove(), 250);
        }, duration);
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    function setDisabled(d) {
        document.getElementById('sendBtn').disabled       = d;
        document.getElementById('messageInput').disabled  = d;
    }
    function scrollToBottom() {
        const m = document.getElementById('messages');
        requestAnimationFrame(() => m.scrollTop = m.scrollHeight);
    }
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    document.getElementById('messageInput').focus();
</script>
</body>
</html>
