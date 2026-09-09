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
    private const STORE_CRYPTOS = array('btc', 'usdt');

    private function log_http_error($context, $error) {
        if (!function_exists('wc_get_logger') || !is_wp_error($error)) {
            return;
        }

        wc_get_logger()->error(
            $context . ': ' . $error->get_error_message(),
            array('source' => 'blockonomics')
        );
    }

    private function connection_error($context, $error) {
        $this->log_http_error($context, $error);

        return array(
            'error' => __('Could not reach Blockonomics. Please check your connection and try again.', 'blockonomics-bitcoin-payments')
        );
    }

    private function response_error($response, $context, $fallback_message, $use_server_message = false) {
        if (is_wp_error($response)) {
            return $this->connection_error($context, $response);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            return null;
        }

        if ($response_code === 401) {
            return array('error' => __('API Key is incorrect', 'blockonomics-bitcoin-payments'));
        }

        if ($response_code === 429 || ($response_code >= 500 && $response_code < 600)) {
            return array(
                'error' => __('Blockonomics is temporarily unavailable. Please try again in a few minutes.', 'blockonomics-bitcoin-payments')
            );
        }

        if ($use_server_message) {
            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($response_data)) {
                $server_message = $response_data['message'] ?? $response_data['error'] ?? '';
                if (is_string($server_message) && $server_message !== '') {
                    return array('error' => sanitize_text_field($server_message));
                }
            }
        }

        return array('error' => $fallback_message);
    }

    private function fetch_wallet_choices($api_key) {
        $response = wp_remote_get(
            Blockonomics::BASE_URL . '/api/v2/wallets',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 8
            )
        );

        $error = $this->response_error(
            $response,
            'Setup wizard wallet lookup failed',
            __('Could not verify API key', 'blockonomics-bitcoin-payments')
        );
        if ($error) {
            return $error;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($response_data) || !isset($response_data['data']) || !is_array($response_data['data'])) {
            return array('error' => __('Could not verify API key', 'blockonomics-bitcoin-payments'));
        }

        $wallet_ids = array();
        foreach ($response_data['data'] as $wallet) {
            if (
                !is_array($wallet) ||
                empty($wallet['id']) ||
                !is_numeric($wallet['id']) ||
                empty($wallet['crypto']) ||
                !is_string($wallet['crypto'])
            ) {
                return array('error' => __('Could not verify API key', 'blockonomics-bitcoin-payments'));
            }

            $crypto = strtolower($wallet['crypto']);
            if (in_array($crypto, self::STORE_CRYPTOS, true) && !isset($wallet_ids[$crypto])) {
                // A store accepts one wallet per crypto; preserve the first API choice.
                $wallet_ids[$crypto] = (int) $wallet['id'];
            }
        }

        if (empty($wallet_ids)) {
            return array(
                'error' => __('Please create a Wallet', 'blockonomics-bitcoin-payments'),
                'error_code' => 'wallet_required'
            );
        }

        return array('wallet_ids' => $wallet_ids);
    }

    private function get_wallet_choices($api_key) {
        $wallet_ids = get_option('blockonomics_temp_wallet_ids', array());
        if (is_array($wallet_ids)) {
            $valid_wallet_ids = array();
            foreach (self::STORE_CRYPTOS as $crypto) {
                if (isset($wallet_ids[$crypto]) && is_numeric($wallet_ids[$crypto]) && (int) $wallet_ids[$crypto] > 0) {
                    $valid_wallet_ids[$crypto] = (int) $wallet_ids[$crypto];
                }
            }

            if (!empty($valid_wallet_ids)) {
                return array('wallet_ids' => $valid_wallet_ids);
            }
        }

        $wallet_result = $this->fetch_wallet_choices($api_key);
        if (isset($wallet_result['error'])) {
            return $wallet_result;
        }

        update_option('blockonomics_temp_wallet_ids', $wallet_result['wallet_ids']);
        delete_option('blockonomics_temp_wallet_id');

        return $wallet_result;
    }

    private function fetch_stores_with_wallets($api_key) {
        // Wallet details are required to select and finalize a configured store.
        $stores_url = add_query_arg(
            'wallets',
            'true',
            Blockonomics::BASE_URL . '/api/v2/stores'
        );

        $response = wp_remote_get(
            $stores_url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 8
            )
        );

        $error = $this->response_error(
            $response,
            'Setup wizard store lookup failed',
            __('Failed to check stores', 'blockonomics-bitcoin-payments')
        );
        if ($error) {
            return $error;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response));
        if (!is_object($response_data) || !isset($response_data->data) || !is_array($response_data->data)) {
            return array(
                'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
            );
        }

        foreach ($response_data->data as $store) {
            if (
                !is_object($store) ||
                !isset($store->id) ||
                !is_numeric($store->id) ||
                !isset($store->name) ||
                !is_string($store->name) ||
                !isset($store->http_callback) ||
                !is_string($store->http_callback) ||
                !isset($store->wallets) ||
                !is_array($store->wallets)
            ) {
                return array(
                    'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
                );
            }

            foreach ($store->wallets as $wallet) {
                if (
                    !is_object($wallet) ||
                    empty($wallet->id) ||
                    !is_numeric($wallet->id) ||
                    empty($wallet->crypto) ||
                    !is_string($wallet->crypto)
                ) {
                    return array(
                        'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
                    );
                }
            }
        }

        return array('stores' => $response_data->data);
    }

    private function get_callback_url() {
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        return add_query_arg('secret', $callback_secret, $api_url);
    }

    // Check if API key is valid and has wallets
    public function validate_api_key($api_key) {
        $wallet_result = $this->fetch_wallet_choices($api_key);
        if (isset($wallet_result['error'])) {
            return $wallet_result;
        }

        update_option('blockonomics_temp_wallet_ids', $wallet_result['wallet_ids']);
        delete_option('blockonomics_temp_wallet_id');

        update_option('blockonomics_api_key', $api_key);

        return array('success' => true);
    }

    public function check_store_setup() {
        $api_key = get_option('blockonomics_api_key');
        $stores_result = $this->fetch_stores_with_wallets($api_key);
        if (isset($stores_result['error'])) {
            return $stores_result;
        }

        $best_store = Blockonomics::findMatchingStore($stores_result['stores']);
        if (!$best_store) {
            // No matching store found - need to create a new one
            return array('needs_store' => true);
        }

        // repair http -> https drift only (never downgrade to http, never rewrite
        // for path differences — WPML prefixes, subfolder installs)
        $wordpress_callback_url = $this->get_callback_url();
        if (strpos($best_store->http_callback, 'http://') === 0
            && strpos($wordpress_callback_url, 'https://') === 0
            && substr($best_store->http_callback, 7) === substr($wordpress_callback_url, 8)) {
            $update_response = wp_remote_post(
                Blockonomics::BASE_URL . '/api/v2/stores/' . $best_store->id,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode(array(
                        'name' => $best_store->name,
                        'http_callback' => $wordpress_callback_url
                    )),
                    'timeout' => 8
                )
            );

            $update_error = $this->response_error(
                $update_response,
                'Setup wizard callback update failed',
                __("Could not update your store's callback URL. Please try again.", 'blockonomics-bitcoin-payments')
            );
            if ($update_error) {
                return $update_error;
            }
        }

        return $this->finalize_store_match($best_store, $api_key);
    }

    /*
     * @param object $store The matched store object (with wallets property from ?wallets=true)
     * @param string $api_key The API key for Blockonomics
     * @return array Result array with success or error
     */
    private function finalize_store_match($store, $api_key) {
        $wallet_result = $this->get_wallet_choices($api_key);
        if (isset($wallet_result['error'])) {
            return $wallet_result;
        }

        $attached_cryptos = array();
        if (!empty($store->wallets) && is_array($store->wallets)) {
            foreach ($store->wallets as $wallet) {
                if (is_object($wallet) && !empty($wallet->crypto)) {
                    $attached_cryptos[strtolower($wallet->crypto)] = true;
                }
            }
        }

        foreach ($wallet_result['wallet_ids'] as $crypto => $wallet_id) {
            // Preserve the merchant's existing wallet choice for this crypto.
            if (isset($attached_cryptos[$crypto])) {
                continue;
            }

            $wallet_attach_response = wp_remote_post(
                Blockonomics::BASE_URL . '/api/v2/stores/' . $store->id . '/wallets',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode(array(
                        'wallet_id' => $wallet_id
                    )),
                    'timeout' => 8
                )
            );

            $attach_error = $this->response_error(
                $wallet_attach_response,
                'Setup wizard wallet attachment failed',
                __('Could not attach your wallet to the store. Please try again.', 'blockonomics-bitcoin-payments')
            );
            if ($attach_error) {
                return $attach_error;
            }
        }

        $store_wallets = $this->fetch_store_wallets($store->id, $api_key);
        if (isset($store_wallets['error'])) {
            return $store_wallets;
        }

        $store->wallets = $store_wallets['wallets'];
        $enabled_cryptos = $this->extract_enabled_cryptos($store);
        $missing_cryptos = array_diff(array_keys($wallet_result['wallet_ids']), $enabled_cryptos);
        if (empty($enabled_cryptos) || !empty($missing_cryptos)) {
            return array(
                'error' => __('Could not verify wallets attached to your store. Please try again.', 'blockonomics-bitcoin-payments')
            );
        }

        update_option('blockonomics_enabled_cryptos', implode(',', $enabled_cryptos));
        update_option('blockonomics_store_name', $store->name);
        $this->enable_gateway();

        delete_option('blockonomics_temp_wallet_ids');
        delete_option('blockonomics_temp_wallet_id');

        return array('success' => true);
    }

    private function fetch_store_wallets($store_id, $api_key) {
        $response = wp_remote_get(
            Blockonomics::BASE_URL . '/api/v2/stores/' . $store_id . '/wallets',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 8
            )
        );

        $error = $this->response_error(
            $response,
            'Setup wizard store wallet refresh failed',
            __('Could not verify wallets attached to your store. Please try again.', 'blockonomics-bitcoin-payments')
        );
        if ($error) {
            return $error;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response));
        if (!is_object($response_data) || !property_exists($response_data, 'data')) {
            return array(
                'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
            );
        }

        if (is_object($response_data->data) && isset($response_data->data->wallets)) {
            // Current live API shape: { data: <store object with wallets[]> }.
            $wallets = $response_data->data->wallets;
        } elseif (is_array($response_data->data)) {
            // Public API reference shape: { data: <wallets[]> }.
            $wallets = $response_data->data;
        } else {
            return array(
                'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
            );
        }

        if (!is_array($wallets)) {
            return array(
                'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
            );
        }

        foreach ($wallets as $wallet) {
            if (
                !is_object($wallet) ||
                empty($wallet->id) ||
                !is_numeric($wallet->id) ||
                empty($wallet->crypto) ||
                !is_string($wallet->crypto)
            ) {
                return array(
                    'error' => __('Invalid response was received. Please retry.', 'blockonomics-bitcoin-payments')
                );
            }
        }

        return array('wallets' => $wallets);
    }

    private function enable_gateway() {
        $settings = get_option('woocommerce_blockonomics_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $settings['enabled'] = 'yes';
        update_option('woocommerce_blockonomics_settings', $settings);
    }

    /*
     * Extract enabled crypto currencies from store's wallets
     * @param object $store Store object with wallets property
     * @return array Array of lowercase crypto codes (e.g., ['btc', 'usdt'])
     */
    private function extract_enabled_cryptos($store) {
        $enabled_cryptos = array();
        if (!empty($store->wallets)) {
            foreach ($store->wallets as $wallet) {
                if (isset($wallet->crypto)) {
                    $crypto = strtolower($wallet->crypto);
                    if (
                        in_array($crypto, self::STORE_CRYPTOS, true) &&
                        !in_array($crypto, $enabled_cryptos, true)
                    ) {
                        $enabled_cryptos[] = $crypto;
                    }
                }
            }
        }
        return $enabled_cryptos;
    }

    public function create_store($store_name) {
        if (empty($store_name)) {
            return array('error' => 'Please enter your store name');
        }
        $api_key = get_option('blockonomics_api_key');
        $callback_url = $this->get_callback_url();
        $store_lookup = $this->find_store_by_callback($api_key);
        if (isset($store_lookup['error'])) {
            return $store_lookup;
        }

        $existing_store = $store_lookup['store'];
        if ($existing_store !== null) {
            // store already exists - use it instead of creating duplicate
            // update store name if user provided a different one
            if ($store_name !== $existing_store->name) {
                $name_update = $this->update_store_name($api_key, $existing_store->id, $store_name);
                if (isset($name_update['error'])) {
                    return $name_update;
                }
                $existing_store->name = $store_name;
            }
            return $this->finalize_store_match($existing_store, $api_key);
        }

        // Step 1: Create store - when no existing store is found
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
                'body' => wp_json_encode($store_data),
                'timeout' => 8
            )
        );

        $create_error = $this->response_error(
            $response,
            'Setup wizard store creation failed',
            __('Failed to create store', 'blockonomics-bitcoin-payments'),
            true
        );
        if ($create_error) {
            return $create_error;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response));
        if (
            !is_object($response_data) ||
            !is_object($response_data->data ?? null) ||
            empty($response_data->data->id) ||
            !is_numeric($response_data->data->id)
        ) {
            return array('error' => __('Failed to create store', 'blockonomics-bitcoin-payments'));
        }

        $created_store = $response_data->data;
        $created_store->name = $store_name;
        $created_store->wallets = array();

        return $this->finalize_store_match($created_store, $api_key);
    }

    /* Find this install's store by callback secret
     * @param string $api_key The API key for Blockonomics
     * @return array Result containing a store object/null, or an error
     */
    private function find_store_by_callback($api_key) {
        $stores_result = $this->fetch_stores_with_wallets($api_key);
        if (isset($stores_result['error'])) {
            return $stores_result;
        }

        return array('store' => Blockonomics::findMatchingStore($stores_result['stores']));
    }

    /*
     * Update a store's name. Its used when user provides a different name for an existing store
     * @param string $api_key The API key for Blockonomics
     * @param int $store_id The store ID to update
     * @param string $new_name The new name for the store
     * @return array Result containing success or an error
     */
    private function update_store_name($api_key, $store_id, $new_name) {
        $response = wp_remote_post(
            Blockonomics::BASE_URL . '/api/v2/stores/' . $store_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => wp_json_encode(array(
                    'name' => $new_name
                )),
                'timeout' => 8
            )
        );

        $update_error = $this->response_error(
            $response,
            'Setup wizard store name update failed',
            __('Could not update your store name. Please try again.', 'blockonomics-bitcoin-payments')
        );
        if ($update_error) {
            return $update_error;
        }

        return array('success' => true);
    }
}
