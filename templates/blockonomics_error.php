<div class="bnomics-order-container">
    
    <!-- Heading row -->
    <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
            <div class="bnomics-order-id">
                <span class="bnomics-order-number"><?=__('Order #', 'blockonomics-bitcoin-payments')?><?php echo $order_id; ?></span>
            </div>
        </div>
    </div>
    
    <div id="address-error-message">
        <?php
            if (isset($error_title)) {
        ?>
            <h2><?php echo $error_title ?></h2>
        <?php
            }
        ?>
        <p><?php echo $error_msg; ?></p>
    </div>
</div>
