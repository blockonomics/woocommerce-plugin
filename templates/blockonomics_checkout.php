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
     * $qrcode_svg_element: Generate QR Code when NoJS mode is active.
     */
?>
<div id="blockonomics_checkout">
    <div class="bnomics-order-container">

        <!-- Heading row -->
        <table>
            <tr>
                <th>
                    <h5>
                        <?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order_id; ?>
                    </h5>
                </th>
            </tr>
        </table>
        
        <!-- Spinner -->
        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <!-- Display Error -->
        <div class="bnomics-display-error">
            <h2><?=__('Display Error', 'blockonomics-bitcoin-payments')?></h2>
            <p><?=__('Unable to render correctly, Note to Administrator: Please try enabling other modes like No Javascript or Lite mode in the Blockonomics plugin > Advanced Settings.', 'blockonomics-bitcoin-payments')?></p>
        </div>
        
        <!-- Payment Expired -->
        <div class="bnomics-order-expired-wrapper">
            <h3><?=__('Payment Expired', 'blockonomics-bitcoin-payments')?></h3><br/>
            <p><a href="#" id="bnomics-try-again"><?=__('Click here to try again', 'blockonomics-bitcoin-payments')?></a></p>
        </div>

        <!-- Blockonomics Checkout Panel -->    
        <div class="bnomics-order-panel">
            <table>
                <tr>
                    <th class="bnomics-header">
                        <!-- Order Header -->
                        <strong><?=__(strtolower($crypto['name']), 'blockonomics-bitcoin-payments')?></strong>
                        
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
                        <strong class="bnomics-address-text">To Pay, send <?=__(strtolower($crypto['name']), 'blockonomics-bitcoin-payments')?> at this address:</strong>
                        <strong class="bnomics-copy-address-text"><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></strong>
                        <div class="bnomics-copy-container">
                            <input type="text" value="<?php echo $order['address']; ?>" id="bnomics-address-input" readonly/>
                            <a href="#" id="bnomics-address-copy" class="blockonomics-icon">
                                <span class="blockonomics-icon-copy"></span>
                            </a>
                            <a href="#" id="bnomics-show-qr" class="blockonomics-icon">
                                <span class="blockonomics-icon-qr"></span>
                            </a>
                        </div>
                        
                        <div class="bnomics-qr-code">
                            <div class="bnomics-qr">
                                <a href="<?php echo $payment_uri; ?>" target="_blank">
                                    <canvas id="bnomics-qr-code"></canvas>
                                </a>
                            </div>
                            <small class="block text-center">
                                <a href="<?php echo $payment_uri; ?>" target="_blank"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a>
                            </small>
                        </div>

                    </th>
                </tr>
            </table>
            <table>
                <tr>
                    <th>
                        <strong class="bnomics-amount-text">Amount of <?=__(strtolower($crypto['name']), 'blockonomics-bitcoin-payments')?> (<?=strtoupper($crypto['code'])?>) to send:</strong>
                        <strong class="bnomics-copy-amount-text"><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></strong>

                        <div class="bnomics-copy-container">
                            <input type="text" value="<?php echo $order_amount; ?>" id="bnomics-amount-input" readonly/>
                            <a href="#" id="bnomics-amount-copy" class="blockonomics-icon">
                                <span class="blockonomics-icon-copy"></span>
                            </a>
                        </div>

                        <small class="block text-center">
                            1 BTC = 30,00,000 <?php echo $order['currency']; ?>, updates in <span class="bnomics-time-left">00:00 min</span>
                        </small>


                        <a href="#" id="bnomics-try-again">
                            <button class="woocommerce-button button">
                                <span class="blockonomics-icon-refresh"></span> Refresh Now
                            </button>
                        </a>
                    </th>
                </tr>

            </table>
            
            <!-- Blockonomics Credit -->
            <div class="text-center">
                <small>
                    Powered by <a href="https://blockonomics.co">Blockonomics</a>
                </small>
            </div>
        </div>

    </div>
</div>
