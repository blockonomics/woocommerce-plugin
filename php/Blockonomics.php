<?php

/**
 * This class is responsible for communicating with the Blockonomics API
 */
class Blockonomics
{
    const BASE_URL = 'https://www.blockonomics.co';
    const STORES_URL = self::BASE_URL . '/api/v2/stores?wallets=true';

    const NEW_ADDRESS_URL = self::BASE_URL . '/api/new_address';
    const PRICE_URL = self::BASE_URL . '/api/price';

    const BCH_BASE_URL = 'https://bch.blockonomics.co';
    const BCH_PRICE_URL = self::BCH_BASE_URL . '/api/price';
    const BCH_NEW_ADDRESS_URL = self::BCH_BASE_URL . '/api/new_address';


    function get_order_paid_fiat($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_payments';
        $query = $wpdb->prepare("SELECT expected_fiat,paid_fiat,currency FROM " . $table_name . " WHERE order_id = %d ", $order_id);
        $results = $wpdb->get_results($query, ARRAY_A);
        return $this->calculate_total_paid_fiat($results);
    }

    public function calculate_total_paid_fiat($transactions) {
        $total_paid_fiats = 0.0;

        foreach ($transactions as $transaction) {
            $total_paid_fiats += (float) $transaction['paid_fiat'];
        }
        $rounded_total_paid_fiats = round($total_paid_fiats, wc_get_price_decimals(), PHP_ROUND_HALF_UP);

        return $rounded_total_paid_fiats;
    }

    private $api_key;

    public function __construct()
    {
        $this->api_key = $this->get_api_key();
    }

    public function get_api_key()
    {
        $api_key = get_option("blockonomics_api_key");
        return $api_key;
    }

    public function test_new_address_gen($crypto, $response)
    {
        $callback_secret = get_option('blockonomics_callback_secret');
        $response = $this->new_address($callback_secret, $crypto, true);
        if ($response->response_code != 200) {
            return isset($response->response_message) && $response->response_message
                ? $response->response_message
                : __('Could not generate new address', 'blockonomics-bitcoin-payments');
        }

        if (empty($response->address)) {
            return __('No address returned from API', 'blockonomics-bitcoin-payments');
        }

        return ''; // Success - no error
    }


    public function new_address($crypto, $reset=false)
    {
        $secret = get_option("blockonomics_callback_secret");
        // Get the full callback URL
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $secret, $api_url);

        // Build query parameters
        $params = array();
        if ($callback_url) {
            $params['match_callback'] = $callback_url;
        }
        if ($reset) {
            $params['reset'] = 1;
        }

