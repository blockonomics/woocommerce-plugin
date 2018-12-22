<?php
/**
 * Plugin Name: Wordpress Bitcoin Payments - Blockonomics
 * Plugin URI: https://github.com/blockonomics/woocommerce-plugin
 * Description: Accept Bitcoin Payments on your WooCommerce-powered website with Blockonomics
 * Version: 1.6.6
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: blockonomics-bitcoin-payments
 * Domain Path: /languages/
 * WC tested up to: 3.9.0
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
                    ),
                    'description' => array(
                        'title' => __( 'Description', 'blockonomics-bitcoin-payments' ),
                        'type' => 'text',
                        'description' => __('This controls the description which the user sees during checkout.', 'blockonomics-bitcoin-payments'),
                        'default' => ''
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
                if(get_woocommerce_currency() != 'BTC'){
                	$price = $blockonomics->get_price(get_woocommerce_currency());
                	$price = $price * 100/(100+get_option('blockonomics_margin', 0));
                }else{
                	$price = 1;
                }

                if($responseObj->response_code != 200) {
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
                    add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
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
                            update_post_meta($wc_order->get_id(), 'paid_btc_amount', $_REQUEST['value']/1.0e8);
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
                            update_post_meta($wc_order->get_id(), 'blockonomics_txid', $order['txid']);
                            update_post_meta($wc_order->get_id(), 'expected_btc_amount', $order['satoshi']/1.0e8);
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
                $setup_errors = testSetup();

                if($setup_errors)
                {
                    $message = $setup_errors;
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
            $txid = get_post_meta($order->get_id(), 'blockonomics_txid', true);
            $address = get_post_meta($order->get_id(), 'blockonomics_address', true);
            include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
            if ($txid && $address) {
                echo '<h2>Payment Details</h2><p><strong>'.__('Transaction').':</strong>  <a href =\''. Blockonomics::BASE_URL ."/api/tx?txid=$txid&addr=$address'>".substr($txid, 0, 10). '</a></p><p>Your order will be processed on confirmation of above transaction by the bitcoin network.</p>';
            }
        }
        function nolo_bnomics_woocommerce_email_customer_details($order)
        {
            $txid = get_post_meta($order->get_id(), 'blockonomics_txid', true);
            $address = get_post_meta($order->get_id(), 'blockonomics_address', true);
            if ($txid && $address) {
              include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
              echo '<h2>Payment Details</h2><p><strong>'.__('Transaction').':</strong>  <a href =\''. Blockonomics::BASE_URL ."/api/tx?txid=$txid&addr=$address'>".substr($txid, 0, 10). '</a></p<p><b>Powered by <a href="https://wordpress.org/plugins/blockonomics-bitcoin-payments/">Blockonomics</a></b> -Easiest way to accept BTC on Wordpress.</p>';
            }
        }

        function bnomics_enqueue_stylesheets(){
          wp_enqueue_style('bnomics-style', plugin_dir_url(__FILE__) . "css/order.css");
          wp_enqueue_style( 'bnomics-altcoins', plugin_dir_url(__FILE__) . "css/cryptofont/cryptofont.min.css");
          wp_enqueue_style( 'bnomics-icons', plugin_dir_url(__FILE__) . "css/icons/icons.css");
        }

        function bnomics_enqueue_scripts(){
          wp_enqueue_script( 'angular', plugins_url('js/angular.min.js', __FILE__) );
          wp_enqueue_script( 'angular-resource', plugins_url('js/angular-resource.min.js', __FILE__) );
          wp_enqueue_script( 'app', plugins_url('js/app.js', __FILE__) );
                            wp_localize_script( 'app', 'ajax_object',
                                array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
          wp_enqueue_script( 'angular-qrcode', plugins_url('js/angular-qrcode.js', __FILE__) );
          wp_enqueue_script( 'vendors', plugins_url('js/vendors.min.js', __FILE__) );
          wp_enqueue_script( 'reconnecting-websocket', plugins_url('js/reconnecting-websocket.min.js', __FILE__) );
        }

        add_action('admin_menu', 'add_page');
        add_action('init', 'woocommerce_handle_blockonomics_return');
        add_action('woocommerce_order_details_after_order_table', 'nolo_custom_field_display_cust_order_meta', 10, 1);
        add_action('woocommerce_email_customer_details', 'nolo_bnomics_woocommerce_email_customer_details', 10, 1);
        add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockonomics_gateway');
        add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );
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
        $html .= __( 'Please configure Blockonomics Bitcoin Payments <a href="options-general.php?page=blockonomics_options">on this page</a>.', 'blockonomics-bitcoin-payments' );
        $html .= '</p>';
        $html .= '</div>';

        echo $html;        
        delete_transient( 'fx-admin-notice-example' );
      }
      if ( isset( $_GET['review_later'] ) ){
        update_option('review_notice_dismissed_timestamp', time());
      } 
      if ( isset( $_GET['already_reviewed'] ) ){
        update_option('review_notice_dismissed_timestamp', 1);
      } 
      $admin_page = get_current_screen();
      if (in_array($admin_page->base, array('dashboard', 'settings_page_blockonomics_options', 'plugins'))){
        //Show review notice only on three pages
        $blockonomics_orders = get_option('blockonomics_orders', array());
        if (count($blockonomics_orders)>10){
          $dismiss_timestamp = get_option('review_notice_dismissed_timestamp', 0);
          if ($dismiss_timestamp!=1 && time()-$dismiss_timestamp>1209600){
            //Prompt user to review the plugin after every 2 weeks 
            //if he has more than 10 orders, until he clicks on I already reviewed
            $class = 'notice notice-info';
            $message = __( 'Hey, I noticed you have been using blockonomics for accepting bitcoins - Awesome!</br> Could you please do me a BIG favor and rate it in on Wordpress?', 'blockonomics-bitcoin-payments' );
            $m1 = __('Ok, I will review it', 'blockonomics-bitcoin-payments');
            $m2=  __('I already did', 'blockonomics-bitcoin-payments');
            $m3=  __('Maybe Later', 'blockonomics-bitcoin-payments');
            printf( '<div class="%1$s"><h4>%2$s</h4><ul><li><a target="_blank" href="https://wordpress.org/support/plugin/blockonomics-bitcoin-payments/reviews/#new-post">%3$s</a></li><li><a href="?already_reviewed">%4$s</a></li><li><a href="?review_later">%5$s</a></li></ul></div>', esc_attr( $class ),  $message, $m1, $m2, $m3); 
          }
        }
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

function testSetup()
{
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
    
    $api_key = get_option("blockonomics_api_key");
    $blockonomics = new Blockonomics;
    $response = $blockonomics->get_callbacks($api_key);
    $error_str = '';
    $responseBody = json_decode(wp_remote_retrieve_body($response));
    $callback_secret = get_option('blockonomics_callback_secret');
    $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
    $callback_url = add_query_arg('secret', $callback_secret, $api_url);
    // Remove http:// or https:// from urls
    $api_url_without_schema = preg_replace('/https?:\/\//', '', $api_url);
    $callback_url_without_schema = preg_replace('/https?:\/\//', '', $callback_url);
    $response_callback_without_schema = preg_replace('/https?:\/\//', '', $responseBody[0]->callback);
    //TODO: Check This: WE should actually check code for timeout
    if (!wp_remote_retrieve_response_code($response)) {
        $error_str = __('Your server is blocking outgoing HTTPS calls', 'blockonomics-bitcoin-payments');
    }
    elseif (wp_remote_retrieve_response_code($response)==401)
        $error_str = __('API Key is incorrect', 'blockonomics-bitcoin-payments');
    elseif (wp_remote_retrieve_response_code($response)!=200)  
        $error_str = $response->data;
    elseif (!isset($responseBody) || count($responseBody) == 0)
    {
        $error_str = __('You have not entered an xpub', 'blockonomics-bitcoin-payments');
    }
    elseif (count($responseBody) == 1)
    {
        if(!$responseBody[0]->callback || $responseBody[0]->callback == null)
        {
          //No callback URL set, set one 
          $blockonomics->update_callback($api_key, $callback_url, $responseBody[0]->address);   
        }
        elseif($response_callback_without_schema != $callback_url_without_schema)
        {
          $base_url = get_bloginfo('wpurl');
          $base_url = preg_replace('/https?:\/\//', '', $base_url);
          // Check if only secret differs
          if(strpos($responseBody[0]->callback, $base_url) !== false)
          {
            //Looks like the user regenrated callback by mistake
            //Just force Update_callback on server
            $blockonomics->update_callback($api_key, $callback_url, $responseBody[0]->address);  
          }
          else
          {
            $error_str = __("You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
          }
        }
    }
    else 
    {
        // Check if callback url is set
        foreach ($responseBody as $resObj)
         if(preg_replace('/https?:\/\//', '', $resObj->callback) == $callback_url_without_schema)
            return "";
        $error_str = __("You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
    }  
    if (!$error_str)
    {
        //Everything OK ! Test address generation
        $response= $blockonomics->new_address($api_key, $callback_secret, true);
        if ($response->response_code!=200){
          $error_str = $response->response_message;
        }
    }
    if($error_str) {
        $error_str = $error_str . __('<p>For more information, please consult <a href="https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'blockonomics-bitcoin-payments');
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
                    <th scope="row"><?php echo __('Accept Altcoin Payments (Using Flyp.me)', 'blockonomics-bitcoin-payments')?></th>
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
                <tr valign="top">
                    <th scope="row"><?php echo __('Extra Currency Rate Margin % (Increase live fiat to BTC rate by small percent)', 'blockonomics-bitcoin-payments')?></th>
                    <td><input type="number" min="0" max="4" step="0.01" name="blockonomics_margin" value="<?php echo esc_attr( get_option('blockonomics_margin', 0) ); ?>" /></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" value="Save"/>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="blockonomics_api_key,blockonomics_altcoins,blockonomics_timeperiod,blockonomics_margin,blockonomics_gen_callback, api_updated" />
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

add_action( 'wp_ajax_fetch_limit', 'bnomics_fetch_limit' );
add_action( 'wp_ajax_create_order', 'bnomics_create_order' );
add_action( 'wp_ajax_check_order', 'bnomics_check_order' );
add_action( 'wp_ajax_send_email', 'bnomics_alt_deposit_email' );
add_action( 'wp_ajax_info_order', 'bnomics_info_order' );

//Look into wether this will ever be needed
add_action( 'wp_ajax_nopriv_fetch_limit', 'bnomics_fetch_limit' );
add_action( 'wp_ajax_nopriv_create_order', 'bnomics_create_order' );
add_action( 'wp_ajax_nopriv_check_order', 'bnomics_check_order' );
add_action( 'wp_ajax_nopriv_send_email', 'bnomics_alt_deposit_email' );
add_action( 'wp_ajax_nopriv_info_order', 'bnomics_info_order' );

function bnomics_fetch_limit(){
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Flyp.php';
    $flypFrom           = $_REQUEST['altcoin'];
    $flypTo             = "BTC";
    $flypme = new FlypMe();
    $limits = $flypme->orderLimits($flypFrom, $flypTo);
    if(isset($limits)){
        print(json_encode($limits));
    }
    wp_die();
}

function bnomics_create_order(){
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Flyp.php';
    $flypFrom           = $_REQUEST['altcoin'];
    $flypAmount         = $_REQUEST['amount'];
    $flypDestination    = $_REQUEST['address'];
    $flypTo             = "BTC";
    $woocommerce_order_id = $_REQUEST['order_id'];
    $flypme = new FlypMe();
    $order = $flypme->orderNew($flypFrom, $flypTo, $flypAmount, $flypDestination);
    if(isset($order->order->uuid)){
        $order_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $order_url = add_query_arg('show_order', $flypDestination, $order_url);
        update_post_meta($woocommerce_order_id, 'flyp_uuid', $order->order->uuid);
        $order = $flypme->orderAccept($order->order->uuid);
        if(isset($order->deposit_address)){
            print(json_encode($order));
        }
    }
    wp_die();
}

function bnomics_check_order(){
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Flyp.php';
    $flypID             = $_REQUEST['uuid'];
    $flypme = new FlypMe();
    $order = $flypme->orderCheck($flypID);
    if(isset($order)){
        print(json_encode($order));
    }
    wp_die();
}

function bnomics_info_order(){
    include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Flyp.php';
    $flypID             = $_REQUEST['uuid'];
    $flypme = new FlypMe();
    $order = $flypme->orderInfo($flypID);
    if(isset($order)){
        print(json_encode($order));
    }
    wp_die();
}

function bnomics_alt_deposit_email(){
    $order_id = $_REQUEST['order_id'];
    $order_link = $_REQUEST['order_link'];
    $order_coin = $_REQUEST['order_coin'];
    $order_coin_sym = $_REQUEST['order_coin_sym'];
    $order = new WC_Order($order_id);
    $billing_email = $order->billing_email;
    $email = $billing_email;
    $subject = $order_coin . __(' Payment Received', 'blockonomics-bitcoin-payments');
    $heading = $order_coin . __(' Payment Received', 'blockonomics-bitcoin-payments');
    $message = __('Your payment has been received. It will take a while for the network to confirm your order.<br>To view your payment status, copy and use the link below.<br>', 'blockonomics-bitcoin-payments').'<a href="'.$order_link.'">'.$order_link.'</a>';
    bnomics_email_woocommerce_style($email, $subject, $heading, $message);
    wp_die();
}

function bnomics_alt_deposit_email_content( $order, $heading = false, $mailer ){
    $template = 'emails/customer-processing-order.php';
 
    return wc_get_template_html( $template, array(
        'order'         => $order,
        'email_heading' => $heading,
        'sent_to_admin' => true,
        'plain_text'    => false,
        'email'         => $mailer
    ) );
}

function bnomics_email_woocommerce_style($email, $subject, $heading, $message) {
  $mailer = WC()->mailer();
  $wrapped_message = $mailer->wrap_message($heading, $message);
  $wc_email = new WC_Email;
  $html_message = $wc_email->style_inline($wrapped_message);
  // Send the email using wordpress mail function
  //wp_mail( $email, $subject, $html_message, HTML_EMAIL_HEADERS );
  // Send the email using woocommerce mailer send
  $mailer->send( $email, $subject, $html_message, array('Content-Type: text/html; charset=UTF-8') );
}

?>
