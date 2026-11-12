<div id="blockonomics_checkout">
    <div class="bnomics-card">
        <?php include plugin_dir_path(__FILE__) . 'blockonomics_header.php'; ?>
        <div class="bnomics-error">
            <span class="bnomics-error-icon"><svg aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v6"/><path d="M12 17h.01"/></svg></span>
            <?php if (!empty($error_title)) { ?>
                <h2><?php echo $error_title ?></h2>
            <?php } ?>
            <div class="bnomics-message"><?php echo $error_msg; ?></div>
        </div>
    </div>
</div>
