<div id="blockonomics_checkout" class="no-js">
    <div class="bnomics-card">
        <?php include plugin_dir_path(__FILE__) . 'blockonomics_header.php'; ?>
        <div class="bnomics-pay">
            <div class="bnomics-body">
                <div class="bnomics-qr-col">
                    <div class="bnomics-qr-tile">
                        <?php echo $qrcode_svg_element ?>
                    </div>
                    <a href="<?php echo esc_attr($payment_uri); ?>" target="_blank" class="bnomics-wallet-btn"><?php esc_html_e('Open in wallet', 'blockonomics-bitcoin-payments'); ?> <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg></a>
                </div>
                <div class="bnomics-fields">
                    <div class="bnomics-field">
                        <span class="bnomics-label"><?php esc_html_e('Send exactly', 'blockonomics-bitcoin-payments'); ?></span>
                        <div class="bnomics-box">
                            <span class="bnomics-value bnomics-amount-value"><?php echo esc_html($order_amount); ?></span>
                            <span class="bnomics-unit"><?php echo esc_html(strtoupper($crypto['code'])); ?></span>
                        </div>
                    </div>
                    <div class="bnomics-field">
                        <span class="bnomics-label"><?php echo esc_html(sprintf(/* translators: %s: crypto name, e.g. Bitcoin */ __('To this %s address', 'blockonomics-bitcoin-payments'), $crypto['name'])); ?></span>
                        <div class="bnomics-box">
                            <span class="bnomics-value"><?php echo esc_html($order['address']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bnomics-bar">
                <span class="bnomics-status"><span class="bnomics-dot"></span><?php esc_html_e('Waiting for payment', 'blockonomics-bitcoin-payments'); ?></span>
                <a href="" class="bnomics-wallet-btn"><?php esc_html_e('Click here if already paid', 'blockonomics-bitcoin-payments'); ?></a>
            </div>
        </div>
    </div>
</div>
