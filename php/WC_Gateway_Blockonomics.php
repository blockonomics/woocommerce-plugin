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
        $this->icon = plugins_url('img', dirname(__FILE__)).'/bitcoin-icon.png';

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
            )
        );
    }

    public function process_admin_options()
    {
        if (!parent::process_admin_options()) {
            return false;
        }
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
        $show_order = isset($_GET["show_order"]) ? sanitize_text_field(wp_unslash($_GET['show_order'])) : "";
        $crypto = isset($_GET["crypto"]) ? sanitize_key($_GET['crypto']) : "";
        $select_crypto = isset($_GET["select_crypto"]) ? sanitize_text_field(wp_unslash($_GET['select_crypto'])) : "";
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

        if($crypto === "empty"){
            $blockonomics->load_blockonomics_template('no_crypto_selected');
        }else if ($show_order && $crypto) {
            $order_id = $blockonomics->decrypt_hash($show_order);
            $blockonomics->load_checkout_template($order_id, $crypto);
        }else if ($select_crypto) {
            $blockonomics->load_blockonomics_template('crypto_options');
        }else if ($finish_order) {
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
