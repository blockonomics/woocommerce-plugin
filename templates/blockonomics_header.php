<?php
$bnomics_price_args = isset($order['currency']) ? array('currency' => $order['currency']) : array();
if ($order_id === '') {
    return;
}
?>
<div class="bnomics-header">
    <div class="bnomics-header-row">
        <span class="bnomics-order-id"><?php esc_html_e('Order #', 'blockonomics-bitcoin-payments'); ?><?php echo esc_html($order_id); ?></span>
        <?php if (isset($total)) { ?>
            <span class="bnomics-fiat<?php echo isset($paid_fiat) ? '' : ' bnomics-fiat-strong'; ?>"><?php echo wp_kses_post(wc_price($total, $bnomics_price_args)); ?> <small><?php echo esc_html($order['currency']); ?></small></span>
        <?php } ?>
    </div>
    <?php if (isset($paid_fiat)) { ?>
        <div class="bnomics-header-row">
            <span class="bnomics-order-id"><?php esc_html_e('Paid', 'blockonomics-bitcoin-payments'); ?></span>
            <span class="bnomics-fiat"><?php echo wp_kses_post(wc_price($paid_fiat, $bnomics_price_args)); ?> <small><?php echo esc_html($order['currency']); ?></small></span>
        </div>
        <div class="bnomics-header-row bnomics-header-remaining">
            <span><?php esc_html_e('Remaining', 'blockonomics-bitcoin-payments'); ?></span>
            <span class="bnomics-fiat bnomics-fiat-strong"><?php echo wp_kses_post(wc_price($order['expected_fiat'], $bnomics_price_args)); ?> <small><?php echo esc_html($order['currency']); ?></small></span>
        </div>
    <?php } ?>
</div>
