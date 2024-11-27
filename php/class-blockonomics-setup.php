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
    private function get_callback_url() {
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        return add_query_arg('secret', $callback_secret, $api_url);
    }

    // Check if API key is valid and has wallets
    public function validate_api_key($api_key) {
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
            // Store the wallet ID for step 2 of store creation
            if (!empty($wallets['data'][0]['id'])) {
                update_option('blockonomics_temp_wallet_id', $wallets['data'][0]['id']);
            }

            $this->api_key = $api_key;
            update_option('blockonomics_api_key', $api_key);  // Also save it to WordPress options
            return array('success' => true);
        }

        return array('error' => 'Could not verify API key');
    }

    public function check_store_setup() {
        $api_key = get_option('blockonomics_api_key');
        $stores_url = Blockonomics::BASE_URL . '/api/v2/stores?wallets=true';
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

        $wordpress_callback_url = $this->get_callback_url();
        $base_url = preg_replace('/https?:\/\//', '', WC()->api_request_url('WC_Gateway_Blockonomics'));

        foreach ($stores->data as $store) {
            if ($store->http_callback === $wordpress_callback_url) {
                update_option('blockonomics_store_name', $store->name);
                return array('success' => true);
            }

            // Check for partial match - only secret or protocol differs
            if (!empty($store->http_callback)) {
                $store_base_url = preg_replace('/https?:\/\//', '', $store->http_callback);
                if (strpos($store_base_url, $base_url) === 0) {
                    $response = wp_remote_post(
                        Blockonomics::BASE_URL . '/api/v2/stores/' . $partial_match_store->id,
                        array(
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $this->api_key,
                                'Content-Type' => 'application/json'
                            ),
                            'body' => wp_json_encode(array(
                                'name' => $partial_match_store->name,
                                'http_callback' => $wordpress_callback_url
                            ))
                        )
                    );

                    if (wp_remote_retrieve_response_code($response) === 200) {
                        update_option('blockonomics_store_name', $partial_match_store->name);
                        return array('success' => true);
                    }
                }
            }
        }
        // No matching store found - need to create a new one
        return array('needs_store' => true);
    }

    public function create_store($store_name) {
        if (empty($store_name)) {
            return array('error' => 'Please enter your store name');
        }
        $api_key = get_option('blockonomics_api_key');
        $wallet_id = get_option('blockonomics_temp_wallet_id');
        $callback_url = $this->get_callback_url();
        // Step 1: Create store
        $store_data = array(
            'name' => $store_name,
            'http_callback' => $callback_url
        );
        $response = wp_remote_post(
            Blockonomics::BASE_URL . '/api/v2/stores',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode($store_data)
            )
        );
        if (is_wp_error($response)) {
            delete_option('blockonomics_temp_wallet_id');
            return array('error' => 'Failed to create store: ' . $response->get_error_message());
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code === 200) {
            $response_data = json_decode($response_body, true);
            if (!empty($response_data['data']['id']) && $wallet_id) {
                $store_id = $response_data['data']['id'];

                // Step 2: Attach wallet to store
                $wallet_attach_response = wp_remote_post(
                    Blockonomics::BASE_URL . '/api/v2/stores/' . $store_id . '/wallets',
                    array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $api_key,
                            'Content-Type' => 'application/json'
                        ),
                        'body' => wp_json_encode(array(
                            'wallet_id' => (int)$wallet_id
                        ))
                    )
                );

                // Step 3: Update enabled cryptos based on attached wallet
                $wallet_attach_data = json_decode(wp_remote_retrieve_body($wallet_attach_response), true);
                if (!empty($wallet_attach_data['data']['wallets'][0]['crypto'])) {
                    $crypto = strtolower($wallet_attach_data['data']['wallets'][0]['crypto']);
                    update_option('blockonomics_enabled_cryptos', $crypto);
                }
            }
            update_option('blockonomics_store_name', $store_name);
            delete_option('blockonomics_temp_wallet_id');
            return array('success' => true);
        }
        return array('error' => 'Failed to create store');
    }
}