/* ShopAI Platform — Admin JavaScript */
/* global shopaiAdmin, jQuery */

(function ($) {
    'use strict';

    var cfg = shopaiAdmin || {};

    // ── Toggle API key visibility ──────────────────────────────────────────
    $('#shopai-toggle-key').on('click', function () {
        var input = $('#shopai_api_key');
        var icon  = $(this).find('.dashicons');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // ── Test connection ────────────────────────────────────────────────────
    $('#shopai-test-btn').on('click', function () {
        var btn      = $(this);
        var statusEl = $('#shopai-conn-status');
        var apiKey   = $('#shopai_api_key').val().trim();
        var baseUrl  = $('#shopai_api_base_url').val().trim();

        if (!apiKey || !baseUrl) {
            showStatus(statusEl, 'error', '⚠ Please fill in both the Platform URL and API Key first.');
            return;
        }

        btn.prop('disabled', true)
           .html('<span class="shopai-spinner"></span> ' + cfg.strings.testing);

        $.ajax({
            url:    cfg.ajax_url,
            method: 'POST',
            data: {
                action:   'shopai_test_connection',
                nonce:    cfg.nonce,
                api_key:  apiKey,
                base_url: baseUrl,
            },
            success: function (res) {
                if (res.success) {
                    showStatus(statusEl, 'ok', '✓ ' + res.data.message);
                    $('#shopai-conn-badge')
                        .text('● Connected')
                        .removeClass('shopai-badge-gray shopai-badge-red')
                        .addClass('shopai-badge-green');
                } else {
                    showStatus(statusEl, 'error', '✗ ' + res.data.message);
                    $('#shopai-conn-badge')
                        .text('○ Not Connected')
                        .removeClass('shopai-badge-green')
                        .addClass('shopai-badge-gray');
                }
            },
            error: function () {
                showStatus(statusEl, 'error', '✗ Network error. Please try again.');
            },
            complete: function () {
                btn.prop('disabled', false)
                   .html('<span class="dashicons dashicons-admin-generic"></span> ' + cfg.strings.test_btn);
            },
        });
    });

    // ── Sync products ──────────────────────────────────────────────────────
    $('#shopai-sync-btn').on('click', function () {
        if (!window.confirm(cfg.strings.confirm_sync)) {
            return;
        }

        var btn      = $(this);
        var statusEl = $('#shopai-sync-status');

        btn.prop('disabled', true)
           .html('<span class="shopai-spinner"></span> ' + cfg.strings.syncing);

        showStatus(statusEl, 'info', 'Syncing products — this may take a moment…');

        $.ajax({
            url:    cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'shopai_sync_products',
                nonce:  cfg.nonce,
            },
            timeout: 180000, // 3 minutes
            success: function (res) {
                if (res.success) {
                    showStatus(statusEl, 'ok', '✓ ' + res.data.message);
                    // Hard-reload to refresh last-sync status block, bypassing LiteSpeed page cache
                    setTimeout(function () {
                        var base = window.location.pathname + window.location.search;
                        var sep  = base.indexOf('?') === -1 ? '?' : '&';
                        window.location.href = base + sep + '_shopai=' + Date.now();
                    }, 1800);
                } else {
                    showStatus(statusEl, 'error', '✗ ' + res.data.message);
                }
            },
            error: function (xhr, status) {
                var msg = status === 'timeout'
                    ? 'Request timed out. Products may still be syncing in the background.'
                    : 'Network error. Please try again.';
                showStatus(statusEl, 'error', '✗ ' + msg);
            },
            complete: function () {
                btn.prop('disabled', false)
                   .html('<span class="dashicons dashicons-update"></span> ' + cfg.strings.sync_btn);
            },
        });
    });

    // ── Auto-sync toggle ───────────────────────────────────────────────────
    $('#shopai_auto_sync_enabled').on('change', function () {
        if ($(this).is(':checked')) {
            $('#shopai-auto-sync-options').removeClass('shopai-hidden');
        } else {
            $('#shopai-auto-sync-options').addClass('shopai-hidden');
        }
    });

    // ── Settings saved notice ──────────────────────────────────────────────
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('settings-updated') === 'true') {
        var notice = $('<div class="shopai-status-msg shopai-ok" style="display:flex;margin-bottom:16px;">✓ Settings saved successfully.</div>');
        $('.shopai-wrap').prepend(notice);
        setTimeout(function () { notice.fadeOut(400); }, 4000);
    }

    // ── Helper: show a status message ──────────────────────────────────────
    function showStatus(el, type, msg) {
        el.removeClass('shopai-ok shopai-error shopai-info')
          .addClass('shopai-' + type)
          .text(msg)
          .show();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIORITY MANAGER
    // ═══════════════════════════════════════════════════════════════════════

    var typeHints = {
        brand:    'e.g. Nike, Adidas, Zara',
        category: 'e.g. shoes, accessories, formal wear',
        size:     'e.g. S, M, L, XL, 42, 44',
        color:    'e.g. black, white, red',
        tag:      'e.g. summer, sale, trending',
    };

    var typeLabels = {
        brand:    '🏷️ Brand',
        category: '📂 Category',
        size:     '📏 Size',
        color:    '🎨 Color',
        tag:      '🔖 Tag',
    };

    // Update value hint when type dropdown changes
    $('#shopai-priority-type').on('change', function () {
        var type = $(this).val();
        var hint = typeHints[type] || '';
        $('#shopai-value-hint').text(hint);
        $('#shopai-priority-value').attr('placeholder', hint.split(',')[0].trim());
    });

    // Boost slider — update bar visualisation and label
    $('#shopai-priority-weight').on('input', function () {
        updateBoostDisplay($(this).val(), '#shopai-boost-bars', '#shopai-weight-label');
    });

    function updateBoostDisplay(val, barsSelector, labelSelector) {
        val = parseFloat(val);
        var bars  = $(barsSelector).find('.shopai-boost-bar');
        var count = Math.round(val * 10);
        bars.each(function (i) {
            $(this).toggleClass('active', i < count);
        });
        var label = val >= 0.8 ? 'High' : (val >= 0.4 ? 'Medium' : 'Low');
        var cls   = val >= 0.8 ? 'shopai-boost-high' : (val >= 0.4 ? 'shopai-boost-medium' : 'shopai-boost-low');
        $(labelSelector)
            .text(label)
            .removeClass('shopai-boost-high shopai-boost-medium shopai-boost-low')
            .addClass(cls);
    }

    // ── Load rules on page load ─────────────────────────────────────────────
    if ($('#shopai-rules-tbody').length) {
        loadPriorityRules();
    }

    function loadPriorityRules() {
        $('#shopai-rules-loading').show();
        $('#shopai-rules-empty').addClass('shopai-hidden');
        $('#shopai-rules-table').addClass('shopai-hidden');

        $.ajax({
            url:    cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'shopai_get_priorities',
                nonce:  cfg.nonce,
            },
            success: function (res) {
                if (res.success) {
                    renderRules(res.data.priorities || []);
                } else {
                    $('#shopai-rules-loading').html(
                        '<span class="shopai-error-text">⚠ ' + (res.data.message || cfg.strings.rules_error) + '</span>'
                    );
                }
            },
            error: function () {
                $('#shopai-rules-loading').html(
                    '<span class="shopai-error-text">⚠ ' + cfg.strings.rules_error + '</span>'
                );
            },
        });
    }

    function renderRules(rules) {
        var tbody  = $('#shopai-rules-tbody').empty();
        var count  = rules.length;

        $('#shopai-rules-loading').hide();
        $('#shopai-rules-count').text(count > 0 ? '(' + count + ')' : '');

        if (count === 0) {
            $('#shopai-rules-empty').removeClass('shopai-hidden');
            $('#shopai-rules-table').addClass('shopai-hidden');
            return;
        }

        $('#shopai-rules-empty').addClass('shopai-hidden');
        $('#shopai-rules-table').removeClass('shopai-hidden');

        $.each(rules, function (i, rule) {
            var weight  = parseFloat(rule.boost_weight);
            var pct     = Math.round(weight * 100);
            var barHtml = buildMiniBarHtml(weight);
            var label   = weight >= 0.8 ? 'High' : (weight >= 0.4 ? 'Medium' : 'Low');
            var cls     = weight >= 0.8 ? 'shopai-boost-high' : (weight >= 0.4 ? 'shopai-boost-medium' : 'shopai-boost-low');

            var row = $(
                '<tr data-id="' + rule.id + '">' +
                    '<td><span class="shopai-type-badge">' + (typeLabels[rule.attribute_type] || rule.attribute_type) + '</span></td>' +
                    '<td class="shopai-rule-value">' + escHtml(rule.attribute_value) + '</td>' +
                    '<td>' +
                        '<div class="shopai-rule-boost">' +
                            barHtml +
                            '<span class="shopai-boost-badge ' + cls + '">' + label + ' (' + pct + '%)</span>' +
                        '</div>' +
                    '</td>' +
                    '<td>' +
                        '<input type="range" class="shopai-slider shopai-row-slider" ' +
                            'min="0.1" max="1.0" step="0.1" value="' + weight + '" ' +
                            'data-id="' + rule.id + '">' +
                    '</td>' +
                    '<td>' +
                        '<button type="button" class="shopai-btn shopai-btn-danger shopai-delete-rule" data-id="' + rule.id + '">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            );
            tbody.append(row);
        });
    }

    function buildMiniBarHtml(weight) {
        var count = Math.round(weight * 10);
        var html  = '<div class="shopai-mini-bars">';
        for (var i = 0; i < 10; i++) {
            html += '<span class="shopai-boost-bar' + (i < count ? ' active' : '') + '"></span>';
        }
        html += '</div>';
        return html;
    }

    // ── Row slider: live update on change ───────────────────────────────────
    $(document).on('change', '.shopai-row-slider', function () {
        var slider  = $(this);
        var id      = parseInt(slider.data('id'), 10);
        var weight  = parseFloat(slider.val());
        var row     = slider.closest('tr');

        // Optimistic UI update
        var label = weight >= 0.8 ? 'High' : (weight >= 0.4 ? 'Medium' : 'Low');
        var cls   = weight >= 0.8 ? 'shopai-boost-high' : (weight >= 0.4 ? 'shopai-boost-medium' : 'shopai-boost-low');
        var pct   = Math.round(weight * 100);
        row.find('.shopai-boost-badge')
            .text(label + ' (' + pct + '%)')
            .removeClass('shopai-boost-high shopai-boost-medium shopai-boost-low')
            .addClass(cls);
        row.find('.shopai-mini-bars').replaceWith(buildMiniBarHtml(weight));

        $.ajax({
            url:    cfg.ajax_url,
            method: 'POST',
            data: {
                action:       'shopai_update_priority',
                nonce:        cfg.nonce,
                id:           id,
                boost_weight: weight,
            },
            error: function () {
                showStatus($('#shopai-priority-form-status'), 'error', '✗ Could not update rule. Please try again.');
            },
        });
    });

    // ── Add rule ────────────────────────────────────────────────────────────
    $('#shopai-add-priority-btn').on('click', function () {
        var btn      = $(this);
        var statusEl = $('#shopai-priority-form-status');
        var type     = $('#shopai-priority-type').val();
        var value    = $('#shopai-priority-value').val().trim();
        var weight   = parseFloat($('#shopai-priority-weight').val());

        if (!value) {
            showStatus(statusEl, 'error', '⚠ Please enter a value for this rule.');
            $('#shopai-priority-value').focus();
            return;
        }

        btn.prop('disabled', true)
           .html('<span class="shopai-spinner"></span> ' + cfg.strings.adding);

        $.ajax({
            url:    cfg.ajax_url,
            method: 'POST',
            data: {
                action:          'shopai_add_priority',
                nonce:           cfg.nonce,
                attribute_type:  type,
                attribute_value: value,
                boost_weight:    weight,
            },
            success: function (res) {
                if (res.success) {
                    showStatus(statusEl, 'ok', '✓ Rule added! AI will now boost "' + value + '" ' + type + ' results.');
                    $('#shopai-priority-value').val('');
                    $('#shopai-priority-weight').val('1.0');
                    updateBoostDisplay('1.0', '#shopai-boost-bars', '#shopai-weight-label');
                    loadPriorityRules();
                } else {
                    showStatus(statusEl, 'error', '✗ ' + res.data.message);
                }
            },
            error: function () {
                showStatus(statusEl, 'error', '✗ Network error. Please try again.');
            },
            complete: function () {
                btn.prop('disabled', false)
                   .html('<span class="dashicons dashicons-plus-alt2"></span> ' + cfg.strings.add_rule_btn);
            },
        });
    });

    // ── Delete rule ─────────────────────────────────────────────────────────
    $(document).on('click', '.shopai-delete-rule', function () {
        if (! window.confirm(cfg.strings.confirm_delete)) {
            return;
        }

        var btn = $(this);
        var id  = parseInt(btn.data('id'), 10);
        var row = btn.closest('tr');

        btn.prop('disabled', true)
           .html('<span class="shopai-spinner"></span>');

        $.ajax({
            url:    cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'shopai_delete_priority',
                nonce:  cfg.nonce,
                id:     id,
            },
            success: function (res) {
                if (res.success) {
                    row.fadeOut(300, function () {
                        $(this).remove();
                        var remaining = $('#shopai-rules-tbody tr').length;
                        $('#shopai-rules-count').text(remaining > 0 ? '(' + remaining + ')' : '');
                        if (remaining === 0) {
                            $('#shopai-rules-table').addClass('shopai-hidden');
                            $('#shopai-rules-empty').removeClass('shopai-hidden');
                        }
                    });
                } else {
                    btn.prop('disabled', false)
                       .html('<span class="dashicons dashicons-trash"></span>');
                    showStatus($('#shopai-priority-form-status'), 'error', '✗ ' + res.data.message);
                }
            },
            error: function () {
                btn.prop('disabled', false)
                   .html('<span class="dashicons dashicons-trash"></span>');
                showStatus($('#shopai-priority-form-status'), 'error', '✗ Network error. Please try again.');
            },
        });
    });

    // ── Press Enter to add rule ─────────────────────────────────────────────
    $('#shopai-priority-value').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#shopai-add-priority-btn').trigger('click');
        }
    });

    // ── HTML escape helper ──────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}(jQuery));
