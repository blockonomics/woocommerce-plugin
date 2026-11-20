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
        <!-- Blockonomics Checkout Panel -->
        <div class="bnomics-web3-order-panel">


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

            <div class="blockonomics-body-container">
                <table class="blockonomics_checkout_table">
                    <tr>
                        <td>
                            <noscript>
                                <div id="address-error-message">
                                    <p><?= __('USDT requires JavaScript. Please enable JavaScript or use a different browser.', 'blockonomics-bitcoin-payments') ?></p>
                                </div>
                            </noscript>
                            <web3-payment
                                order_amount=<?php echo $order['expected_satoshi']/1e6; ?>
                                receive_address=<?php echo $order['address']; ?>
                                redirect_url=<?php echo $context['finish_order_url']; ?>
                                <?php if ($context['testmode'] === '1') {
                                    echo 'testmode=1';
                                }?>
                            ></web3-payment>
                        </td>
                    </tr>
                </table>
            </div>


            <table>
                <tr>
                    <td class="bnomics-footer-container">
                        <div class="bnomics-footer">
                            <div class="bnomics-copy-container" id="bnomics-amount-copy-container">
                                <small class="bnomics-crypto-price-timer">
                                    1 <?php echo strtoupper($crypto['code']); ?> = <span id="bnomics-crypto-rate"><?php echo $crypto_rate_str; ?></span> <?php echo $order['currency']; ?>
                                </small>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</div>