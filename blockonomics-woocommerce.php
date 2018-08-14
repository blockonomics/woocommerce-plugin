<?php
/**
 * Plugin Name: Wordpress Bitcoin Payments - Blockonomics
 * Plugin URI: https://github.com/blockonomics/woocommerce-plugin
 * Description: Accept Bitcoin Payments on your WooCommerce-powered website with Blockonomics
 * Version: 1.4.8
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: blockonomics-bitcoin-payments
 * Domain Path: /languages/
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

if (is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce')) {
    function blockonomics_woocommerce_init()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        /**
         * Blockonomics Payment Gateway
         *
         * Provides a Blockonomics Payment Gateway.
         *
         * @class   WC_Gateway_Blockonomics
         * @extends WC_Payment_Gateway
         * @version 2.0.1
         * @author  Blockonomics Inc.
         */
        class WC_Gateway_Blockonomics extends WC_Payment_Gateway
        {
            public function __construct()
            {
                load_plugin_textdomain('blockonomics-bitcoin-payments', false, dirname(plugin_basename(__FILE__)) . '/languages/');

                $this->id   = 'blockonomics';
                $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/bitcoin-icon.png';

                $this->has_fields        = false;
                $this->order_button_text = __('Pay with bitcoin', 'blockonomics-bitcoin-payments');

                $this->init_form_fields();
                $this->init_settings();

                $this->title       = $this->get_option('title');
                $this->description = $this->get_option('description');


                add_option('blockonomics_orders', array());
                // Actions
                add_action(
                    'woocommerce_update_options_payment_gateways_' . $this->id, array(
                    $this,
                    'process_admin_options'
                    )
                );
                add_action(
                    'woocommerce_receipt_blockonomics', array(
                    $this,
                    'receipt_page'
                    )
                );

                // Payment listener/API hook
                add_action(
                    'woocommerce_api_wc_gateway_blockonomics', array(
                    $this,
                    'check_blockonomics_callback'
                    )
                  );
            }

            public function admin_options()
            {
                echo '<h3>' . __('Blockonomics Payment Gateway', 'blockonomics-bitcoin-payments') . '</h3>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }

            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable Blockonomics plugin', 'blockonomics-bitcoin-payments'),
                        'type' => 'checkbox',
                        'label' => __('Show bitcoin as an option to customers during checkout?', 'blockonomics-bitcoin-payments'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Title', 'blockonomics-bitcoin-payments'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'blockonomics-bitcoin-payments'),
                        'default' => __('Bitcoin', 'blockonomics-bitcoin-payments')
                    )
                );
            }

            public function process_admin_options()
            {
                if (!parent::process_admin_options()) {
                    return false;
                }
            }
            
            public function process_payment($order_id)
            {
                include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
                global $woocommerce;

                $order = new WC_Order($order_id);

                $success_url = add_query_arg('return_from_blockonomics', true, $this->get_return_url($order));

                // Blockonomics mangles the order param so we have to put it somewhere else and restore it on init
                $cancel_url = $order->get_cancel_order_url_raw();
                $cancel_url = add_query_arg('return_from_blockonomics', true, $cancel_url);
                $cancel_url = add_query_arg('cancelled', true, $cancel_url);
                $cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);

                $api_key    = get_option('blockonomics_api_key');

                $blockonomics = new Blockonomics;
                $responseObj = $blockonomics->new_address(get_option('blockonomics_api_key'), get_option("blockonomics_callback_secret"));
                $price = $blockonomics->get_price(get_woocommerce_currency());

                if($responseObj->response_code != 'HTTP/1.1 200 OK') {
                    $this->displayError($woocommerce);
                    return;
                }

                $address = $responseObj->address;

                $blockonomics_orders = get_option('blockonomics_orders');
                $order = array(
                'value'              => $order->get_total(),
                'satoshi'            => intval(1.0e8*$order->get_total()/$price),
                'currency'           => get_woocommerce_currency(),
                'order_id'            => $order_id,
                'status'             => -1,
                'timestamp'          => time(),
                'txid'               => ''
                );
                //Using address as key, as orderid can be tried manually
                //by hit and trial
                $blockonomics_orders[$address] = $order;
                update_option('blockonomics_orders', $blockonomics_orders);
                $order_url = WC()->api_request_url('WC_Gateway_Blockonomics');
                $order_url = add_query_arg('show_order', $address, $order_url);

                update_post_meta($order_id, 'blockonomics_address', $address);

                return array(
                'result'   => 'success',
                'redirect' => $order_url
                );
            }

            public function check_blockonomics_callback()
            {
                $orders = get_option('blockonomics_orders');
                $address = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : "";
                if ($address) {
                    $dir = plugin_dir_path(__FILE__);
                    include $dir."order.php";
                    exit();
                }
                $address = isset($_REQUEST["finish_order"]) ? $_REQUEST["finish_order"] : "";
                if ($address) {
                    $order = $orders[$address];
                    $wc_order = new WC_Order($order['order_id']);
                    echo $order['order_id'];
                    wp_redirect($wc_order->get_checkout_order_received_url());
                    exit();
                }
                $address = isset($_REQUEST['get_order']) ? $_REQUEST['get_order'] : "";
                if ($address) {
                    header("Content-Type: application/json");
                    exit(json_encode($orders[$address]));
                }

                $callback_secret = get_option("blockonomics_callback_secret");
                $secret = isset($_REQUEST['secret']) ? $_REQUEST['secret'] : "";
                if ($callback_secret  && $callback_secret == $secret) {
                    $addr = $_REQUEST['addr'];
                    $order = $orders[$addr];
                    $wc_order = new WC_Order($order['order_id']);
                    if ($order) {
                        $status = intval($_REQUEST['status']);
                        $existing_status = $order['status'];
                        $timestamp = $order['timestamp'];
                        $time_period = get_option("blockonomics_timeperiod", 10) *60;
                        if ($status == 0 && time() > $timestamp + $time_period) {
                            $minutes = (time() - $timestamp)/60;
                            $wc_order->add_order_note(__("Warning: Payment arrived after $minutes minutes. Received BTC may not match current bitcoin price", 'blockonomics-bitcoin-payments'));
                        }
                        elseif ($status == 2) {
                            update_post_meta($wc_order->id, 'paid_btc_amount', $_REQUEST['value']/1.0e8);
                            if ($order['satoshi'] > $_REQUEST['value']) {
                                $status = -2; //Payment error , amount not matching
                                $wc_order->update_status('failed', __('Paid BTC amount less than expected.', 'blockonomics-bitcoin-payments'));
                            }
                            else{
                                if ($order['satoshi'] < $_REQUEST['value']) {
                                    $wc_order->add_order_note(__('Overpayment of BTC amount', 'blockonomics-bitcoin-payments'));
                                }
                                $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                                $wc_order->payment_complete($order['txid']);
                            }
                        }
                        $order['txid'] =  $_REQUEST['txid'];
                        $order['status'] = $status;
                        $orders[$addr] = $order;
                        if ($existing_status == -1) {
                            update_post_meta($wc_order->id, 'blockonomics_txid', $order['txid']);
                            update_post_meta($wc_order->id, 'expected_btc_amount', $order['satoshi']/1.0e8);
                        }
                        update_option('blockonomics_orders', $orders);
                    }
                }
            }

            private function displayError($woocommerce) {
                $unable_to_generate = __('<h1>Unable to generate bitcoin address.</h1><p> Note for site webmaster: ', 'blockonomics-bitcoin-payments');
                
                $error_msg = 'Please login to your admin panel, navigate to Settings > Blockonomics and click <i>Test Setup</i> to diagnose the issue';

                $error_message = $unable_to_generate . $error_msg;

                if (version_compare($woocommerce->version, '2.1', '>=')) {
                    wc_add_notice(__($error_message, 'blockonomics-bitcoin-payments'), 'error');
                } else {
                    $woocommerce->add_error(__($error_message, 'blockonomics-bitcoin-payments'));
                }
            }
        }

        /**
         * Add this Gateway to WooCommerce
         **/
        function woocommerce_add_blockonomics_gateway($methods)
        {
            $methods[] = 'WC_Gateway_Blockonomics';
            return $methods;
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
            generate_secret();
            register_setting('blockonomics_g', 'blockonomics_gen_callback', 'gen_callback');
            add_options_page(
                'Blockonomics', 'Blockonomics', 'manage_options',
                'blockonomics_options', 'show_options'
            );

            if (get_option('api_updated') == 'true' && $_GET['settings-updated'] == 'true')
            {
                $message = __('API Key updated! Please click on Test Setup to verify Installation. ', 'blockonomics-bitcoin-payments');
                $type = 'updated';
                add_settings_error('option_notice', 'option_notice', $message, $type);
            }

            if (isset($_POST['runTest']))
            {

                $urls_count = check_callback_urls();

                if($urls_count == '2')
                {
                    $message = __("Seems that you have set multiple xPubs or you already have a Callback URL set. <a href='https://blockonomics.freshdesk.com/support/solutions/articles/33000209399-merchants-integrating-multiple-websites' target='_blank'>Here is a guide</a> to setup multiple websites.", 'blockonomics-bitcoin-payments');
                    $type = 'error';
                    add_settings_error('option_notice', 'option_notice', $message, $type);
                    return;
                }

                $setup_errors = testSetup();

                if($setup_errors)
                {
                    $message = __($setup_errors . '</p><p>For more information, please consult <a href="https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'blockonomics-bitcoin-payments');
                    $type = 'error';
                    add_settings_error('option_notice', 'option_notice', $message, $type);
                }
                else
                {
                    $message = __('Congrats ! Setup is all done', 'blockonomics-bitcoin-payments');
                    $type = 'updated';
                    add_settings_error('option_notice', 'option_notice', $message, $type);
                }
            }

        }

        function generate_secret()
        {
            $callback_secret = get_option("blockonomics_callback_secret");
            if (!$callback_secret) {
                $callback_secret = sha1(openssl_random_pseudo_bytes(20));
                update_option("blockonomics_callback_secret", $callback_secret);
            }
        }
        function nolo_custom_field_display_cust_order_meta($order)
        {
            $txid = get_post_meta($order->id, 'blockonomics_txid', true);
            $address = get_post_meta($order->id, 'blockonomics_address', true);
            include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
            if ($txid && $address) {
                echo '<h2>Payment Details</h2><p><strong>'.__('Transaction').':</strong>  <a href =\''. Blockonomics::BASE_URL ."/api/tx?txid=$txid&addr=$address'>".substr($txid, 0, 10). '</a></p><p>Your order will be processed on confirmation of above transaction by the bitcoin network.</p>';
            }
        }

        function enqueue_stylesheets(){
          wp_enqueue_style('bnomics-style', plugin_dir_url(__FILE__) . "css/order.css");
        }

        add_action('admin_menu', 'add_page');
        add_action('init', 'woocommerce_handle_blockonomics_return');
        add_action('woocommerce_order_details_after_order_table', 'nolo_custom_field_display_cust_order_meta', 10, 1);
        add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockonomics_gateway');
        add_action('wp_enqueue_scripts', 'enqueue_stylesheets' );
    }



    add_action('plugins_loaded', 'blockonomics_woocommerce_init', 0);

    register_activation_hook( __FILE__, 'blockonomics_activation_hook' );
    add_action('admin_notices', 'plugin_activation');

    function blockonomics_activation_hook() {
        set_transient( 'blockonomics_activation_hook_transient', true, 5);
    }

    //Show message when plugin is activated
    function plugin_activation() {
        if( get_transient( 'blockonomics_activation_hook_transient' ) ){

            $html = '<div class="updated">';
                $html .= '<p>';
                    $html .= __( 'Please configure Blockonomics Bitcoin Payments <a href="options-general.php?page=blockonomics_options">on this page</a>.', 'advanced-google-analytics' );
                $html .= '</p>';
            $html .= '</div>';

            echo $html;        
            delete_transient( 'fx-admin-notice-example' );
        }
    }

    function plugin_add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=blockonomics_options">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
    $plugin = plugin_basename( __FILE__ );
    add_filter( "plugin_action_links_$plugin", 'plugin_add_settings_link' );
}

