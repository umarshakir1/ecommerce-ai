/* ShopAI Platform — Chat Widget
 * Communicates via WordPress AJAX proxy — API key is never exposed to the browser.
 * global shopaiConfig
 */
(function () {
    'use strict';

    var cfg = window.shopaiConfig || {};

    // ── Config defaults ────────────────────────────────────────────────────
    var AJAX_URL  = cfg.ajax_url  || '/wp-admin/admin-ajax.php';
    var NONCE     = cfg.nonce     || '';
    var THEME     = cfg.theme     || 'blue';
    var TITLE     = cfg.title     || 'ShopAI Assistant';
    var SHOP_URL  = cfg.shop_url  || '/shop';

    var SUGGESTIONS = [
        '🆕 Show me new arrivals',
        '🎁 Gift ideas under $50',
        '👕 Black hoodies in large',
        '👟 Best selling shoes',
    ];

    // ── Persistent session ID (maintains conversation context) ─────────────
    var SESSION_KEY = 'shopai_session_id';
    var SESSION_ID  = localStorage.getItem(SESSION_KEY);
    if (!SESSION_ID) {
        SESSION_ID = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        localStorage.setItem(SESSION_KEY, SESSION_ID);
    }

    // ── State ──────────────────────────────────────────────────────────────
    var isOpen       = false;
    var isTyping     = false;
    var hasGreeted   = false;
    var msgCount     = 0;

    // ── Build DOM ──────────────────────────────────────────────────────────
    function buildWidget() {
        // Root container
        var root = el('div', { id: 'shopai-widget' });
        root.setAttribute('data-shopai-theme', THEME);
        document.body.appendChild(root);

        // ── FAB button ─────────────────────────────────────────────────────
        var fab = el('button', { id: 'shopai-fab', 'aria-label': 'Open ShopAI chat', type: 'button' });
        fab.innerHTML =
            '<span class="shopai-fab-open">' + iconChat() + '</span>' +
            '<span class="shopai-fab-close">' + iconX() + '</span>' +
            '<span id="shopai-badge"></span>';
        fab.addEventListener('click', toggleChat);
        root.appendChild(fab);

        // ── Chat window ────────────────────────────────────────────────────
        var win = el('div', { id: 'shopai-window', role: 'dialog', 'aria-label': 'ShopAI Chat', 'aria-modal': 'true' });

        // Header
        win.innerHTML =
            '<div id="shopai-header">' +
                '<div id="shopai-header-left">' +
                    '<div class="shopai-avatar">🤖</div>' +
                    '<div class="shopai-header-info">' +
                        '<strong>' + escHtml(TITLE) + '</strong>' +
                        '<span><span class="shopai-online-dot"></span>Online · AI Shopping Assistant</span>' +
                    '</div>' +
                '</div>' +
                '<button id="shopai-close-btn" type="button" aria-label="Close chat">' + iconX() + '</button>' +
            '</div>' +

            '<div id="shopai-messages" role="log" aria-live="polite" aria-label="Chat messages"></div>' +

            '<div id="shopai-typing" aria-live="polite" aria-label="AI is typing">' +
                '<div class="shopai-row-avatar">🤖</div>' +
                '<div class="shopai-bubble">' +
                    '<span class="shopai-dot"></span>' +
                    '<span class="shopai-dot"></span>' +
                    '<span class="shopai-dot"></span>' +
                '</div>' +
            '</div>' +

            '<div id="shopai-suggestions"></div>' +

            '<div id="shopai-input-area">' +
                '<input id="shopai-input" type="text" placeholder="Ask about products…" autocomplete="off" maxlength="500">' +
                '<button id="shopai-send-btn" type="button" aria-label="Send message" disabled>' + iconSend() + '</button>' +
            '</div>';

        root.appendChild(win);

        // ── Event bindings ─────────────────────────────────────────────────
        document.getElementById('shopai-close-btn').addEventListener('click', toggleChat);

        var input   = document.getElementById('shopai-input');
        var sendBtn = document.getElementById('shopai-send-btn');

        input.addEventListener('input', function () {
            sendBtn.disabled = input.value.trim().length === 0;
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !sendBtn.disabled) {
                e.preventDefault();
                sendMessage();
            }
        });
        sendBtn.addEventListener('click', sendMessage);

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (isOpen && !root.contains(e.target)) {
                closeChat();
            }
        });

        // Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) closeChat();
        });
    }

    // ── Toggle / open / close ──────────────────────────────────────────────
    function toggleChat() {
        isOpen ? closeChat() : openChat();
    }

    function openChat() {
        isOpen = true;
        document.getElementById('shopai-window').classList.add('shopai-open');
        document.getElementById('shopai-fab').classList.add('shopai-open');
        hideBadge();

        // Focus input
        setTimeout(function () {
            document.getElementById('shopai-input').focus();
        }, 240);

        // Show welcome + suggestions on first open
        if (!hasGreeted) {
            hasGreeted = true;
            renderWelcome();
            renderSuggestions();
        }
    }

    function closeChat() {
        isOpen = false;
        document.getElementById('shopai-window').classList.remove('shopai-open');
        document.getElementById('shopai-fab').classList.remove('shopai-open');
    }

    // ── Send message ───────────────────────────────────────────────────────
    function sendMessage() {
        var input = document.getElementById('shopai-input');
        var text  = input.value.trim();
        if (!text || isTyping) return;

        // Clear suggestions after first message
        var sugEl = document.getElementById('shopai-suggestions');
        if (sugEl) sugEl.innerHTML = '';

        input.value = '';
        document.getElementById('shopai-send-btn').disabled = true;

        appendUserMessage(text);
        showTyping();

        // POST via WordPress AJAX proxy
        var body = new FormData();
        body.append('action',     'shopai_chat');
        body.append('nonce',      NONCE);
        body.append('message',    text);
        body.append('session_id', SESSION_ID);

        fetch(AJAX_URL, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                hideTyping();
                if (data.success && data.data) {
                    appendAIMessage(data.data.reply || '', data.data.products || []);
                } else {
                    var errMsg = (data.data && data.data.message)
                        ? data.data.message
                        : 'Sorry, something went wrong. Please try again.';
                    appendAIMessage(errMsg, []);
                }
            })
            .catch(function () {
                hideTyping();
                appendAIMessage('I couldn\'t reach the server. Please check your connection and try again.', []);
            });
    }

    // ── Render helpers ─────────────────────────────────────────────────────
    function renderWelcome() {
        var msgs = document.getElementById('shopai-messages');
        var div  = el('div', { class: 'shopai-welcome' });
        div.innerHTML =
            '<span class="shopai-welcome-icon">👋</span>' +
            '<strong>Hi! I\'m your AI Shopping Assistant</strong>' +
            '<p>Tell me what you\'re looking for and I\'ll find the perfect products for you.</p>';
        msgs.appendChild(div);
        scrollBottom();
    }

    function renderSuggestions() {
        var container = document.getElementById('shopai-suggestions');
        if (!container) return;
        SUGGESTIONS.forEach(function (text) {
            var btn = el('button', { class: 'shopai-suggestion-btn', type: 'button' });
            btn.textContent = text;
            btn.addEventListener('click', function () {
                document.getElementById('shopai-input').value = text;
                sendMessage();
            });
            container.appendChild(btn);
        });
    }

    function appendUserMessage(text) {
        var msgs = document.getElementById('shopai-messages');
        var row  = el('div', { class: 'shopai-msg-row shopai-row-user' });
        var msg  = el('div', { class: 'shopai-msg' });
        msg.innerHTML =
            '<div class="shopai-bubble">' + escHtml(text) + '</div>' +
            '<span class="shopai-msg-time">' + timeNow() + '</span>';
        row.appendChild(msg);
        msgs.appendChild(row);
        scrollBottom();
        msgCount++;
    }

    function appendAIMessage(reply, products) {
        var msgs = document.getElementById('shopai-messages');

        // Text bubble with AI avatar
        if (reply) {
            var row = el('div', { class: 'shopai-msg-row shopai-row-ai' });
            var ava = el('div', { class: 'shopai-row-avatar' });
            ava.textContent = '🤖';
            var msg = el('div', { class: 'shopai-msg' });
            msg.innerHTML =
                '<div class="shopai-bubble">' + formatReply(reply) + '</div>' +
                '<span class="shopai-msg-time">' + timeNow() + '</span>';
            row.appendChild(ava);
            row.appendChild(msg);
            msgs.appendChild(row);
        }

        // Product cards (indented to align with AI bubble)
        if (products && products.length > 0) {
            var productWrap = el('div', { class: 'shopai-products-wrap' });
            productWrap.innerHTML = '<div class="shopai-products-label">✨ Matching Products (' + products.length + ')</div>';
            var scroll = el('div', { class: 'shopai-products-scroll' });

            products.forEach(function (product) {
                scroll.appendChild(buildProductCard(product));
            });

            productWrap.appendChild(scroll);
            msgs.appendChild(productWrap);
        }

        scrollBottom();
        msgCount++;
        showBadge();
    }

    function buildProductCard(product) {
        var card   = el('div', { class: 'shopai-product-card' });
        var name   = product.name        || 'Product';
        var price  = product.price       != null ? parseFloat(product.price).toFixed(2) : null;
        var image  = product.image       || '';
        var color  = product.color       || '';
        var size   = product.size        || '';
        var cat    = product.category    || '';

        // Meta line — always show category, plus color/size if available
        var metaParts = [];
        if (cat) metaParts.push(cat);
        if (color) metaParts.push(color);
        if (size)  metaParts.push('Size: ' + size);
        var meta = metaParts.join(' · ');

        // Search URL for "View Product" — links to WooCommerce search
        var searchUrl = product.url || (SHOP_URL + '?s=' + encodeURIComponent(name));

        // Image with fallback to placeholder on error
        var imgHtml = image
            ? '<img class="shopai-product-img" src="' + escHtml(image) + '" alt="" loading="lazy" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="shopai-product-img-placeholder" style="display:none">🛎️</div>'
            : '<div class="shopai-product-img-placeholder">�️</div>';

        card.innerHTML =
            imgHtml +
            '<div class="shopai-product-body">' +
                '<div class="shopai-product-name">' + escHtml(name) + '</div>' +
                (meta  ? '<div class="shopai-product-meta">' + escHtml(meta) + '</div>' : '') +
                (price ? '<div class="shopai-product-price">$' + escHtml(price) + '</div>' : '') +
                '<a class="shopai-product-btn" href="' + escHtml(searchUrl) + '" target="_blank" rel="noopener">View Product →</a>' +
            '</div>';

        return card;
    }

    // ── Typing indicator ───────────────────────────────────────────────────
    function showTyping() {
        isTyping = true;
        var t = document.getElementById('shopai-typing');
        if (t) { t.style.display = 'flex'; scrollBottom(); }
    }

    function hideTyping() {
        isTyping = false;
        var t = document.getElementById('shopai-typing');
        if (t) t.style.display = 'none';
    }

    // ── Unread badge ───────────────────────────────────────────────────────
    function showBadge() {
        if (isOpen) return;
        var b = document.getElementById('shopai-badge');
        if (b) { b.textContent = '!'; b.style.display = 'flex'; }
    }

    function hideBadge() {
        var b = document.getElementById('shopai-badge');
        if (b) b.style.display = 'none';
    }

    // ── Utilities ──────────────────────────────────────────────────────────
    function scrollBottom() {
        var msgs = document.getElementById('shopai-messages');
        if (msgs) {
            setTimeout(function () { msgs.scrollTop = msgs.scrollHeight; }, 30);
        }
    }

    function timeNow() {
        var d = new Date();
        var h = d.getHours();
        var m = d.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }

    function formatReply(text) {
        return escHtml(text)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*([^*\n]+)\*/g, '<em>$1</em>')
            .replace(/(\n\s*){3,}/g, '\n\n')
            .replace(/\n/g, '<br>');
    }

    function el(tag, attrs) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') {
                    node.className = attrs[k];
                } else {
                    node.setAttribute(k, attrs[k]);
                }
            });
        }
        return node;
    }

    // ── SVG icons ──────────────────────────────────────────────────────────
    function iconChat() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    }

    function iconX() {
        return '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    }

    function iconSend() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
    }

    // ── Init ───────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildWidget);
    } else {
        buildWidget();
    }

}());
