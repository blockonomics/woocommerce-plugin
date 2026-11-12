<?php
/**
 * Idempotent wp-env bootstrap for agentic checkout testing.
 * Run via: npx wp-env run cli wp eval-file wp-content/plugins/woocommerce-plugin/tests/agentic/wp-env-setup.php
 *
 * Does NOT set the Blockonomics API key / store: run the setup wizard in wp-admin once,
 * it needs a real Blockonomics account to generate addresses.
 */

if (!class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce not active');
}

// Store API lives under /wp-json, pretty permalinks keep URLs clean
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
flush_rewrite_rules(true);

update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_currency', 'USD');
update_option('woocommerce_default_country', 'US:CA');
update_option('woocommerce_coming_soon', 'no');

// Virtual product -> no shipping address needed at checkout
$slug = 'agent-test-item';
$existing = get_page_by_path($slug, OBJECT, 'product');
if ($existing) {
    $product_id = $existing->ID;
} else {
    $product = new WC_Product_Simple();
    $product->set_name('Agent Test Item');
    $product->set_slug($slug);
    $product->set_regular_price('5.00');
    $product->set_virtual(true);
    $product->set_status('publish');
    $product_id = $product->save();
}

$settings = get_option('woocommerce_blockonomics_settings', array());
if (!is_array($settings)) {
    $settings = array();
}
$settings['enabled'] = 'yes';
$settings['agentic_checkout'] = 'yes';
update_option('woocommerce_blockonomics_settings', $settings);
update_option('blockonomics_agentic_checkout', 1);

WP_CLI::success(sprintf(
    'product_id=%d api_key=%s enabled_cryptos=%s',
    $product_id,
    get_option('blockonomics_api_key') ? 'set' : 'MISSING (run wizard)',
    get_option('blockonomics_enabled_cryptos', '') ?: 'none'
));
