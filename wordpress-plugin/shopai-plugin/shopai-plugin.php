<?php
/**
 * Plugin Name:       ShopAI Platform
 * Plugin URI:        https://yourplatform.com
 * Description:       Connect your WooCommerce store to ShopAI — sync products and add an AI-powered chat widget to your storefront.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ShopAI
 * License:           GPL v2 or later
 * Text Domain:       shopai
 * WC requires at least: 5.0
 */

defined('ABSPATH') || exit;

define('SHOPAI_VERSION',    '1.0.1');
define('SHOPAI_PLUGIN_FILE', __FILE__);
define('SHOPAI_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('SHOPAI_PLUGIN_URL',  plugin_dir_url(__FILE__));

// ── Load includes ──────────────────────────────────────────────────────────
require_once SHOPAI_PLUGIN_DIR . 'includes/api-handler.php';
require_once SHOPAI_PLUGIN_DIR . 'includes/sync-handler.php';
require_once SHOPAI_PLUGIN_DIR . 'admin/settings-page.php';

// ── Activation / Deactivation ──────────────────────────────────────────────
register_activation_hook(__FILE__, 'shopai_activate');
function shopai_activate(): void {
    shopai_schedule_cron();
}

register_deactivation_hook(__FILE__, 'shopai_deactivate');
function shopai_deactivate(): void {
    wp_clear_scheduled_hook('shopai_cron_sync');
}

function shopai_schedule_cron(): void {
    if (
        get_option('shopai_auto_sync_enabled') &&
        ! wp_next_scheduled('shopai_cron_sync')
    ) {
        $interval = get_option('shopai_sync_interval', 'hourly');
        wp_schedule_event(time(), $interval, 'shopai_cron_sync');
    }
}

// ── Custom cron interval ───────────────────────────────────────────────────
add_filter('cron_schedules', 'shopai_add_cron_intervals');
function shopai_add_cron_intervals(array $schedules): array {
    $schedules['shopai_30min'] = [
        'interval' => 1800,
        'display'  => __('Every 30 Minutes', 'shopai'),
    ];
    return $schedules;
}

// Cron callback
add_action('shopai_cron_sync', 'shopai_do_sync');

// ── Admin menu ─────────────────────────────────────────────────────────────
add_action('admin_menu', 'shopai_register_menu');
function shopai_register_menu(): void {
    add_menu_page(
        __('ShopAI Platform', 'shopai'),
        __('ShopAI', 'shopai'),
        'manage_options',
        'shopai-settings',
        'shopai_render_settings_page',
        'dashicons-cart',
        58
    );
}

// ── Register settings ──────────────────────────────────────────────────────
add_action('admin_init', 'shopai_register_settings');
function shopai_register_settings(): void {
    $options = [
        'shopai_api_base_url',
        'shopai_api_key',
        'shopai_auto_sync_enabled',
        'shopai_sync_interval',
        'shopai_sync_replace_all',
        'shopai_widget_enabled',
        'shopai_widget_theme',
        'shopai_widget_title',
    ];
    foreach ($options as $option) {
        register_setting('shopai_options', $option, ['sanitize_callback' => 'shopai_sanitize_option']);
    }
}

function shopai_sanitize_option($value) {
    if (is_array($value)) {
        return array_map('sanitize_text_field', $value);
    }
    return sanitize_text_field($value);
}

// ── Admin assets ───────────────────────────────────────────────────────────
add_action('admin_enqueue_scripts', 'shopai_admin_assets');
function shopai_admin_assets(string $hook): void {
    if ($hook !== 'toplevel_page_shopai-settings') {
        return;
    }
    wp_enqueue_style(
        'shopai-admin',
        SHOPAI_PLUGIN_URL . 'admin/admin.css',
        [],
        SHOPAI_VERSION
    );
    wp_enqueue_script(
        'shopai-admin',
        SHOPAI_PLUGIN_URL . 'admin/admin.js',
        ['jquery'],
        SHOPAI_VERSION,
        true
    );
    wp_localize_script('shopai-admin', 'shopaiAdmin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('shopai_admin_nonce'),
        'strings'  => [
            'testing'          => __('Testing…',                              'shopai'),
            'syncing'          => __('Syncing…',                              'shopai'),
            'test_btn'         => __('Test Connection',                       'shopai'),
            'sync_btn'         => __('Sync Products Now',                     'shopai'),
            'confirm_sync'     => __('Sync all WooCommerce products to ShopAI now?', 'shopai'),
            'adding'           => __('Adding…',                               'shopai'),
            'add_rule_btn'     => __('Add Rule',                              'shopai'),
            'confirm_delete'   => __('Remove this priority rule?',            'shopai'),
            'no_rules'         => __('No priority rules set yet.',            'shopai'),
            'loading_rules'    => __('Loading rules…',                        'shopai'),
            'rules_error'      => __('Could not load rules. Please save settings and try again.', 'shopai'),
        ],
    ]);
}

