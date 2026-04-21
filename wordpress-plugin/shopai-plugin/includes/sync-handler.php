<?php
/**
 * Sync Handler
 *
 * Fetches WooCommerce products, formats them for ShopAI,
 * and sends them to the sync endpoint.
 */

defined('ABSPATH') || exit;

/**
 * Run a full product sync: WooCommerce → ShopAI.
 *
 * @return array|WP_Error  API response on success, WP_Error on failure.
 */
function shopai_do_sync() {
    $api_key = get_option('shopai_api_key', '');

    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('No API key configured. Go to ShopAI → Settings.', 'shopai'));
    }

    if (empty(get_option('shopai_api_base_url', ''))) {
        return new WP_Error('no_base_url', __('ShopAI Platform URL is not configured.', 'shopai'));
    }

    if (! function_exists('wc_get_products')) {
        return new WP_Error('no_woocommerce', __('WooCommerce is not active. Please activate WooCommerce first.', 'shopai'));
    }

    $products = shopai_fetch_woocommerce_products();

    if (empty($products)) {
        return new WP_Error('no_products', __('No published, in-stock WooCommerce products found.', 'shopai'));
    }

    $replace_all = (bool) get_option('shopai_sync_replace_all', false);

    return shopai_send_products($api_key, $products, $replace_all);
}

/**
 * Fetch all published WooCommerce products and format them for ShopAI.
 *
 * @return array  Array of formatted product arrays.
 */
function shopai_fetch_woocommerce_products(): array {
    $wc_products = wc_get_products([
        'status' => 'publish',
        'limit'  => -1,
        'type'   => ['simple', 'variable', 'grouped', 'external'],
    ]);

    $formatted = [];

    foreach ($wc_products as $product) {
        $data = shopai_format_product($product);
        if ($data !== null) {
            $formatted[] = $data;
        }
    }

    return $formatted;
}

/**
 * Format a single WooCommerce product into the ShopAI product schema.
 *
 * @param  WC_Product  $product
 * @return array|null  Null if the product should be skipped.
 */
function shopai_format_product(WC_Product $product): ?array {
    $name = trim($product->get_name());
    if (empty($name)) {
        return null;
    }

    // ── Description ───────────────────────────────────────────────────────
    $description = wp_strip_all_tags(
        $product->get_description() ?: $product->get_short_description()
    );
    $description = trim(preg_replace('/\s+/', ' ', $description));
    if (empty($description)) {
        $description = $name;
    }

    // ── Price ─────────────────────────────────────────────────────────────
    if ($product->is_type('variable')) {
        /** @var WC_Product_Variable $product */
        $price = (float) ($product->get_variation_price('min') ?: 0);
    } else {
        $price = (float) ($product->get_sale_price() ?: $product->get_regular_price() ?: 0);
    }

    // ── Categories ────────────────────────────────────────────────────────
    $category_names = [];
    foreach ($product->get_category_ids() as $term_id) {
        $term = get_term($term_id, 'product_cat');
        if ($term && ! is_wp_error($term)) {
            $category_names[] = $term->name;
        }
    }
    $category = ! empty($category_names) ? implode(', ', $category_names) : 'General';

    // ── Image ─────────────────────────────────────────────────────────────
    $image_id  = $product->get_image_id();
    $image_url = $image_id ? (string) wp_get_attachment_url($image_id) : '';

    // ── SKU ────────────────────────────────────────────────────────────────
    $sku = trim($product->get_sku());

    // ── Brand ─────────────────────────────────────────────────────────────
    $brand = shopai_extract_brand($product);

    // ── Attributes (color / size / variants) ──────────────────────────────
    [$color, $size, $available_colors, $available_sizes] = shopai_extract_attributes($product);

    return [
        'name'        => $name,
        'sku'         => $sku ?: null,
        'description' => $description,
        'category'    => $category,
        'price'       => $price,
        'attributes'  => [
            'brand'            => $brand ?: null,
            'color'            => $color ?: null,
            'size'             => $size ?: null,
            'available_colors' => $available_colors ?: null,
            'available_sizes'  => $available_sizes ?: null,
        ],
        'image_url'   => $image_url,
    ];
}

/**
 * Extract brand from a WooCommerce product.
 * Checks common brand taxonomies (WooCommerce Brands, Perfect Brands, YITH, custom).
 *
 * @param  WC_Product  $product
 * @return string
 */
function shopai_extract_brand(WC_Product $product): string {
    $brand_taxonomies = ['product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand'];

    foreach ($brand_taxonomies as $taxonomy) {
        $terms = wp_get_post_terms($product->get_id(), $taxonomy);
        if (! is_wp_error($terms) && ! empty($terms)) {
            return $terms[0]->name;
        }
    }

    // Also check a custom attribute named "brand"
    foreach ($product->get_attributes() as $attr_name => $attribute) {
        $label = strtolower(wc_attribute_label($attr_name));
        if ($label === 'brand') {
            if (method_exists($attribute, 'get_terms')) {
                $terms = $attribute->get_terms();
                if ($terms) {
                    return $terms[0]->name;
                }
            } else {
                $opts = $attribute->get_options() ?? [];
                if (! empty($opts)) {
                    return $opts[0];
                }
            }
        }
    }

    return '';
}

/**
 * Extract color, size, available_colors and available_sizes from a product.
 *
 * @param  WC_Product  $product
 * @return array  [string $color, string $size, array $available_colors, array $available_sizes]
 */
function shopai_extract_attributes(WC_Product $product): array {
    $color            = '';
    $size             = '';
    $available_colors = [];
    $available_sizes  = [];

    if ($product->is_type('variable')) {
        /** @var WC_Product_Variable $product */
        foreach ($product->get_variation_attributes() as $raw_name => $values) {
            $label  = strtolower(wc_attribute_label(str_replace('attribute_', '', $raw_name)));
            $values = array_values(array_filter((array) $values));

            if (str_contains($label, 'color') || str_contains($label, 'colour')) {
                $available_colors = $values;
                $color            = implode(', ', $values);
            } elseif (str_contains($label, 'size')) {
                $available_sizes = $values;
                $size            = implode(', ', $values);
            }
        }
    } else {
        foreach ($product->get_attributes() as $attr_name => $attribute) {
            $label = strtolower(wc_attribute_label($attr_name));

            if (method_exists($attribute, 'get_terms')) {
                $terms  = $attribute->get_terms();
                $values = $terms ? array_map(fn($t) => $t->name, $terms) : [];
            } else {
                $values = array_values(array_filter($attribute->get_options() ?? []));
            }

            if (str_contains($label, 'color') || str_contains($label, 'colour')) {
                $available_colors = $values;
                $color            = implode(', ', $values);
            } elseif (str_contains($label, 'size')) {
                $available_sizes = $values;
                $size            = implode(', ', $values);
            }
        }
    }

    return [trim($color), trim($size), $available_colors, $available_sizes];
}
