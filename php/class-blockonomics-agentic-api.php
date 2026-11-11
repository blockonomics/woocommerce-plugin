<?php

/**
 * blockonomics/v1 routes used after a Store API checkout: payment details/status
 * and USDT txhash submission. The order hash acts as the credential, same as the checkout page URL.
 */
class Blockonomics_Agentic_API {

    const NAMESPACE_V1 = 'blockonomics/v1';
    const HASH_PATTERN = '(?P<hash>[a-fA-F0-9]+)';

    public static function register_routes() {
        $blockonomics = new Blockonomics;
        if (!$blockonomics->is_agentic_checkout_active()) {
            return;
        }

        register_rest_route(self::NAMESPACE_V1, '/orders/' . self::HASH_PATTERN, array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_order'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'crypto' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ));

        register_rest_route(self::NAMESPACE_V1, '/orders/' . self::HASH_PATTERN . '/txhash', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'submit_txhash'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'txhash' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route(self::NAMESPACE_V1, '/agent', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array('Blockonomics_Agent_Discovery', 'serve_instructions'),
            'permission_callback' => '__return_true',
        ));
        add_filter('rest_pre_serve_request', array('Blockonomics_Agent_Discovery', 'serve_markdown'), 10, 3);
    }

    public static function get_order(WP_REST_Request $request) {
        $blockonomics = new Blockonomics;
        $order_id = self::resolve_order_id($blockonomics, $request['hash']);
        if (is_wp_error($order_id)) {
            return $order_id;
        }

        $details = $blockonomics->get_agentic_payment_details($order_id, $request['crypto']);
        if (array_key_exists('error', $details)) {
            return new WP_Error('blockonomics_payment_error', $details['error'], array('status' => 400));
        }
        return rest_ensure_response($details);
    }

    public static function submit_txhash(WP_REST_Request $request) {
        $blockonomics = new Blockonomics;
        $order_id = self::resolve_order_id($blockonomics, $request['hash']);
        if (is_wp_error($order_id)) {
            return $order_id;
        }

        $result = $blockonomics->agentic_submit_txhash($order_id, $request['txhash']);
        if (array_key_exists('error', $result)) {
            return new WP_Error('blockonomics_txhash_error', $result['error'], array('status' => 400));
        }
        return rest_ensure_response(array('message' => $result['success']));
    }

    private static function resolve_order_id($blockonomics, $hash) {
        $order_id = $blockonomics->is_valid_hash($hash) ? $blockonomics->decrypt_order_id($hash) : false;
        if ($order_id === false) {
            return new WP_Error('blockonomics_order_not_found', __('Order not found', 'blockonomics-bitcoin-payments'), array('status' => 404));
        }
        return $order_id;
    }
}