// ── Frontend assets ────────────────────────────────────────────────────────
add_action('wp_enqueue_scripts', 'shopai_frontend_assets');
function shopai_frontend_assets(): void {
    if (! get_option('shopai_widget_enabled') || ! get_option('shopai_api_key')) {
        return;
    }
    wp_enqueue_style(
        'shopai-widget',
        SHOPAI_PLUGIN_URL . 'public/chat-widget.css',
        [],
        SHOPAI_VERSION
    );
    wp_enqueue_script(
        'shopai-widget',
        SHOPAI_PLUGIN_URL . 'public/chat-widget.js',
        [],
        SHOPAI_VERSION,
        true
    );
    // API key is intentionally NOT passed to frontend — chat is proxied via WP AJAX
    wp_localize_script('shopai-widget', 'shopaiConfig', [
        'ajax_url'  => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('shopai_chat_nonce'),
        'theme'     => sanitize_text_field(get_option('shopai_widget_theme', 'blue')),
        'title'     => sanitize_text_field(get_option('shopai_widget_title', 'ShopAI Assistant')),
        'shop_url'  => get_permalink(wc_get_page_id('shop')) ?: home_url('/shop'),
    ]);
}

// ── Cache-buster for LiteSpeed and other full-page caches ────────────────
function shopai_no_cache_headers(): void {
    nocache_headers();
    header('X-LiteSpeed-Cache-Control: no-cache, no-store');
    do_action('litespeed_control_set_nocache', 'shopai_ajax');
}

// ── AJAX: Test connection ──────────────────────────────────────────────────
add_action('wp_ajax_shopai_test_connection', 'shopai_ajax_test_connection');
function shopai_ajax_test_connection(): void {
    check_ajax_referer('shopai_admin_nonce', 'nonce');
    shopai_no_cache_headers();

    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'shopai')]);
    }

    $api_key  = sanitize_text_field(wp_unslash($_POST['api_key']  ?? ''));
    $base_url = esc_url_raw(wp_unslash($_POST['base_url'] ?? ''));

    if (empty($api_key)) {
        wp_send_json_error(['message' => __('Please enter an API key.', 'shopai')]);
    }
    if (empty($base_url)) {
        wp_send_json_error(['message' => __('Please enter the ShopAI Platform URL.', 'shopai')]);
    }

    // Temporarily save base_url so api-handler can use it
    update_option('shopai_api_base_url', $base_url);

    $result = shopai_validate_key($api_key);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'message' => sprintf(
            __('Connected successfully! Welcome, %s.', 'shopai'),
            esc_html($result['user']['name'] ?? 'Client')
        ),
        'user' => $result['user'] ?? [],
    ]);
}

// ── AJAX: Manual product sync ──────────────────────────────────────────────
add_action('wp_ajax_shopai_sync_products', 'shopai_ajax_sync_products');
function shopai_ajax_sync_products(): void {
    check_ajax_referer('shopai_admin_nonce', 'nonce');
    shopai_no_cache_headers();
    @set_time_limit(0); // Large catalogues can take several minutes — no PHP timeout

    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'shopai')]);
    }

    $result = shopai_do_sync();

    if (is_wp_error($result)) {
        update_option('shopai_last_sync_status', 'error');
        update_option('shopai_last_sync_message', $result->get_error_message());
        update_option('shopai_last_sync_time',    current_time('mysql'));
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    $synced  = $result['summary']['synced'] ?? 0;
    $failed  = $result['summary']['failed'] ?? 0;
    $message = sprintf(__('Synced %d product(s).', 'shopai'), $synced);
    if ($failed > 0) {
        $message .= sprintf(__(' %d failed (check logs).', 'shopai'), $failed);
    }

    update_option('shopai_last_sync_status',  $failed > 0 ? 'partial' : 'success');
    update_option('shopai_last_sync_message', $message);
    update_option('shopai_last_sync_time',    current_time('mysql'));

    wp_send_json_success(['message' => $message, 'summary' => $result['summary'] ?? []]);
}

// ── AJAX: Get priority rules ───────────────────────────────────────────────
add_action('wp_ajax_shopai_get_priorities', 'shopai_ajax_get_priorities');
function shopai_ajax_get_priorities(): void {
    check_ajax_referer('shopai_admin_nonce', 'nonce');
    shopai_no_cache_headers();
    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'shopai')]);
    }

    $api_key = get_option('shopai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('API key not configured.', 'shopai')]);
    }

    $result = shopai_get_priorities($api_key);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success($result);
}

