<?php
/**
 * Blockonomics Widget Checkout Template
 *
 * Renders the BlockonomicsCheckout widget.  The JS widget calls
 * POST /checkout on blockonomics.co directly (using store_uid) to obtain
 * payment addresses — no server-side address generation needed here.
 *
 * Context variables extracted from $context by load_blockonomics_template():
 *
 * @var string $store_uid        Blockonomics store UID (public identifier).
 * @var int    $order_id         WooCommerce order ID.
 * @var float  $amount           Order total in fiat.
 * @var string $currency         ISO 4217 fiat currency code, e.g. 'USD'.
 * @var int    $timer            Payment window in seconds.
 * @var string $finish_order_url WooCommerce order-received URL for redirect.
 */
?>
<div id="bck-payment"></div>
<script>
(function () {
    function init() {
        if (typeof window.BlockonomicsCheckout === 'undefined') {
            var el = document.getElementById('bck-payment');
            if (el) {
                el.innerHTML = '<div class="blockonomics_error"><p>Payment widget failed to load. Please refresh the page.</p><small>store_uid: <?= esc_html($store_uid) ?></small></div>';
            }
            return;
        }
        window.BlockonomicsCheckout.show({
            msg_area:     'bck-payment',
            store_uid:    <?= json_encode($store_uid) ?>,
            wp_order_id:  <?= json_encode((string) $order_id) ?>,
            amount:       <?= (float) $amount ?>,
            currency:     <?= json_encode($currency) ?>,
            timer:        <?= (int) $timer ?>,
            redirect_url: <?= json_encode($finish_order_url) ?>,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
</script>
