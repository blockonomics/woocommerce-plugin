<?php
/**
 * Plugin Name: blockonomics-woocommerce
 * Plugin URI: https://github.com/blockonomics/blockonomics-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with Coinbase.
 * Version: 2.1.3
 * Author: Coinbase Inc.
 * Author URI: https://blockonomics.com
 * License: MIT
 * Text Domain: blockonomics-woocommerce
 */

/*  Copyright 2014 Coinbase Inc.

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

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	function blockonomics_woocommerce_init() {

		if (!class_exists('WC_Payment_Gateway'))
			return;

		/**
		 * Coinbase Payment Gateway
		 *
		 * Provides a Coinbase Payment Gateway.
		 *
		 * @class       WC_Gateway_Coinbase
		 * @extends     WC_Payment_Gateway
		 * @version     2.0.1
		 * @author      Coinbase Inc.
		 */
		class WC_Gateway_Blockonomics extends WC_Payment_Gateway {

			public function __construct() {
				$this->id   = 'blockonomics';
				$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/blockonomics.png';

				$this->has_fields        = false;
				$this->order_button_text = __('Pay with bitcoin', 'blockonomics-woocommerce');


				$this->title       = $this->get_option('title');
				$this->description = $this->get_option('description');

        add_option('blockonomics_orders', array());
				// Actions
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				));
        //add_action('admin_init', array($this, 'admin_init'));
				add_action('woocommerce_receipt_blockonomics', array(
					$this,
					'receipt_page'
				));

				// Payment listener/API hook
				add_action('woocommerce_api_wc_gateway_blockonomics', array(
					$this,
					'check_blockonomics_callback'
				));
      }
			public function admin_options() {
				echo '<h3>' . __('Coinbase Payment Gateway', 'blockonomics-woocommerce') . '</h3>';
				$blockonomics_account_email = get_option("blockonomics_account_email");
				$blockonomics_error_message = get_option("blockonomics_error_message");
				if ($blockonomics_account_email != false) {
					echo '<p>' . __('Successfully connected Coinbase account', 'blockonomics-woocommerce') . " '$blockonomics_account_email'" . '</p>';
				} elseif ($blockonomics_error_message != false) {
					echo '<p>' . __('Could not validate API Key:', 'blockonomics-woocommerce') . " $blockonomics_error_message" . '</p>';
				}
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}
      public function admin_init() {
        register_setting('blockonomics_options', 'api_key');
      }


			function process_admin_options() {
				if (!parent::process_admin_options())
					return false;

				require_once(plugin_dir_path(__FILE__) . 'blockonomics-php' . DIRECTORY_SEPARATOR . 'Coinbase.php');

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				// Validate merchant API key
				try {
					$blockonomics = Coinbase::withApiKey($api_key, $api_secret);
					$user     = $blockonomics->getUser();
					update_option("blockonomics_account_email", $user->email);
					update_option("blockonomics_error_message", false);
				}
				catch (Exception $e) {
					$error_message = $e->getMessage();
					update_option("blockonomics_account_email", false);
					update_option("blockonomics_error_message", $error_message);
					return;
				}
			}


			function process_payment($order_id) {

				require_once(plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php');
				global $woocommerce;

				$order = new WC_Order($order_id);

				$success_url = add_query_arg('return_from_blockonomics', true, $this->get_return_url($order));

				// Coinbase mangles the order param so we have to put it somewhere else and restore it on init
				$cancel_url = $order->get_cancel_order_url_raw();
				$cancel_url = add_query_arg('return_from_blockonomics', true, $cancel_url);
				$cancel_url = add_query_arg('cancelled', true, $cancel_url);
				$cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);


				$api_key    = $this->get_option('apiKey');
				if ($api_key == '') {
					if ( version_compare( $woocommerce->version, '2.1', '>=' ) ) {
						wc_add_notice(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'blockonomics-woocommerce'), 'error' );
					} else {
						$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'blockonomics-woocommerce'));
					}
					return;
				}

				try {
					$blockonomics = new Blockonomics;
          $address     = $blockonomics->new_address(get_option('blockonomics_api_key'));
          $price = $blockonomics->get_price(get_woocommerce_currency());
          $blockonomics_orders = get_option('blockonomics_orders');
          $order = array(
            'value'              => $order->get_total(),
            'satoshi'            => intval(1.0e8*$order->get_total()/$price),
            'currency'           => get_woocommerce_currency(),
            'address'            => $address,
            'status'             => -1,
            'timestamp'          => time(),
            'txid'               => ''
          );
          $blockonomics_orders[$order_id] = $order;
          update_option('blockonomics_orders', $blockonomics_orders);
				}
				catch (Exception $e) {
					$order->add_order_note(__('Error while processing blockonomics payment:', 'blockonomics-woocommerce') . ' ' . var_export($e, TRUE));
					if ( version_compare( $woocommerce->version, '2.1', '>=' ) ) {
						wc_add_notice(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'blockonomics-woocommerce'), 'error' );
					} else {
						$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'blockonomics-woocommerce'));
					}
					return;
				}

				return array(
					'result'   => 'success',
					'redirect' => "/wp-content/plugins/blockonomics-woocommerce/views/index.html#/$order_id"
				);
			}

      function check_blockonomics_callback() {
        $orderid = $_REQUEST['order_id'];
        $orders = get_option('blockonomics_orders');
        if ($orderid){
        header("Content-Type: application/json");
        exit(json_encode($orders[$orderid]));
        }

				$callback_secret = get_option("blockonomics_callback_secret");
        if ($callback_secret  && $callback_secret == $_REQUEST['secret']) {
          $addr = $_REQUEST['addr'];
          foreach($orders as $key => $value) {
            if ($value['address'] == $addr){
              if ($value['satoshi'] ==  intval($_REQUEST['value']))
              {
                $value['status'] = intval($_REQUEST['status']);
              }
              $value['txid'] =  $_REQUEST['txid'];
            }
          }
        exit(0);  
				}

				// Legitimate order callback from Coinbase
				header('HTTP/1.1 200 OK');

				// Add Coinbase metadata to the order
				update_post_meta($order->id, __('Coinbase Order ID', 'blockonomics-woocommerce'), wc_clean($blockonomics_order->id));
				if (isset($blockonomics_order->customer) && isset($blockonomics_order->customer->email)) {
					update_post_meta($order->id, __('Coinbase Account of Payer', 'blockonomics-woocommerce'), wc_clean($blockonomics_order->customer->email));
				}

				switch (strtolower($blockonomics_order->status)) {

					case 'completed':

						// Check order not already completed
						if ($order->status == 'completed') {
							exit;
						}

						$order->add_order_note(__('Coinbase payment completed', 'blockonomics-woocommerce'));
						$order->payment_complete();

						break;
					case 'canceled':

						$order->update_status('failed', __('Coinbase reports payment cancelled.', 'blockonomics-woocommerce'));
						break;

				}

				exit;
			}
		}

		/**
		 * Add this Gateway to WooCommerce
		 **/
		function woocommerce_add_blockonomics_gateway($methods) {
			$methods[] = 'WC_Gateway_Blockonomics';
			return $methods;
		}

		function woocommerce_handle_blockonomics_return() {
			if (!isset($_GET['return_from_blockonomics']))
				return;

			if (isset($_GET['cancelled'])) {
				$order = new WC_Order($_GET['order']['custom']);
				if ($order->status != 'completed') {
					$order->update_status('failed', __('Customer cancelled blockonomics payment', 'blockonomics-woocommerce'));
				}
			}

			// Coinbase order param interferes with woocommerce
			unset($_GET['order']);
			unset($_REQUEST['order']);
			if (isset($_GET['order_key'])) {
				$_GET['order'] = $_GET['order_key'];
			}
		}


    // Add entry in the settings menu
    function add_page() {
      generate_secret();
      add_options_page('Blockonomics', 'Blockonomics', 'manage_options',
        'blockonomics_options',  'show_options');
    }
    
    function generate_secret() {
      $callback_secret = get_option("blockonomics_callback_secret");
      if (!$callback_secret) {
        $callback_secret = sha1(openssl_random_pseudo_bytes(20));
        update_option("blockonomics_callback_secret", $callback_secret);
      }
    }
      

    add_action('admin_menu', 'add_page');
    add_action('init', 'woocommerce_handle_blockonomics_return');
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockonomics_gateway');
	}