// ── AJAX: Add priority rule ────────────────────────────────────────────────
add_action('wp_ajax_shopai_add_priority', 'shopai_ajax_add_priority');
function shopai_ajax_add_priority(): void {
    check_ajax_referer('shopai_admin_nonce', 'nonce');
    shopai_no_cache_headers();
    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'shopai')]);
    }

    $type  = sanitize_text_field(wp_unslash($_POST['attribute_type']  ?? ''));
    $value = sanitize_text_field(wp_unslash($_POST['attribute_value'] ?? ''));
    $weight = (float) ($_POST['boost_weight'] ?? 0.5);

    $valid_types = ['brand', 'category', 'size', 'color', 'tag'];
    if (! in_array($type, $valid_types, true)) {
        wp_send_json_error(['message' => __('Invalid attribute type.', 'shopai')]);
    }
    if (empty($value)) {
        wp_send_json_error(['message' => __('Please enter a value for this rule.', 'shopai')]);
    }
    $weight = max(0.0, min(1.0, $weight));

    $api_key = get_option('shopai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('API key not configured.', 'shopai')]);
    }

    $result = shopai_add_priority($api_key, $type, $value, $weight);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success($result);
}

// ── AJAX: Update priority rule boost weight ────────────────────────────────
add_action('wp_ajax_shopai_update_priority', 'shopai_ajax_update_priority');
function shopai_ajax_update_priority(): void {
    check_ajax_referer('shopai_admin_nonce', 'nonce');
    shopai_no_cache_headers();
    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'shopai')]);
    }

    $id     = (int) ($_POST['id'] ?? 0);
    $weight = (float) ($_POST['boost_weight'] ?? 0.5);
    $weight = max(0.0, min(1.0, $weight));

    if (! $id) {
        wp_send_json_error(['message' => __('Invalid rule ID.', 'shopai')]);
    }

    $api_key = get_option('shopai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('API key not configured.', 'shopai')]);
    }

    $result = shopai_update_priority($api_key, $id, $weight);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success($result);
}

// ── AJAX: Delete priority rule ─────────────────────────────────────────────
add_action('wp_ajax_shopai_delete_priority', 'shopai_ajax_delete_priority');
function shopai_ajax_delete_priority(): void {
    check_ajax_referer('shopai_admin_nonce', 'nonce');
    shopai_no_cache_headers();
    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'shopai')]);
    }

    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        wp_send_json_error(['message' => __('Invalid rule ID.', 'shopai')]);
    }

    $api_key = get_option('shopai_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => __('API key not configured.', 'shopai')]);
    }

    $result = shopai_delete_priority($api_key, $id);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success(['message' => __('Rule deleted.', 'shopai')]);
}

// ── AJAX: Frontend chat proxy (API key never exposed to browser) ───────────
add_action('wp_ajax_shopai_chat',        'shopai_ajax_chat');
add_action('wp_ajax_nopriv_shopai_chat', 'shopai_ajax_chat');
function shopai_ajax_chat(): void {
    check_ajax_referer('shopai_chat_nonce', 'nonce');
    shopai_no_cache_headers();

    $message    = sanitize_text_field(wp_unslash($_POST['message']    ?? ''));
    $session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));

    if (empty(trim($message))) {
        wp_send_json_error(['message' => __('Message cannot be empty.', 'shopai')]);
    }

    $api_key  = get_option('shopai_api_key', '');
    $base_url = rtrim(get_option('shopai_api_base_url', ''), '/');

    if (empty($api_key) || empty($base_url)) {
        wp_send_json_error(['message' => __('ShopAI is not configured.', 'shopai')]);
    }

    $payload = ['message' => $message];
    if (! empty($session_id)) {
        $payload['session_id'] = $session_id;
    }

    $response = wp_remote_post("{$base_url}/api/chat", [
        'timeout' => 45,
        'headers' => [
            'Authorization'              => 'Bearer ' . $api_key,
            'Content-Type'               => 'application/json',
            'Accept'                     => 'application/json',
            'ngrok-skip-browser-warning' => 'true',
            'X-Site-Domain'              => get_site_url(),
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => __('Could not reach ShopAI. Please try again.', 'shopai')]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200) {
        wp_send_json_error(['message' => $body['error'] ?? __('Unexpected error from ShopAI.', 'shopai')]);
    }

    wp_send_json_success($body);
}
