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

        $order = new WC_Order($order_id);
        $blockonomics_orders = get_option('blockonomics_orders');
        $success_url = add_query_arg('return_from_blockonomics', true, $this->get_return_url($order));

        // Blockonomics mangles the order param so we have to put it somewhere else and restore it on init
        $cancel_url = $order->get_cancel_order_url_raw();
        $cancel_url = add_query_arg('return_from_blockonomics', true, $cancel_url);
        $cancel_url = add_query_arg('cancelled', true, $cancel_url);
        $order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;
        $cancel_url = add_query_arg('order_key', $order_key, $cancel_url);

        $order = array(
            'value'              => $order->get_total(),
            'satoshi'            => '',
            'currency'           => get_woocommerce_currency(),
            'order_id'           => $order_id,
            'crypto'             => 'BTC',
            'address'            => '',
            'status'             => -1,
            'timestamp'          => time(),
            'txid'               => ''
        );
        //Using order_id as key.
        $blockonomics_orders[$order_id] = $order;
        update_option('blockonomics_orders', $blockonomics_orders);
        $order_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $order_url = add_query_arg('show_order', $order_id, $order_url);

        return array(
            'result'   => 'success',
            'redirect' => $order_url
        );
    }

    public function redirect_to_template($template){
        add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );
        add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
        if ( $overridden_template = locate_template( $template ) ) {
            // locate_template() returns path to file
            // if either the child theme or the parent theme have overridden the template
            load_template( $overridden_template );
        } else {
            // If neither the child nor parent theme have overridden the template,
            // we load the template from the 'templates' sub-directory of the directory this file is in
            load_template( plugin_dir_path(__FILE__)."../templates/" .$template );
        }
        exit();
    }

    public function check_blockonomics_callback()
    {
        if(isset($_REQUEST["payment_check"])){
            $this->redirect_to_template('payment_confirmed.php');
        }
        $orders = get_option('blockonomics_orders');
        $order_id = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : "";
        $uuid = isset($_REQUEST["uuid"]) ? $_REQUEST["uuid"] : "";
        if ($order_id) {
            $nojs_version = get_option('blockonomics_nojs');
            if($nojs_version){
              $this->redirect_to_template('blockonomics_nojs_checkout.php');
            }else{
              $this->redirect_to_template('blockonomics_checkout.php');
            }
        }else if ($uuid){
            $this->redirect_to_template('track.php');
        }
        $order_id = isset($_REQUEST["finish_order"]) ? $_REQUEST["finish_order"] : "";
        if ($order_id) {
            $order = $orders[$order_id];
            $wc_order = new WC_Order($order_id);
            echo $order['order_id'];
            wp_redirect($wc_order->get_checkout_order_received_url());
            exit();
        }
        $order_id = isset($_REQUEST['get_order']) ? $_REQUEST['get_order'] : "";
        $crypto= isset($_REQUEST['crypto']) ? $_REQUEST['crypto'] : "";
        if ($order_id && $crypto) {
            include_once 'Blockonomics.php';
            $blockonomics = new Blockonomics;
            global $woocommerce;
            $order = $orders[$order_id];
            $wc_order = new WC_Order($order_id);
            if(get_woocommerce_currency() != 'BTC'){
                $price = $blockonomics->get_price(get_woocommerce_currency(), $crypto);
                $price = $price * 100/(100+get_option('blockonomics_margin', 0));
            }else{
                $price = 1;
            }
            $currentAddress = get_post_meta($order_id,"blockonomics_address");
            if($currentAddress[0]['address'] && $currentAddress[0]['crypto'] == $crypto) {
                $address = $currentAddress[0]['address'];
            } else {
                $responseObj = $blockonomics->new_address(get_option("blockonomics_callback_secret"), $crypto);
                if($responseObj->response_code != 200) {
                    $this->displayError($woocommerce);
                    return;
                }
                $address = $responseObj->address;
            }

            $order['address'] = $address;
            $order['satoshi'] = intval(round(1.0e8*$wc_order->get_total()/$price));
            $order['crypto'] = $crypto;

            $orders[$order_id] = $order;
            update_option('blockonomics_orders', $orders);
            update_post_meta($order_id, 'blockonomics_address', array('crypto'=> $crypto, 'address' => $address));

            header("Content-Type: application/json");
            exit(json_encode($orders[$order_id]));
        }

        $callback_secret = get_option("blockonomics_callback_secret");
        $secret = isset($_REQUEST['secret']) ? $_REQUEST['secret'] : "";
        $network_confirmations=get_option("blockonomics_network_confirmation",2);
        if ($callback_secret  && $callback_secret == $secret) {
            $addr = isset($_REQUEST['addr']) ? $_REQUEST['addr'] : "";
            $order_id = array_search ($addr, $orders);
            if ($order_id){
                $order = $orders[$order_id];
                $wc_order = new WC_Order($order_id);
                $status = intval($_REQUEST['status']);
                $existing_status = $order['status'];
                $timestamp = $order['timestamp'];
                $time_period = get_option("blockonomics_timeperiod", 10) *60;
                if ($status == 0 && time() > $timestamp + $time_period) {
                    $minutes = (time() - $timestamp)/60;
                    $wc_order->add_order_note(__("Warning: Payment arrived after $minutes minutes. Received". $order['crypto'] ."may not match current ". $order['crypto'] ." price", 'blockonomics-bitcoin-payments'));
                }
                elseif ($status >= $network_confirmations && !metadata_exists('post',$wc_order->get_id(),'paid_'. $order['crypto'] .'_amount') )  {
                    update_post_meta($wc_order->get_id(), 'paid_'. $order['crypto'] .'_amount', $_REQUEST['value']/1.0e8);
                    if ($order['satoshi'] > $_REQUEST['value']) {
                        //Check underpayment slack
                        $underpayment_slack = get_option("blockonomics_underpayment_slack", 0)/100 * $order['satoshi'];
                        if ($order['satoshi'] - $underpayment_slack > $_REQUEST['value']) {
                            $status = -2; //Payment error , amount not matching
                            $wc_order->update_status('failed', __('Paid '. $order['crypto'] .' amount less than expected.', 'blockonomics-bitcoin-payments'));
                        }else{
                            $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                            $wc_order->payment_complete($order['txid']);
                        }
                    }
                    else{
                        if ($order['satoshi'] < $_REQUEST['value']) {
                            $wc_order->add_order_note(__('Overpayment of '. $order['crypto'] .' amount', 'blockonomics-bitcoin-payments'));
                        }
                        $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                        $wc_order->payment_complete($order['txid']);
                    }
                    // Keep track of funds in temp wallet
                    if(get_option('blockonomics_temp_api_key') && !get_option("blockonomics_api_key")) {
                        $current_temp_amount = get_option('blockonomics_temp_withdraw_amount');
                        $new_temp_amount = $current_temp_amount + $_REQUEST['value'];
                        update_option('blockonomics_temp_withdraw_amount', $new_temp_amount);
                    }
                }
                $order['txid'] =  $_REQUEST['txid'];
                $order['status'] = $status;
                $orders[$addr] = $order;
                if ($existing_status == -1) {
                    update_post_meta($wc_order->get_id(), 'blockonomics_'. $order['crypto'] .'_txid', $order['txid']);
                    update_post_meta($wc_order->get_id(), 'expected_'. $order['crypto'] .'_amount', $order['satoshi']/1.0e8);
                }
                update_option('blockonomics_orders', $orders);
            }else{
                exit("Error: order not found");
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
