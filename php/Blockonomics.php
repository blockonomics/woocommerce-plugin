<?php

/**
 * This class is responsible for communicating with the Blockonomics API
 */
class Blockonomics
{
    const BASE_URL = 'https://whmcs.testblockonomics.com/';
    const STORES_URL = self::BASE_URL . '/api/v2/stores?wallets=true';

    const NEW_ADDRESS_URL = self::BASE_URL . '/api/new_address';
    const PRICE_URL = self::BASE_URL . '/api/price';
    const STORE_UID_URL = self::BASE_URL . '/api/store_uid';

    const BCH_BASE_URL = 'https://whmcs.testblockonomics.com/';
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
    private $logger;

    public function __construct()
    {
        $this->api_key = $this->get_api_key();
        $this->logger = wc_get_logger();
    }

    private function log($message, $level = 'info') {
        $this->logger->log($level, $message, array('source' => 'blockonomics'));
    }

    public function get_api_key()
    {
        $api_key = get_option("blockonomics_api_key");
        return $api_key;
    }

    public function get_price($currency, $crypto) {
        if($crypto === 'bch'){
            $url = Blockonomics::BCH_PRICE_URL. "?currency=$currency";
        }else{
            $crypto = strtoupper($crypto);
            $url = Blockonomics::PRICE_URL. "?currency=$currency&crypto=$crypto";
        }
        $response = $this->get($url);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        $responseObj->{'response_message'} = '';
        $responseObj->{'price'} = '';
        if (wp_remote_retrieve_body($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));
            // Check if api response is {"price":null} which indicates unsupported currency
            if ($body && property_exists($body, 'price') && $body->price === null) {
                $responseObj->{'response_message'} = sprintf(
                    __('Currency %s is not supported by Blockonomics', 'blockonomics-bitcoin-payments'),
                    $currency
                );
            } else {
                $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
                $responseObj->{'price'} = isset($body->price) ? $body->price : '';
            }
        }
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
                    'uri' => 'bitcoin',
                    'decimals' => 8,
                ),
                'bch' => array(
                    'code' => 'bch',
                    'name' => 'Bitcoin Cash',
                    'uri' => 'bitcoincash',
                    'decimals' => 8,
                ),
                'usdt' => array(
                    'code' => 'usdt',
                    'name' => 'USDT',
                    'decimals' => 6,
                )
          );
    }  
    /*
     * Get list of active crypto currencies
     */
    public function getActiveCurrencies() {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return $this->setup_error(__('API Key is not set. Please enter your API Key.', 'blockonomics-bitcoin-payments'));
        }

        // Get currencies enabled on Blockonomics store from API
        $stores_result = $this->get_stores($api_key);
        if (!empty($stores_result['error'])) {
            return $this->setup_error($stores_result['error']);
        }

        $callback_url = $this->get_callback_url();
        $matching_store = $this->findExactMatchingStore($stores_result['stores'], $callback_url);

        // Result currencies
        $checkout_currencies = [];
        $supported_currencies = $this->getSupportedCurrencies();

        // Add currencies from Blockonomics store if exact match is found
        if ($matching_store) {
            $blockonomics_enabled = $this->getStoreEnabledCryptos($matching_store);
            foreach ($blockonomics_enabled as $code) {
                if ($code != 'bch' && isset($supported_currencies[$code])) {
                    $checkout_currencies[$code] = $supported_currencies[$code];
                }
            }
        }

        // Add BCH if enabled in Woocommerce settings
        $settings = get_option('woocommerce_blockonomics_settings');
        if (is_array($settings) && isset($settings['enable_bch']) && $settings['enable_bch'] === 'yes') {
            $checkout_currencies['bch'] = $supported_currencies['bch'];
        }

        return $checkout_currencies;
    }

    /* Get cached active currencies from wp_options (for checkout display)
     * Uses value saved during Test Setup - no more stores API call
     */
    public function getCachedActiveCurrencies() {
        $cached_cryptos = get_option("blockonomics_enabled_cryptos", "");
        $supported_currencies = $this->getSupportedCurrencies();
        $checkout_currencies = [];
        if (!empty($cached_cryptos)) {
            $crypto_codes = explode(',', $cached_cryptos);
            foreach ($crypto_codes as $code) {
                $code = trim(strtolower($code));
                if (isset($supported_currencies[$code])) {
                    $checkout_currencies[$code] = $supported_currencies[$code];
                }
            }
        }
        //add BCH only if enabled in plugin settings
        $settings = get_option('woocommerce_blockonomics_settings');
        if (is_array($settings) && isset($settings['enable_bch']) && $settings['enable_bch'] === 'yes') {
            $checkout_currencies['bch'] = $supported_currencies['bch'];
        }
        return $checkout_currencies;
    }

    /**
     * Fetches stores from Blockonomics API.
     *
     * @param string $api_key Blockonomics API key.
     * @return array ['error' => string, 'stores' => array]
     */
    private function get_stores($api_key) {
        $result = [];
        $response = $this->get(self::STORES_URL, $api_key);

        $error = $this->check_api_response_error($response);
        if ($error) {
            return ['error' => $error];
        }

        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body);

        if (!$response_data || !isset($response_data->data)) {
            $result['error'] = __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments');
            return $result;
        }

        $result['stores'] = is_array($response_data->data) ? $response_data->data : [];

        if (empty($result['stores'])) {
            $result['error'] = __('Please add a <a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank"><i>Store</i></a> on Blockonomics Dashboard', 'blockonomics-bitcoin-payments');
            return $result;
        }

        return $result;
    }

    private function get($url, $api_key = '')
    {
        $headers = $this->set_headers($api_key);

        $response = wp_remote_get( $url, array(
            'method' => 'GET',
            'headers' => $headers
            )
        );

        return $response;
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
        return $response;
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

    /* Build URL for api/new_address
     *
     * @param string $crypto Cryptocurrency code (btc, bch, usdt)
     * @return string Full URL with query parameters
     */
    private function build_new_address_url($crypto)
    {
        $secret = get_option("blockonomics_callback_secret");
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $secret, $api_url);

        $params = array();
        if ($callback_url) {
            $params['match_callback'] = $callback_url;
        }
        if ($crypto === 'usdt') {
            $params['crypto'] = "USDT";
        }

        $url = $crypto === 'bch' ? self::BCH_NEW_ADDRESS_URL : self::NEW_ADDRESS_URL;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    /* Build URL for price API call
     *
     * @param string $currency Fiat currency code (USD, EUR, etc.)
     * @param string $crypto Cryptocurrency code (btc, bch, usdt)
     * @return string Full URL with query parameters
     */
    private function build_price_url($currency, $crypto)
    {
        if ($crypto === 'bch') {
            return self::BCH_PRICE_URL . "?currency=$currency";
        }
        $crypto_upper = strtoupper($crypto);
        return self::PRICE_URL . "?currency=$currency&crypto=$crypto_upper";
    }

    /* Fetch address and price in parallel using WordPress Requests library
     *
     * @param string $crypto Cryptocurrency code
     * @param string $currency Fiat currency code
     * @return array ['address' => string, 'price' => float] or ['error' => string]
     */
    private function fetch_order_data_parallel($crypto, $currency){
        // build url for both API calls
        $address_url = $this->build_new_address_url($crypto);
        $price_url = $this->build_price_url($currency, $crypto);

        // build requests array for Requests::request_multiple()
        $requests = array(
            'address' => array(
                'url' => $address_url,
                'type' => \WpOrg\Requests\Requests::POST,
                'headers' => $this->set_headers($this->api_key),
                'options' => array('timeout' => 8)
            ),
            'price' => array(
                'url' => $price_url,
                'type' => \WpOrg\Requests\Requests::GET,
                'headers' => $this->set_headers('')
            )
        );

        // parallely process api/price and api/new_address
        try {
            $responses = \WpOrg\Requests\Requests::request_multiple($requests);
        } catch (\Exception $e) {
            $this->log('Checkout: parallel request exception: ' . $e->getMessage(), 'error');
            return array('error' => $e->getMessage());
        }
        return $this->process_parallel_responses($responses, $currency);
    }

    /* Process responses from parallel API calls (Requests library format)
     *
     * @param array $responses Array of \WpOrg\Requests\Response objects
     * @param string $currency Fiat currency for error messages
     * @return array ['address' => string, 'price' => float] or ['error' => string]
     */
    private function process_parallel_responses($responses, $currency)
    {
        $result = array();

        // Process address response
        $address_response = $responses['address'];
        if ($address_response instanceof \WpOrg\Requests\Exception) {
            $this->log('Checkout: new_address exception: ' . $address_response->getMessage(), 'error');
            return array('error' => $address_response->getMessage());
        }
        if ($address_response->status_code != 200) {
            $body = json_decode($address_response->body);
            $error_msg = '';
            if (isset($body->message)) {
                $error_msg = $body->message;
            } elseif (isset($body->error) && isset($body->error->message)) {
                $error_msg = $body->error->message;
            }
            $this->log('Checkout: new_address HTTP ' . $address_response->status_code . ' - ' . ($error_msg ?: $address_response->body), 'error');
            return array('error' => $error_msg ?: __('Could not generate address', 'blockonomics-bitcoin-payments'));
        }
        $address_body = json_decode($address_response->body);
        if (!isset($address_body->address) || empty($address_body->address)) {
            $this->log('Checkout: new_address returned 200 but no address in body: ' . $address_response->body, 'error');
            return array('error' => __('Could not generate address', 'blockonomics-bitcoin-payments'));
        }
        $result['address'] = $address_body->address;

        // Process price response
        $price_response = $responses['price'];
        if ($price_response instanceof \WpOrg\Requests\Exception) {
            $this->log('Checkout: price API exception: ' . $price_response->getMessage(), 'error');
            return array('error' => $price_response->getMessage());
        }
        if ($price_response->status_code != 200) {
            $this->log('Checkout: price API HTTP ' . $price_response->status_code . ' - ' . $price_response->body, 'error');
            return array('error' => __('Could not get price', 'blockonomics-bitcoin-payments'));
        }
        $price_body = json_decode($price_response->body);

        // Check for null price (unsupported currency)
        if ($price_body && property_exists($price_body, 'price') && $price_body->price === null) {
            $this->log('Checkout: price API returned null price for currency ' . $currency, 'error');
            return array('error' => sprintf(
                __('Currency %s is not supported by Blockonomics', 'blockonomics-bitcoin-payments'),
                $currency
            ));
        }
        if (!isset($price_body->price) || empty($price_body->price)) {
            $this->log('Checkout: price API returned empty/missing price: ' . $price_response->body, 'error');
            return array('error' => __('Could not get price', 'blockonomics-bitcoin-payments'));
        }
        $result['price'] = $price_body->price;

        return $result;
    }

    /* Calculate order parameters using pre-fetched price
     *
     * @param array $order Order data with order_id, crypto, address
     * @param float $price Crypto price (already fetched)
     * @return array Order with expected_fiat, expected_satoshi, currency
     */
    public function calculate_order_params_with_price($order, $price)
    {
        $wc_order = new WC_Order($order['order_id']);
        global $wpdb;
        $order_id = $wc_order->get_id();
        $table_name = $wpdb->prefix . 'blockonomics_payments';
        $query = $wpdb->prepare("SELECT expected_fiat,paid_fiat,currency FROM " . $table_name . " WHERE order_id = %d", $order_id);
        $results = $wpdb->get_results($query, ARRAY_A);
        $paid_fiat = $this->calculate_total_paid_fiat($results);

        // woocommerce_cart_calculate_fees already applied crypto payment discount
        $total = (float) $wc_order->get_total();
        $order['expected_fiat'] = $total - $paid_fiat;
        $order['currency'] = get_woocommerce_currency();

        // apply margin to price
        $margin = floatval(get_option('blockonomics_margin', 0));
        $adjusted_price = $price * 100 / (100 + $margin);

        $crypto_data = $this->getSupportedCurrencies();
        $crypto = $crypto_data[$order['crypto']];
        $multiplier = pow(10, $crypto['decimals']);
        $order['expected_satoshi'] = (int) round($multiplier * $order['expected_fiat'] / $adjusted_price);

        return $order;
    }

    /**
     * Get enabled cryptocurrencies from a store's wallets
     *
     * @param object $store Store object from Blockonomics API
     * @return array List of enabled cryptocurrency codes
     */
    private function getStoreEnabledCryptos($store)
    {
        $enabled_cryptos = [];

        if (!empty($store->wallets)) {
            foreach ($store->wallets as $wallet) {
                if (isset($wallet->crypto)) {
                    $crypto = strtolower($wallet->crypto);
                    if (!in_array($crypto, $enabled_cryptos)) {
                        $enabled_cryptos[] = $crypto;
                    }
                }
            }
        }

        return $enabled_cryptos;
    }

    // save to cache, what cryptos are enabled on blockonomics store
    public function saveBlockonomicsEnabledCryptos($cryptos)
    {
        return update_option("blockonomics_enabled_cryptos", implode(',', $cryptos));

    }

    /* Find store with exact callback URL match, if multiple matches exist, prefers store with wallets attached
     *
     * @param array $stores List of stores from API
     * @param string $callback_url The callback URL to match
     * @return object|null Matching store or null
     */
    private function findExactMatchingStore($stores, $callback_url) {
        $best_store = null;
        foreach ($stores as $store) {
            if ($store->http_callback === $callback_url) {
                // prefer store with wallets (so checkout works)
                if (!$best_store || (!empty($store->wallets) && empty($best_store->wallets))) {
                    $best_store = $store;
                }
            }
        }
        return $best_store;
    }

    /**
     * Helper to check API response for errors.
     *
     * @param mixed $response WP HTTP response object.
     * @return string|false String with 'error' if error, false otherwise.
     */
    private function check_api_response_error($response)
    {
        if (is_wp_error($response)) {
            return __('Something went wrong', 'blockonomics-bitcoin-payments') . ': ' . $response->get_error_message();
        }
        if (!$response) {
            return __('Your server is blocking outgoing HTTPS calls', 'blockonomics-bitcoin-payments');
        }

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code === 401) {
            return __('API Key is incorrect', 'blockonomics-bitcoin-payments');
        }

        if ($http_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            return __('API Error: ', 'blockonomics-bitcoin-payments') . $body;
        }

        return false;
    }

    /**
     * Get the wallets from the API, also checks if API key is valid.
     *
     * @param string $api_key Blockonomics API key.
     * @return array [
     *   'error' => string,     // Error message if any
     *   'wallets' => array     // Array of configured wallet currencies
     * ]
     */
    public function get_wallets($api_key)
    {
        $response = $this->get(self::WALLETS_URL, $api_key);

        $error = $this->check_api_response_error($response);
        if ($error) {
            return ['error' => $error];
        }

        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body);

        if (!$response_data || !isset($response_data->data)) {
            return ['error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')];
        }

        $wallets = [];
        foreach ($response_data->data as $wallet) {
            if (!empty($wallet->crypto)) {
                $crypto = strtolower($wallet->crypto);
                if (!in_array($crypto, $wallets)) {
                    $wallets[] = $crypto;
                }
            }
        }

        if (empty($wallets)) {
            return ['error' => __('Please add a <a href="https://whmcs.testblockonomics.com/dashboard#/wallet" target="_blank"><i>Wallet</i></a> on Blockonomics Dashboard', 'blockonomics-bitcoin-payments')];
        }

        return ['wallets' => $wallets];
    }

    public function testSetup()
    {
        // just clear these first, they will only be set again on success
        delete_option("blockonomics_store_name");
        delete_option("blockonomics_enabled_cryptos");

        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return $this->setup_error(__('API Key is not set. Please enter your API Key.', 'blockonomics-bitcoin-payments'));
        }

        $stores_result = $this->get_stores($api_key);
        if (!empty($stores_result['error'])) {
            return $this->setup_error($stores_result['error']);
        }

        $callback_url = $this->get_callback_url();
        $matching_store = $this->findExactMatchingStore($stores_result['stores'], $callback_url);

        if ($match_type === 'none') {
            return $this->setup_error(__(var_export($stores_result['stores'], true) . '   This is the Callback URL:' . $callback_url . 'Please add a <a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank"><i>Store</i></a> on Blockonomics Dashboard', 'blockonomics-bitcoin-payments'));
        }

        if ($match_type === 'partial') {
            return $this->setup_error(__('Please copy Callback URL from Advanced Settings and paste it as your <a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank">Store Callback URL</a>', 'blockonomics-bitcoin-payments'));
        }

        if ($match_type === 'empty') {
            $update_result = $this->update_store($matching_store->id, [
                'name' => $matching_store->name,
                'http_callback' => $callback_url
            ]);
            if (!empty($update_result)) {
                return $this->setup_error($update_result);
            }
        }

        if (!$matching_store) {
            return $this->setup_error(__('Please add a <a href="https://www.blockonomics.co/dashboard#/store" target="_blank">new store</a> with the callback URL shown in advanced settings', 'blockonomics-bitcoin-payments'));
        }

        $this->update_store_name_option($matching_store->name);

        $enabled_cryptos = $this->getStoreEnabledCryptos($matching_store);
        if (empty($enabled_cryptos)) {
            // if no crypto enabled on store, show error msg
            // with store name: Please enable Payment method on your store MySampleStoreName
            // empty store name: Please enable Payment method on Stores
            if (!empty($matching_store->name)){
                $error_msg = sprintf(
                    __('Please enable Payment method on your store <a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank"><i>%s</i></a>', 'blockonomics-bitcoin-payments'),
                    esc_html($matching_store->name)
                );
            } else{
                $error_msg = __('Please enable Payment method on <a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank"><i>Stores</i></a>', 'blockonomics-bitcoin-payments');
            }
            return $this->setup_error($error_msg);
        }

        $this->saveBlockonomicsEnabledCryptos($enabled_cryptos);

        $result = $this->test_cryptos($enabled_cryptos);
        $duplicate_count = isset($match_result['duplicate_count']) ? $match_result['duplicate_count'] : 0;
        if ($duplicate_count > 0) {
            $store_name = !empty($matching_store->name) ? $matching_store->name : __('(unnamed)', 'blockonomics-bitcoin-payments');
            $notice = sprintf(
                __('Note: Found %d duplicate store(s) with matching callback URL. Using "%s" which has payments enabled. You may want to remove unused stores from your <a href="https://whmcs.testblockonomics.com/dashboard#/store" target="_blank">Blockonomics dashboard</a>.', 'blockonomics-bitcoin-payments'),
                $duplicate_count,
                esc_html($store_name)
            );
            $result['duplicate_notice'] = $notice;
        }
        // include store info for JS to update UI without page refresh
        $result['store_name'] = $matching_store->name ?? '';
        $result['enabled_cryptos'] = $enabled_cryptos;

        return $result;
    }

    private function setup_error($msg) {
        return ['error' => $msg];
    }

    private function get_callback_url() {
        $callback_secret = get_option("blockonomics_callback_secret");
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        return add_query_arg('secret', $callback_secret, $api_url);
    }

    private function update_store_name_option($store_name) {
        $current_name = get_option("blockonomics_store_name", "");
        if ($current_name !== $store_name) {
            update_option("blockonomics_store_name", $store_name);
        }
    }

    private function test_cryptos($enabled_cryptos) {
        // Build parallel requests for all cryptos (BTC, USDT from stores API - not BCH)
        $requests = [];
        foreach ($enabled_cryptos as $code) {
            $requests[$code] = [
                'url' => $this->build_new_address_url($code),
                'type' => \WpOrg\Requests\Requests::POST,
                'headers' => $this->set_headers($this->api_key),
                'options' => ['timeout' => 8]
            ];
        }

        // Execute all requests in parallel
        try {
            $responses = \WpOrg\Requests\Requests::request_multiple($requests);
        } catch (\Exception $e) {
            return ['error_messages' => [__('Could not connect to Blockonomics API', 'blockonomics-bitcoin-payments')]];
        }

        // Process responses
        $success_messages = [];
        $error_messages = [];

        foreach ($responses as $code => $response) {
            if ($response instanceof \WpOrg\Requests\Exception) {
                $msg = $response->getMessage();
                // cURL error 28 is standard for api timeoput
                if (strpos($msg, 'cURL error 28') !== false || stripos($msg, 'timed out') !== false) {
                    $error_messages[] = strtoupper($code) . ": " . __('Request timed out (server/firewall issue)', 'blockonomics-bitcoin-payments');
                } else { // other possible connection errors (DNS, rate limited, SSL)
                    $error_messages[] = strtoupper($code) . ": " . __('Connection failed (server/firewall issue)', 'blockonomics-bitcoin-payments');
                }
                continue;
            }

            if ($response->status_code == 200) {
                $body = json_decode($response->body);
                if (isset($body->address) && !empty($body->address)) {
                    $success_messages[] = strtoupper($code) . " ✅";
                } else {
                    $msg = isset($body->message) ? $body->message : __('Could not generate new address', 'blockonomics-bitcoin-payments');
                    $error_messages[] = strtoupper($code) . ": " . $msg;
                }
            } else {
                $body = json_decode($response->body);
                $msg = isset($body->message) ? $body->message : __('Could not generate new address', 'blockonomics-bitcoin-payments');
                $error_messages[] = strtoupper($code) . ": " . $msg;
            }
        }

        $final_messages = [];
        if ($error_messages) {
            $final_messages['error_messages'] = $error_messages;
        }
        if ($success_messages) {
            $final_messages['success_messages'] = $success_messages;
        }

        return $final_messages;
    }


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
        $order_hash = $this->encrypt_hash($order_id);
        return $this->get_parameterized_wc_url('page', array('show_order' => $order_hash));
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
    public function add_blockonomics_checkout_style($template_name){
        wp_enqueue_style( 'bnomics-style' );
        if ($template_name === 'checkout' || $template_name === 'widget_checkout') {
            wp_enqueue_script( 'bnomics-checkout' );
            wp_enqueue_style( 'blockonomics-checkout' );
            // Priority: manual setting → classic-theme auto-detect → CSS var fallback (block themes).
            $accent = sanitize_hex_color( get_option( 'blockonomics_accent_color', '' ) );
            if ( ! $accent ) {
                $accent = $this->get_theme_accent_color();
            }
            if ( $accent ) {
                wp_add_inline_style( 'blockonomics-checkout',
                    '#bck-payment { --bck-btn-bg: ' . esc_attr( $accent ) . '; --bck-accent: ' . esc_attr( $accent ) . '; }' .
                    '#web3-payment { --primary-color: ' . esc_attr( $accent ) . '; }'
                );
            }
        } elseif ($template_name === 'web3_checkout') {
            wp_enqueue_script( 'bnomics-web3-checkout' );
        }
    }

    /**
     * Auto-detect the active theme's primary/accent colour for classic themes.
     * Block themes are handled passively via var(--wp--preset--color--primary) in the CSS.
     *
     * @return string Hex colour string, or empty string if not detected.
     */
    private function get_theme_accent_color() {
        // Classic theme customizer keys — ordered most-specific first.
        $customizer_keys = array(
            'storefront_accent_color', // Storefront (most popular WC theme)
            'accent_color',
            'primary_color',
            'button_color',
        );
        foreach ( $customizer_keys as $key ) {
            $val = get_theme_mod( $key );
            if ( $val && is_string( $val ) ) {
                return sanitize_hex_color( $val ) ?: '';
            }
        }
        return '';
    }

    public function set_template_context($context) {
        // Todo: With WP 5.5+, the load_template methods supports args
        // and can be used as a replacement to this.
        foreach ($context as $key => $value) {
            set_query_var($key, $value);
        }
    }

    // Adds the selected template to the blockonomics page
    public function load_blockonomics_template($template_name, $context = array()){
        $this->add_blockonomics_checkout_style($template_name);

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
        // woocommerce_cart_calculate_fees already applied bitcoin payment method discount
        $total = (float) $wc_order->get_total();
        $order['expected_fiat'] = $total - $paid_fiat;
        $order['currency'] = get_woocommerce_currency();
        if (get_woocommerce_currency() != 'BTC') {
            $responseObj = $this->get_price($order['currency'], $order['crypto']);
            if ($responseObj->response_code != 200 || empty($responseObj->price)) {
                $error_msg = !empty($responseObj->response_message) ? $responseObj->response_message : __('Could not get price', 'blockonomics-bitcoin-payments');
                return array("error" => $error_msg);
            }
            $price = $responseObj->price;
            $margin = floatval(get_option('blockonomics_margin', 0));
            $price = $price * 100 / (100 + $margin);
        } else {
            $price = 1;
        }
        $crypto_data = $this->getSupportedCurrencies();
        $crypto = $crypto_data[$order['crypto']];
        $multiplier = pow(10, $crypto['decimals']);
        $order['expected_satoshi'] = (int) round($multiplier * $order['expected_fiat'] / $price);
        return $order;
    }
    
    // Get new addr and update amount after confirmed underpayment
    public function create_and_insert_new_order_on_underpayment($order){
        $order = $this->create_new_order($order['order_id'], $order['crypto']);
        if (array_key_exists("error", $order)) {
            // Some error in Address Generation from API, return the same array.
            return $order;
        }
        $result = $this->insert_order($order);
        if (array_key_exists("error", $result)) {
            // Some error in inserting order to DB, return the error.
            return $result;
        }
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
        $currency = get_woocommerce_currency();

        // Fetch address and price in parallel (or just address if currency is BTC)
        $api_results = $this->fetch_order_data_parallel($crypto, $currency);

        if (isset($api_results['error'])) {
            return array('error' => $api_results['error']);
        }

        $order = array(
            'order_id'       => $order_id,
            'payment_status' => 0,
            'crypto'         => $crypto,
            'address'        => $api_results['address']
        );

        // Calculate order params using the pre-fetched price
        return $this->calculate_order_params_with_price($order, $api_results['price']);
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
        } else if ($error_type == 'bch_no_wallet') {
            // BCH wallet not configured on bch.blockonomics.co
            $context['error_title'] = __('Could not generate new address (This may be a temporary error. Please try again)', 'blockonomics-bitcoin-payments');
            $context['error_msg'] = __('If this continues, please ask website administrator to do following:<br/><ul><li><strong>Administrator action required:</strong> Please add a BCH wallet on <a href="https://bch.blockonomics.co/merchants#/page3" target="_blank">bch.blockonomics.co</a></li><li>Check blockonomics registered email address for error messages</li></ul>', 'blockonomics-bitcoin-payments');
        } else if ($error_type == 'bch_callback_mismatch') {
            // BCH callback URL mismatch
            $context['error_title'] = __('Could not generate new address (This may be a temporary error. Please try again)', 'blockonomics-bitcoin-payments');
            $context['error_msg'] = __('If this continues, please ask website administrator to do following:<br/><ul><li><strong>Administrator action required:</strong> Please ensure callback URL on <a href="https://bch.blockonomics.co/merchants#/page3" target="_blank">bch.blockonomics.co</a> matches the one in plugin Advanced Settings</li><li>Check blockonomics registered email address for error messages</li></ul>', 'blockonomics-bitcoin-payments');
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

    public function fix_displaying_small_values($crypto, $satoshi){
        $crypto_data = $this->getSupportedCurrencies();
        $crypto_obj = $crypto_data[$crypto];
        $divider = pow(10, $crypto_obj['decimals']);
        if ($satoshi < 10000){
            return rtrim(number_format($satoshi/$divider, $crypto_obj['decimals']), '0');
        } else {
            return $satoshi/$divider;
        }
    }

    public function get_crypto_rate_from_params($crypto, $value, $satoshi) {
        $crypto_data = $this->getSupportedCurrencies();
        $crypto_obj = $crypto_data[$crypto];
        $multiplier = pow(10, $crypto_obj['decimals']);
        return number_format($value * $multiplier / $satoshi, 2, '.', '');
    }

    public function get_crypto_payment_uri($crypto, $address, $order_amount) {
        return $crypto['uri'] . ":" . $address . "?amount=" . $order_amount;
    }

    // -------------------------------------------------------------------------
    // Blockonomics Checkout widget support
    // -------------------------------------------------------------------------

    /**
     * Fetch the store_uid for this merchant's store.
     * Call once on plugin settings save; result is cached in wp_options.
     *
     * @param string $callback_url Optional: pass the store callback URL to
     *                             disambiguate when the merchant has multiple stores.
     * @return string|false store_uid on success, false on failure.
     */
    public function get_store_uid($callback_url = '') {
        $url = self::STORE_UID_URL;
        if ($callback_url) {
            $url = add_query_arg('callback', rawurlencode($callback_url), $url);
        }
        $response = $this->get($url, $this->api_key);
        if (is_wp_error($response)) return false;
        if (wp_remote_retrieve_response_code($response) !== 200) return false;
        $body = json_decode(wp_remote_retrieve_body($response));
        return isset($body->store_uid) ? $body->store_uid : false;
    }

    /**
     * Load the widget-based checkout template.
     * No server-side address generation — the JS widget handles that.
     *
     * @param int $order_id WooCommerce order ID.
     * @return string Rendered HTML.
     */
    public function load_widget_checkout($order_id) {
        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            return $this->load_blockonomics_template('error', $this->get_error_context('generic'));
        }

        $store_uid = get_option('blockonomics_store_uid', '');
        if (empty($store_uid)) {
            $fetched = $this->get_store_uid();
            if ($fetched) {
                update_option('blockonomics_store_uid', $fetched);
                $store_uid = $fetched;
            }
        }

        $context = array(
            'store_uid'        => $store_uid,
            'order_id'         => $order_id,
            'amount'           => (float) $wc_order->get_total(),
            'currency'         => get_woocommerce_currency(),
            // blockonomics_timeperiod is stored in minutes; widget expects seconds
            'timer'            => (int) get_option('blockonomics_timeperiod', 10) * 60,
            'finish_order_url' => $this->get_wc_order_received_url($order_id),
        );

        return $this->load_blockonomics_template('widget_checkout', $context);
    }

    /**
     * Handle a Blockonomics Checkout callback (POST JSON with wp_order_id).
     * Called when the new widget flow is used.
     *
     * @param array $body Decoded JSON body from the callback request.
     */
    public function process_checkout_callback($body) {
        $order_id = isset($body['wp_order_id'])
            ? sanitize_text_field($body['wp_order_id'])
            : null;

        $order_status = isset($body['order_status'])
            ? sanitize_text_field($body['order_status'])
            : '';

        if (!$order_id) {
            exit(__('Error: missing wp_order_id', 'blockonomics-bitcoin-payments'));
        }

        // Acknowledge pending_confirmation without marking paid
        if ($order_status !== 'paid') {
            exit('ok');
        }

        $crypto_details = isset($body['crypto_details']) && is_array($body['crypto_details'])
            ? $body['crypto_details']
            : array();

        $txid = isset($crypto_details['txid']) ? sanitize_text_field($crypto_details['txid']) : '';

        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            exit(__('Error: WooCommerce order not found', 'blockonomics-bitcoin-payments'));
        }

        $wc_order->payment_complete($txid);
        exit('ok');
    }

    public function get_checkout_context($order, $crypto){
        $context = array();
        $error_context = NULL;

        $context['order_id'] = isset($order['order_id']) ? $order['order_id'] : '';
        $cryptos = $this->getSupportedCurrencies();
        $context['crypto'] = $cryptos[$crypto];

        if (array_key_exists('error', $order)) {
            $this->log('get_checkout_context: error for order_id=' . ($order['order_id'] ?? 'unknown') . ' - "' . $order['error'] . '"', 'error');
            // Check if this is a currency error
            if (strpos($order['error'], 'Currency') === 0) {
                $error_context = $this->get_error_context('currency');
            } else if (strpos($order['error'], 'add an xpub') !== false) {
                // BCH wallet not configured
                $error_context = $this->get_error_context('bch_no_wallet');
            } else if (strpos($order['error'], 'Could not find matching xpub') !== false) {
                // BCH callback URL mismatch
                $error_context = $this->get_error_context('bch_callback_mismatch');
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
                $context['order_amount'] = $this->fix_displaying_small_values($context['crypto']['code'], $order['expected_satoshi']);
                $order_hash = $this->encrypt_hash($context['order_id']);
                if ($context['crypto']['code'] === 'usdt') {
                    // Include the finish_order_url for USDT payment redirect
                    $context['finish_order_url'] = $this->get_parameterized_wc_url('api',array('finish_order'=>$order_hash, 'crypto'=>  $context['crypto']['code']));
                    $context['testmode'] = (strpos($order['address'], '0xTestUSDTAddress') === 0) ? '1' : '0';
                }else {
                    // Payment URI is sent as part of context to provide initial Payment URI, this can be calculated using javascript
                    // but we also need the URI for NoJS Templates and it makes sense to generate it from a single location to avoid redundancy!
                    $context['payment_uri'] = $this->get_crypto_payment_uri($context['crypto'], $order['address'], $context['order_amount']);
                    $context['finish_order_url'] = $this->get_wc_order_received_url($context['order_id']);
                    $context['get_order_amount_url'] = $this->get_parameterized_wc_url('api', array('get_amount' => $order_hash, 'crypto' => $context['crypto']['code']));
                    $context['time_period'] = get_option('blockonomics_timeperiod', 10);
                }
                $context['crypto_rate_str'] = $this->get_crypto_rate_from_params($context['crypto']['code'], $order['expected_fiat'], $order['expected_satoshi']);
                //Using svg library qrcode.php to generate QR Code in NoJS mode
                // only generate QR if payment_uri exists (USDT doesn't use payment_uri)
                if($this->is_nojs_active() && isset($context['payment_uri'])){
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


    public function get_checkout_template($context, $crypto){
        if (array_key_exists('error_msg', $context)) {
            return 'error';
        } else {
            if ($crypto === 'usdt') {
                return 'web3_checkout';
            }
            return ($this->is_nojs_active()) ? 'nojs_checkout' : 'checkout';
        }
    }

    // Load the the checkout template in the page
    public function load_checkout_template($order_id, $crypto){
        // Create or update the order
        $order = $this->process_order($order_id, $crypto);
        
        // Load Checkout Context
        $context = $this->get_checkout_context($order, $crypto);
        
        // Get Template to Load
        $template_name = $this->get_checkout_template($context, $crypto);
        
        // Load the template
        return $this->load_blockonomics_template($template_name, $context);
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

    public function insert_order($order) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_payments';

        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id'         => $order['order_id'],
                'crypto'           => $order['crypto'],
                'address'          => $order['address'],
                'txid'             => isset($order['txid']) ? $order['txid'] : '',
                'payment_status'   => $order['payment_status'],
                'currency'         => $order['currency'],
                'expected_fiat'    => $order['expected_fiat'],
                'expected_satoshi' => $order['expected_satoshi'],
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%f', '%d')
        );

        if ($result === false) {
            $error_msg = $wpdb->last_error ?: 'Unknown database error';
            return array("error" => 'Failed to insert order into blockonomics_payments: ' . $error_msg);
        }

        return array("success" => true);
    }

    // Updates an order in blockonomics_payments table
    public function update_order($order){
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_payments';

        if (strtolower($order['crypto']) === 'usdt') {
          $where = array(
              'order_id' => $order['order_id'],
              'crypto' => $order['crypto'],
              'txid' => $order['txid']
          );
        } else{
          $where = array(
              'order_id' => $order['order_id'],
              'crypto' => $order['crypto'],
              'address' => $order['address']
          );
      }

      $wpdb->update($table_name, $order, $where);
    }

    // Check and update the crypto order or create a new order
    public function process_order($order_id, $crypto){
        $order = $this->get_order_by_id_and_crypto($order_id, $crypto);
        if ($order) {
            // Update the existing order info
            $this->log('process_order: existing order found for order_id=' . $order_id . ' crypto=' . $crypto . ' status=' . $order['payment_status']);
            $order = $this->calculate_order_params($order);
            if (array_key_exists("error", $order)) {
                $this->log('process_order: calculate_order_params error: ' . $order['error'], 'error');
                return $order;
            }
            $this->update_order($order);
        }else {
            // Create and add the new order to the database
            $this->log('process_order: creating new order for order_id=' . $order_id . ' crypto=' . $crypto);
            $order = $this->create_new_order($order_id, $crypto);
            if (array_key_exists("error", $order)) {
                // Some error in Address Generation from API, return the same array.
                $this->log('process_order: create_new_order error: ' . $order['error'], 'error');
                return $order;
            }
            $this->log('process_order: inserting new order, address=' . $order['address']);
            $result = $this->insert_order($order);
            if (array_key_exists("error", $result)) {
                // Some error in inserting order to DB, return the error.
                $this->log('process_order: insert_order error: ' . $result['error'], 'error');
                return $result;
            }
            $this->log('process_order: order created successfully, address=' . $order['address']);
            $this->record_address($order_id, $crypto, $order['address']);
            $this->record_expected_satoshi($order_id, $crypto, $order['expected_satoshi']);
        }
        return $order;
    }

    // Get the order info by id and crypto
    public function get_order_amount_info($order_id, $crypto){
        $order = $this->process_order($order_id, $crypto);
        if (array_key_exists('error', $order)) {
            header("Content-Type: application/json");
            exit(json_encode(array("error" => $order['error'])));
        }
        $order_amount = $this->fix_displaying_small_values($crypto, $order['expected_satoshi']);        
        $cryptos = $this->getSupportedCurrencies();
        $crypto_obj = $cryptos[$crypto];

        $response = array(
            "payment_uri" => $this->get_crypto_payment_uri($crypto_obj, $order['address'], $order_amount),
            "order_amount" => $order_amount,
            "crypto_rate_str" => $this->get_crypto_rate_from_params($crypto, $order['expected_fiat'], $order['expected_satoshi'])
        );
        header("Content-Type: application/json");
        exit(json_encode($response));
    }

    // Get the order info by crypto address
    public function get_order_by_address($address){
        global $wpdb;
        $order = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM ".$wpdb->prefix."blockonomics_payments WHERE address = %s ORDER BY order_id DESC LIMIT 1", array($address)),
            ARRAY_A
        );
        if($order){
            return $order;
        }
        $this->log('get_order_by_address: no order found for address=' . $address, 'error');
        exit(__("Error: Blockonomics order not found", 'blockonomics-bitcoin-payments'));
    }

    // Get the order info by crypto txid
    public function get_order_by_txid($txid){
        global $wpdb;
        $order = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM ".$wpdb->prefix."blockonomics_payments WHERE txid = %s", array($txid)),
            ARRAY_A
        );
        if($order){
            return $order;
        }
        $this->log('get_order_by_txid: no order found for txid=' . $txid, 'error');
        exit(__("Error: Blockonomics order not found", 'blockonomics-bitcoin-payments'));
    }

    // Check if the callback secret in the request matches
    public function check_callback_secret($secret){
        $callback_secret = get_option("blockonomics_callback_secret");
        if ($callback_secret  && $callback_secret == $secret) {
            return true;
        }
        $this->log('check_callback_secret: secret mismatch, expected=' . substr($callback_secret, 0, 6) . '... got=' . substr($secret, 0, 6) . '...', 'error');
        exit(__("Error: secret does not match", 'blockonomics-bitcoin-payments'));
    }

    public function save_transaction($txid, $wc_order){
        $txid_meta_key = 'blockonomics_payments_txids';
        $txid_meta_value = $wc_order->get_meta($txid_meta_key);
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
        $formatted_amount = $this->fix_displaying_small_values($crypto, $expected_satoshi);
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
            // set WC order to 'On Hold' upon 1st confirmation, this prevents woo from auto cancelling the order after 60 mins
            if ($wc_order->get_status() === 'pending') {
                $wc_order->update_status('on-hold', __('Crypto payment detected, awaiting confirmations.', 'blockonomics-bitcoin-payments'));
            }
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
        $underpayment_slack = ceil(floatval(get_option("blockonomics_underpayment_slack", 0))/100 * $order['expected_satoshi']);
        $is_order_underpaid = ($order['expected_satoshi'] - $underpayment_slack > $order['paid_satoshi'] && !empty($order['paid_satoshi'])) ? TRUE : FALSE;
        return $is_order_underpaid;
    }

    // Process the blockonomics callback
    public function process_callback($secret, $crypto, $address, $status, $value, $txid, $rbf){
        $this->log('Callback received: crypto=' . $crypto . ' address=' . $address . ' status=' . $status . ' value=' . $value . ' txid=' . $txid . ' rbf=' . ($rbf ? 'true' : 'false'));
        $this->check_callback_secret($secret);

        if (strtolower($crypto) == "usdt"){
            $order = $this->get_order_by_txid($txid);
        }else{
            $order = $this->get_order_by_address($address);
        }
        $this->log('Callback: matched order_id=' . $order['order_id'] . ' crypto=' . $order['crypto'] . ' current_status=' . $order['payment_status']);

        $wc_order = wc_get_order($order['order_id']);

        if (empty($wc_order)) {
            $this->log('process_callback: WooCommerce order not found for order_id=' . $order['order_id'], 'error');
            exit(__("Error: Woocommerce order not found", 'blockonomics-bitcoin-payments'));
        }

        $order['txid'] = $txid;

        if (!$rbf){
          // Unconfirmed RBF payments are easily cancelled should be ignored
          // https://insights.blockonomics.co/bitcoin-payments-can-now-easily-cancelled-a-step-forward-or-two-back/
          $order = $this->update_paid_amount($status, $value, $order, $wc_order);
          $this->save_transaction($order['txid'], $wc_order);
        }

        $this->update_order($order);
        $this->log('Callback: order updated, new_status=' . $order['payment_status'] . ' paid_satoshi=' . ($order['paid_satoshi'] ?? 'N/A'));
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

    /**
     * Check if a transaction ID exists in the blockonomics_payments table.
     *
     * @param string $txid The transaction ID to check.
     * @return bool True if exists, false otherwise.
     */
    function txid_exists($txid) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_payments';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE txid = %s",
                $txid
            )
        );

        return ($exists > 0);
    }

    /**
     * Add a TXID to an existing order row, only if the txid is currently empty or null.
     *
     * @param int    $order_id The WooCommerce order ID.
     * @param string $crypto   The crypto code (e.g., BTC, ETH).
     * @param string $txid     The transaction ID to store.
     * @return bool True if updated, false otherwise.
     */
    function update_order_txhash($order_id, $crypto, $txid) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blockonomics_payments';

        // Check if row exists and txid is empty
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT address FROM $table_name WHERE order_id = %d AND crypto = %s AND (txid IS NULL OR txid = '')",
                $order_id,
                $crypto
            )
        );

        if ($row) {
            // Update txid for the matching row
            $updated = $wpdb->update(
                $table_name,
                [ 'txid' => $txid ],
                [ 'order_id' => $order_id, 'crypto' => $crypto ],
                [ '%s' ],
                [ '%d', '%s' ]
            );
            return ($updated !== false);
        }

        // No matching row found or txid already exists
        return false;
    }

    /**
     * Display a formatted error message and exit.
     *
     * @param string $msg      Main error message.
     * @param int    $order_id WooCommerce order ID.
     * @param string $txhash   Transaction hash.
     * @param string $extra    Extra error details (optional).
     */
    private function display_order_error($msg, $order_id, $txhash, $extra = '') {
        echo esc_html($msg) . ' Please contact support with your order id and transaction hash.<br/>';
        echo 'Order ID: ' . esc_html($order_id) . '<br/>';
        echo 'Transaction Hash: ' . esc_html($txhash) . '<br/>';
        if ($extra) {
            echo 'Error: ' . esc_html($extra) . '<br/>';
        }
    }

    /**
     * Start monitoring the token txhash on Blockonomics.
     *
     * @param int    $order_id WooCommerce order ID.
     * @param string $crypto   Crypto code (e.g., 'usdt').
     * @param string $txhash   Transaction hash to monitor.
     */
    public function process_token_order($order_id, $crypto, $txhash) {
        $wc_order = wc_get_order($order_id);

        // Check if the txhash has already been used for another order
        if ($this->txid_exists($txhash)) {
            $msg = __('Transaction already exists!', 'blockonomics-bitcoin-payments');
            $wc_order->add_order_note("$msg<br/>txhash: $txhash");
            $this->display_order_error($msg, $order_id, $txhash);
            exit;
        }

        // Prepare callback URL and monitoring request
        $callback_secret = get_option("blockonomics_callback_secret");
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $api_url);
        $monitor_url = self::BASE_URL . '/api/monitor_tx';
        $post_data = array(
            'txhash' => $txhash,
            'crypto' => strtoupper($crypto),
            'match_callback' => $callback_url,
        );

        // Update order with txhash
        if (!$this->update_order_txhash($order_id, $crypto, $txhash)) {
            $msg = __('Error updating transaction!', 'blockonomics-bitcoin-payments');
            $wc_order->add_order_note("$msg<br/>txhash: $txhash");
            $this->display_order_error($msg, $order_id, $txhash);
            exit;
        }

        // Monitor transaction via Blockonomics API
        $response = $this->post($monitor_url, $this->api_key, wp_json_encode($post_data), 8);
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_message = '';
        if ($body) {
            $body_obj = json_decode($body);
            if (isset($body_obj->message)) {
                $response_message = $body_obj->message;
            }
        }

        if ($response_code != 200) {
            $msg = __('Error monitoring transaction!', 'blockonomics-bitcoin-payments');
            $wc_order->add_order_note("$msg<br/>txhash: $txhash<br/>Error: $response_message");
            $this->display_order_error($msg, $order_id, $txhash, $response_message);
            exit;
        }

        $this->save_transaction($txhash, $wc_order);
        $wc_order->add_order_note(__('Invoice will be automatically marked as paid on transaction confirm by the network. No further action is required.', 'blockonomics-bitcoin-payments'));
    }

}

