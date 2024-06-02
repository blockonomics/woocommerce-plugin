<?php
/**
 * Plugin Name: WordPress Bitcoin Payments - Blockonomics
 * Plugin URI: https://github.com/blockonomics/woocommerce-plugin
 * Description: Accept Bitcoin Payments on your WooCommerce-powered website with Blockonomics
 * Version: 3.7.0
 * Author: Blockonomics
 * Author URI: https://www.blockonomics.co
 * License: MIT
 * Text Domain: blockonomics-bitcoin-payments
 * Domain Path: /languages/
 * WC tested up to: 8.4.0
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
    add_shortcode('blockonomics_payment', 'add_payment_page_shortcode');
    add_action('wp_enqueue_scripts', 'bnomics_register_stylesheets');
    add_action('wp_enqueue_scripts', 'bnomics_register_scripts');
    add_filter("wp_list_pages_excludes", "bnomics_exclude_pages");
    add_action( 'admin_enqueue_scripts', 'blockonomics_enqueue_custom_admin_style' );
    add_action( 'wp_ajax_test_setup', 'blockonomics_test_setup' );

    function bnomics_exclude_pages( $exclude ) {
        $exclude[] = wc_get_page_id( 'payment' );
        return $exclude;
    }

    function blockonomics_enqueue_custom_admin_style() {
        if (
            isset($_GET['tab']) &&
            'checkout' === $_GET['tab'] &&
            isset($_GET['section']) &&
            'blockonomics' === $_GET['section']
        ) {
            wp_register_style('blockonomics-admin-style', plugin_dir_url(__FILE__) . "css/admin.css", '', get_plugin_data( __FILE__ )['Version']);
		    wp_enqueue_style( 'blockonomics-admin-style' );

            wp_register_script( 'blockonomics-admin-scripts', plugins_url('js/admin.js', __FILE__), array(), get_plugin_data( __FILE__ )['Version'], array( 'strategy' => 'defer' ) );
    
            wp_localize_script('blockonomics-admin-scripts', 'blockonomics_params', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'apikey'  => get_option('blockonomics_api_key')
            ));

            wp_enqueue_script( 'blockonomics-admin-scripts' );
        }
    }

    function blockonomics_test_setup() {
        include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        $result = array();

        $result['crypto'] = $blockonomics->testSetup();

        wp_send_json($result);
        wp_die();
    }

    function add_payment_page_shortcode() {
        // This is to make sure we only run the shortcode when executed to render the page.
        // Because the shortcode can be run multiple times by other plugin like All in One SEO.
        // Where it tries to build SEO content from the shortcode and this could lead to checkout page not loading correctly.
        $currentFilter = current_filter();
        if ($currentFilter == 'wp_head'){
            return;
        }
        
        $show_order = isset($_GET["show_order"]) ? sanitize_text_field(wp_unslash($_GET['show_order'])) : "";
        $crypto = isset($_GET["crypto"]) ? sanitize_key($_GET['crypto']) : "";
        $select_crypto = isset($_GET["select_crypto"]) ? sanitize_text_field(wp_unslash($_GET['select_crypto'])) : "";
        $blockonomics = new Blockonomics;

        if ($crypto === "empty") {
            return $blockonomics->load_blockonomics_template('no_crypto_selected');
        } else if ($show_order && $crypto) {
            $order_id = $blockonomics->decrypt_hash($show_order);
            return $blockonomics->load_checkout_template($order_id, $crypto);
        } else if ($select_crypto) {
            return $blockonomics->load_blockonomics_template('crypto_options');
        }
    }
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
                $error_message = isset($response->message) ? $response->message : '';
                $message = __('Error while generating temporary APIKey: '. $error_message, 'blockonomics-bitcoin-payments');
                display_admin_message($message, 'error');
            }
            else
            {
                update_option("blockonomics_temp_api_key", isset($response->apikey) ? $response->apikey : '');
            }
        }
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

    function bnomics_register_stylesheets(){
        wp_register_style('bnomics-style', plugin_dir_url(__FILE__) . "css/order.css", '', get_plugin_data( __FILE__ )['Version']);
    }

    function bnomics_register_scripts(){
        wp_register_script( 'reconnecting-websocket', plugins_url('js/vendors/reconnecting-websocket.min.js', __FILE__), array(), get_plugin_data( __FILE__ )['Version'], array( 'strategy' => 'defer' ) );
        wp_register_script( 'qrious', plugins_url('js/vendors/qrious.min.js', __FILE__), array(), get_plugin_data( __FILE__ )['Version'], array( 'strategy' => 'defer' ) );
        wp_register_script( 'copytoclipboard', plugins_url('js/vendors/copytoclipboard.js', __FILE__), array(), get_plugin_data( __FILE__ )['Version'], array( 'strategy' => 'defer' ) );
        wp_register_script( 'bnomics-checkout', plugins_url('js/checkout.js', __FILE__), array('reconnecting-websocket', 'qrious','copytoclipboard'), get_plugin_data( __FILE__ )['Version'], array('in_footer' => true, 'strategy' => 'defer'  ) );  
    }
}

// After all plugins have been loaded, initialize our payment gateway plugin
add_action('plugins_loaded', 'blockonomics_woocommerce_init', 0);

register_activation_hook( __FILE__, 'blockonomics_activation_hook' );
add_action('admin_notices', 'blockonomics_plugin_activation');

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

global $blockonomics_db_version;
$blockonomics_db_version = '1.4';

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
// Page creation function  for the Blockonomics payement following woo-commerce page creation shortcode logic 
function blockonomics_create_payment_page()
{
    global $wp_rewrite;

    if ( null === $wp_rewrite ) {
        $wp_rewrite = new \WP_Rewrite;
    }
    wc_create_page(
        'payment',
        'woocommerce_payment_page_id',
        'Payment',
        '<!-- wp:shortcode -->[blockonomics_payment]<!-- /wp:shortcode -->',
        'checkout',
        'publish'
    );
}

// Since WP 3.1 the activation function registered with register_activation_hook() is not called when a plugin is updated.
// blockonomics_update_db_check() is loaded for every PHP page by plugins_loaded hook
function blockonomics_update_db_check() {
    global $blockonomics_db_version;
    $installed_ver = get_site_option( 'blockonomics_db_version' );
    // blockonomics_create_table() and blockonomics_create_payment_page() should only be run if there is no $installed_ver, refer https://github.com/blockonomics/woocommerce-plugin/issues/296
    if (empty($installed_ver)){
        include_once(WC()->plugin_path().'/includes/admin/wc-admin-functions.php');
        blockonomics_plugin_setup();
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
    if (version_compare($installed_ver, '1.4', '<')){ // Plugin version should be 1.4
        include_once(WC()->plugin_path().'/includes/admin/wc-admin-functions.php');
        blockonomics_create_payment_page();
    }
    update_option( 'blockonomics_db_version', $blockonomics_db_version );
}

add_action( 'plugins_loaded', 'blockonomics_update_db_check' );
register_activation_hook( __FILE__, 'blockonomics_plugin_setup' );

function blockonomics_plugin_setup() {
    blockonomics_create_table();
    blockonomics_create_payment_page();
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
  if( get_transient( 'blockonomics_activation_hook_transient' ) || get_option('blockonomics_api_key') == '' ){

    $html = '<div class="notice notice-warning is-dismissible">';
    $html .= '<p>';
    $html .= __( 'Blockonomics is almost ready. To get started, connect your account <a href="admin.php?page=wc-settings&tab=checkout&section=blockonomics">on the Account setup page</a>.', 'blockonomics-bitcoin-payments' );
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
    // blockonomics_lite is only for db version below 1.3
    delete_option('blockonomics_lite');
    delete_option('blockonomics_nojs');
    delete_option('blockonomics_network_confirmation');
    delete_option('blockonomics_partial_payments');
    delete_option('woocommerce_blockonomics_settings');

    global $wpdb;
    // drop blockonomics_orders & blockonomics_payments on uninstallation
    // blockonomics_orders was the payments table before db version 1.2
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS ".$wpdb->prefix."blockonomics_orders , ".$wpdb->prefix."blockonomics_payments"));
    delete_option("blockonomics_db_version");
    


    // Remove the custom page and shortcode added for payment
    remove_shortcode('blockonomics_payment');
    wp_trash_post( get_option( 'woocommerce_payment_page_id' ) );
}

function blockonomics_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=blockonomics">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'blockonomics_plugin_add_settings_link' );

add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_blockonomics_woocommerce_block_support' );

function woocommerce_gateway_blockonomics_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'class-wc-blockonomics-blocks-support.php';
		// priority is important here because this ensures this integration is
		// registered before the WooCommerce Blocks built-in blockonomics registration.
		// Blocks code has a check in place to only register if 'blockonomics' is not
		// already registered.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					WC_Blockonomics_Blocks_Support::class,
					function() {
						return new WC_Blockonomics_Blocks_Support();
					}
				);
				$payment_method_registry->register(
					$container->get( WC_Blockonomics_Blocks_Support::class )
				);
			},
			5
		);
	}
}