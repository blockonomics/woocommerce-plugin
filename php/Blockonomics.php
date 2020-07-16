<?php

/**
 * This class is responsible for communicating with the Blockonomics API
 */
class Blockonomics
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';
    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    const GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    const TEMP_API_KEY_URL = 'https://www.blockonomics.co/api/temp_wallet';
    const TEMP_WITHDRAW_URL = 'https://www.blockonomics.co/api/temp_withdraw_request';

    const BCH_NEW_ADDRESS_URL = 'https://bch.blockonomics.co/api/new_address';
    const BCH_PRICE_URL = 'https://bch.blockonomics.co/api/price';

    public function __construct()
    {
        $this->api_key = $this->get_api_key();
    }

    public function get_api_key()
    {
        $api_key = get_option("blockonomics_api_key");
        if (!$api_key)
        {
            $api_key = get_option("blockonomics_temp_api_key");
        }
        return $api_key;
    }


    public function new_address($secret, $crypto, $reset=false)
    {
        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }
        if($crypto == 'btc'){
            $url = Blockonomics::NEW_ADDRESS_URL.$get_params;
        }else{
            $url = Blockonomics::BCH_NEW_ADDRESS_URL.$get_params;
        }
        $response = $this->post($url, $this->api_key, '', 8);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
          $responseObj->{'address'} = isset($body->address) ? $body->address : '';
        }
        return $responseObj;
    }

    public function get_price($currency, $crypto)
    {
        if($crypto == 'btc'){
            $url = Blockonomics::PRICE_URL. "?currency=$currency";
        }else{
            $url = Blockonomics::BCH_PRICE_URL. "?currency=$currency";
        }
        $response = $this->get($url);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
          $responseObj->{'price'} = isset($body->price) ? $body->price : '';
        }
        return $responseObj;
    }

    public function update_callback($callback_url, $xpub)
    {
        $url = Blockonomics::SET_CALLBACK_URL;
        $body = json_encode(array('callback' => $callback_url, 'xpub' => $xpub));
        $response = $this->post($url, $this->api_key, $body);
        return json_decode(wp_remote_retrieve_body($response));
    }

    public function get_callbacks()
    {
        $url = Blockonomics::GET_CALLBACKS_URL;
        $response = $this->get($url, $this->api_key);
        return $response;
    }

    public function get_temp_api_key($callback_url)
    {
        $url = Blockonomics::TEMP_API_KEY_URL;
        $body = json_encode(array('callback' => $callback_url));
        $response = $this->post($url, '', $body);
        $responseObj = json_decode(wp_remote_retrieve_body($response));
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        return $responseObj;
    }

    /*
     * Get list of crypto currencies supported by Blockonomics
     */
    public function getSupportedCurrencies() {
        return array(
              'btc' => array(
                    'name' => 'Bitcoin',
                    'uri' => 'bitcoin'
              ),
              'bch' => array(
                    'name' => 'Bitcoin Cash',
                    'uri' => 'bitcoincash'
              )
          );
    }

    /*
     * Get list of active crypto currencies
     */
    public function getActiveCurrencies() {
        $active_currencies = array();
        $blockonomics_currencies = $this->getSupportedCurrencies();
        foreach ($blockonomics_currencies as $code => $currency) {
            if($code == 'btc'){
                $enabled = true;
            }else{
                $enabled = get_option('blockonomics_'.$code);
            }
            if($enabled){
                $active_currencies[$code] = $currency;
            }
        }
        return $active_currencies;
    }

    public function make_withdraw()
    {
        $api_key = $this->api_key;
        $temp_api_key = get_option('blockonomics_temp_api_key');
        if (!$api_key || !$temp_api_key || $temp_api_key == $api_key) {
            return null;
        }
        if (get_option('blockonomics_temp_withdraw_amount') > 0)
        {
            $url = Blockonomics::TEMP_WITHDRAW_URL.'?tempkey='.$temp_api_key;
            $response = $this->post($url, $api_key);
            $responseObj = json_decode(wp_remote_retrieve_body($response));
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code != 200)
            {
                $message = __('Error while making withdraw: '.$responseObj->message, 'blockonomics-bitcoin-payments');
                return [$message, 'error'];
            }
            update_option("blockonomics_temp_api_key", null);
            update_option('blockonomics_temp_withdraw_amount', 0);
            $message = __('Your funds withdraw request has been submitted. Please check your Blockonomics registered emailid for details', 'blockonomics-bitcoin-payments');
            return [$message, 'updated'];
        }
        update_option("blockonomics_temp_api_key", null);
        return null;
    }

    private function get($url, $api_key = '')
    {
        $headers = $this->set_headers($api_key);

        $response = wp_remote_get( $url, array(
            'method' => 'GET',
            'headers' => $headers
            )
        );

        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            return $response;
        }
    }

    private function post($url, $api_key = '', $body = '', $timeout = '')
    {
        $headers = $this->set_headers($api_key);

        $data = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body
            );
        if($timeout){
            $data['timeout'] = $timeout;
        }
        
        $response = wp_remote_post( $url, $data );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            return $response;
        }
    }

    private function set_headers($api_key)
    {
        if($api_key){
            return 'Authorization: Bearer ' . $api_key;
        }else{
            return '';
        }
    }

    public function testSetup()
    {
        $response = $this->get_callbacks();
        $error_str = '';
        $response_body = json_decode(wp_remote_retrieve_body($response));
        if(isset($response_body[0])){
            $response_callback = isset($response_body[0]->callback) ? $response_body[0]->callback : '';
            $response_address = isset($response_body[0]->address) ? $response_body[0]->address : '';
        }else{
            $response_callback = '';
            $response_address = '';
        }
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $api_url);
        // Remove http:// or https:// from urls
        $api_url_without_schema = preg_replace('/https?:\/\//', '', $api_url);
        $callback_url_without_schema = preg_replace('/https?:\/\//', '', $callback_url);
        $response_callback_without_schema = preg_replace('/https?:\/\//', '', $response_callback);
        //TODO: Check This: WE should actually check code for timeout
        if (!wp_remote_retrieve_response_code($response)) {
            $error_str = __('Your server is blocking outgoing HTTPS calls', 'blockonomics-bitcoin-payments');
        }
        elseif (wp_remote_retrieve_response_code($response)==401)
            $error_str = __('API Key is incorrect', 'blockonomics-bitcoin-payments');
        elseif (wp_remote_retrieve_response_code($response)!=200)  
            $error_str = $response->data;
        elseif (!isset($response_body) || count($response_body) == 0)
        {
            $error_str = __('You have not entered an xpub', 'blockonomics-bitcoin-payments');
        }
        elseif (count($response_body) == 1)
        {
            if(!$response_callback || $response_callback == null)
            {
              //No callback URL set, set one 
              $this->update_callback($callback_url, $response_address);   
            }
            elseif($response_callback_without_schema != $callback_url_without_schema)
            {
              $base_url = get_bloginfo('wpurl');
              $base_url = preg_replace('/https?:\/\//', '', $base_url);
              // Check if only secret differs
              if(strpos($response_callback, $base_url) !== false)
              {
                //Looks like the user regenrated callback by mistake
                //Just force Update_callback on server
                $this->update_callback($callback_url, $response_address);  
              }
              else
              {
                $error_str = __("You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
              }
            }
        }
        else 
        {
            $error_str = __("You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
            // Check if callback url is set
            foreach ($response_body as $res_obj)
             if(preg_replace('/https?:\/\//', '', $res_obj->callback) == $callback_url_without_schema)
                $error_str = "";
        }  
        if (!$error_str)
        {
            //Everything OK ! Test address generation
            $response= $this->new_address($callback_secret, 'BTC', true);
            if ($response->response_code!=200){
              $error_str = $response->response_message;
            }
        }
        if($error_str) {
            $error_str = $error_str . __('<p>For more information, please consult <a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'blockonomics-bitcoin-payments');
            return $error_str;
        }
        // No errors
        return false;
    }

    // Create the order url to redirect the user to during checkout
    public function create_order_url($order_id, $order_url){
        // Check if more than one crypto is activated
        if (count($this->getActiveCurrencies()) > 1) {
            $order_url = add_query_arg('select_crypto', $order_id, $order_url);
        }else{
            $order_url = add_query_arg('show_order', $order_id, $order_url);
            $order_url = add_query_arg('crypto', 'btc', $order_url);
        }
        return $order_url;
    }

    // Adds the selected template to the blockonomics page
    public function load_blockonomics_template($template_name){
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

    // Load the the payment confirmed template in the page
    public function load_payment_confirmation_template(){
        $this->load_blockonomics_template('nojs_payment_confirmation');
    }

    // Check if any pending payments linked to the order
    public function is_payment_pending($order_id){
        $orders = get_option('blockonomics_orders');
        $network_confirmations = get_option("blockonomics_network_confirmation",2);
        foreach ($orders[$order_id] as $addr => $order){
            if ($order['status'] >= 0 && $order['status'] < $network_confirmations){
                return true;
            };
        };
        return false;
    }

    // Load the the checkout template in the page
    public function load_checkout_template($order_id, $crypto){
        // Check to send the user to nojs page
        $nojs_version = get_option('blockonomics_nojs', false);
        if($nojs_version){
            // Create or update the order for the nojs template
            $this->process_order($order_id, $crypto);
            $this->load_blockonomics_template('nojs_checkout');
        }else{
            $this->load_blockonomics_template('checkout');
        }
    }

    // Load the the crypto options template in the page
    public function load_crypto_options_template(){
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
    public function redirect_finish_order($order_id){
        $wc_order = new WC_Order($order_id);
        wp_redirect($wc_order->get_checkout_order_received_url());
        exit();
    }

    // Check and update the bitcoin order
    public function process_order($order_id, $crypto){
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
            $status = $order['status'];
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
            $status = -1;
        }
        $order = array(
                'value'              => $wc_order->get_total(),
                'currency'           => get_woocommerce_currency(),
                'order_id'           => $order_id,
                'status'             => $status,
                'satoshi'            => $satoshi,
                'crypto'             => $crypto,
                'timestamp'          => $timestamp,
                'addr'               => $address,
                'txid'               => ''
        );

        $orders[$order_id][$address] = $order;
        update_option('blockonomics_orders', $orders);
        update_post_meta($order_id, $crypto .'_address', $address);
        return $order;
    }

    // Get the order info by id and crypto
    public function get_order_info($order_id, $crypto){
        $order = $this->process_order($order_id, $crypto);
        header("Content-Type: application/json");
        exit(json_encode($order));
    }

    // Process the blockonomics callback
    public function process_callback($secret, $addr, $status, $value, $txid){
        $callback_secret = get_option("blockonomics_callback_secret");
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
                $existing_status = $order['status'];
                $timestamp = $order['timestamp'];
                $time_period = get_option("blockonomics_timeperiod", 10) *60;
                $network_confirmations=get_option("blockonomics_network_confirmation",2);
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
