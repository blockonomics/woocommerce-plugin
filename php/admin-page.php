<?php
function blockonomics_setup_page() {
    $api_key = get_option('blockonomics_api_key', '');
    ?>
    <div class="wrap">
        <div class="bnomics-welcome-header">
            <h1></h1>
        </div>
        <div class="bnomics-progress-bar">
            <div class="bnomics-progress-line">
                <div class="bnomics-progress-line-inner"></div>
            </div>
            <div class="bnomics-progress-step active">1</div>
            <div class="bnomics-progress-line">
                <div class="bnomics-progress-line-inner"></div>
            </div>
            <div class="bnomics-progress-step">2</div>
            <div class="bnomics-progress-line">
                <div class="bnomics-progress-line-inner"></div>
            </div>
        </div>
        <div class="blockonomics-setup-wizard">
            <div class="bnomics-wizard-heading">
                <h2>Get started with Blockonomics</h2>
                <div class="blockonomics-logo">
                    <img src="<?php echo plugins_url('../img/logo.png', __FILE__); ?>" alt="Blockonomics Logo">
                </div>
            </div>
            <ol>
                <li><a href="https://www.blockonomics.co/merchants" target="_blank">Sign up</a> to Blockonomics</li>
                <li>Create a <a href="https://www.blockonomics.co/merchants#/wallet" target="_blank">Wallet</a></li>
                <li>Copy your <a href="https://www.blockonomics.co/merchants#/api" target="_blank">API Key</a> and Enter below</li>
            </ol>
            <form method="post" action="options.php">
                <?php settings_fields('blockonomics_options'); ?>
                <?php do_settings_sections('blockonomics_options'); ?>
                <input type="text" name="blockonomics_api_key" value="<?php echo esc_attr($api_key); ?>" placeholder="Enter your API Key" style="width: 100%;">
                <?php submit_button('Continue', 'primary', 'submit', false); ?>
            </form>
        </div>
    </div>
    <style>
        .bnomics-welcome-header {
            max-width: 600px;
            margin: 20px auto;
        }
        .bnomics-welcome-header h1 {
            font-size: 28px;
            margin: 0;
        }
        .blockonomics-setup-wizard {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .bnomics-progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px auto 30px;
            max-width: 600px;
        }
        .bnomics-progress-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            z-index: 2;
            font-size: 18px;
        }
        .bnomics-progress-step.active {
            background-color: #4CAF50;
        }
        .bnomics-progress-line {
            flex-grow: 1;
            height: 12px;
            background-color: #e0e0e0;
            position: relative;
        }
        .bnomics-progress-line-inner {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            background-color: #4CAF50;
        }
        .bnomics-progress-bar .bnomics-progress-line:first-child .bnomics-progress-line-inner,
        .bnomics-progress-bar .bnomics-progress-line:nth-child(2) .bnomics-progress-line-inner {
            width: 100%;
        }
        .bnomics-progress-bar .bnomics-progress-line:nth-child(3) .bnomics-progress-line-inner,
        .bnomics-progress-bar .bnomics-progress-line:last-child .bnomics-progress-line-inner {
            width: 0%;
        }
        .bnomics-wizard-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .bnomics-wizard-heading h2 {
            font-size: 24px;
            margin: 0;
        }
        .blockonomics-logo img {
            max-width: 100px;
            height: auto;
        }
        .blockonomics-setup-wizard ol {
            margin-bottom: 20px;
        }
        .blockonomics-setup-wizard li {
            margin-bottom: 10px;
        }
        .blockonomics-setup-wizard input[type="text"] {
            margin-bottom: 20px;
        }
    </style>
    <?php
}

// Register settings
function blockonomics_register_settings() {
    register_setting('blockonomics_options', 'blockonomics_api_key');
}
add_action('admin_init', 'blockonomics_register_settings');
