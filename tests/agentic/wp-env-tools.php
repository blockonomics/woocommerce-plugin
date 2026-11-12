<?php
/**
 * Test helpers for agentic checkout scenarios.
 * npx wp-env run cli wp eval-file wp-content/plugins/woocommerce-plugin/tests/agentic/wp-env-tools.php <cmd> [args]
 *
 *   order <hash>            dump order id, WC status and blockonomics_payments rows
 *   expire <hash> <crypto>  backdate the agentic quote so the next read re-prices
 */

$cmd = isset($args[0]) ? $args[0] : '';
$blockonomics = new Blockonomics;

function agentic_tools_order_id($blockonomics, $hash) {
    $order_id = $blockonomics->decrypt_order_id($hash);
    if ($order_id === false) {
        WP_CLI::error('order not found for hash ' . $hash);
    }
    return $order_id;
}

switch ($cmd) {
    case 'order':
        global $wpdb;
        $order_id = agentic_tools_order_id($blockonomics, $args[1]);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT crypto, address, txid, payment_status, expected_satoshi, paid_satoshi, expected_fiat, paid_fiat FROM {$wpdb->prefix}blockonomics_payments WHERE order_id = %d",
            $order_id
        ), ARRAY_A);
        echo wp_json_encode(array(
            'order_id' => (int) $order_id,
            'status'   => wc_get_order($order_id)->get_status(),
            'rows'     => $rows,
        ), JSON_PRETTY_PRINT) . "\n";
        break;

    case 'expire':
        $order_id = agentic_tools_order_id($blockonomics, $args[1]);
        $wc_order = wc_get_order($order_id);
        $wc_order->update_meta_data('blockonomics_quote_expires_' . $args[2], time() - 1);
        $wc_order->save();
        WP_CLI::success('quote expired for order ' . $order_id);
        break;

    default:
        WP_CLI::error('usage: order <hash> | expire <hash> <crypto>');
}
