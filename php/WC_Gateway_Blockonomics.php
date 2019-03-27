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

    /**
    public function admin_options()
    {
        echo '<h3>' . __('Blockonomics Payment Gateway', 'blockonomics-bitcoin-payments') . '</h3>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }
    */

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
        include_once 'Blockonomics.php';
        global $woocommerce;

        $order = new WC_Order($order_id);

        $success_url = add_query_arg('return_from_blockonomics', true, $this->get_return_url($order));

        // Blockonomics mangles the order param so we have to put it somewhere else and restore it on init
        $cancel_url = $order->get_cancel_order_url_raw();
        $cancel_url = add_query_arg('return_from_blockonomics', true, $cancel_url);
        $cancel_url = add_query_arg('cancelled', true, $cancel_url);
        $cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);

        $blockonomics = new Blockonomics;
        $responseObj = $blockonomics->new_address(get_option("blockonomics_callback_secret"));
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
        $uuid = isset($_REQUEST["uuid"]) ? $_REQUEST["uuid"] : "";
        if ($address) {
            $dir = plugin_dir_path(__FILE__);
            add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
            include $dir."../templates/order.php";
            exit();
        }else if ($uuid){
            $dir = plugin_dir_path(__FILE__);
            add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
            include $dir."../templates/track.php";
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
                        //Check underpayment slack
                        $underpayment_slack = get_option("blockonomics_underpayment_slack", 0)/100 * $order['satoshi'];
                        if ($order['satoshi'] > $_REQUEST['value'] - $underpayment_slack) {
                            $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                            $wc_order->payment_complete($order['txid']);
                        }else{
                            $status = -2; //Payment error , amount not matching
                            $wc_order->update_status('failed', __('Paid BTC amount less than expected.', 'blockonomics-bitcoin-payments'));
                        }
                    }
                    else{
                        if ($order['satoshi'] < $_REQUEST['value']) {
                            $wc_order->add_order_note(__('Overpayment of BTC amount', 'blockonomics-bitcoin-payments'));
                        }
                        $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                        $wc_order->payment_complete($order['txid']);
                    }
                    // Keep track of funds in temp wallet
                    if(get_option('blockonomics_temp_api_key')) {
                        $current_temp_amount = get_option('blockonomics_temp_withdraw_amount');
                        $new_temp_amount = $current_temp_amount + $_REQUEST['value'];
                        update_option('blockonomics_temp_withdraw_amount', $new_temp_amount);
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