add_action('plugins_loaded', 'blockonomics_woocommerce_init', 0);
}


function show_options(){
  ?>	
  <div class="wrap">
    <h2>Blockonomics</h2>
    <form method="post" action="options.php">
    <?php wp_nonce_field('update-options') ?>
  <table class="form-table">
    <tr valign="top"><th scope="row">BLOCKONOMICS API KEY (Generate from <a href="https://blockonomics.co/blockonomics">Wallet Watcher</a> &gt; Settings)</th>
    <td><input type="text" name="blockonomics_api_key" value="<?php echo get_option('blockonomics_api_key'); ?>" /></td>
    </tr>
    <tr valign="top"><th scope="row">CALLBACK URL (Copy this url and set in <a href="https://www.blockonomics.co/merchants">Merchants</a>)</th>
    <td><?php
        $callback_secret = get_option('blockonomics_callback_secret'); 
				$notify_url = WC()->api_request_url('WC_Gateway_Blockonomics');
				$notify_url = add_query_arg('secret', $callback_secret, $notify_url);
        echo $notify_url ?></td>
    </tr>
    </table>
    <p class="submit">
    <input type="submit" class="button-primary" value="Save" />
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="blockonomics_api_key" />
    </p>
    </form>
    </div> 	
<?php
}
?>