        $url = $crypto === 'bch' ? self::BCH_NEW_ADDRESS_URL : self::NEW_ADDRESS_URL;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->post($url, $this->api_key, '', 8);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);

        if (wp_remote_retrieve_body($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));
            if (isset($body->message)) {
                $responseObj->{'response_message'} = $body->message;
            } elseif (isset($body->error_code) && $body->error_code == 1002) {
                $responseObj->{'response_message'} = __('Multiple wallets found. Please ensure callback URL is set correctly.', 'blockonomics-bitcoin-payments');
            } else {
                $responseObj->{'response_message'} = '';
            }
            $responseObj->{'address'} = isset($body->address) ? $body->address : '';
        }
        return $responseObj;
    }

    public function get_price($currency, $crypto) {
        if($crypto === 'btc'){
            $url = Blockonomics::PRICE_URL. "?currency=$currency";
        }else{
            $url = Blockonomics::BCH_PRICE_URL. "?currency=$currency";
        }
        $response = $this->get($url);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));
            // Check if api response is {"price":null} which indicates unsupported currency
            if ($body && property_exists($body, 'price') && $body->price === null) {
                $responseObj->{'response_message'} = sprintf(
                    __('Currency %s is not supported by Blockonomics', 'blockonomics-bitcoin-payments'),
                    $currency
                );
                $responseObj->{'price'} = '';
            } else {
                $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
                $responseObj->{'price'} = isset($body->price) ? $body->price : '';
            }
        }
        return $responseObj;
    }

    public function get_callbacks($crypto)
    {
        if ($crypto !== 'btc') {
            return false;
        }
        $url = self::GET_CALLBACKS_URL;
        return $this->get($url, $this->api_key);
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
            $settings = get_option('woocommerce_blockonomics_settings');
            if ($code === 'btc' || ($code === 'bch' && is_array($settings) && isset($settings['enable_bch']) && $settings['enable_bch'] === 'yes')) {
                $active_currencies[$code] = $currency;
            }
        }
        return $active_currencies;
    }

    private function get_stores() {
        return $this->get(self::STORES_URL, $this->api_key);
    }

    private function update_store($store_id, $data) {
        // Ensure we're using the specific store endpoint
        $url = self::BASE_URL . '/api/v2/stores/' . $store_id;
        return $this->post($url, $this->api_key, wp_json_encode($data), 45);
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
        $headers = array(
            'Content-Type' => 'application/json'
        );
        if($api_key){
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        return $headers;
    }
    // Runs when the Blockonomics Test Setup button is clicked
    // Returns any errors or false if no errors
    public function testSetup()
    {
        $test_results = array(
            'crypto' => array()
        );
        // Update here for USDT Integration
        $active_cryptos = $this->getActiveCurrencies();
        foreach ($active_cryptos as $code => $crypto) {
            $result = $this->test_one_crypto($code);

            if (is_array($result) && isset($result['error'])) {
                $test_results['crypto'][$code] = $result['error'];
                if (isset($result['metadata_cleared'])) {
                    $test_results['metadata_cleared'] = true;
                }
            } else {
                $test_results['crypto'][$code] = $result;
                if ($result === false) {
                    // Success case
                    $test_results['store_data'] = array(
                        'name' => get_option('blockonomics_store_name'),
                        'enabled_cryptos' => get_option('blockonomics_enabled_cryptos')
                    );
                }
            }
        }
        wp_send_json($test_results);
    }
    
    public function test_one_crypto($crypto) {
        $api_key = get_option("blockonomics_api_key");

        // Function to clear stored metadata
        $clear_metadata = function($error_message = '', $clear_all = true) {
            if ($clear_all) {
                delete_option('blockonomics_store_name');
            }
            delete_option('blockonomics_enabled_cryptos');
            return array(
                'error' => $error_message !== null ? $error_message : __('Please set your Blockonomics API Key', 'blockonomics-bitcoin-payments'),
                'metadata_cleared' => $clear_all // Only set true when clearing all metadata
            );
        };

        // Function to process store metadata and enabled cryptos
        $process_store = function($store) use ($clear_metadata) {
            // Store name should always be saved
            update_option('blockonomics_store_name', $store->name);

            // Extract enabled cryptos from wallets
            $enabled_cryptos = array();
            if (!empty($store->wallets)) {
                foreach ($store->wallets as $wallet) {
                    if (isset($wallet->crypto)) {
                        $enabled_cryptos[] = strtolower($wallet->crypto);
                    }
                }
            }

            if (empty($enabled_cryptos)) {
                return $clear_metadata(
                    __('No crypto enabled for this store', 'blockonomics-bitcoin-payments'),
                    false // Don't clear store name
                );
            }

            update_option('blockonomics_enabled_cryptos', implode(',', array_unique($enabled_cryptos)));
            return false; // Success
        };

        if (!$api_key) {
            return $clear_metadata(null);
        }

        if ($crypto !== 'btc') {
            return __('Test Setup only supports BTC', 'blockonomics-bitcoin-payments');
        }

        // Get all stores to check if we have any
        $stores_response = $this->get_stores();

        // Check if the API key is valid first
        if (wp_remote_retrieve_response_code($stores_response) === 401) {
            return $clear_metadata(__('API Key is incorrect', 'blockonomics-bitcoin-payments'));
        }

        if (!$stores_response || is_wp_error($stores_response) || wp_remote_retrieve_response_code($stores_response) !== 200) {
            return $clear_metadata(__('Could not connect to Blockonomics API', 'blockonomics-bitcoin-payments'));
        }

        $stores = json_decode(wp_remote_retrieve_body($stores_response));

        if (empty($stores->data)) {
            return $clear_metadata(
                wp_kses(
                    sprintf(
                        __('Please add a %s', 'blockonomics-bitcoin-payments'),
                        '<a href="https://www.blockonomics.co/dashboard#/store" target="_blank">Store</a>'
                    ),
                    array(
                        'a' => array(
                            'href' => array(),
                            'target' => array()
                        )
                    )
                )
            );
        }
        // find matching store or store without callback
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $wordpress_callback_url = add_query_arg('secret', $callback_secret, $api_url);
        $base_url = preg_replace('/https?:\/\//', '', $api_url);

        $matching_store = null;
        $store_without_callback = null;
        $partial_match_store = null;

        foreach ($stores->data as $store) {
            if ($store->http_callback === $wordpress_callback_url) {
                $matching_store = $store;
                break;
            }
            if (empty($store->http_callback)) {
                $store_without_callback = $store;
                continue;
            }
            // Check for partial match - only secret or protocol differs
            $store_base_url = preg_replace('/https?:\/\//', '', $store->http_callback);
            if (strpos($store_base_url, $base_url) === 0) {
                $partial_match_store = $store;
            }
        }

        // If we found an exact match, process it
        if ($matching_store) {
            $store_result = $process_store($matching_store);
            if ($store_result !== false) {
                return $store_result;
            }
            // Test address generation
            $error = $this->test_new_address_gen($crypto, $stores_response);
            return $error ? array('error' => $error) : false;
        }

        // If we found a partial match, update its callback
        if ($partial_match_store) {
            $response = $this->update_store($partial_match_store->id, array(
                'name' => $partial_match_store->name,
                'http_callback' => $wordpress_callback_url
            ));

            if (wp_remote_retrieve_response_code($response) !== 200) {
                return $clear_metadata(__('Could not update store callback', 'blockonomics-bitcoin-payments'));
            }

            $store_result = $process_store($partial_match_store);
            if ($store_result !== false) {
                return $store_result;
            }
            // Test address generation
            $error = $this->test_new_address_gen($crypto, $stores_response);
            return $error ? array('error' => $error) : false;
        }

        // If we found a store without callback, update it and process
        if ($store_without_callback) {
            $response = $this->update_store($store_without_callback->id, array(
                'name' => $store_without_callback->name,
                'http_callback' => $wordpress_callback_url
            ));

            if (wp_remote_retrieve_response_code($response) !== 200) {
                return $clear_metadata(__('Could not update store callback', 'blockonomics-bitcoin-payments'));
            }

            $store_result = $process_store($store_without_callback);
            if ($store_result !== false) {
                return $store_result;
            }
            // Test address generation
            $error = $this->test_new_address_gen($crypto, $stores_response);
            return $error ? array('error' => $error) : false;
        }

        return $clear_metadata(sprintf(__('Please add a <a href="https://www.blockonomics.co/dashboard#/store">Store</a>', 'blockonomics-bitcoin-payments')));    }

    // Returns WC page endpoint of order adding the given extra parameters

    public function get_parameterized_wc_url($type, $params = array())
    {   
        $order_url = ($type === 'page') ? wc_get_page_permalink('payment') : WC()->api_request_url('WC_Gateway_Blockonomics');
        
        if (is_array($params) && count($params) > 0) {
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
            $order_url = $this->get_parameterized_wc_url('page',array('select_crypto' => $order_hash));
        } elseif (count($active_cryptos) === 1) {
            $order_url = $this->get_parameterized_wc_url('page',array('show_order' => $order_hash, 'crypto' => array_keys($active_cryptos)[0]));
        } else if (count($active_cryptos) === 0) {
            $order_url = $this->get_parameterized_wc_url('page',array('crypto' => 'empty'));
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

    public function is_partial_payments_active(){
        return get_option('blockonomics_partial_payments', true);
    }

    public function is_error_template($template_name) {
        if (strpos($template_name, 'error') === 0) {
            return true;
        }
        return false;
    }

    // Adds the style for blockonomics checkout page
    public function add_blockonomics_checkout_style($template_name, $additional_script=NULL){
        wp_enqueue_style( 'bnomics-style' );
        if ($template_name === 'checkout') {
            add_action('wp_footer', function() use ($additional_script) {
                printf('<script type="text/javascript">%s</script>', $additional_script);
            });
            wp_enqueue_script( 'bnomics-checkout' );
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
        $this->add_blockonomics_checkout_style($template_name, $additional_script);

        // Load the selected template
        $template = 'blockonomics_'.$template_name.'.php';
        // Load Template Context
        extract($context);
        // Load the checkout template
        ob_start(); // Start buffering
        include_once plugin_dir_path(__FILE__)."../templates/" .$template;
        return ob_get_clean(); // Return the buffered content
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
        $paid_fiat = $this->calculate_total_paid_fiat($results);
        $discount_percent = floatval( get_option( 'blockonomics_bitcoin_discount', 0 ) );
        $subtotal = (float) $wc_order->get_subtotal();
        $total = (float) $wc_order->get_total();
        
        // Calculate the expected amount after applying the Bitcoin discount
        $expected_fiat = $total - ( $subtotal * ( $discount_percent / 100 ) );
        
        $order['expected_fiat'] = $expected_fiat - $paid_fiat;
        $order['currency'] = get_woocommerce_currency();
        if (get_woocommerce_currency() != 'BTC') {
            $responseObj = $this->get_price($order['currency'], $order['crypto']);
            if($responseObj->response_code != 200) {
                exit();
            }
            $price = $responseObj->price;
            $margin = floatval(get_option('blockonomics_margin', 0));
            $price = $price * 100 / (100 + $margin);
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
        $wc_order = wc_get_order( $order_id );
        $addr_meta_key = 'blockonomics_payments_addresses';
        $addr_meta_value = $wc_order->get_meta($addr_meta_key);
        if (empty($addr_meta_value)){ 
            $wc_order->update_meta_data( $addr_meta_key, $address );
        } 
        // when address meta value is not empty and $address is not in it 
        else if (strpos($addr_meta_value, $address) === false) {
            $wc_order->update_meta_data( $addr_meta_key, $addr_meta_value. ', '. $address );
        }
        $wc_order->save();
    }

    public function create_new_order($order_id, $crypto){
        $responseObj = $this->new_address($crypto);
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
        return $this->calculate_order_params($order);
    }

    public function get_error_context($error_type){
        $context = array();

        if ($error_type == 'currency') {
            // For unsupported currency errors
            // $context['error_title'] = __('Checkout Page Error', 'blockonomics-bitcoin-payments');
            $context['error_title'] = '';

            $context['error_msg'] = sprintf(
                __('Currency %s selected on this store is not supported by Blockonomics', 'blockonomics-bitcoin-payments'),
                get_woocommerce_currency()
            );
        } else if ($error_type == 'generic') {
            // Show Generic Error to Client
            $context['error_title'] = __('Could not generate new address (This may be a temporary error. Please try again)', 'blockonomics-bitcoin-payments');
            $context['error_msg'] = __('If this continues, please ask website administrator to do following:<br/><ul><li>Login to WordPress admin panel, navigate to WooCommerce > Settings > Payment. Select Manage on "Blockonomics Bitcoin" and click Test Setup to diagnose the exact issue.</li><li>Check blockonomics registered email address for error messages</li></ul>', 'blockonomics-bitcoin-payments');
        } else if ($error_type == 'underpaid') {
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

        $context['order_id'] = isset($order['order_id']) ? $order['order_id'] : '';
        $cryptos = $this->getActiveCurrencies();
        $context['crypto'] = $cryptos[$crypto];

        if (array_key_exists('error', $order)) {
            // Check if this is a currency error
            if (strpos($order['error'], 'Currency') === 0) {
                $error_context = $this->get_error_context('currency');
            } else {
                // All other errors use generic error handling
                $error_context = $this->get_error_context('generic');
            }
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
                if($this->is_nojs_active()){
                    $context['qrcode_svg_element'] = $this->generate_qrcode_svg_element($context['payment_uri']);
                }

                $context['total'] = $order['expected_fiat'];
                $paid_fiat = $this->get_order_paid_fiat($order['order_id']);

                if ($paid_fiat > 0) {
                    $context['paid_fiat'] = $paid_fiat;
                    $context['total'] = $order['expected_fiat'] + $context['paid_fiat'];
                }
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
                'get_order_amount_url' => $this->get_parameterized_wc_url('api',array('get_amount'=>$order_hash, 'crypto'=>  $context['crypto']['code'])),
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
        return $this->load_blockonomics_template($template_name, $context, $script);
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
            $wpdb->prepare(
                "SELECT * FROM " . $wpdb->prefix . "blockonomics_payments WHERE order_id = %d AND crypto = %s ORDER BY expected_satoshi ASC",
                $order_id,
                $crypto
            ),
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
            $this->record_expected_satoshi($order_id, $crypto, $order['expected_satoshi']);
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
        $txid_meta_value = $wc_order->get_meta($txid_meta_key);
        $txid = $order['txid'];
        if (empty($txid_meta_value)){
            $wc_order->update_meta_data($txid_meta_key, $txid);
        }
        // when txid meta value is not empty and $txid is not in it 
        else if (strpos($txid_meta_value, $txid) === false){
            $wc_order->update_meta_data($txid_meta_key, $txid_meta_value.', '. $txid);
        }
        $wc_order->save();
    }

    // Record the expected amount as a custom field
    public function record_expected_satoshi($order_id, $crypto, $expected_satoshi){
        $wc_order = wc_get_order($order_id);
        $expected_satoshi_meta_key = 'blockonomics_expected_' . $crypto . '_amount';
        $formatted_amount = $this->fix_displaying_small_values($expected_satoshi);
        $wc_order->update_meta_data( $expected_satoshi_meta_key, $formatted_amount );
        $wc_order->save();
    }

    public function update_paid_amount($callback_status, $paid_satoshi, $order, $wc_order){
        $network_confirmations = get_option("blockonomics_network_confirmation",2);
        if ($order['payment_status'] == 2) {
            return $order;
        }
        if ($callback_status >= $network_confirmations){
            $order['payment_status'] = 2;
            $order = $this->check_paid_amount($paid_satoshi, $order, $wc_order);
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
        $order['paid_fiat'] = number_format($order['expected_fiat']*$paid_amount_ratio,wc_get_price_decimals(),'.','');

        // This is to update the order table before we send an email on failed and confirmed state
        // So that the updated data is used to build the email
        $this->update_order($order);

        if ($this->is_order_underpaid($order)) {
            if ($this->is_partial_payments_active()){
                $this->add_note_on_underpayment($order, $wc_order);
                $this->send_email_on_underpayment($order);
            }
            else {
                $wc_order->add_order_note(__(  wc_price($order['paid_fiat']). " paid via ".strtoupper($order['crypto'])." (Blockonomics).", 'blockonomics-bitcoin-payments' ));
                $wc_order->update_status('failed', __( "Order Underpaid.", 'blockonomics-bitcoin-payments'));
            }
        }
        else{
            $wc_order->add_order_note(__(wc_price($order['paid_fiat']). " paid via ".strtoupper($order['crypto'])." (Blockonomics).", 'blockonomics-bitcoin-payments'));
            $wc_order->payment_complete($order['txid']);
        }
        if ($order['expected_satoshi'] < $paid_satoshi) {
            $wc_order->add_order_note(__( 'Paid amount more than expected.', 'blockonomics-bitcoin-payments' ));
        }
        return $order;
    }

    public function is_order_underpaid($order){
        // Return TRUE only if there has been a payment which is less than required.
        $underpayment_slack = floatval(get_option("blockonomics_underpayment_slack", 0))/100 * $order['expected_satoshi'];
        $is_order_underpaid = ($order['expected_satoshi'] - $underpayment_slack > $order['paid_satoshi'] && !empty($order['paid_satoshi'])) ? TRUE : FALSE;
        return $is_order_underpaid;
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

        $blockonomics_currencies = $this->getSupportedCurrencies();
        $selected_currency = $blockonomics_currencies[$order['crypto']];
        $wc_order->set_payment_method_title($selected_currency['name']);
        $wc_order->save();
    }

    // Auto generate and apply coupon on underpaid callbacks
    public function add_note_on_underpayment($order, $wc_order){
        $paid_amount = $order['paid_fiat'];
        $wc_order->add_order_note(__( wc_price($paid_amount). " paid via ".strtoupper($order['crypto'])." (Blockonomics).", 'blockonomics-bitcoin-payments' ));
        $wc_order->add_order_note(__( "Customer has been mailed invoice to pay the remaining amount. You can resend invoice from Order actions.", 'blockonomics-bitcoin-payments' ));
    }

    // Send Invoice email to customer to pay remaining amount
    public function send_email_on_underpayment($order){
        $wc_email = WC()->mailer()->emails['WC_Email_Customer_Invoice'];
        $wc_email->settings['subject'] = __('Additional Payment Required for order #{order_number} on {site_title}');
        $wc_email->settings['heading'] = __('Use below link to pay remaining amount.'); 
        $wc_email->trigger($order['order_id']);
    }

    public function generate_qrcode_svg_element($data) {
        include_once plugin_dir_path(__FILE__) . 'qrcode.php';
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

