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
        if($crypto === 'btc'){
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
        if($crypto === 'btc'){
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
        if ($crypto === 'btc'){
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
        if ($crypto === 'btc'){
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
        $order_hash = $this->encrypt_hash($order_id);
        if (count($active_cryptos) > 1) {
            $order_url = $this->get_parameterized_wc_url(array('select_crypto'=>$order_hash));
        } elseif (count($active_cryptos) === 1) {
            $order_url = $this->get_parameterized_wc_url(array('show_order'=>$order_hash, 'crypto'=> array_keys($active_cryptos)[0]));
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

    public function is_partial_payments_active(){
        return get_option('blockonomics_partial_payments', false);
    }

    public function is_error_template($template_name) {
        if (strpos($template_name, 'error') === 0) {
            return true;
        }
        return false;
    }

    // Adds the header to the blockonomics page
    public function load_blockonomics_header($template_name, $additional_script=NULL){
        
        // Lite mode will render without wordpress theme headers
        if($this->is_lite_mode_active()){
        ?>
            <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/order.css', dirname(__FILE__));?>">
        <?php
            if ($template_name === 'checkout') {
        ?>
            <script src="<?php echo plugins_url('js/vendors/reconnecting-websocket.min.js', dirname(__FILE__));?>" defer="defer"></script>
            <script src="<?php echo plugins_url('js/vendors/qrious.min.js', dirname(__FILE__));?>" defer="defer"></script>
            <script><?php echo $additional_script; ?></script>
            <script src="<?php echo plugins_url('js/checkout.js', dirname(__FILE__));?>" defer="defer"></script>
        <?php
            }
        } else {
            add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );
            
            // wp_enqueue_scripts needs to be called before get_header(), but the scripts are loaded in footer as
            // $in_footer is set to TRUE for scripts in bnomics_enqueu_scripts

            if ($template_name === 'checkout') {
                
                add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
                
                if (isset($additional_script)) {
                    add_action('wp_enqueue_scripts', function () use ($additional_script) {
                        wp_add_inline_script('bnomics-checkout', $additional_script, 'before');
                    });
                }
            }

            get_header();
        }
    }

    // Adds the footer to the blockonomics page
    public function load_blockonomics_footer($template_name){
        
        // Lite mode will render without wordpress theme footers
        if(!$this->is_lite_mode_active()){
            get_footer();
        }
    }

    public function set_template_context($context) {
        // Todo: With WP 5.5+, the load_template methods supports args
        // and can be used as a replacement to this.
        foreach ($context as $key => $value) {
            set_query_var($key, $value);
        }
    }

    // Adds the selected template to the blockonomics page
    public function load_blockonomics_template($template_name, $context = array(), $additional_script = NULL){
        $this->load_blockonomics_header($template_name, $additional_script);

        // Load the selected template
        $template = 'blockonomics_'.$template_name.'.php';
        // Load Template Context
        $this->set_template_context($context);
        
        // Check if child theme or parent theme have overridden the template
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
        if ( $order['payment_status'] == 0) {
            return $this->calculate_new_order_params($order);
        }
        if ($order['payment_status'] == 2){
            if ($this->is_order_underpaid($order) && $this->is_partial_payments_active()){
                return $this->create_and_insert_new_order_on_underpayment($order);
            }
        }
        return $order;
    }

    // Get order info for unused or new orders
    public function calculate_new_order_params($order){
        $wc_order = new WC_Order($order['order_id']);
        global $wpdb;
        $order_id = $wc_order->get_id();
        $table_name = $wpdb->prefix .'blockonomics_payments'; 
        $query = $wpdb->prepare("SELECT expected_fiat,paid_fiat,currency FROM ". $table_name." WHERE order_id = %d " , $order_id);
        $results = $wpdb->get_results($query,ARRAY_A);
        $paid_fiat = 0;
        foreach ($results as $row) {
            $paid_fiat += (float)$row['paid_fiat'];
        }
        $order['expected_fiat'] = $wc_order->get_total() - $paid_fiat;
        $order['currency'] = get_woocommerce_currency();
        if (get_woocommerce_currency() != 'BTC') {
            $responseObj = $this->get_price($order['currency'], $order['crypto']);
            if($responseObj->response_code != 200) {
                exit();
            }
            $price = $responseObj->price;
            $price = $price * 100/(100+get_option('blockonomics_margin', 0));
        } else {
            $price = 1;
        }
        $order['expected_satoshi'] = intval(round(1.0e8*$order['expected_fiat']/$price));
        return $order;
    }
    
    // Get new addr and update amount after confirmed underpayment
    public function create_and_insert_new_order_on_underpayment($order){
        $order = $this->create_new_order($order['order_id'], $order['crypto']);
        if (array_key_exists("error", $order)) {
            // Some error in Address Generation from API, return the same array.
            return $order;
        }
        $this->insert_order($order);
        $this->record_address($order['order_id'], $order['crypto'], $order['address']);
        return $order;
    }

    // Save the new address to the WooCommerce order
    public function record_address($order_id, $crypto, $address){
        $addr_meta_key = 'blockonomics_payments_addresses';
        $addr_meta_value = get_post_meta($order_id, $addr_meta_key);
        if (empty($addr_meta_value)){ 
            update_post_meta($order_id, $addr_meta_key, $address);
        } 
        // when address meta value is not empty and $address is not in it 
        else if (strpos($addr_meta_value[0], $address) === false) {
            update_post_meta($order_id, $addr_meta_key, $addr_meta_value[0]. ', '. $address);
        }
    }

    public function create_new_order($order_id, $crypto){
        $responseObj = $this->new_address(get_option("blockonomics_callback_secret"), $crypto);
        if($responseObj->response_code != 200) {
            return array("error"=>$responseObj->response_message);
        }
        $address = $responseObj->address;
        $order = array(
                'order_id'           => $order_id,
                'payment_status'     => 0,
                'crypto'             => $crypto,
                'address'            => $address
        );
        $order = $this->calculate_order_params($order);
        return $order;
    }

    public function get_error_context($error_type){
        $context = array();

        if ($error_type == 'generic') {
            // Show Generic Error to Client.
            $context['error_title'] = __('Could not generate new address (This may be a temporary error. Please try again)', 'blockonomics-bitcoin-payments');
            $context['error_msg'] = __('If this continues, please ask website administrator to do following:<br/><ul><li>Login to admin panel, navigate to Settings > Blockonomics > Currencies and click Test Setup to diagnose the exact issue.</li><li>Check blockonomics registered email address for error messages</li>', 'blockonomics-bitcoin-payments');
        } else if($error_type == 'underpaid') {
            $context['error_title'] = '';
            $context['error_msg'] = __('Paid order BTC amount is less than expected. Contact merchant', 'blockonomics-bitcoin-payments');
        }

        return $context;
    }

    public function fix_displaying_small_values($satoshi){
        if ($satoshi < 10000){
            return rtrim(number_format($satoshi/1.0e8, 8),0);
        } else {
            return $satoshi/1.0e8;
        }
    }

    public function get_crypto_rate_from_params($value, $satoshi) {
        // Crypto Rate is re-calculated here and may slightly differ from the rate provided by Blockonomics
        // This is required to be recalculated as the rate is not stored anywhere in $order, only the converted satoshi amount is.
        // This method also helps in having a constant conversion and formatting for both JS and NoJS Templates avoiding the scientific notations.
        return number_format($value*1.0e8/$satoshi, 2, '.', '');
    }

    public function get_crypto_payment_uri($crypto, $address, $order_amount) {
        return $crypto['uri'] . ":" . $address . "?amount=" . $order_amount;
    }

    public function get_checkout_context($order, $crypto){
        
        $context = array();
        $error_context = NULL;

        $context['order_id'] = $order['order_id'];

        $cryptos = $this->getActiveCurrencies();
        $context['crypto'] = $cryptos[$crypto];

        if (array_key_exists('error', $order)) {
            $error_context = $this->get_error_context('generic');
        } else {
            $context['order'] = $order;

            if ($order['payment_status'] == 1 || ($order['payment_status'] == 2 && !$this->is_order_underpaid($order)) ) {
                // Payment not confirmed i.e. payment in progress
                // Redirect to order received page- dont alllow new payment until existing payments are confirmed
                $this->redirect_finish_order($context['order_id']);
            } else if (($order['payment_status'] == 2 && $this->is_order_underpaid($order)) && !$this->is_partial_payments_active() ) {
                $error_context = $this->get_error_context('underpaid');
            } else {
                // Display Checkout Page
                $context['order_amount'] = $this->fix_displaying_small_values($order['expected_satoshi']);
                // Payment URI is sent as part of context to provide initial Payment URI, this can be calculated using javascript
                // but we also need the URI for NoJS Templates and it makes sense to generate it from a single location to avoid redundancy!
                $context['payment_uri'] = $this->get_crypto_payment_uri($context['crypto'], $order['address'], $context['order_amount']);
                $context['crypto_rate_str'] = $this->get_crypto_rate_from_params($order['expected_fiat'], $order['expected_satoshi']);
                //Using svg library qrcode.php to generate QR Code in NoJS mode
                $context['qrcode_svg_element'] = $this->generate_qrcode_svg_element($context['payment_uri']);
            }
        }

        if ($error_context != NULL) {
            $context = array_merge($context, $error_context);
        }

        return $context;
    }


    public function get_checkout_template($context){
        if (array_key_exists('error_msg', $context)) {
            return 'error';
        } else {
            return ($this->is_nojs_active()) ? 'nojs_checkout' : 'checkout';
        }
    }

    public function get_checkout_script($context, $template_name) {
        $script = NULL;

        if ($template_name === 'checkout') {
            $order_hash = $this->encrypt_hash($context['order_id']);
            
            $script = "const blockonomics_data = '" . json_encode( array (
                'crypto' => $context['crypto'],
                'crypto_address' => $context['order']['address'],
                'time_period' => get_option('blockonomics_timeperiod', 10),
                'finish_order_url' => $this->get_wc_order_received_url($context['order_id']),
                'get_order_amount_url' => $this->get_parameterized_wc_url(array('get_amount'=>$order_hash, 'crypto'=>  $context['crypto']['code'])),
                'payment_uri' => $context['payment_uri']
            )). "'";
        }

        return $script;
    }

    // Load the the checkout template in the page
    public function load_checkout_template($order_id, $crypto){
        // Create or update the order
        $order = $this->process_order($order_id, $crypto);
        
        // Load Checkout Context
        $context = $this->get_checkout_context($order, $crypto);
        
        // Get Template to Load
        $template_name = $this->get_checkout_template($context);

        // Get any additional inline script to load
        $script = $this->get_checkout_script($context, $template_name);
        
        // Load the template
        $this->load_blockonomics_template($template_name, $context, $script);
    }

    public function get_wc_order_received_url($order_id){
        $wc_order = new WC_Order($order_id);
        return $wc_order->get_checkout_order_received_url();
    }

    // Redirect the user to the woocommerce finish order page
    public function redirect_finish_order($order_id){
        $wc_order = new WC_Order($order_id);
        wp_safe_redirect($wc_order->get_checkout_order_received_url());
        exit();
    }

    // Fetch the correct crypto order linked to the order id
    public function get_order_by_id_and_crypto($order_id, $crypto){
        global $wpdb;
        $order = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM ".$wpdb->prefix."blockonomics_payments WHERE order_id = ". $order_id." AND crypto = '". $crypto."' ORDER BY expected_satoshi ASC"),
            ARRAY_A
        );
        if($order){
            return $order[0];
        }
        return false;
    }


    // Inserts a new row in blockonomics_payments table
    public function insert_order($order){
        global $wpdb;
        $wpdb->hide_errors();
        $table_name = $wpdb->prefix . 'blockonomics_payments';
        return $wpdb->insert( 
            $table_name, 
            $order 
        );
    }

    // Updates an order in blockonomics_payments table
    public function update_order($order){
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_payments';
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
            if (array_key_exists("error", $order)) {
                // Some error in Address Generation from API, return the same array.
                return $order;
            }
            $this->insert_order($order);
            $this->record_address($order_id, $crypto, $order['address']);
        }
        return $order;
    }

    // Get the order info by id and crypto
    public function get_order_amount_info($order_id, $crypto){
        $order = $this->process_order($order_id, $crypto);
        $order_amount = $this->fix_displaying_small_values($order['expected_satoshi']);        
        $cryptos = $this->getActiveCurrencies();
        $crypto_obj = $cryptos[$crypto];

        $response = array(
            "payment_uri" => $this->get_crypto_payment_uri($crypto_obj, $order['address'], $order_amount),
            "order_amount" => $order_amount,
            "crypto_rate_str" => $this->get_crypto_rate_from_params($order['expected_fiat'], $order['expected_satoshi'])
        );
        header("Content-Type: application/json");
        exit(json_encode($response));
    }

    // Get the order info by crypto address
    public function get_order_by_address($address){
        global $wpdb;
        $order = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM ".$wpdb->prefix."blockonomics_payments WHERE address = %s", array($address)),
            ARRAY_A
        );
        if($order){
            return $order;
        }
        exit(__("Error: Blockonomics order not found", 'blockonomics-bitcoin-payments'));
    }

    // Check if the callback secret in the request matches
    public function check_callback_secret($secret){
        $callback_secret = get_option("blockonomics_callback_secret");
        if ($callback_secret  && $callback_secret == $secret) {
            return true;
        }
        exit(__("Error: secret does not match", 'blockonomics-bitcoin-payments'));
    }

    public function save_transaction($order, $wc_order){
        $txid_meta_key = 'blockonomics_payments_txids';
        $txid_meta_value = get_post_meta($order['order_id'], $txid_meta_key);
        $txid = $order['txid'];
        if (empty($txid_meta_value)){
            update_post_meta($wc_order->get_id(), $txid_meta_key, $txid);
        }
        // when txid meta value is not empty and $txid is not in it 
        else if (strpos($txid_meta_value[0], $txid) === false){
            update_post_meta($wc_order->get_id(), $txid_meta_key, $txid_meta_value[0].', '. $txid);
        }
    }

    public function update_paid_amount($callback_status, $paid_satoshi, $order, $wc_order){
        $network_confirmations = get_option("blockonomics_network_confirmation",2);
        if ($order['payment_status'] == 2) {
            return $order;
        }
        if ($callback_status >= $network_confirmations){
            $order['payment_status'] = 2;
            $order = $this->check_paid_amount($paid_satoshi, $order, $wc_order);
            $this->update_temp_draw_amount($paid_satoshi);
        } 
        else {
            // since $callback_status < $network_confirmations payment_status should be 1 i.e. payment in progress if payment is not already completed
            $order['payment_status'] = 1;
        }
        return $order;
    }

    // Check for underpayment, overpayment or correct amount
    public function check_paid_amount($paid_satoshi, $order, $wc_order){
        $order['paid_satoshi'] = $paid_satoshi;
        $paid_amount_ratio = $paid_satoshi/$order['expected_satoshi'];
        $order['paid_fiat'] =number_format($order['expected_fiat']*$paid_amount_ratio,2,'.','');

        // This is to update the order table before we send an email on failed and confirmed state
        // So that the updated data is used to build the email
        $this->update_order($order);

        if ($this->is_order_underpaid($order)) {
            if ($this->is_partial_payments_active()){
                $this->add_note_on_underpayment($order, $wc_order);
                $this->send_email_on_underpayment($order);
                $wc_order->save;
            }
            else {
                $wc_order->update_status('failed', __(get_woocommerce_currency()." ".sprintf('%0.2f', round($order['paid_fiat'], 2))." was paid via Blockonomics. Less than expected Order Amount.", 'blockonomics-bitcoin-payments'));
            }
        }
        else{
            $wc_order->add_order_note(__('Payment completed', 'blockonomics-bitcoin-payments'));
            $wc_order->payment_complete($order['txid']);
        }
        if ($order['expected_satoshi'] < $paid_satoshi) {
            $wc_order->add_order_note(__( 'Paid amount more than expected.', 'blockonomics-bitcoin-payments' ));
        }
        return $order;
    }

    public function is_order_underpaid($order){
        // Return TRUE only if there has been a payment which is less than required.
        $underpayment_slack = get_option("blockonomics_underpayment_slack", 0)/100 * $order['expected_satoshi'];
        $is_order_underpaid = ($order['expected_satoshi'] - $underpayment_slack > $order['paid_satoshi'] && !empty($order['paid_satoshi'])) ? TRUE : FALSE;
        return $is_order_underpaid;
    }
    // Keep track of funds in temp wallet
    public function update_temp_draw_amount($paid_satoshi){
        if(get_option('blockonomics_temp_api_key') && !get_option("blockonomics_api_key")) {
            $current_temp_amount = get_option('blockonomics_temp_withdraw_amount');
            $new_temp_amount = $current_temp_amount + $paid_satoshi;
            update_option('blockonomics_temp_withdraw_amount', $new_temp_amount);
        }
    }

    // Process the blockonomics callback
    public function process_callback($secret, $address, $status, $value, $txid, $rbf){
        $this->check_callback_secret($secret);

        $order = $this->get_order_by_address($address);
        $wc_order = wc_get_order($order['order_id']);

        if (empty($wc_order)) {
            exit(__("Error: Woocommerce order not found", 'blockonomics-bitcoin-payments'));
        }
        
        $order['txid'] = $txid;

        if (!$rbf){
          // Unconfirmed RBF payments are easily cancelled should be ignored
          // https://insights.blockonomics.co/bitcoin-payments-can-now-easily-cancelled-a-step-forward-or-two-back/ 
          $order = $this->update_paid_amount($status, $value, $order, $wc_order);
          $this->save_transaction($order, $wc_order);
        }

        $this->update_order($order);
    }

    // Auto generate and apply coupon on underpaid callbacks
    public function add_note_on_underpayment($order, $wc_order){
        $paid_amount = $order['paid_fiat'];
        $note = wc_price($paid_amount). " paid via ".$order['crypto']. " (Blockonomics). Customer has been mailed invoice to pay the remaining amount";
        $wc_order->add_order_note(__( $note, 'blockonomics-bitcoin-payments' ));
    }

    // Send Invoice email to customer to pay remaining amount
    public function send_email_on_underpayment($order){
        $wc_email = WC()->mailer()->emails['WC_Email_Customer_Invoice'];
        $wc_email->settings['subject'] = __('Additional Payment Required for order #{order_number} on {site_title}');
        $wc_email->settings['heading'] = __('Use below link to pay remaining amount.'); 
        $wc_email->settings['additional_content'] = __('<strong>Note: Your existing payment has been applied as a discount to the order</strong>'); 
        $wc_email->trigger($order['order_id']);
    }

    public function generate_qrcode_svg_element($data) {
        include plugin_dir_path(__FILE__) . 'qrcode.php';
        $codeText = sanitize_text_field($data);
        return QRcode::svg($codeText);
    } 

    /**
     * Encrypts a string using the application secret. This returns a hex representation of the binary cipher text
     *
     * @param  $input
     * @return string
     */
    public function encrypt_hash($input)
    {
        $encryption_algorithm = 'AES-128-CBC';
        $hashing_algorith = 'sha256';
        $secret = get_option('blockonomics_callback_secret');;
        $key = hash($hashing_algorith, $secret, true);
        $iv = substr($secret, 0, 16);

        $cipherText = openssl_encrypt(
            $input,
            $encryption_algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return bin2hex($cipherText);
    }

    /**
     * Decrypts a string using the application secret.
     *
     * @param  $hash
     * @return string
     */
    public function decrypt_hash($hash)
    {
        $encryption_algorithm = 'AES-128-CBC';
        $hashing_algorith = 'sha256';
        $secret = get_option('blockonomics_callback_secret');;
        // prevent decrypt failing when $hash is not hex or has odd length
        if (strlen($hash) % 2 || !ctype_xdigit($hash)) {
            echo __("Error: Incorrect Hash. Hash cannot be validated.", 'blockonomics-bitcoin-payments');
            exit();
        }

        // we'll need the binary cipher
        $binaryInput = hex2bin($hash);
        $iv = substr($secret, 0, 16);
        $cipherText = $binaryInput;
        $key = hash($hashing_algorith, $secret, true);

        $decrypted = openssl_decrypt(
            $cipherText,
            $encryption_algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if (empty(wc_get_order($decrypted))) {
            echo __("Error: Incorrect hash. Woocommerce order not found.", 'blockonomics-bitcoin-payments');
            exit();
        }

        return $decrypted;
    }
}
