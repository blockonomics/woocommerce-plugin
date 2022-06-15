<?php
$blockonomics = new Blockonomics;
$order_hash = isset($_REQUEST["error_order"]) ? sanitize_text_field(wp_unslash($_REQUEST["error_order"])) : "";
$order_id = $blockonomics->decrypt_hash($order_hash);

$error_type = isset($_REQUEST["error_type"]) ? sanitize_text_field(wp_unslash($_REQUEST["error_type"])) : "unknown";
$error_msg = isset($_REQUEST["error_msg"]) ? sanitize_text_field(wp_unslash($_REQUEST["error_msg"])) : "";

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

<?php
    if ($error_type == "paid_amount_is_less") {
?>
    <!-- Payment Error -->
    <div class="bnomics-order-error-wrapper">
        <h3 class="warning bnomics-status-warning"><?=__('Paid order BTC amount is less than expected. Contact merchant', 'blockonomics-bitcoin-payments')?></h3>
    </div>
<?php
    } else if ($error_type == "display_error") {
?>
    <!-- Display Error -->
    <div id="display-error" class="bnomics-display-error">
        <h2><?=__('Display Error', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Unable to render correctly, Note to Administrator: Please enable lite mode in the Blockonomics plugin.', 'blockonomics-bitcoin-payments')?></p>
    </div>
<?php
    } else if ($error_type == "address_generation_btc") {
?>
    <!-- BTC Address Error -->
    <div id="address-error-btc">
        <h2><?=__('Could not generate new Bitcoin address', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Note to webmaster: Please login to your admin panel, navigate to Settings > Blockonomics > Currencies and click <i>Test Setup</i> to diagnose the issue.', 'blockonomics-bitcoin-payments')?></p>
    </div>

<?php
    } else if ($error_type == "address_generation_bch") {
?>
    <!-- BCH Address Error -->
    <div id="address-error-bch">
        <h2><?=__('Could not generate new Bitcoin Cash address', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Note to webmaster: Please follow the instructions <a href="https://help.blockonomics.co/en/support/solutions/articles/33000253348-bch-setup-on-woocommerce" target="_blank">here</a> to configure BCH payments.', 'blockonomics-bitcoin-payments')?></p>
    </div>

<?php
    } else if ($error_type == "api" || $error_type == "unknown") {
?>
    <!-- Gap limit + Duplicate Address Error -->
    <div id="address-error-message">
        <h2>
            <?php 
                if ($error_type == 'api') { 
                    echo __('Could not generate new address', 'blockonomics-bitcoin-payments');
                } else {
                    echo __('Unknown Error Occurred', 'blockonomics-bitcoin-payments');
                }
            ?>
        </h2>
        <p>
            <?php echo $error_msg; ?>
        </p>
    </div>
<?php
    }
?>

</div>