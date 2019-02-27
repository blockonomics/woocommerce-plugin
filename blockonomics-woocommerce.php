<?php
/**
 * Plugin Name: Wordpress Bitcoin Payments - Blockonomics
 * Plugin URI: https://github.com/blockonomics/woocommerce-plugin
 * Description: Accept Bitcoin Payments on your WooCommerce-powered website with Blockonomics
 * Version: 1.7.2
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

/**
 * Initialize hooks needed for the payment gateway
 */
function blockonomics_woocommerce_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'WC_Gateway_Blockonomics.php';

    add_action('admin_menu', 'add_page');
    add_action('init', 'woocommerce_handle_blockonomics_return');
    add_action('woocommerce_order_details_after_order_table', 'nolo_custom_field_display_cust_order_meta', 10, 1);
    add_action('woocommerce_email_customer_details', 'nolo_bnomics_woocommerce_email_customer_details', 10, 1);
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockonomics_gateway');
    add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );

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
        include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
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
                $message = __('Error while generating temporary APIKey: '. $response->message, 'blockonomics-bitcoin-payments');
                display_admin_message($message, 'error');
            }
            else
            {
                update_option("blockonomics_temp_api_key", $response->apikey);
            }
        }

        add_options_page(
            'Blockonomics', 'Blockonomics', 'manage_options',
            'blockonomics_options', 'show_options'
        );

        if (get_option('blockonomics_api_updated') == 'true' && $_GET['settings-updated'] == 'true')
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
        load_plugin_textdomain('blockonomics-bitcoin-payments', false, dirname(plugin_basename(__FILE__)) . '/languages/');
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
        </script>

        <div class="wrap">
            <h2>Blockonomics</h2>
            <div id="installation-instructions">
                <p>
                    <b><?php echo __('Installation instructions', 'blockonomics-bitcoin-payments');?>: </b><a href="https://www.youtube.com/watch?v=Kck3a-9nh6E" target="_blank">Youtube Tutorial</a> | <a href="https://blog.blockonomics.co/how-to-accept-bitcoin-payments-on-woocommerce-using-blockonomics-f18661819a62" target="_blank">Blog Tutorial</a>
                </p>
            </div>
            <form method="post" id="myform" onsubmit="return validateBlockonomicsForm()" action="options.php">
                <?php wp_nonce_field('update-options') ?>
                <input type="hidden" name="blockonomics_api_updated" id="blockonomics_api_updated" value="false">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">CALLBACK URL 
                            <a href="javascript:gen_secret()" id="generate-callback" style="font:400 20px/1 dashicons;margin-left: 5px;top: 4px;position:relative;text-decoration: none;" title="Generate New Callback URL">&#xf463;<a>
                        </th>
                        <td><?php echo get_callback_url(); ?></td>
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
                    <tr valign="top">
                        <th scope="row">Destination BTC wallet for payments</th>
                        <td>
                            <?php
                            $total_received = get_option('blockonomics_temp_withdraw_amount') / 1.0e8;
                            $api_key = get_option("blockonomics_api_key");
                            $temp_api_key = get_option("blockonomics_temp_api_key");
                            if ($temp_api_key && !($total_received > 0)): ?>

                            <p><b>Blockonomics Wallet</b> (Balance: 0 BTC)</p>
                            <p>We are using a temporary wallet on Blockonomics to receive your payments.</p>
                            <p>To receive payments directly to your wallet (recommended) -> Follow Wizard by clicking on <i>Get Started for Free</i> on <a href="https://www.blockonomics.co/merchants" target="_blank">Merchants</a> and enter the APIKey below</p>

                            <?php elseif ($temp_api_key && $total_received > 0): ?>

                            <p><b>Blockonomics Wallet</b> (Balance: <?php echo "$total_received"; ?> BTC)</p>
                            <?php if (!$api_key): ?>
                            <p> To withdraw, follow wizard by clicking on <i>Get Started for Free</i> on <a href="https://www.blockonomics.co/merchants" target="_blank">Merchants</a>, then enter the APIKey below.
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
                <p class="submit">
                    <input type="submit" class="button-primary" value="Save"/>
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="page_options" value="blockonomics_api_key,blockonomics_altcoins,blockonomics_timeperiod,blockonomics_margin,blockonomics_gen_callback,blockonomics_api_updated" />
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

    //Ajax for user checkouts through Woocommerce
    add_action( 'wp_ajax_save_uuid', 'bnomics_alt_save_uuid' );
    add_action( 'wp_ajax_send_email', 'bnomics_alt_refund_email' );

    //Ajax for guest checkouts through Woocommerce
    add_action( 'wp_ajax_nopriv_save_uuid', 'bnomics_alt_save_uuid' );
    add_action( 'wp_ajax_nopriv_send_email', 'bnomics_alt_refund_email' );

    function bnomics_alt_save_uuid(){
        $orders = get_option('blockonomics_orders');
        $address = $_REQUEST['address'];
        $uuid = $_REQUEST['uuid'];
        $order = $orders[$address];
        $wc_order = new WC_Order($order['order_id']);
        update_post_meta($wc_order->get_id(), 'flyp_uuid', $uuid);
        wp_die();
    }

    function bnomics_alt_refund_email(){
        $order_id = $_REQUEST['order_id'];
        $order_link = $_REQUEST['order_link'];
        $order_coin = $_REQUEST['order_coin'];
        $order_coin_sym = $_REQUEST['order_coin_sym'];
        $order = new WC_Order($order_id);
        $billing_email = $order->billing_email;
        $email = $billing_email;
        $subject = $order_coin . ' ' . __('Refund', 'blockonomics-bitcoin-payments');
        $heading = $order_coin . ' ' . __('Refund', 'blockonomics-bitcoin-payments');
        $message = __('Your order couldn\'t be processed as you paid less than expected.<br>The amount you paid will be refunded.<br>Visit the link below to enter your refund address.<br>', 'blockonomics-bitcoin-payments').'<a href="'.$order_link.'">'.$order_link.'</a>';
        bnomics_email_woocommerce_style($email, $subject, $heading, $message);
        wp_die();
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
}

