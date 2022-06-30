<?php
$blockonomics = new Blockonomics;
$crypto = isset($_REQUEST["crypto"]) ? sanitize_key($_REQUEST["crypto"]) : "";
$order_hash = isset($_REQUEST["show_order"]) ? sanitize_text_field(wp_unslash($_REQUEST["show_order"])) : "";
$order_id = $blockonomics->decrypt_hash($order_hash);
$order = $blockonomics->get_order_by_id_and_crypto($order_id, $crypto);

if ($order['status'] >= 0){
  // Payment is recevied
  $blockonomics->redirect_finish_order($order_id);
} else if ($order['status'] == -2) {
  // Partial payment is recevied
  $blockonomics->redirect_error_page($order_id, 'paid_amount_is_less');
} else {
  // Display Checkout page
  if($order['satoshi'] < 10000){
    $order_amount = rtrim(number_format($order['satoshi']/1.0e8, 8),0);
  } else{
    $order_amount = $order['satoshi']/1.0e8;
  }

  $cryptos = $blockonomics->getActiveCurrencies();
  $crypto = $cryptos[$crypto];

  $payment_uri = $crypto['uri'] . ":" . $order['address'] . "?amount=" . $order_amount;
?>

<div id="blockonomics_checkout">
  <div
    class="blockonomics-data" 
    data-crypto='<?php echo json_encode($crypto); ?>'
    data-crypto_address="<?php echo $order['address']; ?>"
    data-time_period="<?php echo get_option('blockonomics_timeperiod', 10); ?>"
    data-finish_order_url="<?php echo $blockonomics->get_wc_order_received_url($order_id); ?>"
    data-payment_uri="<?php echo $payment_uri; ?>"
  ></div>
  
  <div class="bnomics-order-container">
    <!-- Heading row -->
    <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
            <div class="bnomics-order-id">
                <span class="bnomics-order-number"><?=__('Order', 'blockonomics-bitcoin-payments')?> #<?php echo $order_id; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Spinner -->
    <div class="bnomics-spinner-wrapper">
        <div class="bnomics-spinner"></div>
    </div>
    
    <!-- Payment Expired -->
    <div class="bnomics-order-expired-wrapper">
        <h3 class="warning bnomics-status-warning"><?=__('Payment Expired', 'blockonomics-bitcoin-payments')?></h3><br/>
        <p><a href="#" id="bnomics-try-again"><?=__('Click here to try again', 'blockonomics-bitcoin-payments')?></a></p>
    </div>

    <!-- Blockonomics Checkout Panel -->
    <div class="bnomics-order-panel">
        <div class="bnomics-order-info">
            <div class="bnomics-bitcoin-pane">
                <div class="bnomics-btc-info">
                    <!-- Left Side -->
                    <div class="bnomics-qr-code">
                        <!-- QR and Open in wallet -->
                        <div class="bnomics-qr">
                            <a href="<?php echo $payment_uri; ?>" target="_blank">
                                <canvas id="bnomics-qr-code"></canvas>
                            </a>
                        </div>
                        <div class="bnomics-qr-code-hint">
                            <a href="<?php echo $payment_uri; ?>" target="_blank"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a>
                        </div>
                    </div>

                    <!-- Right Side -->
                    <div class="bnomics-amount">
                        <div class="bnomics-bg">
                            <!-- Order Amounts -->
                            <div class="bnomics-amount">
                                <div class="bnomics-amount-text"><?=__('To pay, send exactly this '.strtoupper($crypto['code']).' amount', 'blockonomics-bitcoin-payments')?></div>
                                <div class="bnomics-copy-amount-text"><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
                                <ul id="bnomics-amount-input" class="bnomics-amount-input">
                                    <li id="bnomics-amount-copy"><?php echo $order_amount; ?></li>
                                    <li><?php echo strtoupper($crypto['code']); ?></li>
                                    <li class="bnomics-grey"> ≈ </li>
                                    <li class="bnomics-grey"><?php echo $order['value']; ?></li>
                                    <li class="bnomics-grey"><?php echo $order['currency']; ?></li>
                                </ul>
                            </div>
                            <!-- Order Address -->
                            <div class="bnomics-address">
                                <div class="bnomics-address-text"><?=__('To this '.strtolower($crypto['name']).' address', 'blockonomics-bitcoin-payments')?></div>
                                <div class="bnomics-copy-address-text"><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
                                <ul id="bnomics-address-input" class="bnomics-address-input">
                                    <li id="bnomics-address-copy"><?php echo $order['address']; ?></li>
                                </ul>
                            </div>
                            <!-- Order Countdown Timer -->
                            <div class="bnomics-progress-bar-wrapper">
                                <div class="bnomics-progress-bar-container">
                                <div class="bnomics-progress-bar" style="width: 0%;"></div>
                            </div>
                        </div>

                        <span class="bnomics-time-left">00:00 min</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Blockonomics How to pay + Credit -->
    <div class="bnomics-powered-by">
        <a href="https://insights.blockonomics.co/how-to-pay-a-bitcoin-invoice/" target="_blank"><?=__('How do I pay? | Check reviews of this shop', 'blockonomics-bitcoin-payments')?></a><br>
    </div>
  </div>
</div>

<script type="text/javascript">
  let is_initialised = false

  setTimeout(() => {
    if (!is_initialised) 
      window.location.href = '<?php echo $blockonomics->get_order_error_url($order_id, 'display_error'); ?>'
  }, 30000);

  let blockonomics = new Blockonomics();
  blockonomics.init();

  is_initialised = true;
</script>

<?php
}