function gen_callback($input)
{
  if ($input == 1)
  {
    $callback_secret = sha1(openssl_random_pseudo_bytes(20));
    update_option("blockonomics_callback_secret", $callback_secret);
  }

  return 0;
}

/**
 * Check the status of callback urls
 * If no xPubs set, return
 * If one xPub is set without callback url, set the url
 * If more than one xPubs are set, give instructions on integrating to multiple sites
 * @return Strin Count of found xPubs
 */
function check_callback_urls()
{
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
    $blockonomics = new Blockonomics;

    $responseObj = $blockonomics->get_xpubs(get_option('blockonomics_api_key'));

    // No xPubs set
    if (count($responseObj) == 0)
    {
        return "0";
    }

    // One xPub set
    if (count($responseObj) == 1)
    {
        //$callback_url = WC()->api_request_url('WC_Gateway_Blockonomics') . "?secret=" . get_option('blockonomics_callback_secret');

        $callback_secret = get_option('blockonomics_callback_secret');
        $callback_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $callback_url);

        // No Callback URL set, set one
        if($responseObj[0]->callback == null)
        { 
            $blockonomics->update_callback(
                get_option('blockonomics_api_key'),
                $callback_url,
                $responseObj[0]->address
            );

            return "1";
        }
        // One xPub with one Callback URL
        else
        {
            if($responseObj[0]->callback == $callback_url)
            {
                return "1";
            }
            else
            {

            }
            return "2";
        }
    }

    if (count($responseObj) > 1)
    {
        //$callback_url = WC()->api_request_url('WC_Gateway_Blockonomics') . "?secret=" . get_option('blockonomics_callback_secret');

        $callback_secret = get_option('blockonomics_callback_secret');
        $callback_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $callback_url);

        // Check if callback url is set
        foreach ($responseObj as $resObj) {
            if($resObj->callback == $callback_url)
                {
                    return "1";
                }
        }
        return "2";
    }
}

