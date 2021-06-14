<?php
$blockonomics = new Blockonomics;
$crypto = isset($_REQUEST["crypto"]) ? sanitize_key($_REQUEST["crypto"]) : "";
$order_id = isset($_REQUEST["show_order"]) ? sanitize_text_field($_REQUEST["show_order"]) : "";

$order = $blockonomics->get_order_by_id_and_crypto($order_id, $crypto);
if ($order['status'] >= 0){
  $blockonomics->redirect_finish_order($order_id);
}else {
  if($order['satoshi'] < 10000){
    $order_amount = rtrim(number_format($order['satoshi']/1.0e8, 8),0);
  }else{
    $order_amount = $order['satoshi']/1.0e8;
  }
  $check_payment_url = $blockonomics->get_parameterized_wc_url(array('show_order'=>$order_id, 'crypto'=>$crypto));
  $cryptos = $blockonomics->getActiveCurrencies();
  $qrcode_url = $blockonomics->get_parameterized_wc_url(array('qrcode'=>$cryptos[$crypto]['uri'] . ':' .$order['address'].'?amount='.$order_amount));
  ?>
  <div class="bnomics-order-container">
    <!-- Heading row -->
    <div class="bnomics-order-heading">
      <div class="bnomics-order-heading-wrapper">
        <div class="bnomics-order-id">
          <span class="bnomics-order-number"><?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order['order_id']?></span>
        </div>
      </div>
    </div>
    <!-- Blockonomics Checkout Panel -->
    <div class="bnomics-order-panel">
      <div class="bnomics-order-info">
        <div class="bnomics-bitcoin-pane">
          <div class="bnomics-btc-info">
            <!-- Left Side -->
            <!-- QR and Open in wallet -->
            <div class="bnomics-qr-code">
              <div class="bnomics-qr" style="width: 100%">
                <a href="<?php echo $cryptos[$crypto]['uri'] ?>:<?php echo $order['address'] ?>?amount=<?php echo $order_amount ?>" target="_blank">
                  <img style="margin: auto;width: 180px;" src="<?php echo $qrcode_url ?>" />
                </a>
              </div>
              <div class="bnomics-qr-code-hint"><a href="<?php echo $cryptos[$crypto]['uri'] ?>:<?php echo $order['address'] ?>?amount=<?php echo $order_amount ?>" target="_blank"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a></div>
            </div>
            <!-- Right Side -->
            <div class="bnomics-amount">
              <div class="bnomics-bg">
                <!-- Order Amounts -->
                <div class="bnomics-amount">
                  <div class="bnomics-amount-text">To pay, send exactly this <?php echo strtoupper($order['crypto'])?> amount</div>
                  <input type="text" id="bnomics-amount-input" class="bnomics-amount-input" value="<?php echo $order_amount ?>" readonly>
                </div>
                <!-- Order Address -->
                <div class="bnomics-address">
                  <div class="bnomics-address-text">To this <?php echo strtolower('bitcoin')?> address</div>
                  <input type="text" id="bnomics-address-input" class="bnomics-address-input" value="<?php echo $order['address']; ?>" readonly>
                </div>

                <div>
                  <a href="<?php echo $check_payment_url;?>">Click here if already paid</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Blockonomics How to pay + Credit -->
    <div class="bnomics-powered-by">
      <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank"><?=__('How do I pay? | Check reviews of this shop', 'blockonomics-bitcoin-payments')?></a><br>
      <div class="bnomics-powered-by-text bnomics-grey"><?=__('Powered by Blockonomics', 'blockonomics-bitcoin-payments')?></div>
    </div>
  </div>
<?php
}
