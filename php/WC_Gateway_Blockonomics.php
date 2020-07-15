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
        $order = new WC_Order($order_id);
        $success_url = add_query_arg('return_from_blockonomics', true, $this->get_return_url($order));

        // Blockonomics mangles the order param so we have to put it somewhere else and restore it on init
        $cancel_url = $order->get_cancel_order_url_raw();
        $cancel_url = add_query_arg('return_from_blockonomics', true, $cancel_url);
        $cancel_url = add_query_arg('cancelled', true, $cancel_url);
        $order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;
        $cancel_url = add_query_arg('order_key', $order_key, $cancel_url);

        $order_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $order_url = $this->create_order_url($order_id, $order_url);

        return array(
            'result'   => 'success',
            'redirect' => $order_url
        );
    }

    // Create the order url to redirect the user to during checkout
    private function create_order_url($order_id, $order_url){
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        // check if more than one crypto is activated
        if (count($blockonomics->getActiveCurrencies()) > 1) {
            $order_url = add_query_arg('select_crypto', $order_id, $order_url);
        }else{
            $order_url = add_query_arg('show_order', $order_id, $order_url);
            $order_url = add_query_arg('crypto', 'btc', $order_url);
        }
        return $order_url;
    }

    // Handles requests to the blockonomics page
    public function handle_requests()
    {
        $payment_check = isset($_REQUEST["payment_check"]) ? $_REQUEST["payment_check"] : "";
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

        if ($payment_check) {
            $this->load_payment_confirmed_template();
        }else if ($show_order && $crypto) {
            $this->load_checkout_template($show_order, $crypto);
        }else if ($select_crypto) {
            $this->load_crypto_options_template();
        }else if ($finish_order) {
            $this->redirect_finish_order($finish_order);
        }else if ($get_order && $crypto) {
            $this->get_order_info($get_order, $crypto);
        }else if ($secret && $addr && $status && $value && $txid) {
            $this->process_callback($secret, $addr, $status, $value, $txid);
        }
    }

    // Adds the selected template to the blockonomics page
    private function load_blockonomics_template($template_name){
        add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );
        // Apply nojs header changes + set nojs_checkout flag
        if (strpos($template_name, 'nojs') === 0) {
            $nojs_checkout = true;
        }else{
            add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
        }
        $template = 'blockonomics_'.$template_name.'.php';
        // Apply lite-mode header
        $lite_version = get_option('blockonomics_lite');
        if($lite_version){
        ?>
          <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/order.css', dirname(__FILE__));?>">
        <?php
        }else{
          get_header();
        }
        // Load the selected template
        // Check if child theme or parent theme have overridden the template
        if ( $overridden_template = locate_template( $template ) ) {
            load_template( $overridden_template );
        } else {
            load_template( plugin_dir_path(__FILE__)."../templates/" .$template );
        }
        // Apply lite-mode footer changes
        if($lite_version){
            // Apply nojs footer changes
            if (!$nojs_checkout) {
                ?>
                  <script>var ajax_object = {ajax_url:"<?php echo admin_url( 'admin-ajax.php' ); ?>", wc_url:"<?php echo WC()->api_request_url('WC_Gateway_Blockonomics'); ?>"};
                  </script>
                  <script src="<?php echo plugins_url('js/angular.min.js', dirname(__FILE__));?>"></script>
                  <script src="<?php echo plugins_url('js/angular-resource.min.js', dirname(__FILE__));?>"></script>
                  <script src="<?php echo plugins_url('js/app.js', dirname(__FILE__));?>"></script>
                  <script src="<?php echo plugins_url('js/angular-qrcode.js', dirname(__FILE__));?>"></script>
                  <script src="<?php echo plugins_url('js/vendors.min.js', dirname(__FILE__));?>"></script>
                  <script src="<?php echo plugins_url('js/reconnecting-websocket.min.js', dirname(__FILE__));?>"></script>
                <?php
            }
        }else{
          get_footer();
        }
        exit();
    }

    // Load the the payment confirmed tempate in the page
    private function load_payment_confirmed_template(){
        $this->load_blockonomics_template('nojs_payment_confirmed');
    }

    // Load the the checkout tempate in the page
    private function load_checkout_template($order_id, $crypto){
        // Check to send the user to nojs page
        $nojs_version = get_option('blockonomics_nojs', false);
        if($nojs_version){
            // Create or update the order for the nojs template
            $this->update_order($order_id, $crypto);
            $this->load_blockonomics_template('nojs_checkout');
        }else{
            $this->load_blockonomics_template('checkout');
        }
    }

    // Load the the crypto options tempate in the page
    private function load_crypto_options_template(){
        // Check to send the user to nojs page
        $nojs_version = get_option('blockonomics_nojs', false);
        if($nojs_version){
            $this->load_blockonomics_template('nojs_crypto_options');
        }
        else{
            $this->load_blockonomics_template('crypto_options');
        }
    }

    // Redirect the user to the woocommerce finish order page
    private function redirect_finish_order($order_id){
        $wc_order = new WC_Order($order_id);
        wp_redirect($wc_order->get_checkout_order_received_url());
        exit();
    }

    // Check and update the bitcoin order
    private function update_order($order_id, $crypto){
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        global $woocommerce;
        $orders = get_option('blockonomics_orders');
        // Check post-meta for existing address by currency
        $currentAddress = get_post_meta($order_id,$crypto .'_address', false);
        if(! empty( $currentAddress ) && isset($currentAddress[0]) && $currentAddress[0] != "") {
            $address = $currentAddress[0];
        } else {
            $responseObj = $blockonomics->new_address(get_option("blockonomics_callback_secret"), $crypto);
            if($responseObj->response_code != 200) {
                exit();
            }
            $address = $responseObj->address;
        }
        $wc_order = new WC_Order($order_id);
        $order = $orders[$order_id][$address];
        // Check if order has not expired yet
        if ( isset($order['timestamp']) && $order['timestamp'] >= time() - get_option("blockonomics_timeperiod") * 60 ) {
            $timestamp = $order['timestamp'];
            $satoshi = $order['satoshi'];
        }else{
            // Refresh the timestamp and expected satoshi
            $timestamp = time();
            if(get_woocommerce_currency() != 'BTC'){
                $responseObj = $blockonomics->get_price(get_woocommerce_currency(), $crypto);
                if($responseObj->response_code != 200) {
                    exit();
                }
                $price = $responseObj->price;
                $price = $price * 100/(100+get_option('blockonomics_margin', 0));
            }else{
                $price = 1;
            }
            $satoshi = intval(round(1.0e8*$wc_order->get_total()/$price));
        }
        $order = array(
                'value'              => $wc_order->get_total(),
                'currency'           => get_woocommerce_currency(),
                'order_id'           => $order_id,
                'status'             => -1,
                'satoshi'            => $satoshi,
                'crypto'             => $crypto,
                'timestamp'          => $timestamp,
                'addr'               => $address
        );

        $orders[$order_id][$address] = $order;
        update_option('blockonomics_orders', $orders);
        update_post_meta($order_id, $crypto .'_address', $address);
        return $order;
    }

    // Get the order info by id and crypto
    private function get_order_info($order_id, $crypto){
        $order = $this->update_order($order_id, $crypto);
        header("Content-Type: application/json");
        exit(json_encode($order));
    }

    // Process the blockonomics callback
    private function process_callback($secret, $addr, $status, $value, $txid){
        $callback_secret = get_option("blockonomics_callback_secret");
        $network_confirmations=get_option("blockonomics_network_confirmation",2);
        if ($callback_secret  && $callback_secret == $secret) {
            $orders = get_option('blockonomics_orders');
            // fetch the order id by bitcoin address
            foreach($orders as $id => $order){
                if(isset($order[$addr])){
                    $order_id = $id;
                    break;
                }
            }
            if ($order_id){
                $order = $orders[$order_id][$addr];
                $wc_order = new WC_Order($order_id);
                $status = intval($status);
                $existing_status = $order['status'];
                $timestamp = $order['timestamp'];
                $time_period = get_option("blockonomics_timeperiod", 10) *60;
                if ($status == 0 && time() > $timestamp + $time_period) {
                    $minutes = (time() - $timestamp)/60;
                    $wc_order->add_order_note(__("Warning: Payment arrived after $minutes minutes. Received". $order['crypto'] ."may not match current ". $order['crypto'] ." price", 'blockonomics-bitcoin-payments'));
                }
                elseif ($status >= $network_confirmations && !metadata_exists('post',$wc_order->get_id(),'paid_'. $order['crypto'] .'_amount') )  {
                    update_post_meta($wc_order->get_id(), 'paid_'. $order['crypto'] .'_amount', $value/1.0e8);
                    if ($order['satoshi'] > $value) {
                        //Check underpayment slack
                        $underpayment_slack = get_option("blockonomics_underpayment_slack", 0)/100 * $order['satoshi'];
                        if ($order['satoshi'] - $underpayment_slack > $value) {
                            $status = -2; //Payment error , amount not matching
                            $wc_order->update_status('failed', __('Paid '. $order['crypto'] .' amount less than expected.', 'blockonomics-bitcoin-payments'));
                        }else{
                            $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                            $wc_order->payment_complete($order['txid']);
                        }
                    }
                    else{
                        if ($order['satoshi'] < $value) {
                            $wc_order->add_order_note(__('Overpayment of '. $order['crypto'] .' amount', 'blockonomics-bitcoin-payments'));
                        }
                        $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
                        $wc_order->payment_complete($order['txid']);
                    }
                    // Keep track of funds in temp wallet
                    if(get_option('blockonomics_temp_api_key') && !get_option("blockonomics_api_key")) {
                        $current_temp_amount = get_option('blockonomics_temp_withdraw_amount');
                        $new_temp_amount = $current_temp_amount + $value;
                        update_option('blockonomics_temp_withdraw_amount', $new_temp_amount);
                    }
                }
                $order['txid'] = $txid;
                $order['status'] = $status;
                $orders[$order_id][$addr] = $order;
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
}