// After all plugins have been loaded, initialize our payment gateway plugin
add_action('plugins_loaded', 'blockonomics_woocommerce_init', 0);

register_activation_hook( __FILE__, 'blockonomics_activation_hook' );
add_action('admin_notices', 'plugin_activation');

function blockonomics_activation_hook() {
    if(!is_plugin_active('woocommerce/woocommerce.php'))
    {
        trigger_error(__( 'Wordpress Bitcoin Payments - Blockonomics requires WooCommere plugin to be installed and active.', 'blockonomics-bitcoin-payments' ).'<br>', E_USER_ERROR);
    }
    set_transient( 'blockonomics_activation_hook_transient', true, 5);
}

//Show message when plugin is activated
function plugin_activation() {
  if(!is_plugin_active('woocommerce/woocommerce.php'))
  {
      $html = '<div class="error">';
      $html .= '<p>';
      $html .= __( 'Wordpress Bitcoin Payments - Blockonomics failed to load. Please activate WooCommere plugin.', 'blockonomics-bitcoin-payments' );
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
  if ( isset( $_GET['review_later'] ) ){
    update_option('blockonomics_review_notice_dismissed_timestamp', time());
  } 
  if ( isset( $_GET['already_reviewed'] ) ){
    update_option('blockonomics_review_notice_dismissed_timestamp', 1);
  } 
  $admin_page = get_current_screen();
  if (in_array($admin_page->base, array('dashboard', 'settings_page_blockonomics_options', 'plugins'))){
    //Show review notice only on three pages
    $blockonomics_orders = get_option('blockonomics_orders', array());
    if (count($blockonomics_orders)>10){
      $dismiss_timestamp = get_option('blockonomics_review_notice_dismissed_timestamp', 0);
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

// On uninstallation, clear every option the plugin has set
register_uninstall_hook( __FILE__, 'blockonomics_uninstall_hook' );
function blockonomics_uninstall_hook() {
    delete_option('blockonomics_callback_secret');
    delete_option('blockonomics_api_key');
    delete_option('blockonomics_temp_api_key');
    delete_option('blockonomics_temp_withdraw_amount');
    delete_option('blockonomics_orders');
    delete_option('blockonomics_review_notice_dismissed_timestamp');
    delete_option('blockonomics_margin');
    delete_option('blockonomics_timeperiod');
    delete_option('blockonomics_api_updated');
    delete_option('blockonomics_altcoins');
}


function plugin_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=blockonomics_options">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'plugin_add_settings_link' );
