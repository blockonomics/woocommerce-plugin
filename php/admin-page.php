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
                <h1 style="font-size: 28px; font-weight: bold; margin-bottom: 10px;">Get started with Blockonomics</h1>
                <div class="blockonomics-logo">
                    <img src="<?php echo plugins_url('../img/blockonomics_logo_black.svg', __FILE__); ?>" alt="Blockonomics Logo">
                </div>
            </div>
            <ul style="list-style-type: none; padding-left: 0; margin-top: 10px;">
                <li><span style="font-weight: bold;">I.</span> <a href="https://www.blockonomics.co/merchants" target="_blank">Sign up</a> to Blockonomics</li>
                <li><span style="font-weight: bold;">II.</span> Create a <a href="https://www.blockonomics.co/dashboard#/wallet" target="_blank">Wallet</a></li>
                <li><span style="font-weight: bold;">III.</span> Copy your <a href="https://www.blockonomics.co/merchants#/api" target="_blank">API Key</a> and Enter below</li>
            </ul>
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
            max-width: 550px; /* Reduced from 600px */
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        .bnomics-progress-bar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin: 20px auto 0;
            max-width: 590px; /* Reduced from 640px */
            position: relative;
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
            position: relative;
            bottom: 14px;
            transform: translateY(27px); /* Changed from 24px to 27px */
        }
        .bnomics-progress-step.active {
            background-color: #3fc47c; /* Changed from #4CAF50 to #3fc47c */
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
            background-color: #3fc47c; /* Changed from #4CAF50 to #3fc47c */
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
