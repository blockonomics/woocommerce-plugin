<div id="blockonomics_checkout">
    <div class="bnomics-card">
        <?php include plugin_dir_path(__FILE__) . 'blockonomics_header.php'; ?>
        <div class="bnomics-web3">
            <noscript>
                <div class="bnomics-message">
                    <p><?php esc_html_e('USDT requires JavaScript. Please enable JavaScript or use a different browser.', 'blockonomics-bitcoin-payments'); ?></p>
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
        </div>
    </div>
</div>
