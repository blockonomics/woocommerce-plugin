<?php
/**
 * Plugin Name: WordPress Bitcoin Payments - Blockonomics
 * Plugin URI: https://github.com/blockonomics/woocommerce-plugin
 * Description: Accept Bitcoin Payments on your WooCommerce-powered website with Blockonomics
 * Version: 2.4.1
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: blockonomics-bitcoin-payments
 * Domain Path: /languages/
 * WC tested up to: 4.7.1
 */

/*  Copyright 2017 Blockonomics Inc.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Initialize hooks needed for the payment gateway
 */
function blockonomics_woocommerce_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'WC_Gateway_Blockonomics.php';
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
    
    add_action('admin_menu', 'add_page');
    add_action('init', 'woocommerce_handle_blockonomics_return');
    add_action('init', 'load_plugin_translations');
    add_action('woocommerce_order_details_after_order_table', 'nolo_custom_field_display_cust_order_meta', 10, 1);
    add_action('woocommerce_email_customer_details', 'nolo_bnomics_woocommerce_email_customer_details', 10, 1);
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockonomics_gateway');
    add_filter('clean_url', 'bnomics_async_scripts', 11, 1 );

    /**
     * Add this Gateway to WooCommerce
     **/
    function woocommerce_add_blockonomics_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Blockonomics';
        return $methods;
    }

    function load_plugin_translations()
    {
        load_plugin_textdomain('blockonomics-bitcoin-payments', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    function woocommerce_handle_blockonomics_return()
    {
        if (!isset($_GET['return_from_blockonomics'])) {
            return;
        }

        if (isset($_GET['cancelled'])) {
            $order = new WC_Order($_GET['order']['custom']);
            if ($order->status != 'completed') {
                $order->update_status('failed', __('Customer cancelled blockonomics payment', 'blockonomics-bitcoin-payments'));
            }
        }

        // Blockonomics order param interferes with woocommerce
        unset($_GET['order']);
        unset($_REQUEST['order']);
        if (isset($_GET['order_key'])) {
            $_GET['order'] = $_GET['order_key'];
        }
    }


    // Add entry in the settings menu
    function add_page()
    {
        $blockonomics = new Blockonomics;

        if (isset($_POST['generateSecret']))
        {
            generate_secret(true);
        }

        $api_key = $blockonomics->get_api_key();
        
        // get_api_key() will return api key or temp api key
        // if both are null, generate new blockonomics guest account with temporary wallet
        // temp wallet will be used with temp api key
        if (!$api_key)
        {
            generate_secret();
            $callback_url = get_callback_url();
            $response = $blockonomics->get_temp_api_key($callback_url);

            if ($response->response_code != 200)
            {
                $message = __('Error while generating temporary APIKey: '. isset($response->message) ? $response->message : '', 'blockonomics-bitcoin-payments');
                display_admin_message($message, 'error');
            }
            else
            {
                update_option("blockonomics_temp_api_key", isset($response->apikey) ? $response->apikey : '');
            }
        }

        add_options_page(
            'Blockonomics', 'Blockonomics', 'manage_options',
            'blockonomics_options', 'show_options'
        );

        if (get_option('blockonomics_api_updated') == 'true' && isset($_GET['settings-updated']) ? $_GET['settings-updated'] : '' == 'true')
        {
            $message = __('API Key updated! Please click on Test Setup to verify Installation. ', 'blockonomics-bitcoin-payments');
            display_admin_message($message, 'updated');
        }

        if (isset($_POST['runTest']))
        {
            $setup_errors = $blockonomics->testSetup();

            if($setup_errors)
            {
                display_admin_message($setup_errors, 'error');
            }
            else
            {
                $message = __('Congrats ! Setup is all done', 'blockonomics-bitcoin-payments');
                display_admin_message($message, 'updated');

                $message = $blockonomics->make_withdraw();
                if ($message) {
                    display_admin_message($message[0], $message[1]);
                }
            }
        }
    }

    function display_admin_message($msg, $type)
    {
        add_settings_error('option_notice', 'option_notice', $msg, $type);
    }

    function generate_secret($force_generate = false)
    {
        $callback_secret = get_option("blockonomics_callback_secret");
        if (!$callback_secret || $force_generate) {
            $callback_secret = sha1(openssl_random_pseudo_bytes(20));
            update_option("blockonomics_callback_secret", $callback_secret);
        }
    }

    function get_callback_url()
    {
        $callback_secret = get_option('blockonomics_callback_secret');
        $callback_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $callback_url);
        return $callback_url;
    }

    function show_options()
    {
        ?>

        <script type="text/javascript">
            function gen_secret() {
                document.generateSecretForm.submit();
            }
            function value_changed() {
                document.getElementById('blockonomics_api_updated').value = 'true';
            }
            function checkForAPIKeyChange() {
                if (document.getElementById('blockonomics_api_updated').value == 'true') {
                    alert('Settings have changed, click on Save first');
                } else {
                    document.testSetupForm.submit();
                }
            }
            function validateBlockonomicsForm() {
                newApiKey = document.getElementById("blockonomics_api_key").value;
                apiKeyChanged = newApiKey != "<?php echo get_option("blockonomics_api_key")?>";
                if (apiKeyChanged && newApiKey.length != 43) {
                    alert("ERROR: Invalid APIKey");
                    return false
                }
                return true;
            }
            function show_advanced() {
                document.getElementById("advanced_title").style.display = 'none';
                document.getElementById("advanced_window").style.display = 'block';
            }
            function show_basic() {
                document.getElementById("advanced_title").style.display = 'block';
                document.getElementById("advanced_window").style.display = 'none';
            }
        </script>
        <div class="wrap">
            <h2>Blockonomics</h2>
            <form method="post" id="myform" onsubmit="return validateBlockonomicsForm()" action="options.php">
                <?php wp_nonce_field('update-options') ?>
                <input type="hidden" name="blockonomics_api_updated" id="blockonomics_api_updated" value="false">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">CALLBACK URL 
                            <a href="javascript:gen_secret()" id="generate-callback" style="font:400 20px/1 dashicons;margin-left: 5px;top: 4px;position:relative;text-decoration: none;" title="Generate New Callback URL">&#xf463;</a>
                        </th>
                        <td><?php echo get_callback_url(); ?></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php echo __('BCH Enabled (Select if you want to accept Bitcoin Cash)')?></th>
                        <td><input type="checkbox" name="blockonomics_bch" value="1" <?php checked("1", get_option('blockonomics_bch')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Destination BTC wallet for payments</th>
                        <td>
                            <?php
                            $total_received = get_option('blockonomics_temp_withdraw_amount') / 1.0e8;
                            $api_key = get_option("blockonomics_api_key");
                            $temp_api_key = get_option("blockonomics_temp_api_key");
                            if ($temp_api_key && !$api_key && !($total_received > 0)): ?>

                            <p><b>Blockonomics Wallet</b> (Balance: 0 BTC)</p>
                            <p>We are using a temporary wallet on Blockonomics to receive your payments.</p>
                            <p>To receive payments directly to your wallet (recommended) -> Follow Wizard by clicking on <i>Get Started for Free</i> on <a href="https://www.blockonomics.co/merchants" target="_blank">Merchants</a> and enter the APIKey below [<a href="https://blog.blockonomics.co/how-to-accept-bitcoin-payments-on-woocommerce-using-blockonomics-f18661819a62">Blog Instructions</a>]</p>

                            <?php elseif ($temp_api_key && $total_received > 0): ?>

                            <p><b>Blockonomics Wallet</b> (Balance: <?php echo "$total_received"; ?> BTC)</p>
                            <?php if (!$api_key): ?>
                            <p> To withdraw, follow wizard by clicking on <i>Get Started for Free</i> on <a href="https://www.blockonomics.co/merchants" target="_blank">Merchants</a>, then enter the APIKey below [<a href="https://blog.blockonomics.co/how-to-accept-bitcoin-payments-on-woocommerce-using-blockonomics-f18661819a62">Blog Instructions</a>]
                            </p>
                            <?php else: ?>
                            <p> To withdraw, Click on <b>Test Setup</b></p>
                            <?php endif; ?>

                            <?php elseif ($api_key): ?>

                            <p><b>Your wallet</b></p>
                            <p>Payments will go directly to the wallet which your setup on <a href="https://www.blockonomics.co/merchants" target="_blank">Blockonomics</a>. There is no need for withdraw</p>

                            <?php else: ?>

                            <p><b>ERROR:</b> No wallet set up</p>

                            <?php endif; ?>

                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">APIKey</th>
                        <td><input onchange="value_changed()" type="text" id="blockonomics_api_key" name="blockonomics_api_key" value="<?php echo get_option('blockonomics_api_key'); ?>" /></td>
                    </tr>
                </table>
                <p id="advanced_title" style="font-weight:bold"><a href="javascript:show_advanced()">Advanced Settings &#9660;</a></p>
                <div id="advanced_window" style="display:none">
                    <p style="font-weight:bold"><a href="javascript:show_basic()">Advanced Settings &#9650;</a></p>
                    <table class="form-table">
                        <tr valign="top"><th scope="row"><?php echo __('Time period of countdown timer on payment page (in minutes)', 'blockonomics-bitcoin-payments')?></th>
                            <td>
                                <select name="blockonomics_timeperiod" />
                                    <option value="10" <?php selected(get_option('blockonomics_timeperiod'), 10); ?>>10</option>
                                    <option value="15" <?php selected(get_option('blockonomics_timeperiod'), 15); ?>>15</option>
                                    <option value="20" <?php selected(get_option('blockonomics_timeperiod'), 20); ?>>20</option>
                                    <option value="25" <?php selected(get_option('blockonomics_timeperiod'), 25); ?>>25</option>
                                    <option value="30" <?php selected(get_option('blockonomics_timeperiod'), 30); ?>>30</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Extra Currency Rate Margin % (Increase live fiat to BTC rate by small percent)', 'blockonomics-bitcoin-payments')?></th>
                            <td><input type="number" min="0" max="4" step="0.01" name="blockonomics_margin" value="<?php echo esc_attr( get_option('blockonomics_margin', 0) ); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Underpayment Slack % (Allow payments that are off by a small percentage)', 'blockonomics-bitcoin-payments')?></th>
                            <td><input type="number" min="0" max="10" step="0.01" name="blockonomics_underpayment_slack" value="<?php echo esc_attr( get_option('blockonomics_underpayment_slack', 0) ); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Display Payment Page in Lite Mode (Enable this if you are having problems in rendering checkout page)', 'blockonomics-bitcoin-payments')?></th>
                            <td><input type="checkbox" name="blockonomics_lite" value="1" <?php checked("1", get_option('blockonomics_lite')); ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('No Javascript checkout page (Enable this if you have majority customer that use tor like browser that block Javascript)', 'blockonomics-bitcoin-payments')?></th>
                            <td><input type="checkbox" name="blockonomics_nojs" value="1" <?php checked("1", get_option('blockonomics_nojs')); ?> /></td>
                        </tr>
						<tr valign="top">
                            <th scope="row"><?php echo __('Network Confirmations required for payment to complete)', 'blockonomics-bitcoin-payments')?></th>
                            <td><select name="blockonomics_network_confirmation" />
                                    <option value="2" <?php selected(get_option('blockonomics_network_confirmation'), 2); ?>>2 (Recommended)</option>
                                    <option value="1" <?php selected(get_option('blockonomics_network_confirmation'), 1); ?>>1</option>
                                    <option value="0" <?php selected(get_option('blockonomics_network_confirmation'), 0); ?>>0</option>
                                </select></td>
                        </tr>
                    </table>
                </div>
                <p class="submit">
                    <input type="submit" class="button-primary" value="Save"/>
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="page_options" value="blockonomics_api_key,blockonomics_bch,blockonomics_timeperiod,blockonomics_margin,blockonomics_gen_callback,blockonomics_api_updated,blockonomics_underpayment_slack,blockonomics_lite,blockonomics_nojs,blockonomics_network_confirmation" />
                    <input onclick="checkForAPIKeyChange();" class="button-primary" name="test-setup-submit" value="Test Setup" style="max-width:85px;">
                </p>
            </form>
            <form method="POST" name="testSetupForm">
                <p class="submit">
                    <input type="hidden" name="page" value="blockonomics_options">
                    <input type="hidden" name="runTest" value="true">
                </p>
            </form>
            <form method="POST" name="generateSecretForm">
                <p class="submit">
                    <input type="hidden" name="generateSecret" value="true">
                </p>
            </form>
    </div>

    <?php
    }
    function bnomics_display_tx_info($order, $email=false)
    {
        $blockonomics = new Blockonomics();
        $active_cryptos = $blockonomics->getActiveCurrencies();
        foreach ($active_cryptos as $crypto) {
            $txid = get_post_meta($order->get_id(), 'blockonomics_'.$crypto['code'].'_txid', true);
            $address = get_post_meta($order->get_id(), $crypto['code'].'_address', true);
            if ($txid && $address) {
                if ($crypto['code'] == 'btc') {
                    $base_url = Blockonomics::BASE_URL;
                }else{
                    $base_url = Blockonomics::BCH_BASE_URL;
                }
                echo '<h2>'.__('Payment Details', 'blockonomics-bitcoin-payments').'</h2><p><strong>'.__('Transaction', 'blockonomics-bitcoin-payments').':</strong>  <a href =\''. $base_url ."/api/tx?txid=$txid&addr=$address'>".substr($txid, 0, 10). '</a></p>';
                if (!$email) {
                   echo '<p>'.__('Your order will be processed on confirmation of above transaction by the bitcoin network.', 'blockonomics-bitcoin-payments').'</p>';
                } 
            }
        }      
    }
    function nolo_custom_field_display_cust_order_meta($order)
    {
        bnomics_display_tx_info($order);
    }
    function nolo_bnomics_woocommerce_email_customer_details($order)
    {
        bnomics_display_tx_info($order, true);
    }

    function bnomics_enqueue_stylesheets(){
      wp_enqueue_style('bnomics-style', plugin_dir_url(__FILE__) . "css/order.css", '', get_plugin_data( __FILE__ )['Version']);
    }

    function bnomics_enqueue_scripts(){
      wp_enqueue_script( 'angular', plugins_url('js/angular.min.js#deferload', __FILE__), '', get_plugin_data( __FILE__ )['Version'] );
      wp_enqueue_script( 'angular-resource', plugins_url('js/angular-resource.min.js#deferload', __FILE__), '', get_plugin_data( __FILE__ )['Version'] );
      wp_enqueue_script( 'app', plugins_url('js/app.js#deferload', __FILE__), '', get_plugin_data( __FILE__ )['Version'] );
      wp_enqueue_script( 'angular-qrcode', plugins_url('js/angular-qrcode.js#deferload', __FILE__), '', get_plugin_data( __FILE__ )['Version'] );
      wp_enqueue_script( 'vendors', plugins_url('js/vendors.min.js#deferload', __FILE__), '', get_plugin_data( __FILE__ )['Version'] );
      wp_enqueue_script( 'reconnecting-websocket', plugins_url('js/reconnecting-websocket.min.js#deferload', __FILE__), '', get_plugin_data( __FILE__ )['Version'] );
    }

    // Async load
    function bnomics_async_scripts($url)
    {
        if ( strpos( $url, '#deferload') === false )
            return $url;
        else if ( is_admin() )
            return str_replace( '#deferload', '', $url );
        else
        return str_replace( '#deferload', '', $url )."' defer='defer"; 
    }
}

// After all plugins have been loaded, initialize our payment gateway plugin
add_action('plugins_loaded', 'blockonomics_woocommerce_init', 0);

register_activation_hook( __FILE__, 'blockonomics_activation_hook' );
add_action('admin_notices', 'blockonomics_plugin_activation');

function blockonomics_activation_hook() {
    if(!is_plugin_active('woocommerce/woocommerce.php'))
    {
        trigger_error(__( 'Wordpress Bitcoin Payments - Blockonomics requires WooCommerce plugin to be installed and active.', 'blockonomics-bitcoin-payments' ).'<br>', E_USER_ERROR);
    }
    set_transient( 'blockonomics_activation_hook_transient', true, 5);
}

//Show message when plugin is activated
function blockonomics_plugin_activation() {
  if(!is_plugin_active('woocommerce/woocommerce.php'))
  {
      $html = '<div class="error">';
      $html .= '<p>';
      $html .= __( 'Wordpress Bitcoin Payments - Blockonomics failed to load. Please activate WooCommerce plugin.', 'blockonomics-bitcoin-payments' );
      $html .= '</p>';
      $html .= '</div>';
      echo $html;
  }
  if( get_transient( 'blockonomics_activation_hook_transient' ) ){

    $html = '<div class="updated">';
    $html .= '<p>';
    $html .= __( 'Congrats, you are now accepting BTC payments! You can configure Blockonomics <a href="options-general.php?page=blockonomics_options">on this page</a>.', 'blockonomics-bitcoin-payments' );
    $html .= '</p>';
    $html .= '</div>';

    echo $html;        
    delete_transient( 'fx-admin-notice-example' );
  }
}

// On uninstallation, clear every option the plugin has set
register_uninstall_hook( __FILE__, 'blockonomics_uninstall_hook' );
function blockonomics_uninstall_hook() {
    delete_option('blockonomics_callback_secret');
    delete_option('blockonomics_api_key');
    delete_option('blockonomics_temp_api_key');
    delete_option('blockonomics_temp_withdraw_amount');
    delete_option('blockonomics_orders');
    delete_option('blockonomics_margin');
    delete_option('blockonomics_timeperiod');
    delete_option('blockonomics_api_updated');
    delete_option('blockonomics_bch');
    delete_option('blockonomics_underpayment_slack');
    delete_option('blockonomics_lite');
    delete_option('blockonomics_nojs');
	delete_option('blockonomics_network_confirmation');
}


function blockonomics_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=blockonomics_options">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'blockonomics_plugin_add_settings_link' );
