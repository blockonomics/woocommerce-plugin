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
    public function handle_requests()
    {
        $show_order = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : "";
        $crypto = isset($_REQUEST["crypto"]) ? $_REQUEST["crypto"] : "";
        $select_crypto = isset($_REQUEST["select_crypto"]) ? $_REQUEST["select_crypto"] : "";
        $finish_order = isset($_REQUEST["finish_order"]) ? $_REQUEST["finish_order"] : "";
        $get_order = isset($_REQUEST['get_order']) ? $_REQUEST['get_order'] : "";
        $secret = isset($_REQUEST['secret']) ? $_REQUEST['secret'] : "";
        $addr = isset($_REQUEST['addr']) ? $_REQUEST['addr'] : "";
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : "";
        $value = isset($_REQUEST['value']) ? $_REQUEST['value'] : "";
        $txid = isset($_REQUEST['txid']) ? $_REQUEST['txid'] : "";
        $qrcode = isset($_REQUEST['qrcode']) ? $_REQUEST['qrcode'] : "";
        
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;

        if ($show_order && $crypto) {
            $blockonomics->load_checkout_template($show_order, $crypto);
        }else if ($select_crypto) {
            $blockonomics->load_blockonomics_template('crypto_options');
        }else if ($finish_order) {
            $blockonomics->redirect_finish_order($finish_order);
        }else if ($get_order && $crypto) {
            $blockonomics->get_order_info($get_order, $crypto);
        }else if ($secret && $addr && isset($status) && $value && $txid) {
            $blockonomics->process_callback($secret, $addr, intval($status), $value, $txid);
        }else if ($qrcode) {
          $blockonomics->generate_qrcode($qrcode);
        }

        exit();
    }
}
