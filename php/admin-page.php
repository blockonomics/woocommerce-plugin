<?php
function blockonomics_setup_page() {
    // Check if form is submitted
    if (isset($_POST['submit'])) {
        // Verify nonce for security
        if (!isset($_POST['blockonomics_setup_nonce']) || !wp_verify_nonce($_POST['blockonomics_setup_nonce'], 'blockonomics_setup_action')) {
            wp_die('Security check failed');
        }

        // Check if API key is provided and not empty
        if (isset($_POST['blockonomics_api_key']) && !empty($_POST['blockonomics_api_key'])) {
            $api_key = sanitize_text_field($_POST['blockonomics_api_key']);
            update_option('blockonomics_api_key', $api_key);
            
            // First check if any wallet is added
            $wallets_url = 'https://www.blockonomics.co/api/v2/wallets';
            $response = wp_remote_get($wallets_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                )
            ));
            if (is_wp_error($response)) {
                $error_message = 'Failed to check wallets: ' . $response->get_error_message();
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 401){
                    $error_message = 'Invalid API key';
                } else if ($response_code === 200){
                    $wallets = json_decode(wp_remote_retrieve_body($response), true);
                    if (empty($wallets['data'])){
                        $error_message = 'Please create a wallet';
                    } else {
                    // Wallet exists, save API key and proceed with test setup
                    update_option('blockonomics_api_key', $api_key);

                    // Create Blockonomics instance to test the API key
                    $bnomics = new Blockonomics();
                    $test_result = $bnomics->test_one_crypto('btc');

                    if (is_array($test_result) && isset($test_result['error'])) {
                        // Test setup failed
                        $error_message = $test_result['error'];
                    } else {
                        // Test setup successful, redirect to step 2
                        wp_redirect(admin_url('admin.php?page=blockonomics-setup&step=2'));
                        exit;
                    }
                }
            }
        }
        } else {
            $error_message = 'Please enter your API key';
        }
    }

    // Handle store name submission
    if (isset($_POST['submit_store'])) {
        if (isset($_POST['store_name']) && !empty($_POST['store_name'])) {
            $store_name = sanitize_text_field($_POST['store_name']);
            // TODO: Add HTTP POST request to Blockonomics API to update store name
            // Current code only updates WordPress option
            update_option('blockonomics_store_name', $store_name);
            wp_redirect(admin_url('admin.php?page=blockonomics-setup&step=2&setup_complete=1'));
            exit;
        } else {
            $store_error = 'Please enter your store name';
        }
    }

    $api_key = get_option('blockonomics_api_key', '');
    $current_step = isset($_GET['step']) ? intval($_GET['step']) : 1;
    if (empty($api_key)) {
        $current_step = 1;
    }

    // Dummy API call to check store name
    $store_name = '';
    if ($current_step == 2) {
        // TODO: Add HTTP GET request to Blockonomics API to fetch store name
        // using the stored api_key
        // Current code only reads from WordPress options
        $store_name = get_option('blockonomics_store_name', '');
        if (empty($store_name)) {
            $needs_store_name = true;
        }
    }
    ?>
    <div class="wrap">
        <div class="bnomics-welcome-header">
            <h1>Welcome</h1>
        </div>
        <!-- Moved progress bar outside the setup wizard -->
        <div class="bnomics-progress-bar">
            <div class="bnomics-progress-line">
                <div class="bnomics-progress-line-inner" style="width: 100%;"></div>
            </div>
            <div class="bnomics-progress-step active">1</div>
            <div class="bnomics-progress-line">
                <div class="bnomics-progress-line-inner" style="width: <?php echo $current_step >= 2 ? '100%' : '0%'; ?>;"></div>
            </div>
            <div class="bnomics-progress-step <?php echo $current_step >= 2 ? 'active' : ''; ?>">2</div>
            <div class="bnomics-progress-line">
                <div class="bnomics-progress-line-inner" style="width: <?php echo ($current_step == 2 && !isset($needs_store_name)) ? '100%' : '0%'; ?>;"></div>
            </div>
        </div>
        <div class="blockonomics-setup-wizard">
            <?php if ($current_step == 1): ?>
                <div class="bnomics-wizard-heading">
                    <h2>Get started with Blockonomics</h2>
                    <div class="blockonomics-logo">
                        <img src="<?php echo plugins_url('../img/blockonomics_logo_black.svg', __FILE__); ?>" alt="Blockonomics Logo">
                    </div>
                </div>
                <ol>
                    <li><a href="https://www.blockonomics.co/merchants" target="_blank">Sign up</a> to Blockonomics</li>
                    <li>Create a <a href="https://www.blockonomics.co/dashboard#/wallet" target="_blank">Wallet</a></li>
                    <li>Copy your <a href="https://www.blockonomics.co/merchants#/api" target="_blank">API Key</a> and Enter below</li>
                </ol>
                <form method="post" action="">
                    <?php 
                    // Display error message if exists
                    if (isset($error_message)): ?>
                        <div class="notice notice-error">
                            <p><?php echo esc_html($error_message); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php wp_nonce_field('blockonomics_setup_action', 'blockonomics_setup_nonce'); ?>
                    <input type="text" name="blockonomics_api_key" value="<?php echo esc_attr($api_key); ?>" placeholder="Enter your API Key" style="width: 100%;">
                    <?php submit_button('Continue', 'primary', 'submit', false); ?>
                </form>
            <?php else: ?>
                <?php if (isset($needs_store_name) && $needs_store_name): ?>
                    <!-- Store Name Input Screen -->
                    <script>
                        // Fill progress bar before step 2 circle
                        document.addEventListener('DOMContentLoaded', function() {
                            document.querySelector('.bnomics-progress-line:nth-child(3) .bnomics-progress-line-inner').style.width = '100%';
                        });
                    </script>
                    <div class="bnomics-wizard-heading">
                        <h2>Enter Your Store Name</h2>
                        <div class="blockonomics-logo">
                            <img src="<?php echo plugins_url('../img/blockonomics_logo_black.svg', __FILE__); ?>" alt="Blockonomics Logo">
                        </div>
                    </div>
                    <form method="post" action="">
                        <?php if (isset($store_error)): ?>
                            <div class="notice notice-error">
                                <p><?php echo esc_html($store_error); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php wp_nonce_field('blockonomics_setup_action', 'blockonomics_setup_nonce'); ?>
                        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
                            
                            <input type="text" 
                                   name="store_name" 
                                   placeholder="Enter your store name" 
                                   style="flex: 1;"
                                   value="<?php echo esc_attr($store_name); ?>">
                        </div>
                        <?php submit_button('Continue', 'primary', 'submit_store', false); ?>
                    </form>
                <?php else: ?>
                    <!-- Final Success Screen -->
                    <div class="bnomics-wizard-heading">
                        <h3>Congrats! Your store <?php echo esc_html(strtoupper($store_name)); ?> setup is complete!</h3>
                        <div class="blockonomics-logo">
                            <img src="<?php echo plugins_url('../img/blockonomics_logo_black.svg', __FILE__); ?>" alt="Blockonomics Logo">
                        </div>
                    </div>
                    <p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=blockonomics'); ?>" class="button button-primary">Done</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Register settings
function blockonomics_register_settings() {
    register_setting('blockonomics_options', 'blockonomics_api_key');
}
add_action('admin_init', 'blockonomics_register_settings');
