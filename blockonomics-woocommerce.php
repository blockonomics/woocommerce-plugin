<?php
/**
 * Plugin Name: WordPress Bitcoin Payments - Blockonomics
 * Plugin URI: https://github.com/blockonomics/woocommerce-plugin
 * Description: Accept Bitcoin Payments on your WooCommerce-powered website with Blockonomics
 * Version: 3.6.4
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: blockonomics-bitcoin-payments
 * Domain Path: /languages/
 * WC tested up to: 7.8.1
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
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
require_once ABSPATH . 'wp-admin/install-helper.php';


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
    add_action('init', 'load_plugin_translations');
    add_action('woocommerce_order_details_after_order_table', 'nolo_custom_field_display_cust_order_meta', 10, 1);
    add_action('woocommerce_email_customer_details', 'nolo_bnomics_woocommerce_email_customer_details', 10, 1);
    add_action('admin_enqueue_scripts', 'blockonomics_load_admin_scripts' );
    add_action('restrict_manage_posts', 'filter_orders' , 20 );
    add_filter('woocommerce_get_checkout_payment_url','update_payment_url_on_underpayments',10,2);
    add_filter('request', 'filter_orders_by_address_or_txid' ); 
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockonomics_gateway');
    add_filter('clean_url', 'bnomics_async_scripts', 11, 1 );
    
    /**
     * Redriect to the checkout page  
     **/
    function  update_payment_url_on_underpayments($pay_url, $order) {
        $payment_method = $order->get_payment_method();
        $is_blockonomics = ($payment_method === 'blockonomics');

        if (!$is_blockonomics || !$order->needs_payment()) {
            return $pay_url;
        }

        // Check the partial payments setting
        $blockonomics = new Blockonomics();
        $is_partial_payments_active = $blockonomics->is_partial_payments_active();

        if (!$is_partial_payments_active) {
            return $pay_url;
        }

        $order_id = $order->get_id();
        $paid_fiat = $blockonomics->get_order_paid_fiat($order_id);

        if (!$paid_fiat) {
            return $pay_url;
        }
        
        return esc_url($blockonomics->get_order_checkout_url($order_id));

    }
     
    /**
     * Add Styles to Blockonomics Admin Page
     **/
    function blockonomics_load_admin_scripts($hook){ 
        if ( $hook === 'settings_page_blockonomics_options') {        
            wp_enqueue_style('bnomics-admin-style', plugin_dir_url(__FILE__) . "css/blockonomics_options.css", '', get_plugin_data( __FILE__ )['Version']);
        }
    }
    /**
     * Adding new filter to WooCommerce orders
     **/
    function filter_orders() {
		global $typenow;
		if ( 'shop_order' === $typenow ) {
            $filter_by = isset($_GET['filter_by']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['filter_by']))) : "";
			?>
			<input size='26' value="<?php echo($filter_by ); ?>" type='name' placeholder='Filter by crypto address/txid' name='filter_by'>
			<?php
		}
	}
	function filter_orders_by_address_or_txid( $vars ) {
		global $typenow;
		if ( 'shop_order' === $typenow && !empty( $_GET['filter_by'])) {
            $santized_filter = wc_clean( sanitize_text_field(wp_unslash($_GET['filter_by'])) );
            $vars['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'blockonomics_payments_addresses',
                    'value'   => $santized_filter,
                    'compare' => 'LIKE'
                ),
                array(
                    'key'     => 'blockonomics_payments_txids',
                    'value'   => $santized_filter,
                    'compare' => 'LIKE'
                ),
            );
        }
		return $vars;
	}
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

    // Add entry in the settings menu
    function add_page()
    {
        $blockonomics = new Blockonomics;

        $nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce( sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'update-options' ) : "";
        if (isset($_POST['generateSecret']) && $nonce)
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
    }

    function display_admin_message($msg, $type)
    {
        add_settings_error('option_notice', 'option_notice', $msg, $type);
    }

    function get_started_message($domain = '', $label_class = 'bnomics-options-intendation', $message = 'To configure')
    {
        echo 
        "<label class=$label_class>".
            __("$message, click <b> Get Started for Free </b> on ", 'blockonomics-bitcoin-payments').
            '<a href="https://'.$domain.'blockonomics.co/merchants" target="_blank">'.
                __('https://'.$domain.'blockonomics.co/merchants', 'blockonomics-bitcoin-payments').
            '</a>
        </label>';
    }

    function success_message()
    {
        echo '<td colspan="2"class="notice notice-success bnomics-test-setup-message">'.__("Success", 'blockonomics-bitcoin-payments').'</td>';
    }

    function error_message($error)
    {
        echo 
        '<td colspan="2" class="notice notice-error bnomics-test-setup-message">'.$error.'.<br/>'.
            __("Please consult ", 'blockonomics-bitcoin-payments').
            '<a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">'.
            __("this troubleshooting article", 'blockonomics-bitcoin-payments').'</a>.
        </td>';
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
        if( isset( $_GET[ 'tab' ] ) ) {
            $active_tab = sanitize_key($_GET[ 'tab' ]);
        } else {
            $active_tab = 'settings';
        }
        $settings_updated = isset($_GET['settings-updated']) ? wp_validate_boolean(sanitize_text_field(wp_unslash($_GET['settings-updated']))) : "";
        if ($active_tab == "currencies" && $settings_updated == 'true')
        {
            $blockonomics = new Blockonomics;
            $setup_errors = $blockonomics->testSetup();
            $btc_error = isset($setup_errors['btc']) ? $setup_errors['btc'] : 'false';
            $bch_error = isset($setup_errors['bch']) ? $setup_errors['bch'] : 'false';
            $withdraw_requested = $blockonomics->make_withdraw();
        }
        ?>
        <script type="text/javascript">
            function gen_secret() {
                document.generateSecretForm.submit();
            }
            function check_form(tab) {
                const urlParams = new URLSearchParams(window.location.href);
                const currentTab = urlParams.get('tab') ?? 'settings';
                if (currentTab == tab){
                    return;
                }
                if (document.getElementById('blockonomics_form_updated').value == 'true' || document.getElementById('blockonomics_api_updated').value == 'true'){
                    if(validateBlockonomicsForm()){
                        save_form_then_redirect(tab);
                    }
                } else {
                    window.location.href = "options-general.php?page=blockonomics_options&tab="+tab;
                }
            }
            function save_form_then_redirect(tab) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "options.php"); 
                xhr.onload = function(event){ 
                    window.location.href = "options-general.php?page=blockonomics_options&tab="+tab;
                }; 
                const formData = new FormData(document.myform); 
                xhr.send(formData);
                document.getElementById('myform').innerHTML = "Saving Settings...";
            }
            function value_changed() {
                document.getElementById('blockonomics_api_updated').value = 'true';
                add_asterisk("settings");
            }
            function add_asterisk(tab) {
                document.getElementById('blockonomics_form_updated').value = 'true';
                document.getElementById(tab+'_nav_bar').style.background = "#e6d9cb";
                document.getElementById(tab+'_nav_bar').textContent = tab.charAt(0).toUpperCase() + tab.slice(1)+"*";
            }
            function validateBlockonomicsForm() {
                if(document.getElementById("blockonomics_api_key")){
                    newApiKey = document.getElementById("blockonomics_api_key").value;
                    apiKeyChanged = newApiKey != "<?php echo get_option("blockonomics_api_key")?>";
                    if (apiKeyChanged && newApiKey.length != 43) {
                        alert("ERROR: Invalid APIKey");
                        return false
                    }
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
            <h1><?php echo __('Blockonomics', 'blockonomics-bitcoin-payments')?></h1>
            <?php 
                if (isset($withdraw_requested)):?>
                <div class="bnomics-width-withdraw">
                    <td colspan='2' class="bnomics-options-no-padding bnomics-width">
                        <p class='notice notice-<?php echo $withdraw_requested[1]?>'>
                            <?php echo $withdraw_requested[0].'.' ?> 
                        </p>
                    </td>
                </div>
            <?php endif; ?>
            <form method="post" name="myform" id="myform" onsubmit="return validateBlockonomicsForm()" action="options.php">
                <h2 class="nav-tab-wrapper">
                    <a onclick="check_form('settings')" id='settings_nav_bar'  class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php echo __('Settings', 'blockonomics-bitcoin-payments')?></a>
                    <a onclick="check_form('currencies')" id='currencies_nav_bar' class="nav-tab <?php echo $active_tab == 'currencies' ? 'nav-tab-active' : ''; ?>"><?php echo __('Currencies', 'blockonomics-bitcoin-payments')?></a>
                </h2>
                <input type="hidden" name="blockonomics_form_updated" id="blockonomics_form_updated" value="false">
                <input type="hidden" name="blockonomics_api_updated" id="blockonomics_api_updated" value="false">
                <?php wp_nonce_field('update-options');
                switch ( $active_tab ){
                case 'settings' :?>
                <div class="bnomics-width">
                    <h4><?php echo __('API Key', 'blockonomics-bitcoin-payments')?></h4>
                    <input class="bnomics-options-input" onchange="value_changed()" size="130" type="text" id="blockonomics_api_key" name="blockonomics_api_key" value="<?php echo get_option('blockonomics_api_key'); ?>" />
                    <?php get_started_message('', '', 'To get your API Key');?>
                    <h4><?php echo __('Callback URL', 'blockonomics-bitcoin-payments')?>
                        <a href="javascript:gen_secret()" id="generate-callback" class="bnomics-options-callback-icon" title="Generate New Callback URL">&#xf463;</a>
                    </h4>
                    <input class="bnomics-options-input" size="130" type="text" value="<?php echo get_callback_url();?>" disabled/>
                    <p id="advanced_title" class="bnomics-options-bold"><a href="javascript:show_advanced()"><?php echo __('Advanced Settings', 'blockonomics-bitcoin-payments')?> &#9660;</a></p>
                    <div id="advanced_window" style="display:none">
                        <p class="bnomics-options-bold"><a href="javascript:show_basic()"><?php echo __('Advanced Settings', 'blockonomics-bitcoin-payments')?> &#9650;</a></p>
                        <table class="form-table">
                            <tr valign="top"><th scope="row"><?php echo __('Time period of countdown timer on payment page (in minutes)', 'blockonomics-bitcoin-payments')?></th>
                                <td>
                                    <select onchange="add_asterisk('settings')" name="blockonomics_timeperiod" />
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
                                <td><input onchange="add_asterisk('settings')" type="number" min="0" max="20" step="0.01" name="blockonomics_margin" value="<?php echo esc_attr( get_option('blockonomics_margin', 0) ); ?>" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php echo __('Underpayment Slack % (Allow payments that are off by a small percentage)', 'blockonomics-bitcoin-payments')?></th>
                                <td><input onchange="add_asterisk('settings')" type="number" min="0" max="20" step="0.01" name="blockonomics_underpayment_slack" value="<?php echo esc_attr( get_option('blockonomics_underpayment_slack', 0) ); ?>" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php echo __('Display Payment Page in Lite Mode (Enable this if you are having problems in rendering checkout page)', 'blockonomics-bitcoin-payments')?></th>
                                <td><input onchange="add_asterisk('settings')" type="checkbox" name="blockonomics_lite" value="1" <?php checked("1", get_option('blockonomics_lite')); ?> /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php echo __('No Javascript checkout page (Enable this if you have majority customer that use tor like browser that block Javascript)', 'blockonomics-bitcoin-payments')?></th>
                                <td><input onchange="add_asterisk('settings')" type="checkbox" name="blockonomics_nojs" value="1" <?php checked("1", get_option('blockonomics_nojs')); ?> /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php echo __('Network Confirmations required for payment to complete)', 'blockonomics-bitcoin-payments')?></th>
                                <td><select onchange="add_asterisk('settings')" name="blockonomics_network_confirmation">
                                        <option value="2" <?php selected(get_option('blockonomics_network_confirmation'), 2); ?>><?php echo __('2 (Recommended)', 'blockonomics-bitcoin-payments')?></option>
                                        <option value="1" <?php selected(get_option('blockonomics_network_confirmation'), 1); ?>>1</option>
                                        <option value="0" <?php selected(get_option('blockonomics_network_confirmation'), 0); ?>>0</option>
                                    </select></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php echo __('Allow Partial Payments (Customer can pay order via multiple payments)', 'blockonomics-bitcoin-payments')?></th>
                                <td><input onchange="add_asterisk('settings')" type="checkbox" name="blockonomics_partial_payments" value="1" <?php checked("1", get_option('blockonomics_partial_payments', $default_value = true)); ?> /></td>
                            </tr>
                        </table>
                    </div>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php echo __("Save", 'blockonomics-bitcoin-payments')?>"/>
                        <input type="hidden" name="action" value="update" />
                        <input type="hidden" name="page_options" value="blockonomics_api_key,blockonomics_timeperiod,blockonomics_margin,blockonomics_gen_callback,blockonomics_api_updated,blockonomics_underpayment_slack,blockonomics_lite,blockonomics_nojs,blockonomics_network_confirmation,blockonomics_partial_payments" />
                    </p>
                </form>
                <form method="POST" name="generateSecretForm">
                    <p class="submit">
                        <?php wp_nonce_field('update-options');?>
                        <input type="hidden" name="generateSecret" value="true">
                    </p>
                </form>
                </div>
                    <?php
                    break;
                case 'currencies' :?>
                    <table width="100%" cellspacing="0" cellpadding="0" class="form-table bnomics-options-intendation bnomics-width">
                        <h2>
                            <input onchange="add_asterisk('currencies')" type="checkbox" name="blockonomics_btc" value="1"<?php checked("1", get_option('blockonomics_btc', true)); ?>" />
                            <?php echo __('Bitcoin (BTC)', 'blockonomics-bitcoin-payments')?>
                        </h2>
                        <?php 
                        get_started_message();
                        $btc_enabled = get_option("blockonomics_btc");
                        if ($btc_enabled || get_option("blockonomics_btc") === false):  
                            $total_received = get_option('blockonomics_temp_withdraw_amount') / 1.0e8;
                            $api_key = get_option("blockonomics_api_key");
                            $temp_api_key = get_option("blockonomics_temp_api_key");
                            if ($temp_api_key): ?>
                                <th class="blockonomics-narrow-th" scope="row"><b><?php echo __('Temporary Destination', 'blockonomics-bitcoin-payments')?></b></th>
                                <td colspan="2" class="bnomics-options-no-padding">
                                    <label><b><?php echo __("Blockonomics Wallet (Balance: $total_received BTC)", 'blockonomics-bitcoin-payments')?></b></label>
                                    <label><?php echo __("Our temporary wallet receives your payments until your configure your own wallet. Withdraw to your wallet is triggered automatically when configuration is done", 'blockonomics-bitcoin-payments')?></label>
                                </td>
                            </tr>
                            <?php endif; 
                        endif; 
                        if (get_option('blockonomics_btc') == '1' && isset($btc_error)):
                            if ($btc_error):
                                error_message($btc_error);
                            else:
                                success_message();
                            endif;
                        endif; ?>
                    </table>
                    <table class="form-table bnomics-options-intendation bnomics-width">
                        <h2>
                            <input onchange="add_asterisk('currencies')" type="checkbox" name="blockonomics_bch" value="1"<?php checked("1", get_option('blockonomics_bch')); ?>" />
                            <?php echo __("Bitcoin Cash (BCH)", 'blockonomics-bitcoin-payments')?>
                        </h2>
                        <?php 
                        get_started_message('bch.');
                        $bch_enabled = get_option("blockonomics_bch");
                        if ($bch_enabled == '1' && isset($bch_error)):
                            if ($bch_error):
                                error_message($bch_error);
                            else:
                                success_message();
                            endif; 
                        endif; ?>
                    </table>
                    <div class="bnomics-options-small-margin-top">
                        <input type="submit" class="button-primary" value="<?php echo __("Test Setup", 'blockonomics-bitcoin-payments')?>" />
                        <input type="hidden" name="page_options" value="blockonomics_bch, blockonomics_btc" />
                        <input type="hidden" name="action" value="update" />
                    </div>
                    </form>
                    <?php
                    break;
                }
            ?>
        </div>
    <?php
    }

    
    function bnomics_display_payment_details($order, $transactions, $email=false)
    {
        $blockonomics = new Blockonomics;
        
        $output  = '<h2 class="woocommerce-column__title">Payment details</h2>';
        $output .= '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">'; 
        $output .= '<tbody>';
        $total_paid_fiat = $blockonomics->calculate_total_paid_fiat($transactions);
        foreach ($transactions as $transaction) {
           
            $base_url = ($transaction['crypto'] === 'btc') ? Blockonomics::BASE_URL : Blockonomics::BCH_BASE_URL;
            
            $output .=  '<tr><td scope="row">';
            $output .=  '<a style="word-wrap: break-word;word-break: break-all;" href="' . $base_url . '/api/tx?txid=' . $transaction['txid'] . '&addr=' . $transaction['address'] . '">' . $transaction['txid'] . '</a></td>';
            
            $formatted_paid_fiat = ($transaction['payment_status'] == '2') ? wc_price($transaction['paid_fiat']) : 'Processing';
            $output .= '<td>' . $formatted_paid_fiat . '</td></tr>';
            
        }
        $output .= '</tbody>';
        $expected_fiat = (float)$order->get_total();

        if ($blockonomics->is_partial_payments_active() && $total_paid_fiat !== 0.0 && $total_paid_fiat < $expected_fiat ) {
            $remaining_fiat = $expected_fiat - $total_paid_fiat;
            $output .= '<tfoot>';
            $output .=  '<tr><th scope="row"><b>Paid:</b></th><td>' . wc_price($total_paid_fiat) . '</td></tr>';
            $output .=  '<tr><th scope="row"><b>Remaining Amount:</b></th><td>' . wc_price($remaining_fiat) . '</td></tr>';
            $output .= '</tfoot>';
        }
        $output .= '</table>';
        echo $output;

    }
    function bnomics_display_tx_info($order,$email=false)
    {
        global $wpdb;
        $order_id = $order->get_id();
        $table_name = $wpdb->prefix .'blockonomics_payments';
        $query = $wpdb->prepare("SELECT * FROM ". $table_name." WHERE order_id = %d AND txid != ''", $order_id);
        $transactions = $wpdb->get_results($query,ARRAY_A);
        
        if (empty($transactions)) {
            return;
        }
        bnomics_display_payment_details($order, $transactions, $email);
    }
    function nolo_custom_field_display_cust_order_meta($order)
    {
        bnomics_display_tx_info($order);
    }
    function nolo_bnomics_woocommerce_email_customer_details($order)
    {
        bnomics_display_tx_info($order);
    }

    function bnomics_enqueue_stylesheets(){
      wp_enqueue_style('bnomics-style', plugin_dir_url(__FILE__) . "css/order.css", '', get_plugin_data( __FILE__ )['Version']);
    }

    function bnomics_enqueue_scripts(){
        wp_enqueue_script( 'reconnecting-websocket', plugins_url('js/vendors/reconnecting-websocket.min.js#deferload', __FILE__), array(), get_plugin_data( __FILE__ )['Version'] );
        wp_enqueue_script( 'qrious', plugins_url('js/vendors/qrious.min.js#deferload', __FILE__), array(), get_plugin_data( __FILE__ )['Version'] );
        wp_enqueue_script( 'bnomics-checkout', plugins_url('js/checkout.js#deferload', __FILE__), array('reconnecting-websocket', 'qrious'), get_plugin_data( __FILE__ )['Version'] );
        wp_enqueue_script( 'copytoclipboard', plugins_url('js/vendors/copytoclipboard.js#deferload', __FILE__), array(), get_plugin_data( __FILE__ )['Version'] );
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

global $blockonomics_db_version;
$blockonomics_db_version = '1.2';

function blockonomics_create_table() {
    // Create blockonomics_payments table
    // https://codex.wordpress.org/Creating_Tables_with_Plugins
    global $wpdb;
    global $blockonomics_db_version;
    /* for db version 1.2, new table blockonomics_payments is introduced
        status is renamed to payment_status
        satoshi is renamed to expected_satoshi
        value is renamed to expected_fiat & it is no longer longtext
        2 new fields are added for logging purpose only - paid_satoshi & paid_fiat */ 

    $table_name = $wpdb->prefix . 'blockonomics_payments';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        order_id int NOT NULL,
        payment_status int NOT NULL,
        crypto varchar(3) NOT NULL,
        address varchar(191) NOT NULL,
        expected_satoshi bigint,
        expected_fiat double,
        currency varchar(3),
        paid_satoshi bigint,
        paid_fiat double,
        txid text,
        PRIMARY KEY  (address),
        KEY orderkey (order_id,crypto)
    ) $charset_collate;";
    dbDelta( $sql );

    update_option( 'blockonomics_db_version', $blockonomics_db_version );
}

function blockonomics_activation_hook() {
    if(!is_plugin_active('woocommerce/woocommerce.php'))
    {
        trigger_error(__( 'Wordpress Bitcoin Payments - Blockonomics requires WooCommerce plugin to be installed and active.', 'blockonomics-bitcoin-payments' ).'<br>', E_USER_ERROR);
    }

    set_transient( 'blockonomics_activation_hook_transient', true, 3);
}

// Since WP 3.1 the activation function registered with register_activation_hook() is not called when a plugin is updated.
// blockonomics_update_db_check() is loaded for every PHP page by plugins_loaded hook
function blockonomics_update_db_check() {
    global $blockonomics_db_version;
    $installed_ver = get_site_option( 'blockonomics_db_version' );
    // blockonomics_create_table() should only be run if there is no $installed_ver, refer https://github.com/blockonomics/woocommerce-plugin/issues/296
    if (empty($installed_ver)){
        blockonomics_create_table();
    } else if (version_compare( $installed_ver, $blockonomics_db_version, '!=')) {
        blockonomics_run_db_updates($installed_ver);
    }
}

function blockonomics_run_db_updates($installed_ver){
    global $wpdb;
    global $blockonomics_db_version;
    if (version_compare($installed_ver, '1.2', '<')){
        blockonomics_create_table();
    }
    update_option( 'blockonomics_db_version', $blockonomics_db_version );
}

add_action( 'plugins_loaded', 'blockonomics_update_db_check' );
register_activation_hook( __FILE__, 'blockonomics_create_table' );

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
    delete_option('blockonomics_margin');
    delete_option('blockonomics_timeperiod');
    delete_option('blockonomics_api_updated');
    delete_option('blockonomics_bch');
    delete_option('blockonomics_btc');
    delete_option('blockonomics_underpayment_slack');
    delete_option('blockonomics_lite');
    delete_option('blockonomics_nojs');
    delete_option('blockonomics_network_confirmation');
    delete_option('blockonomics_partial_payments');

    global $wpdb;
    // drop blockonomics_orders & blockonomics_payments on uninstallation
    // blockonomics_orders was the payments table before db version 1.2
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS ".$wpdb->prefix."blockonomics_orders , ".$wpdb->prefix."blockonomics_payments"));
    delete_option("blockonomics_db_version");
}


function blockonomics_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=blockonomics_options">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'blockonomics_plugin_add_settings_link' );
