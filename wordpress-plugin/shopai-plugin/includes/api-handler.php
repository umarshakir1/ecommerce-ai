<?php
/**
 * API Handler
 *
 * All communication between the plugin and the ShopAI Platform API.
 */

defined('ABSPATH') || exit;

/**
 * Return the base URL for all ShopAI API calls.
 */
function shopai_base_url(): string {
    return rtrim(get_option('shopai_api_base_url', ''), '/');
}

/**
 * Build the standard request headers.
 *
 * @param  string  $api_key
 * @return array
 */
function shopai_headers(string $api_key): array {
    return [
        'Authorization'              => 'Bearer ' . $api_key,
        'Content-Type'               => 'application/json',
        'Accept'                     => 'application/json',
        'ngrok-skip-browser-warning' => 'true',
        'X-Site-Domain'              => get_site_url(),
    ];
}

/**
 * Validate an API key against the ShopAI platform.
 *
 * Calls GET /api/validate-key
 *
 * @param  string  $api_key
 * @return array|WP_Error  Decoded response body on success, WP_Error on failure.
 */
function shopai_validate_key(string $api_key) {
    $url = shopai_base_url() . '/api/validate-key';

    if (empty(shopai_base_url())) {
        return new WP_Error('no_base_url', __('ShopAI Platform URL is not configured.', 'shopai'));
    }

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => shopai_headers($api_key),
    ]);

    return shopai_parse_response($response, 200);
}

/**
 * Send a formatted product array to the ShopAI sync endpoint.
 *
 * Calls POST /api/sync-products
 *
 * @param  string  $api_key
 * @param  array   $products
 * @param  bool    $replace_all  If true, wipes existing client products before sync.
 * @return array|WP_Error
 */
function shopai_send_products(string $api_key, array $products, bool $replace_all = false) {
    $url = shopai_base_url() . '/api/sync-products';

    $response = wp_remote_post($url, [
        'timeout' => 300,
        'headers' => shopai_headers($api_key),
        'body'    => wp_json_encode([
            'products'    => $products,
            'replace_all' => $replace_all,
        ]),
    ]);

    return shopai_parse_response($response, [200, 207]);
}

/**
 * Retrieve all priority rules for this client.
 *
 * Calls GET /api/priorities
 *
 * @param  string  $api_key
 * @return array|WP_Error
 */
function shopai_get_priorities(string $api_key) {
    $url = shopai_base_url() . '/api/priorities';
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => shopai_headers($api_key),
    ]);
    return shopai_parse_response($response, 200);
}

/**
 * Add a priority rule.
 *
 * Calls POST /api/priorities
 *
 * @param  string  $api_key
 * @param  string  $attribute_type   brand|category|size|color|tag
 * @param  string  $attribute_value  e.g. "nike", "L", "black"
 * @param  float   $boost_weight     0.0 – 1.0
 * @return array|WP_Error
 */
function shopai_add_priority(string $api_key, string $attribute_type, string $attribute_value, float $boost_weight) {
    $url = shopai_base_url() . '/api/priorities';
    $response = wp_remote_post($url, [
        'timeout' => 15,
        'headers' => shopai_headers($api_key),
        'body'    => wp_json_encode([
            'attribute_type'  => $attribute_type,
            'attribute_value' => $attribute_value,
            'boost_weight'    => $boost_weight,
        ]),
    ]);
    return shopai_parse_response($response, [200, 201]);
}

/**
 * Update the boost weight of an existing priority rule.
 *
 * Calls PUT /api/priorities/{id}
 *
 * @param  string  $api_key
 * @param  int     $id
 * @param  float   $boost_weight
 * @return array|WP_Error
 */
function shopai_update_priority(string $api_key, int $id, float $boost_weight) {
    $url = shopai_base_url() . '/api/priorities/' . $id;
    $response = wp_remote_request($url, [
        'method'  => 'PUT',
        'timeout' => 15,
        'headers' => shopai_headers($api_key),
        'body'    => wp_json_encode(['boost_weight' => $boost_weight]),
    ]);
    return shopai_parse_response($response, 200);
}

/**
 * Delete a priority rule.
 *
 * Calls DELETE /api/priorities/{id}
 *
 * @param  string  $api_key
 * @param  int     $id
 * @return array|WP_Error
 */
function shopai_delete_priority(string $api_key, int $id) {
    $url = shopai_base_url() . '/api/priorities/' . $id;
    $response = wp_remote_request($url, [
        'method'  => 'DELETE',
        'timeout' => 15,
        'headers' => shopai_headers($api_key),
    ]);
    return shopai_parse_response($response, 200);
}

/**
 * Parse a wp_remote_* response.
 *
 * @param  array|WP_Error  $response
 * @param  int|int[]        $expected_codes  HTTP status code(s) considered success.
 * @return array|WP_Error
 */
function shopai_parse_response($response, $expected_codes) {
    if (is_wp_error($response)) {
        return new WP_Error(
            'connection_failed',
            sprintf(
                __('Could not connect to ShopAI: %s', 'shopai'),
                $response->get_error_message()
            )
        );
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    $allowed = (array) $expected_codes;

    if ($code === 401) {
        return new WP_Error('invalid_key', __('Invalid API key. Please check your credentials.', 'shopai'));
    }

    if (! in_array($code, $allowed, true)) {
        $msg = $body['error'] ?? sprintf(__('ShopAI returned HTTP %d.', 'shopai'), $code);
        if (! empty($body['detail'])) {
            $msg .= ': ' . $body['detail'];
        }
        return new WP_Error('api_error', $msg);
    }

    return is_array($body) ? $body : [];
}
