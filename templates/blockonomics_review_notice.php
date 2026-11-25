<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div id="bnomics-review-notice" class="notice is-dismissible">
    <img class="bnomics-review-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Blockonomics">
    <div class="bnomics-review-body">
        <p class="bnomics-review-text">
            <?php esc_html_e( "Hey, you've been using Blockonomics for over a week, that's awesome!", 'blockonomics-bitcoin-payments' ); ?><br>
            <?php esc_html_e( "If it's been useful, would you consider leaving us a 5-star review?", 'blockonomics-bitcoin-payments' ); ?>
        </p>
        <div class="bnomics-review-links">
            <a href="https://wordpress.org/support/plugin/blockonomics-bitcoin-payments/reviews/?filter=5#new-post" target="_blank" rel="noopener" data-action="done"><?php esc_html_e( 'Yes, you deserve it', 'blockonomics-bitcoin-payments' ); ?></a>
            <a href="#" data-action="later"><?php esc_html_e( 'No, maybe later', 'blockonomics-bitcoin-payments' ); ?></a>
            <a href="#" data-action="done"><?php esc_html_e( 'I already did', 'blockonomics-bitcoin-payments' ); ?></a>
        </div>
    </div>
</div>