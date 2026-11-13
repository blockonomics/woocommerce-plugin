<?php
$bnomics_copy_icon = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
?>
<div id="blockonomics_checkout"
    data-time-left="<?php echo esc_attr($context['time_left']); ?>"
    data-crypto-code="<?php echo esc_attr($crypto['code']); ?>"
    data-crypto-address="<?php echo esc_attr($order['address']); ?>"
    data-finish-order-url="<?php echo esc_attr($context['finish_order_url']); ?>"
    data-get-order-amount-url="<?php echo esc_attr($context['get_order_amount_url']); ?>"
>
    <div class="bnomics-card">
        <?php include plugin_dir_path(__FILE__) . 'blockonomics_header.php'; ?>

        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <div class="bnomics-display-error">
            <h2><?php esc_html_e('Display Error', 'blockonomics-bitcoin-payments'); ?></h2>
            <p><?php esc_html_e('Unable to render correctly, Note to Administrator: Please try enabling No Javascript mode from the Blockonomics plugin > Advanced Settings.', 'blockonomics-bitcoin-payments'); ?></p>
        </div>

        <div class="bnomics-pay">
            <div class="bnomics-body">
                <div class="bnomics-qr-col">
                    <div class="bnomics-qr-tile">
                        <canvas id="bnomics-qr-code"></canvas>
                    </div>
                    <a href="<?php echo esc_attr($payment_uri); ?>" target="_blank" class="bnomics-wallet-btn"><?php esc_html_e('Open in wallet', 'blockonomics-bitcoin-payments'); ?> <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg></a>
                </div>
                <div class="bnomics-fields">
                    <div class="bnomics-field">
                        <span class="bnomics-label"><?php esc_html_e('Send exactly', 'blockonomics-bitcoin-payments'); ?></span>
                        <div class="bnomics-box" id="bnomics-amount-box">
                            <span class="bnomics-value bnomics-amount-value" id="bnomics-amount"><?php echo esc_html($order_amount); ?></span>
                            <span class="bnomics-unit"><?php echo esc_html(strtoupper($crypto['code'])); ?></span>
                            <button type="button" class="bnomics-copy" data-copy="bnomics-amount" aria-label="<?php esc_attr_e('Copy', 'blockonomics-bitcoin-payments'); ?>"><?php echo $bnomics_copy_icon; ?></button>
                            <span class="bnomics-copied"><?php esc_html_e('Copied', 'blockonomics-bitcoin-payments'); ?></span>
                        </div>
                    </div>
                    <div class="bnomics-field">
                        <span class="bnomics-label"><?php echo esc_html(sprintf(/* translators: %s: crypto name, e.g. Bitcoin */ __('To this %s address', 'blockonomics-bitcoin-payments'), $crypto['name'])); ?></span>
                        <div class="bnomics-box">
                            <span class="bnomics-value" id="bnomics-address"><?php echo esc_html($order['address']); ?></span>
                            <button type="button" class="bnomics-copy" data-copy="bnomics-address" aria-label="<?php esc_attr_e('Copy', 'blockonomics-bitcoin-payments'); ?>"><?php echo $bnomics_copy_icon; ?></button>
                            <span class="bnomics-copied"><?php esc_html_e('Copied', 'blockonomics-bitcoin-payments'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bnomics-bar">
                <span class="bnomics-status"><span class="bnomics-dot"></span><?php esc_html_e('Waiting for payment', 'blockonomics-bitcoin-payments'); ?></span>
                <span class="bnomics-timer">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <span class="bnomics-time-left">00:00</span>
                </span>
            </div>
        </div>

        <div class="bnomics-detected">
            <span class="bnomics-detected-icon"><svg aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
            <span class="bnomics-detected-text"><?php esc_html_e('Payment detected', 'blockonomics-bitcoin-payments'); ?></span>
        </div>
    </div>
</div>
