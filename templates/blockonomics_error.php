<?php
$blockonomics = new Blockonomics;
$order_hash = isset($_REQUEST["error_order"]) ? sanitize_text_field(wp_unslash($_REQUEST["error_order"])) : "";
$order_id = $blockonomics->decrypt_hash($order_hash);

$error_type = isset($_REQUEST["error_type"]) ? sanitize_text_field(wp_unslash($_REQUEST["error_type"])) : "unknown";
$error_msg = isset($_REQUEST["error_msg"]) ? sanitize_text_field(wp_unslash($_REQUEST["error_msg"])) : "";

$error_title = NULL;

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
    $error_title = __('Unknown Error Occurred', 'blockonomics-bitcoin-payments');
}

?>

<div class="bnomics-order-container">
    <!-- Heading row -->
    <div class="bnomics-order-heading">
      <div class="bnomics-order-heading-wrapper">
        <div class="bnomics-order-id">
          <span class="bnomics-order-number"><?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order_id; ?></span>
        </div>
      </div>
    </div>
    <div id="address-error-message">
        <?php
            if (isset($error_title)) {
        ?>
            <h2><?php echo $error_title ?></h2>
        <?php
            }
        ?>
        <p><?php echo $error_msg; ?></p>
    </div>
</div>
