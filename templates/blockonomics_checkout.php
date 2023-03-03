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
     * $qrcode_svg_element: Generate QR Code when NoJS mode is active.
     */
?>
<style>
    .how-to-pay{
        cursor: pointer !important;
    }
    .how-to-pay:hover{
        background: #000000 !important;
    }
    .how-to-pay ul {
        margin:0;
        font-size:0.8em;
    }
    .how-to-pay b {
        font-weight: 800;
    }
</style>
<div id="blockonomics_checkout">
    <div class="bnomics-order-container">
        
        <!-- Spinner -->
        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <!-- Display Error -->
        <div class="bnomics-display-error">
            <h2><?=__('Display Error', 'blockonomics-bitcoin-payments')?></h2>
            <p><?=__('Unable to render correctly, Note to Administrator: Please try enabling other modes like No Javascript or Lite mode in the Blockonomics plugin > Advanced Settings.', 'blockonomics-bitcoin-payments')?></p>
        </div>
        
        <!-- Blockonomics Checkout Panel -->    
        <div class="bnomics-order-panel">
            <table>
                <tr>
                    <th class="how-to-pay" onclick="howToPay()">
                        <div>
                            How do I pay?
                            <div id="down-arrow" style="float:right">▼</div>
                            <div id="up-arrow" style="float:right;display:none;">▲</div>
                        </div>
                        <!-- Order Header -->
                        <div id="pay-instructions" style="display:none;margin:10px 0;">
                        <ul>
                            <li><b>Get a bitcoin wallet:</b> The first step is to get a bitcoin wallet. There are various types of wallets available such as software wallets, hardware wallets, and mobile wallets. Choose one that suits your needs.</li>
                            <li><b>Add bitcoin to your wallet:</b> You can add bitcoin to your wallet by purchasing it from a cryptocurrency exchange or receiving it from someone else.</li>
                            <li><b>Enter the bitcoin address and amount:</b> Open your bitcoin wallet and look for the option to send bitcoin. Enter the bitcoin address below and the amount to send.</li>
                            <li><b>Review the transaction:</b> Review the transaction details and make sure they are correct. Once you are satisfied, click on the send button. The transaction will be broadcast to the bitcoin network.</li>
                            <li><b>Wait for confirmation:</b> Bitcoin transactions usually take a few minutes to be confirmed. Once the transaction is confirmed, the order will be processed.</li>
                        </ul>
                        </div>
                    </th>
                </tr>
            </table>
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
                        <label class="bnomics-copy-address-text"><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></label>
                        <div class="bnomics-copy-container">
                            <input type="text" value="<?php echo $order['address']; ?>" id="bnomics-address-input" readonly/>
                            <span id="bnomics-address-copy" class="blockonomics-icon-copy"></span>
                            <span id="bnomics-show-qr" class="blockonomics-icon-qr"></span>
                        </div>
                        
                        <div class="bnomics-qr-code">
                            <div class="bnomics-qr">
                                <a href="<?php echo $payment_uri; ?>" target="_blank" class="bnomics-qr-link">
                                    <canvas id="bnomics-qr-code"></canvas>
                                </a>
                            </div>
                            <small class="bnomics-qr-code-hint">
                                <a href="<?php echo $payment_uri; ?>" target="_blank" class="bnomics-qr-link"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a>
                            </small>
                        </div>

                    </th>
                </tr>
            </table>
            <table>
                <tr>
                    <th>
                        <label class="bnomics-amount-text"><?=__('Amount of', 'blockonomics-bitcoin-payments')?> <?php echo strtolower($crypto['name']); ?> (<?php echo strtoupper($crypto['code']); ?>) <?=__('to send:', 'blockonomics-bitcoin-payments')?></label>
                        <label class="bnomics-copy-amount-text"><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></label>

                        <div class="bnomics-copy-container" id="bnomics-amount-copy-container">
                            <input type="text" value="<?php echo $order_amount; ?>" id="bnomics-amount-input" readonly/>
                            <span id="bnomics-amount-copy" class="blockonomics-icon-copy"></span>
                            <span id="bnomics-refresh" class="blockonomics-icon-refresh"></span>
                        </div>

                        <small class="bnomics-crypto-price-timer">
                            1 <?php echo strtoupper($crypto['code']); ?> = <span id="bnomics-crypto-rate"><?php echo $crypto_rate_str; ?></span> <?php echo $order['currency']; ?>, <?=__('updates in', 'blockonomics-bitcoin-payments')?> <span class="bnomics-time-left">00:00 min</span>
                        </small>
                    </th>
                </tr>

            </table>
        </div>
    </div>
</div>
<script>
    function howToPay() {
        var x = document.getElementById("pay-instructions");
        if (x.style.display === "none") {
            x.style.display = "block";
            document.getElementById("down-arrow").style.display = "none";
            document.getElementById("up-arrow").style.display = "block";
        } else {
            x.style.display = "none";
            document.getElementById("down-arrow").style.display = "block";
            document.getElementById("up-arrow").style.display = "none";
        }
    }
</script>