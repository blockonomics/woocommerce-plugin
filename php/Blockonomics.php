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

    const BCH_BASE_URL = 'https://bch.blockonomics.co';
    const BCH_NEW_ADDRESS_URL = 'https://bch.blockonomics.co/api/new_address';
    const BCH_PRICE_URL = 'https://bch.blockonomics.co/api/price';
    const BCH_SET_CALLBACK_URL = 'https://bch.blockonomics.co/api/update_callback';
    const BCH_GET_CALLBACKS_URL = 'https://bch.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';

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

    public function test_new_address_gen($crypto, $response)
    {
        $error_str = '';
        $callback_secret = get_option('blockonomics_callback_secret');
        $response = $this->new_address($callback_secret, $crypto, true);
        if ($response->response_code!=200){ 
             $error_str = $response->response_message;
        }
        return $error_str;
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

    public function update_callback($callback_url, $crypto, $xpub)
    {
        if ($crypto == 'btc'){
            $url = Blockonomics::SET_CALLBACK_URL;
        }else{
            $url = Blockonomics::BCH_SET_CALLBACK_URL;
        }
        $body = json_encode(array('callback' => $callback_url, 'xpub' => $xpub));
        $response = $this->post($url, $this->api_key, $body);
        $responseObj = json_decode(wp_remote_retrieve_body($response));
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        return $responseObj;
    }

    public function get_callbacks($crypto)
    {
        if ($crypto == 'btc'){
            $url = Blockonomics::GET_CALLBACKS_URL;
        }else{
            $url = Blockonomics::BCH_GET_CALLBACKS_URL;
        }
        $response = $this->get($url, $this->api_key);
        return $response;
    }
    
    public function check_get_callbacks_response_code($response){
        $error_str = '';
        //TODO: Check This: WE should actually check code for timeout
        if (!wp_remote_retrieve_response_code($response)) {
            $error_str = __('Your server is blocking outgoing HTTPS calls', 'blockonomics-bitcoin-payments');
        }
        elseif (wp_remote_retrieve_response_code($response)==401)
            $error_str = __('API Key is incorrect', 'blockonomics-bitcoin-payments');
        elseif (wp_remote_retrieve_response_code($response)!=200)
            $error_str = $response->data;
        return $error_str;
    }

    public function check_get_callbacks_response_body ($response, $crypto){
        $error_str = '';
        $response_body = json_decode(wp_remote_retrieve_body($response));

        //if merchant doesn't have any xPubs on his Blockonomics account
        if (!isset($response_body) || count($response_body) == 0)
        {
            $error_str = __('Please add a new store on blockonomics website', 'blockonomics-bitcoin-payments');
        }
        //if merchant has at least one xPub on his Blockonomics account
        elseif (count($response_body) >= 1)
        {
            $error_str = $this->examine_server_callback_urls($response_body, $crypto);
        }
        return $error_str;
    }

    // checks each existing xpub callback URL to update and/or use
    public function examine_server_callback_urls($response_body, $crypto)
    {
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $wordpress_callback_url = add_query_arg('secret', $callback_secret, $api_url);
        $base_url = preg_replace('/https?:\/\//', '', $api_url);
        $available_xpub = '';
        $partial_match = '';
        //Go through all xpubs on the server and examine their callback url
        foreach($response_body as $one_response){
            $server_callback_url = isset($one_response->callback) ? $one_response->callback : '';
            $server_base_url = preg_replace('/https?:\/\//', '', $server_callback_url);
            $xpub = isset($one_response->address) ? $one_response->address : '';
            if(!$server_callback_url){
                // No callback
                $available_xpub = $xpub;
            }else if($server_callback_url == $wordpress_callback_url){
                // Exact match
                return '';
            }
            else if(strpos($server_base_url, $base_url) === 0 ){
                // Partial Match - Only secret or protocol differ
                $partial_match = $xpub;
            }
        }
        // Use the available xpub
        if($partial_match || $available_xpub){
          $update_xpub = $partial_match ? $partial_match : $available_xpub;
            $response = $this->update_callback($wordpress_callback_url, $crypto, $update_xpub);
            if ($response->response_code != 200) {
                return $response->message;
            }
            return '';
        }
        // No match and no empty callback
        $error_str = __("Please add a new store on blockonomics website", 'blockonomics-bitcoin-payments');
        return $error_str;
    }



    public function check_callback_urls_or_set_one($crypto, $response) 
    {
        $api_key = get_option("blockonomics_api_key");
        //If BCH enabled and API Key is not set: give error
        if (!$api_key && $crypto === 'bch'){
            $error_str = __('Set the API Key or disable BCH', 'blockonomics-bitcoin-payments');
            return $error_str;
        }
        //chek the current callback and detect any potential errors
        $error_str = $this->check_get_callbacks_response_code($response, $crypto);
        if(!$error_str){
            //if needed, set the callback.
            $error_str = $this->check_get_callbacks_response_body($response, $crypto);
        }
        return $error_str;
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
                    'code' => 'btc',
                    'name' => 'Bitcoin',
                    'uri' => 'bitcoin'
              ),
              'bch' => array(
                    'code' => 'bch',
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
            $enabled = get_option('blockonomics_'.$code);
            if($enabled || ($code === 'btc' && $enabled === false )){
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
            return [$message, 'success'];
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
           echo __('Something went wrong', 'blockonomics-bitcoin-payments').': '.$error_message;
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
           echo __('Something went wrong', 'blockonomics-bitcoin-payments').': '.$error_message;
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
    // Runs when the Blockonomics Test Setup button is clicked
    // Returns any errors or false if no errors
    public function testSetup()
    {
        $test_results = array();
        $active_cryptos = $this->getActiveCurrencies();
        foreach ($active_cryptos as $code => $crypto) {
            $test_results[$code] = $this->test_one_crypto($code);
        }
        return $test_results;
    }
    
    public function test_one_crypto($crypto)
    {
        $response = $this->get_callbacks($crypto);
        $error_str = $this->check_callback_urls_or_set_one($crypto, $response);
        if (!$error_str)
        {
            //Everything OK ! Test address generation
            $error_str = $this->test_new_address_gen($crypto, $response);
        }
        if($error_str) {
            return $error_str;
        }
        // No errors
        return false;
    }
    
    // Returns WC endpoint of order adding the given extra parameters
    public function get_parameterized_wc_url($params = array()){
        $order_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        if(is_array($params) && count($params)>0){
            foreach ($params as $param_name => $param_value) {
                $order_url = add_query_arg($param_name, $param_value, $order_url);
            }
        }
        return $order_url;
    }

    // Returns url to redirect the user to during checkout
    public function get_order_checkout_url($order_id){
        $active_cryptos = $this->getActiveCurrencies();
        // Check if more than one crypto is activated
        if (count($active_cryptos) > 1) {
            $order_url = $this->get_parameterized_wc_url(array('select_crypto'=>$order_id));
        } elseif (count($active_cryptos) === 1) {
            $order_url = $this->get_parameterized_wc_url(array('show_order'=>$order_id, 'crypto'=> array_keys($active_cryptos)[0]));
        } else if (count($active_cryptos) === 0) {
            $order_url = $this->get_parameterized_wc_url(array('crypto'=>'empty'));
        }
        return $order_url;
    }
    
    // Check if a template is a nojs template
    public function is_nojs_template($template_name){
        if (strpos($template_name, 'nojs') === 0) {
            return true;
        }
        return false;
    }

    // Check if the nojs setting is activated
    public function is_nojs_active(){
        return get_option('blockonomics_nojs', false);
    }

    // Check if a lite mode setting is activated
    public function is_lite_mode_active(){
        return get_option('blockonomics_lite', false);
    }

    // Adds the header to the blockonomics page
    public function load_blockonomics_header($template_name){
        add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );
        // Don't load javascript files if no js is active
        if (!$this->is_nojs_template($template_name)) {
            add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
        }
        // Lite mode will render without wordpress theme headers
        if($this->is_lite_mode_active()){
        ?>
          <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/order.css', dirname(__FILE__));?>">
        <?php
        }else{
          get_header();
        }
    }

    // Adds the footer to the blockonomics page
    public function load_blockonomics_footer($template_name){
        // Lite mode will render without wordpress theme footers
        if($this->is_lite_mode_active()){
            // Only load the lite mode javascript if nojs is not active
            if (!$this->is_nojs_template($template_name)) {
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
    }

    // Adds the selected template to the blockonomics page
    public function load_blockonomics_template($template_name){
        $this->load_blockonomics_header($template_name);
        // Load the selected template
        // Check if child theme or parent theme have overridden the template
        $template = 'blockonomics_'.$template_name.'.php';
        if ( $overridden_template = locate_template( $template ) ) {
            load_template( $overridden_template );
        } else {
            load_template( plugin_dir_path(__FILE__)."../templates/" .$template );
        }

        $this->load_blockonomics_footer($template_name);

        exit();
    }

    public function calculate_order_params($order){
        // Check if order is unused or new
        if ( $order['status'] == -1) {
            $wc_order = new WC_Order($order['order_id']);
            $order['value'] = $wc_order->get_total();
            $order['currency'] = get_woocommerce_currency();
            if(get_woocommerce_currency() != 'BTC'){
                $responseObj = $this->get_price($order['currency'], $order['crypto']);
                if($responseObj->response_code != 200) {
                    exit();
                }
                $price = $responseObj->price;
                $price = $price * 100/(100+get_option('blockonomics_margin', 0));
            }else{
                $price = 1;
            }
            $order['satoshi'] = intval(round(1.0e8*$wc_order->get_total()/$price));
        }
        return $order;
    }

    // Save the new address to the WooCommerce order
    public function record_address($order_id, $crypto, $address){
        update_post_meta($order_id, $crypto .'_address', $address);
    }

    public function create_new_order($order_id, $crypto){
        $responseObj = $this->new_address(get_option("blockonomics_callback_secret"), $crypto);
        if($responseObj->response_code != 200) {
            exit(json_encode(array("error"=>$responseObj->response_message)));
        }
        $address = $responseObj->address;
        $order = array(
                'order_id'           => $order_id,
                'status'             => -1,
                'crypto'             => $crypto,
                'address'            => $address
        );
        $order = $this->calculate_order_params($order);
        return $order;
    }

    // Load the the checkout template in the page
    public function load_checkout_template($order_id, $crypto){
        // Check to send the user to nojs page
        if($this->is_nojs_active()){
            // Create or update the order for the nojs template
            $this->process_order($order_id, $crypto);
            $this->load_blockonomics_template('nojs_checkout');
        }else{
            $this->load_blockonomics_template('checkout');
        }
    }

    // Redirect the user to the woocommerce finish order page
    public function redirect_finish_order($order_id){
        $wc_order = new WC_Order($order_id);
        wp_redirect($wc_order->get_checkout_order_received_url());
        exit();
    }

    // Fetch the correct crypto order linked to the order id
    public function get_order_by_id_and_crypto($order_id, $crypto){
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_orders';
        $order = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %s AND crypto = %s", array($order_id, $crypto)
        ), ARRAY_A);
        if($order){
            return $order;
        }
        return false;
    }

    // Inserts a new order in blockonomics_orders table
    public function insert_order($order){
        global $wpdb;
        $wpdb->hide_errors();
        $table_name = $wpdb->prefix . 'blockonomics_orders';
        return $wpdb->insert( 
            $table_name, 
            $order 
        );
    }

    // Updates an order in blockonomics_orders table
    public function update_order($order){
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_orders';
        $wpdb->replace( 
            $table_name, 
            $order 
        );
    }

    // Check and update the crypto order or create a new order
    public function process_order($order_id, $crypto){
        $order = $this->get_order_by_id_and_crypto($order_id, $crypto);
        if ($order) {
            // Update the existing order info
            $order = $this->calculate_order_params($order);
            $this->update_order($order);
        }else {
            // Create and add the new order to the database
            $order = $this->create_new_order($order_id, $crypto);
            if (!$this->insert_order($order)) {
                // insert_order fails if duplicate address found. Ensures no duplicate orders in the database
                exit(json_encode(array("error"=>"Duplicate Address Error. This is Temporary error, Please try again")));
            }
            $this->record_address($order_id, $crypto, $order['address']);
        }
        return $order;
    }

    // Get the order info by id and crypto
    public function get_order_info($order_id, $crypto){
        $order = $this->process_order($order_id, $crypto);
        header("Content-Type: application/json");
        exit(json_encode($order));
    }

    // Get the order info by crypto address
    public function get_order_by_address($address){
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_orders';
        $order = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE address = %s", array($address)
        ), ARRAY_A);
        if($order){
            return $order;
        }
        exit(__("Error: order not found", 'blockonomics-bitcoin-payments'));
    }

    // Check if the callback secret in the request matches
    public function check_callback_secret($secret){
        $callback_secret = get_option("blockonomics_callback_secret");
        if ($callback_secret  && $callback_secret == $secret) {
            return true;
        }
        exit(__("Error: secret does not match", 'blockonomics-bitcoin-payments'));
    }

    public function save_transaction($value, $order, $wc_order){
        if (!metadata_exists('post',$wc_order->get_id(),'blockonomics_'. $order['crypto'] .'_txid', $order['txid']) )  {
            update_post_meta($wc_order->get_id(), 'blockonomics_'. $order['crypto'] .'_txid', $order['txid']);
            update_post_meta($wc_order->get_id(), 'expected_'. $order['crypto'], $order['satoshi']/1.0e8);
        }
    }

    public function update_paid_amount($status, $value, $order, $wc_order){
        $network_confirmations = get_option("blockonomics_network_confirmation",2);
        if ($status >= $network_confirmations && !metadata_exists('post',$wc_order->get_id(),'paid_'. $order['crypto']) )  {
          update_post_meta($wc_order->get_id(), 'paid_'. $order['crypto'], $value/1.0e8);
          $status = $this->check_paid_amount($status, $value, $order, $wc_order);
          $this->update_temp_draw_amount($value);
          return $status;
        }
        return $status;
    }

    // Check for underpayment, overpayment or correct amount
    public function check_paid_amount($status, $value, $order, $wc_order){
      $underpayment_slack = get_option("blockonomics_underpayment_slack", 0)/100 * $order['satoshi'];
      if ($order['satoshi'] - $underpayment_slack > $value) {
        $status = -2; //Payment error , amount less than expected 
        $wc_order->update_status('failed', __('Paid amount less than expected.', 'blockonomics-bitcoin-payments'));
      }else{
        $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
        $wc_order->payment_complete($order['txid']);
      }
      if ($order['satoshi'] < $value) {
        $wc_order->add_order_note(__( 'Paid amount more than expected.', 'blockonomics-bitcoin-payments' ));
      }
      return $status;
    }

    // Keep track of funds in temp wallet
    public function update_temp_draw_amount($value){
        if(get_option('blockonomics_temp_api_key') && !get_option("blockonomics_api_key")) {
            $current_temp_amount = get_option('blockonomics_temp_withdraw_amount');
            $new_temp_amount = $current_temp_amount + $value;
            update_option('blockonomics_temp_withdraw_amount', $new_temp_amount);
        }
    }

    // Process the blockonomics callback
    public function process_callback($secret, $address, $status, $value, $txid, $rbf){
        $this->check_callback_secret($secret);

        $order = $this->get_order_by_address($address);
        $wc_order = new WC_Order($order['order_id']);
        
        $order['txid'] = $txid;

        if (!$rbf){
          // Unconfirmed RBF payments are easily cancelled should be ignored
          // https://blog.blockonomics.co/bitcoin-payments-can-now-easily-cancelled-a-step-forward-or-two-back-bdef08276382  
          $this->save_transaction($value, $order, $wc_order);
          $status = $this->update_paid_amount($status, $value, $order, $wc_order);
        }

        $order['status'] = $status;
        $this->update_order($order);
    }

    public function generate_qrcode($data) {
        include plugin_dir_path(__FILE__) . 'phpqrcode.php';
        ob_start("callback");
        $codeText = sanitize_text_field($data);
        $debugLog = ob_get_contents();
        ob_end_clean();
        QRcode::png($codeText);
    } 
}
