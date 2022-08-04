<?php
$blockonomics = new Blockonomics;

$error_type = get_query_var('blockonomics_error_type', NULL);
$error_msg = get_query_var('blockonomics_error_msg', NULL);

if (isset($order) && $order['status'] == -2) {
  // Partial payment is recevied
  $error_type = 'paid_amount_is_less';
}

$error_title = NULL;
$has_error = TRUE;

if ($error_type == 'paid_amount_is_less') {
    $error_title = '';
    $error_msg = __('Paid order BTC amount is less than expected. Contact merchant', 'blockonomics-bitcoin-payments');
} else if($error_type == 'address_generation_btc') {
    $error_title = __('Could not generate new Bitcoin address', 'blockonomics-bitcoin-payments');
    $error_msg = __('Note to webmaster: Please login to your admin panel, navigate to Settings > Blockonomics > Currencies and click <i>Test Setup</i> to diagnose the issue.', 'blockonomics-bitcoin-payments');
} else if($error_type == 'address_generation_bch') {
    $error_title = __('Could not generate new Bitcoin Cash address', 'blockonomics-bitcoin-payments');
    $error_msg = __('Note to webmaster: Please follow the instructions <a href="https://help.blockonomics.co/en/support/solutions/articles/33000253348-bch-setup-on-woocommerce" target="_blank">here</a> to configure BCH payments.', 'blockonomics-bitcoin-payments');
} else if ($error_type == 'api') {
    $error_title = __('Could not generate new address', 'blockonomics-bitcoin-payments');
} else {
    $has_error = FALSE;
}

if ($has_error) {
    
    set_query_var('error_title', $error_title);
    set_query_var('error_msg', $error_msg);
    set_query_var('order_id', $order_id);

    if ($template = locate_template('blockonomics_error.php') ) {
        load_template($template);
    } else {
        load_template(BLOCKONOMICS_PLUGIN_DIR.'templates/blockonomics_error.php');
    }
}
