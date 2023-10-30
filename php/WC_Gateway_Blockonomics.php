<?php

/**
 * This class provides the functions needed for extending the WooCommerce
 * Payment Gateway class
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
        
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        $active_cryptos = $blockonomics->getActiveCurrencies();

        if (isset($active_cryptos['btc']) && isset($active_cryptos['bch'])) {
            $this->icon = plugins_url('img', dirname(__FILE__)) . '/bitcoin-bch-icon.png';
        } elseif (isset($active_cryptos['btc'])) {
            $this->icon = plugins_url('img', dirname(__FILE__)) . '/bitcoin-icon.png';
        } elseif (isset($active_cryptos['bch'])) {
            $this->icon = plugins_url('img', dirname(__FILE__)) . '/bch-icon.png';
        }

        $this->has_fields        = false;
        $this->order_button_text = __('Pay with bitcoin', 'blockonomics-bitcoin-payments');

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

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
                'handle_requests'
            )
        );

		
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
            ),
            'apikey' => array(
                'title' => __( 'API Key', 'blockonomics-bitcoin-payments' ),
                'type' => 'text',
                'description' => __('To get your API Key, click Get Started for Free on https://blockonomics.co/merchants', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_api_key')
            ),
            'no_javascript' => array(
                'title' => __( '', 'blockonomics-bitcoin-payments' ),
                'type' => 'checkbox',
                'label' => __('No JS ', 'blockonomics-bitcoin-payments'),
                'description' => __('To get your API Key, click Get Started for Free on https://blockonomics.co/merchants', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_nojs') == 1 ? 'yes' : 'no',
            ),
            'partialpayment' => array(
                'title' => __('', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Partial Payment ', 'blockonomics-bitcoin-payments'),
                'description' => __('Allow customer to pay order via multiple payement  ', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_partial_payments') == 1 ? 'yes' : 'no',
            ),
            'currency' => array(
                'id'    => 'currency',
                'type'  => 'currency',
                'title' => __( 'Currency', 'blockonomics-bitcoin-payments' ),
            )
        );
    }

    function get_callback_url()
    {
        $callback_secret = get_option('blockonomics_callback_secret');
        $callback_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $callback_url);
        return $callback_url;
    }
    public function load_network_method_options() {
        return array(
            'local_delivery'   => __('Local Delivery', 'blockonomics-bitcoin-payments'),
            'express_shipping' => __('Express Shipping', 'blockonomics-bitcoin-payments'),
            'standard_post'    => __('Standard Post', 'blockonomics-bitcoin-payments'),
        );
    }

    public function generate_currency_html( $key, $value ) {
        ob_start();
        ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    Bitcoin
                </th>
                <td class="forminp">
                    assdadasdsad
                </td>
		    </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    Bitcoin Cash
                </th>
                <td class="forminp">
                </td>
		    </tr>
        <?php
        return ob_get_clean();
    }

    public  function get_btc_enabled($btc_enabled){
      
    
        // Check if BTC is enabled and if an error exists
        if ($btc_enabled && isset($btc_error)) {
            
            if ($btc_error) {
                echo 
                '<td colspan="2" class="notice notice-error bnomics-test-setup-message">'.$error.'.<br/>'.
                    __("Please consult ", 'blockonomics-bitcoin-payments').
                    '<a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">'.
                    __("this troubleshooting article", 'blockonomics-bitcoin-payments').'</a>.
                </td>';
            } else {
                echo '<td colspan="2"class="notice notice-success bnomics-test-setup-message">'.__("Success", 'blockonomics-bitcoin-payments').'</td>';
            }
        }

    }
  

   
    public function process_admin_options()
    {
        if (!parent::process_admin_options()) {
            return false;
        }
        update_option('blockonomics_partial_payments', parent::get_option('partialpayment') == 'yes' ? 1 : 0);
        update_option('blockonomics_api_key', parent::get_option('apikey'));
        update_option('blockonomics_nojs', parent::get_option('no_javascript') == 'yes' ? 1 : 0);
    }
    
    // Woocommerce process payment, runs during the checkout
    public function process_payment($order_id)
    {
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        
        $order_url = $blockonomics->get_order_checkout_url($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order_url
        );
    }

    // Handles requests to the blockonomics page
    // Sanitizes all request/input data
    public function handle_requests()
    {
        $crypto = isset($_GET["crypto"]) ? sanitize_key($_GET['crypto']) : "";
        $finish_order = isset($_GET["finish_order"]) ? sanitize_text_field(wp_unslash($_GET['finish_order'])) : "";
        $get_amount = isset($_GET['get_amount']) ? sanitize_text_field(wp_unslash($_GET['get_amount'])) : "";
        $secret = isset($_GET['secret']) ? sanitize_text_field(wp_unslash($_GET['secret'])) : "";
        $addr = isset($_GET['addr']) ? sanitize_text_field(wp_unslash($_GET['addr'])) : "";
        $status = isset($_GET['status']) ? intval($_GET['status']) : "";
        $value = isset($_GET['value']) ? absint($_GET['value']) : "";
        $txid = isset($_GET['txid']) ? sanitize_text_field(wp_unslash($_GET['txid'])) : "";
        $rbf = isset($_GET['rbf']) ? wp_validate_boolean(intval(wp_unslash($_GET['rbf']))) : "";

        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;

        if ($finish_order) {
            $order_id = $blockonomics->decrypt_hash($finish_order);
            $blockonomics->redirect_finish_order($order_id);
        }else if ($get_amount && $crypto) {
            $order_id = $blockonomics->decrypt_hash($get_amount);
            $blockonomics->get_order_amount_info($order_id, $crypto);
        }else if ($secret && $addr && isset($status) && $value && $txid) {
            $blockonomics->process_callback($secret, $addr, $status, $value, $txid, $rbf);
        }

        exit();
    }
}
