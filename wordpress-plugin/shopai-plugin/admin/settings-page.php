<?php
/**
 * Admin Settings Page
 *
 * Renders the ShopAI settings page in wp-admin.
 */

defined('ABSPATH') || exit;

function shopai_render_settings_page(): void {
    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'shopai'));
    }

    // Retrieve saved options
    $base_url     = esc_url(get_option('shopai_api_base_url', ''));
    $api_key      = esc_attr(get_option('shopai_api_key', ''));
    $auto_sync    = (bool) get_option('shopai_auto_sync_enabled', false);
    $interval     = esc_attr(get_option('shopai_sync_interval', 'hourly'));
    $replace_all  = (bool) get_option('shopai_sync_replace_all', false);
    $widget_on    = (bool) get_option('shopai_widget_enabled', true);
    $widget_theme = esc_attr(get_option('shopai_widget_theme', 'blue'));
    $widget_title = esc_attr(get_option('shopai_widget_title', 'ShopAI Assistant'));

    $last_status  = get_option('shopai_last_sync_status', '');
    $last_message = get_option('shopai_last_sync_message', '');
    $last_time    = get_option('shopai_last_sync_time', '');
    ?>
    <div class="wrap shopai-wrap">

        <!-- ── Header ───────────────────────────────────────────────── -->
        <div class="shopai-header">
            <div class="shopai-header-brand">
                <div class="shopai-logo">🛍️</div>
                <div>
                    <h1>ShopAI Platform</h1>
                    <p>Connect, sync, and power your store with AI-driven chat.</p>
                </div>
            </div>
            <div class="shopai-header-badges">
                <span class="shopai-badge shopai-badge-blue">v<?php echo esc_html(SHOPAI_VERSION); ?></span>
                <?php if ($api_key && $base_url) : ?>
                    <span class="shopai-badge shopai-badge-green" id="shopai-conn-badge">● Connected</span>
                <?php else : ?>
                    <span class="shopai-badge shopai-badge-gray" id="shopai-conn-badge">○ Not Connected</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Settings form ────────────────────────────────────────── -->
        <form method="post" action="options.php" id="shopai-settings-form">
            <?php settings_fields('shopai_options'); ?>

            <!-- ═══════════════════════════════════════════════════════
                 SECTION 1 — CONNECTION
            ════════════════════════════════════════════════════════ -->
            <div class="shopai-card">
                <div class="shopai-card-header">
                    <div class="shopai-card-icon shopai-icon-blue">🔗</div>
                    <div>
                        <h2><?php esc_html_e('Platform Connection', 'shopai'); ?></h2>
                        <p><?php esc_html_e('Enter your ShopAI Platform credentials.', 'shopai'); ?></p>
                    </div>
                </div>
                <div class="shopai-card-body">

                    <div class="shopai-field">
                        <label for="shopai_api_base_url">
                            <?php esc_html_e('ShopAI Platform URL', 'shopai'); ?>
                            <span class="shopai-required">*</span>
                        </label>
                        <input
                            type="url"
                            id="shopai_api_base_url"
                            name="shopai_api_base_url"
                            value="<?php echo $base_url; ?>"
                            placeholder="http://localhost:8000"
                            class="shopai-input"
                        >
                        <span class="shopai-hint">
                            <?php esc_html_e('The URL where your ShopAI backend is running.', 'shopai'); ?>
                        </span>
                    </div>

                    <div class="shopai-field">
                        <label for="shopai_api_key">
                            <?php esc_html_e('API Key', 'shopai'); ?>
                            <span class="shopai-required">*</span>
                        </label>
                        <div class="shopai-key-wrap">
                            <input
                                type="password"
                                id="shopai_api_key"
                                name="shopai_api_key"
                                value="<?php echo $api_key; ?>"
                                placeholder="<?php esc_attr_e('Paste your API key here', 'shopai'); ?>"
                                class="shopai-input"
                                autocomplete="off"
                            >
                            <button type="button" class="shopai-btn shopai-btn-ghost" id="shopai-toggle-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <span class="shopai-hint">
                            <?php
                            printf(
                                wp_kses(
                                    /* translators: %s: dashboard URL */
                                    __('Get your API key from the <a href="%s" target="_blank">ShopAI dashboard</a>.', 'shopai'),
                                    ['a' => ['href' => [], 'target' => []]]
                                ),
                                esc_url(get_option('shopai_api_base_url', '#') . '/dashboard')
                            );
                            ?>
                        </span>
                    </div>

                    <div class="shopai-actions-row">
                        <button type="button" class="shopai-btn shopai-btn-secondary" id="shopai-test-btn">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e('Test Connection', 'shopai'); ?>
                        </button>
                        <div class="shopai-status-msg" id="shopai-conn-status"></div>
                    </div>

                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 SECTION 2 — PRODUCT SYNC
            ════════════════════════════════════════════════════════ -->
            <div class="shopai-card">
                <div class="shopai-card-header">
                    <div class="shopai-card-icon shopai-icon-purple">🔄</div>
                    <div>
                        <h2><?php esc_html_e('Product Sync', 'shopai'); ?></h2>
                        <p><?php esc_html_e('Push your WooCommerce catalogue to ShopAI.', 'shopai'); ?></p>
                    </div>
                </div>
                <div class="shopai-card-body">

                    <!-- Last sync status -->
                    <?php if ($last_time) : ?>
                        <div class="shopai-sync-status <?php echo $last_status === 'success' ? 'shopai-sync-ok' : 'shopai-sync-warn'; ?>">
                            <span class="shopai-sync-dot"></span>
                            <div>
                                <strong>
                                    <?php
                                    if ($last_status === 'success') {
                                        esc_html_e('Last sync succeeded', 'shopai');
                                    } elseif ($last_status === 'partial') {
                                        esc_html_e('Last sync partially succeeded', 'shopai');
                                    } else {
                                        esc_html_e('Last sync failed', 'shopai');
                                    }
                                    ?>
                                </strong>
                                <span><?php echo esc_html($last_message); ?></span>
                                <span class="shopai-time"><?php echo esc_html(human_time_diff(strtotime($last_time), current_time('timestamp')) . ' ago'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sync now -->
                    <div class="shopai-actions-row" style="margin-bottom:24px;">
                        <button type="button" class="shopai-btn shopai-btn-primary" id="shopai-sync-btn">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Sync Products Now', 'shopai'); ?>
                        </button>
                        <div class="shopai-status-msg" id="shopai-sync-status"></div>
                    </div>

                    <div class="shopai-divider"></div>

                    <!-- Auto-sync -->
                    <div class="shopai-toggle-row">
                        <label class="shopai-toggle">
                            <input
                                type="checkbox"
                                name="shopai_auto_sync_enabled"
                                id="shopai_auto_sync_enabled"
                                value="1"
                                <?php checked($auto_sync); ?>
                            >
                            <span class="shopai-toggle-slider"></span>
                        </label>
                        <div class="shopai-toggle-label">
                            <strong><?php esc_html_e('Enable Auto-Sync', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Automatically sync products on a schedule using WP-Cron.', 'shopai'); ?></span>
                        </div>
                    </div>

                    <div class="shopai-sub-options <?php echo $auto_sync ? '' : 'shopai-hidden'; ?>" id="shopai-auto-sync-options">
                        <div class="shopai-field" style="margin-top:16px;max-width:280px;">
                            <label for="shopai_sync_interval"><?php esc_html_e('Sync Interval', 'shopai'); ?></label>
                            <select name="shopai_sync_interval" id="shopai_sync_interval" class="shopai-select">
                                <option value="shopai_30min" <?php selected($interval, 'shopai_30min'); ?>>
                                    <?php esc_html_e('Every 30 Minutes', 'shopai'); ?>
                                </option>
                                <option value="hourly" <?php selected($interval, 'hourly'); ?>>
                                    <?php esc_html_e('Every Hour', 'shopai'); ?>
                                </option>
                                <option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>>
                                    <?php esc_html_e('Twice Daily', 'shopai'); ?>
                                </option>
                                <option value="daily" <?php selected($interval, 'daily'); ?>>
                                    <?php esc_html_e('Once Daily', 'shopai'); ?>
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="shopai-toggle-row" style="margin-top:16px;">
                        <label class="shopai-toggle">
                            <input
                                type="checkbox"
                                name="shopai_sync_replace_all"
                                value="1"
                                <?php checked($replace_all); ?>
                            >
                            <span class="shopai-toggle-slider"></span>
                        </label>
                        <div class="shopai-toggle-label">
                            <strong><?php esc_html_e('Replace All on Sync', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Delete all existing ShopAI products before each sync (clean slate).', 'shopai'); ?></span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 SECTION 3 — CHAT WIDGET
            ════════════════════════════════════════════════════════ -->
            <div class="shopai-card">
                <div class="shopai-card-header">
                    <div class="shopai-card-icon shopai-icon-green">💬</div>
                    <div>
                        <h2><?php esc_html_e('Chat Widget', 'shopai'); ?></h2>
                        <p><?php esc_html_e('Embed an AI-powered chat on your storefront.', 'shopai'); ?></p>
                    </div>
                </div>
                <div class="shopai-card-body">

                    <div class="shopai-toggle-row">
                        <label class="shopai-toggle">
                            <input
                                type="checkbox"
                                name="shopai_widget_enabled"
                                value="1"
                                <?php checked($widget_on); ?>
                            >
                            <span class="shopai-toggle-slider"></span>
                        </label>
                        <div class="shopai-toggle-label">
                            <strong><?php esc_html_e('Enable Chat Widget', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Show the floating AI chat button on all frontend pages.', 'shopai'); ?></span>
                        </div>
                    </div>

                    <div class="shopai-divider" style="margin:20px 0;"></div>

                    <div class="shopai-two-col">
                        <div class="shopai-field">
                            <label for="shopai_widget_title"><?php esc_html_e('Chat Title', 'shopai'); ?></label>
                            <input
                                type="text"
                                name="shopai_widget_title"
                                id="shopai_widget_title"
                                value="<?php echo $widget_title; ?>"
                                placeholder="ShopAI Assistant"
                                class="shopai-input"
                            >
                        </div>

                        <div class="shopai-field">
                            <label for="shopai_widget_theme"><?php esc_html_e('Color Theme', 'shopai'); ?></label>
                            <select name="shopai_widget_theme" id="shopai_widget_theme" class="shopai-select">
                                <option value="blue"   <?php selected($widget_theme, 'blue'); ?>>
                                    <?php esc_html_e('🔵 Blue (Default)', 'shopai'); ?>
                                </option>
                                <option value="purple" <?php selected($widget_theme, 'purple'); ?>>
                                    <?php esc_html_e('🟣 Purple', 'shopai'); ?>
                                </option>
                                <option value="green"  <?php selected($widget_theme, 'green'); ?>>
                                    <?php esc_html_e('🟢 Green', 'shopai'); ?>
                                </option>
                                <option value="dark"   <?php selected($widget_theme, 'dark'); ?>>
                                    <?php esc_html_e('⚫ Dark', 'shopai'); ?>
                                </option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Save button ───────────────────────────────────────── -->
            <div class="shopai-save-row">
                <?php submit_button(__('Save Settings', 'shopai'), 'primary', 'submit', false, ['class' => 'shopai-btn shopai-btn-primary shopai-btn-lg']); ?>
                <p class="shopai-save-hint">
                    <?php esc_html_e('Settings are saved immediately and take effect on your next page load.', 'shopai'); ?>
                </p>
            </div>

        </form><!-- /shopai-settings-form -->

        <!-- ═══════════════════════════════════════════════════════
             SECTION 4 — SEARCH PRIORITY MANAGER
             (outside <form> — uses its own AJAX, no page reload)
        ════════════════════════════════════════════════════════ -->
        <div class="shopai-card" id="shopai-priority-card">
            <div class="shopai-card-header">
                <div class="shopai-card-icon shopai-icon-orange">⭐</div>
                <div>
                    <h2><?php esc_html_e('Search Priority Rules', 'shopai'); ?></h2>
                    <p><?php esc_html_e('Control which products appear first in AI recommendations. Higher priority = stronger boost.', 'shopai'); ?></p>
                </div>
            </div>
            <div class="shopai-card-body">

                <?php if (! $api_key || ! $base_url) : ?>
                    <div class="shopai-priority-notice">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php esc_html_e('Please configure and save your Platform Connection settings first.', 'shopai'); ?>
                    </div>
                <?php else : ?>

                <!-- ── How it works ──────────────────────────────── -->
                <div class="shopai-priority-explainer">
                    <div class="shopai-explainer-item">
                        <span class="shopai-explainer-icon">🏷️</span>
                        <div>
                            <strong><?php esc_html_e('Brand', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Boost products from a specific brand (e.g. Nike, Zara)', 'shopai'); ?></span>
                        </div>
                    </div>
                    <div class="shopai-explainer-item">
                        <span class="shopai-explainer-icon">📂</span>
                        <div>
                            <strong><?php esc_html_e('Category', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Prioritise a product category (e.g. shoes, accessories)', 'shopai'); ?></span>
                        </div>
                    </div>
                    <div class="shopai-explainer-item">
                        <span class="shopai-explainer-icon">📏</span>
                        <div>
                            <strong><?php esc_html_e('Size', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Surface a specific size first (e.g. M, L, 42)', 'shopai'); ?></span>
                        </div>
                    </div>
                    <div class="shopai-explainer-item">
                        <span class="shopai-explainer-icon">🎨</span>
                        <div>
                            <strong><?php esc_html_e('Color', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Promote a specific colour (e.g. black, white)', 'shopai'); ?></span>
                        </div>
                    </div>
                    <div class="shopai-explainer-item">
                        <span class="shopai-explainer-icon">🔖</span>
                        <div>
                            <strong><?php esc_html_e('Tag', 'shopai'); ?></strong>
                            <span><?php esc_html_e('Boost products with a keyword tag (e.g. summer, sale)', 'shopai'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="shopai-divider"></div>

                <!-- ── Add new rule form ─────────────────────────── -->
                <div class="shopai-priority-form">
                    <h3><?php esc_html_e('Add a New Priority Rule', 'shopai'); ?></h3>
                    <div class="shopai-priority-inputs">

                        <div class="shopai-field">
                            <label for="shopai-priority-type"><?php esc_html_e('Attribute Type', 'shopai'); ?></label>
                            <select id="shopai-priority-type" class="shopai-select">
                                <option value="brand">🏷️ <?php esc_html_e('Brand', 'shopai'); ?></option>
                                <option value="category">📂 <?php esc_html_e('Category', 'shopai'); ?></option>
                                <option value="size">📏 <?php esc_html_e('Size', 'shopai'); ?></option>
                                <option value="color">🎨 <?php esc_html_e('Color', 'shopai'); ?></option>
                                <option value="tag">🔖 <?php esc_html_e('Tag / Keyword', 'shopai'); ?></option>
                            </select>
                        </div>

                        <div class="shopai-field">
                            <label for="shopai-priority-value">
                                <?php esc_html_e('Value', 'shopai'); ?>
                                <span class="shopai-hint" id="shopai-value-hint"><?php esc_html_e('e.g. Nike, Adidas', 'shopai'); ?></span>
                            </label>
                            <input
                                type="text"
                                id="shopai-priority-value"
                                class="shopai-input"
                                placeholder="<?php esc_attr_e('e.g. Nike', 'shopai'); ?>"
                                maxlength="100"
                            >
                        </div>

                        <div class="shopai-field">
                            <label for="shopai-priority-weight">
                                <?php esc_html_e('Boost Strength', 'shopai'); ?>
                                <span class="shopai-boost-badge" id="shopai-weight-label">High</span>
                            </label>
                            <div class="shopai-slider-wrap">
                                <span class="shopai-slider-min"><?php esc_html_e('Low', 'shopai'); ?></span>
                                <input
                                    type="range"
                                    id="shopai-priority-weight"
                                    min="0.1"
                                    max="1.0"
                                    step="0.1"
                                    value="1.0"
                                    class="shopai-slider"
                                >
                                <span class="shopai-slider-max"><?php esc_html_e('High', 'shopai'); ?></span>
                            </div>
                            <div class="shopai-boost-bars" id="shopai-boost-bars">
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                                <span class="shopai-boost-bar active"></span>
                            </div>
                        </div>

                    </div>

                    <div class="shopai-actions-row">
                        <button type="button" class="shopai-btn shopai-btn-primary" id="shopai-add-priority-btn">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add Rule', 'shopai'); ?>
                        </button>
                        <div class="shopai-status-msg" id="shopai-priority-form-status"></div>
                    </div>
                </div>

                <div class="shopai-divider"></div>

                <!-- ── Active rules table ────────────────────────── -->
                <div class="shopai-priority-rules-section">
                    <h3>
                        <?php esc_html_e('Active Rules', 'shopai'); ?>
                        <span class="shopai-rules-count" id="shopai-rules-count"></span>
                    </h3>

                    <div id="shopai-priority-rules-wrap">
                        <div class="shopai-rules-loading" id="shopai-rules-loading">
                            <span class="shopai-spinner"></span>
                            <?php esc_html_e('Loading rules…', 'shopai'); ?>
                        </div>

                        <div id="shopai-rules-empty" class="shopai-rules-empty shopai-hidden">
                            <span class="dashicons dashicons-star-empty"></span>
                            <p><?php esc_html_e('No priority rules yet. Add one above to start boosting products.', 'shopai'); ?></p>
                        </div>

                        <table class="shopai-rules-table shopai-hidden" id="shopai-rules-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Type', 'shopai'); ?></th>
                                    <th><?php esc_html_e('Value', 'shopai'); ?></th>
                                    <th><?php esc_html_e('Boost Strength', 'shopai'); ?></th>
                                    <th><?php esc_html_e('Adjust', 'shopai'); ?></th>
                                    <th><?php esc_html_e('Remove', 'shopai'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="shopai-rules-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div><!-- /priority card -->

    </div><!-- /shopai-wrap -->
    <?php
}