function testSetup()
{
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';

    $blockonomics = new Blockonomics;
    $responseObj = $blockonomics->new_address(get_option('blockonomics_api_key'), get_option("blockonomics_callback_secret"), true);

    if(!ini_get('allow_url_fopen')) {
        $error_str = __('<i>allow_url_fopen</i> is not enabled, please enable this in php.ini', 'blockonomics-bitcoin-payments');

    }  elseif(!isset($responseObj->response_code)) {
        $error_str = __('Your webhost is blocking outgoing HTTPS connections. Blockonomics requires an outgoing HTTPS POST (port 443) to generate new address. Check with your webhosting provider to allow this.', 'blockonomics-bitcoin-payments');

    } else {

        switch ($responseObj->response_code) {

            case 'HTTP/1.1 200 OK':
                break;

            case 'HTTP/1.1 401 Unauthorized': {
                $error_str = __('API Key is incorrect. Make sure that the API key set in admin Blockonomics module configuration is correct.', 'blockonomics-bitcoin-payments');
                break;
            }

            case 'HTTP/1.1 500 Internal Server Error': {

                if(isset($responseObj->message)) {

                    $error_code = $responseObj->message;

                    switch ($error_code) {
                        case "Could not find matching xpub":
                            $error_str = __('There is a problem in the Callback URL. Make sure that you have set your Callback URL from the admin Blockonomics module configuration to your Merchants > Settings.', 'blockonomics-bitcoin-payments');
                            break;
                        case "This require you to add an xpub in your wallet watcher":
                            $error_str = __('There is a problem in the XPUB. Make sure that the you have added an address to Wallet Watcher > Address Watcher. If you have added an address make sure that it is an XPUB address and not a Bitcoin address.', 'blockonomics-bitcoin-payments');
                            break;
                        default:
                            $error_str = $responseObj->message;
                    }
                    break;
                } else {
                    $error_str = $responseObj->response_code;
                    break;
                }
            }

            default:
                $error_str = $responseObj->response_code;
                break;

        }
    }

    if(isset($error_str)) {
        return $error_str;
    }

    // No errors
    return false;
}


