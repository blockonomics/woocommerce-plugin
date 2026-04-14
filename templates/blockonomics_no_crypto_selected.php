<div class="bnomics-order-container">
    <h3>
    <?php esc_html_e('No crypto currencies are enabled for checkout', 'blockonomics-bitcoin-payments'); ?>
    </h3>
    <p>
    <?php
    printf(
    esc_html__('Note to webmaster: Please enable Payment method on %s to enable crypto payments.', 'blockonomics-bitcoin-payments'),
    '<a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank">Stores</a>'
    );
    ?>
    </p>
</div>
