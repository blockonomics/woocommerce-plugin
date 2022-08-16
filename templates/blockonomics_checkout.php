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
     * $qrcode_url: QR Code URL, can be used for NoJS QRCode Generation
     */
?>
<div id="blockonomics_checkout">
    <div class="bnomics-order-container">
        <!-- Heading row -->
        <div class="bnomics-order-heading">
            <div class="bnomics-order-heading-wrapper">
                <div class="bnomics-order-id">
                    <span class="bnomics-order-number"><?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order_id; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Spinner -->
        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <!-- Display Error -->
        <div class="bnomics-display-error">
            <h2><?=__('Display Error', 'blockonomics-bitcoin-payments')?></h2>
            <p><?=__('Unable to render correctly, Note to Administrator: Please enable lite mode in the Blockonomics plugin.', 'blockonomics-bitcoin-payments')?></p>
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
    
    <!-- Note to Template Editor:
        Blockonomics Data Attributes for JS,
        Modifying the div below can cause unexpected errors and rendering issues
    -->
</div>
