<?php
    /** 
     * Blockonomics Checkout Page (JS Enabled)
     * 
     * The following variables are available to be used in the template along with all WP Functions/Methods/Globals
     * 
     * $order: Order Object
     * $order_id: WooCommerce Order ID
     * $order_amount: Crypto Amount
     * $crypto: Crypto Object (code, name, uri) e.g. (btc, Bitcoin, bitcoin)
     * $payment_uri: Crypto URI with Amount and Protocol
     * $crypto_rate_str: Conversion Rate of Crypto to Fiat. Please see comment on php/Blockonomics.php -> get_crypto_rate_from_params() on rate difference.
     * $api_uri: URL for WC-API for Blockonomics
     * $qrcode_svg_element: Generate QR Code when NoJS mode is active.
     */
?>
<div id="blockonomics_checkout" class="no-js">
    <div class="bnomics-order-container">
        
        <!-- Blockonomics Checkout Panel -->    
        <div class="bnomics-order-panel">
            <table>
                <tr>
                    <th class="bnomics-header">
                        <!-- Order Header -->
                        <span class="bnomics-order-id">
                            <?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order_id; ?>
                        </span>
                        
                        <div>
                            <span class="blockonomics-icon-cart"></span>
                            <?php echo $order['value'] ?> <?php echo $order['currency'] ?>
                        </div>
                    </th>
                </tr>
            </table>
            <table>
                <tr>
                    <th>
                        <!-- Order Address -->
                        <label class="bnomics-address-text"><?=__('To pay, send', 'blockonomics-bitcoin-payments')?> <?php echo strtolower($crypto['name']); ?> <?=__('to this address:', 'blockonomics-bitcoin-payments')?></label>
                        
                        <div class="bnomics-copy-container">
                          <input type="text" value="<?php echo $order['address']; ?>" id="bnomics-address-input" readonly/>
                        </div>
                        
                        <div class="bnomics-qr-code">
                            <div class="bnomics-qr">
                                <a href="<?php echo $payment_uri; ?>" target="_blank">
                                  <?php echo $qrcode_svg_element ?>
                                </a>
                            </div>
                            <small class="bnomics-qr-code-hint">
                                <a href="<?php echo $payment_uri; ?>" target="_blank"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a>
                            </small>
                        </div>

                    </th>
                </tr>
            </table>
            <table>
                <tr>
                    <th>
                        <label class="bnomics-amount-text"><?=__('Amount of', 'blockonomics-bitcoin-payments')?> <?php echo strtolower($crypto['name']); ?> (<?php echo strtoupper($crypto['code']); ?>) <?=__('to send:', 'blockonomics-bitcoin-payments')?></label>

                        <div class="bnomics-copy-container">
                          <input type="text" value="<?php echo $order_amount; ?>" id="bnomics-amount-input" readonly/>
                        </div>

                        <small class="bnomics-crypto-price-timer">
                            1 <?php echo strtoupper($crypto['code']); ?> = <span id="bnomics-crypto-rate"><?php echo $crypto_rate; ?></span> <?php echo $order['currency']; ?>, <?=__('updates in', 'blockonomics-bitcoin-payments')?> <span class="bnomics-time-left">00:00 min</span>
                        </small>

                        <a href="">
                          <button class="woocommerce-button button" id="bnomics-refresh">
                              <?=__('Click here if already paid', 'blockonomics-bitcoin-payments')?>
                          </button>
                        </a>
                    </th>
                </tr>

            </table>
        </div>
    </div>
</div>
