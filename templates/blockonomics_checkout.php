<?php
// Controls that need the script (copy buttons, countdown, refresh button) start hidden and the script reveals them.
$bnomics_copy_icon = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
// "Copied" with a tick, shown over the box after a copy; the script writes the message into the status element
$bnomics_copied_tip = '<span class="bnomics-copied"><span role="status"></span><svg aria-hidden="true" width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2.19" stroke-linecap="round" stroke-linejoin="round"><path d="M4.04706 14C4.04706 8.55609 8.46025 4.1429 13.9042 4.1429C19.3482 4.1429 23.7613 8.55609 23.7613 14C23.7613 19.444 19.3482 23.8572 13.9042 23.8572C8.46025 23.8572 4.04706 19.444 4.04706 14Z"/><path d="M9.52325 14L12.809 17.2858L18.2852 11.8096"/></svg></span>';
?>
<div id="blockonomics_checkout" class="woocommerce"
    data-time-left="<?php echo esc_attr($context['time_left']); ?>"
    data-crypto-code="<?php echo esc_attr($crypto['code']); ?>"
    data-crypto-address="<?php echo esc_attr($order['address']); ?>"
    data-finish-order-url="<?php echo esc_attr($context['finish_order_url']); ?>"
    data-get-order-amount-url="<?php echo esc_attr($context['get_order_amount_url']); ?>"
    data-msg-copied="<?php esc_attr_e('Copied', 'blockonomics-bitcoin-payments'); ?>"
    data-msg-copy-failed="<?php esc_attr_e('Copy failed', 'blockonomics-bitcoin-payments'); ?>"
    data-msg-updated="<?php esc_attr_e('Amount updated:', 'blockonomics-bitcoin-payments'); ?>"
>
    <div class="bnomics-card">
        <?php include plugin_dir_path(__FILE__) . 'blockonomics_header.php'; ?>

        <div class="bnomics-pay">
            <div class="bnomics-body">
                <div class="bnomics-qr-col">
                    <div class="bnomics-qr-tile" role="img" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: crypto name, e.g. Bitcoin */ __('QR code for the %s payment', 'blockonomics-bitcoin-payments'), $crypto['name'])); ?>">
                        <?php echo $qrcode_svg_element ?>
                    </div>
                    <a href="<?php echo esc_attr($payment_uri); ?>" target="_blank" class="<?php echo esc_attr(trim('bnomics-wallet-btn button alt ' . $this->get_theme_button_class())); ?>"><?php esc_html_e('Open in wallet', 'blockonomics-bitcoin-payments'); ?> <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg></a>
                </div>
                <div class="bnomics-fields">
                    <div class="bnomics-field">
                        <span class="bnomics-label"><?php esc_html_e('Send exactly', 'blockonomics-bitcoin-payments'); ?></span>
                        <div class="bnomics-box" id="bnomics-amount-box">
                            <span class="bnomics-value bnomics-amount-value" id="bnomics-amount"><?php echo esc_html($order_amount); ?></span>
                            <span class="bnomics-unit"><?php echo esc_html(strtoupper($crypto['code'])); ?></span>
                            <button type="button" class="bnomics-copy" data-copy="bnomics-amount" data-needs-js hidden aria-label="<?php esc_attr_e('Copy amount', 'blockonomics-bitcoin-payments'); ?>"><?php echo $bnomics_copy_icon; ?></button>
                            <?php echo $bnomics_copied_tip; ?>
                        </div>
                    </div>
                    <div class="bnomics-field">
                        <span class="bnomics-label"><?php echo esc_html(sprintf(/* translators: %s: crypto name, e.g. Bitcoin */ __('To this %s address', 'blockonomics-bitcoin-payments'), $crypto['name'])); ?></span>
                        <div class="bnomics-box">
                            <span class="bnomics-value" id="bnomics-address"><?php echo esc_html($order['address']); ?></span>
                            <button type="button" class="bnomics-copy" data-copy="bnomics-address" data-needs-js hidden aria-label="<?php esc_attr_e('Copy address', 'blockonomics-bitcoin-payments'); ?>"><?php echo $bnomics_copy_icon; ?></button>
                            <?php echo $bnomics_copied_tip; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bnomics-bar">
                <span class="bnomics-status"><span class="bnomics-dot"></span><?php esc_html_e('Waiting for payment', 'blockonomics-bitcoin-payments'); ?></span>
                <span class="bnomics-quote">
                    <span class="bnomics-rate"><span class="bnomics-rate-text">1 <?php echo esc_html(strtoupper($crypto['code'])); ?> = <span id="bnomics-crypto-rate"><?php echo esc_html($crypto_rate_str); ?></span> <?php echo esc_html($order['currency']); ?><span data-needs-js hidden>, <?php esc_html_e('updates in', 'blockonomics-bitcoin-payments'); ?> <span class="bnomics-time-left">00:00</span></span></span></span>
                    <button type="button" class="bnomics-refresh" data-needs-js hidden aria-label="<?php esc_attr_e('Refresh price', 'blockonomics-bitcoin-payments'); ?>"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg></button>
                </span>
                <a href="" class="bnomics-paid-link" data-without-js><?php esc_html_e('Click here if already paid', 'blockonomics-bitcoin-payments'); ?></a>
            </div>
        </div>

        <div class="bnomics-detected" tabindex="-1">
            <span class="bnomics-detected-icon"><svg aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
            <span class="bnomics-detected-text"><?php esc_html_e('Payment detected', 'blockonomics-bitcoin-payments'); ?></span>
        </div>

        <span class="bnomics-sr" role="status"></span>
    </div>
</div>