function show_options()
{
    load_plugin_textdomain('blockonomics-bitcoin-payments', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    ?>

    <div class="wrap">
        <h2>Blockonomics</h2>
        <div id="installation-instructions">
            <p>
                <b><?php echo __('Installation instructions', 'blockonomics-bitcoin-payments');?>: </b><a href="https://www.youtube.com/watch?v=Kck3a-9nh6E" target="_blank">Youtube Tutorial</a> | <a href="https://blog.blockonomics.co/how-to-accept-bitcoin-payments-on-woocommerce-using-blockonomics-f18661819a62" target="_blank">Blog Tutorial</a>
            </p>
            <?php
                if (get_option('blockonomics_api_key') == null) {
                    echo __('<p>You are few clicks away from accepting bitcoin payments</p>', 'blockonomics-bitcoin-payments');
                    echo __("<p>Click on <b>Get Started for Free</b> on <a href='https://www.blockonomics.co/merchants' target='_blank'>Blockonomics Merchants</a>. Complete the Wizard, Copy the API Key when shown here</p>", 'blockonomics-bitcoin-payments');
                }
            ?>
        </div>
        <form method="post" id="myform" action="options.php">
            <?php wp_nonce_field('update-options') ?>
            <input type="hidden" name="api_updated" id="api_updated" value="false">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">BLOCKONOMICS API KEY</th>
                    <td><input onchange="value_changed()" type="text" name="blockonomics_api_key" value="<?php echo get_option('blockonomics_api_key'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">CALLBACK URL 
                        <a href="javascript:gen_callback()" id="generate-callback" style="font:400 20px/1 dashicons;margin-left: 5px;top: 4px;position:relative;text-decoration: none;" title="Generate New Callback URL">&#xf463;<a>
                    </th>
                    <td><?php
                            $callback_secret = get_option('blockonomics_callback_secret');
                            $notify_url = WC()->api_request_url('WC_Gateway_Blockonomics');
                            $notify_url = add_query_arg('secret', $callback_secret, $notify_url);
                            echo $notify_url ?></td>
                    <input hidden="text" value="0" id="callback_flag" name="blockonomics_gen_callback"/>
                      <script type="text/javascript">
                      function gen_callback()
                      {
                        document.getElementById("callback_flag").value = 1;
                        document.getElementById("myform").submit();
                      }
                      function value_changed()
                      {
                        document.getElementById('api_updated').value = 'true';
                      }
                      function checkForAPIKeyChange()
                      {
                        if (document.getElementById('api_updated').value == 'true')
                        {
                            alert('Settings have changed, click on Save first');
                        }
                        else
                        {
                            document.testSetupForm.submit();
                        }
                      }
                      </script>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo __('Accept Altcoin Payments (Using Shapeshift)', 'blockonomics-bitcoin-payments')?></th>
                    <td><input type="checkbox" name="blockonomics_altcoins" value="1" <?php checked("1", get_option('blockonomics_altcoins')); ?>" /></td>
                </tr>
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
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" value="Save"/>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="blockonomics_api_key,blockonomics_altcoins,blockonomics_timeperiod,blockonomics_gen_callback, api_updated" />
                <input onclick="checkForAPIKeyChange();" class="button-primary" name="test-setup-submit" value="Test Setup" style="max-width:85px;">
            </p>
        </form>
        <form method="POST" name="testSetupForm">
            <p class="submit">
                <input type="hidden" name="page" value="blockonomics_options">
                <input type="hidden" name="runTest" value="true">
            </p>
        </form>
    </div>

<?php
}
?>
