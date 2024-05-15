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
<div id="blockonomics_checkout">
    <div class="bnomics-order-container">

        <!-- Spinner -->
        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <!-- Display Error -->
        <div class="bnomics-display-error">
            <h2><?= __('Display Error', 'blockonomics-bitcoin-payments') ?></h2>
            <p><?= __('Unable to render correctly, Note to Administrator: Please try enabling No Javascript mode from the Blockonomics plugin > Advanced Settings.', 'blockonomics-bitcoin-payments') ?></p>
        </div>

        <!-- Blockonomics Checkout Panel -->
        <div class="bnomics-order-panel">


            <table>
                <tr>
                    <td class="bnomics-header-container">
                        <!-- Order Header -->
                        <div class="bnomics-header">
                            <span class="bnomics-order-id">
                                <?= __('Order #', 'blockonomics-bitcoin-payments') ?><?php echo $order_id; ?>
                            </span>

                            <div>
                                <span class="blockonomics-icon-cart"></span>
                                <?php echo $total ?> <?php echo $order['currency'] ?>
                            </div>
                        </div>

                        <?php
                        if (isset($paid_fiat)) {
                        ?>
                            <div class="bnomics-header-row">
                                <span class="bnomics-order-id">Paid Amount :</span>
                                <div>
                                    <?php echo $paid_fiat  ?> <?php echo $order['currency'] ?>
                                </div>
                            </div>

                            <div class="bnomics-header-row">
                                <span class="bnomics-order-id">Remaining Amount :</span>
                                <div>
                                    <?php echo  $order['expected_fiat'] ?> <?php echo $order['currency'] ?>
                                </div>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            </table>


            <table class="blockonomics_checkout_table">
                <tr>
                    <td>
                        <div class="blockonomics-body-container">
                            <div class="bnomics-qr-block">
                                <div class="bnomics-qr">
                                    <span class="scan-title">Scan</span>
                                    <a href="<?php echo $payment_uri; ?>" target="_blank" class="bnomics-qr-link">
                                        <canvas id="bnomics-qr-code"></canvas>
                                    </a>
                                    <a href="<?php echo $payment_uri; ?>" target="_blank" class="bnomics-qr-link"><?= __('Open in wallet', 'blockonomics-bitcoin-payments') ?></a>
                                </div>
                            </div>
                            
                            <div class="bnomics-order-address">
                                <span class="copy-title">Copy</span>
                                <div class="bnomics-address">
                                    <label class="bnomics-address-text"><?= __('Send ', 'blockonomics-bitcoin-payments') ?> <?php echo strtolower($crypto['name']); ?> <?= __('to this address:', 'blockonomics-bitcoin-payments') ?></label>
                                    <label class="bnomics-copy-address-text"><?= __('Copied to clipboard', 'blockonomics-bitcoin-payments') ?></label>
                                </div>

                                <div class="bnomics-copy-container">
                                    <input type="text" value="<?php echo $order['address']; ?>" id="bnomics-address-input" readonly data-copy />
                                </div>

                                <div class="bnomics-amount">
                                    <label class="bnomics-amount-text"><?= __('Amount of', 'blockonomics-bitcoin-payments') ?> <?php echo strtolower($crypto['name']); ?> (<?php echo strtoupper($crypto['code']); ?>) <?= __('to send:', 'blockonomics-bitcoin-payments') ?></label>
                                    <label class="bnomics-copy-amount-text"><?= __('Copied to clipboard', 'blockonomics-bitcoin-payments') ?></label>
                                </div>

                                <div class="bnomics-copy-container" id="bnomics-amount-copy-container">
                                    <input type="text" value="<?php echo $order_amount; ?>" id="bnomics-amount-input" readonly data-copy />
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>


            <table>
                <tr>
                    <td class="bnomics-footer-container">
                        <div class="bnomics-footer">
                            <div class="bnomics-copy-container" id="bnomics-amount-copy-container">
                                <small class="bnomics-crypto-price-timer">
                                    1 <?php echo strtoupper($crypto['code']); ?> = <span id="bnomics-crypto-rate"><?php echo $crypto_rate_str; ?></span> <?php echo $order['currency']; ?>, <?= __('updates in', 'blockonomics-bitcoin-payments') ?> <span class="bnomics-time-left">00:00 min</span>
                                </small>
                                <span id="bnomics-refresh" class="blockonomics-icon-refresh"></span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</div>