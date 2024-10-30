<?php
/**
 * Blockonomics Setup Wizard
 *
 * Handles the intial setup wizard functionality for Blockonomics plugin
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Blockonomics_Setup {
    private $api_key;
    private $blockonomics;

    public function __construct() {
        $this->blockonomics = new Blockonomics();
    }
    // TODO: Move this method to Blockonomics class
    private function get_callback_url() {
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        return add_query_arg('secret', $callback_secret, $api_url);
    }


    public function validate_api_key($api_key) {
        // Check if API key is valid and has wallets
        $wallets_url = 'https://www.blockonomics.co/api/v2/wallets';
        $response = wp_remote_get($wallets_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array('error' => 'Failed to check wallets: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 401) {
            return array('error' => 'API Key is incorrect');
        }

        if ($response_code === 200) {
            $wallets = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($wallets['data'])) {
                return array('error' => 'Please create a Wallet');
            }

            // Valid API key with wallets
            $this->api_key = $api_key;  // Set the API key here
            update_option('blockonomics_api_key', $api_key);  // Also save it to WordPress options
            return array('success' => true);
        }

        return array('error' => 'Could not verify API key');
    }

    public function check_store_setup() {
        // Make stores API call
        $stores_url = Blockonomics::BASE_URL . '/v2/stores?wallets=true';
        $response = wp_remote_get($stores_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array('error' => 'Failed to check stores');
        }

        $stores = json_decode(wp_remote_retrieve_body($response));
        if (empty($stores->data)) {
            return array('needs_store' => true);
        }

        // Store exists, save its name
        // TODO: Check callback url via match_callback parameter
        // logic here for partial match or exact match or no match
        update_option('blockonomics_store_name', $stores->data[0]->name);
        return array('success' => true);
    }

    public function create_store($store_name) {
        if (empty($store_name)) {
            return array('error' => 'Please enter your store name');
        }
    
        $api_key = get_option('blockonomics_api_key');

        $callback_url = $this->get_callback_url();

        $store_data = array(
            'name' => $store_name,
            'http_callback' => $callback_url
        );

        $api_url = Blockonomics::BASE_URL . '/v2/stores';

        $response = wp_remote_post(
            $api_url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($store_data)
            )
        );

        if (is_wp_error($response)) {
            return array('error' => 'Failed to create store: ' . $response->get_error_message());
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        // $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            update_option('blockonomics_store_name', $store_name);
            return array('success' => true);
        }

        return array('error' => 'Failed to create store');
    }

}