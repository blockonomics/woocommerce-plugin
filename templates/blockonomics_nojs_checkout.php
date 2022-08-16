<?php
    /** 
     * Blockonomics Checkout Page (JS Disabled)
     * 
     * The following variables are available to be used in the template along with all WP Functions/Methods/Globals
     * 
     * $order: Order Object
     * $order_id: WooCommerce Order ID
     * $order_amount: Crypto Amount
     * $crypto: Crypto Object (code, name, uri) e.g. (btc, Bitcoin, bitcoin)
     * $payment_uri: Crypto URI with Amount and Protocol
     * $qrcode_url: QR Code URL, can be used for NoJS QRCode Generation
     */
?>

<div id="blockonomics_checkout" class="no-js">
  <div class="bnomics-order-container">
    <!-- Heading row -->
    <div class="bnomics-order-heading">
      <div class="bnomics-order-heading-wrapper">
        <div class="bnomics-order-id">
          <span class="bnomics-order-number"><?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order_id ?></span>
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
                <a href="<?php echo $payment_uri ?>" target="_blank">
                  <img style="margin: auto;width: 180px;" src="<?php echo $qrcode_url ?>" />
                </a>
              </div>
              <div class="bnomics-qr-code-hint"><a href="<?php echo $payment_uri ?>" target="_blank"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a></div>
            </div>
            <!-- Right Side -->
            <div class="bnomics-amount">
              <div class="bnomics-bg">
                <!-- Order Amounts -->
                <div class="bnomics-amount">
                  <div class="bnomics-amount-text"><?=__('To pay, send exactly this '.strtoupper($crypto['code']).' amount', 'blockonomics-bitcoin-payments')?></div>
                  <input type="text" id="bnomics-amount-input" class="bnomics-amount-input" style="cursor: text;" value="<?php echo $order_amount ?>" readonly>
                </div>
                <!-- Order Address -->
                <div class="bnomics-address">
                  <div class="bnomics-address-text"><?=__('To this '.strtolower($crypto['name']).' address', 'blockonomics-bitcoin-payments')?></div>
                  <input type="text" id="bnomics-address-input" class="bnomics-address-input" style="cursor: text;" value="<?php echo $order['address']; ?>" readonly>
                </div>

                <div>
                  <a href="">Click here if already paid</a>
                </div>
              </div>
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