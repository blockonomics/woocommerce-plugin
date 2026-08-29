<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UCP Store API Handler.
 * Stateless: cart data is encoded in the checkout token.
 * Orders are created via WooCommerce PHP API when payment is requested.
 */
class UCP_Store_API
{
    /**
     * Create a checkout session from a list of items.
     */
    public function create_checkout($items)
    {
        $line_items = array();
        $subtotal = 0.0;

        foreach ($items as $item) {
            $product = wc_get_product(absint($item['id']));
            if (!$product || !$product->is_purchasable()) {
                continue;
            }
            $qty = max(1, absint($item['quantity'] ?? 1));
            $price = (float) $product->get_price();
            $line_total = $price * $qty;
            $subtotal += $line_total;

            $line_items[] = array(
                'id' => (int) $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $qty,
                'price' => $price,
                'total' => $line_total,
            );
        }

        $token_data = array(
            'items' => $line_items,
            'address' => array(),
            'coupons' => array(),
        );

        return $this->build_response(base64_encode(json_encode($token_data)), $token_data, $subtotal);
    }

    /**
     * Update an existing checkout with address or discount codes.
     */
    public function update_checkout($checkout_id, $updates)
    {
        $token_data = $this->decode_token($checkout_id);

        if (!empty($updates['shipping_address'])) {
            $token_data['address'] = $updates['shipping_address'];
        }

        if (!empty($updates['discounts']['codes'])) {
            $token_data['coupons'] = array_merge(
                $token_data['coupons'] ?? array(),
                $updates['discounts']['codes']
            );
        }

        $subtotal = array_sum(array_column($token_data['items'], 'total'));
        return $this->build_response(base64_encode(json_encode($token_data)), $token_data, $subtotal);
    }

    /**
     * Finalize checkout — create a WC order and return a payment URL for browser checkout.
     */
    public function complete_checkout($checkout_id)
    {
        $order = $this->create_order_from_token($checkout_id, 'bacs');

        return array(
            'status' => 'requires_escalation',
            'continue_url' => $order->get_checkout_payment_url(),
            'messages' => array(
                array(
                    'type' => 'info',
                    'code' => 'ESCALATION_REQUIRED',
                    'content' => 'Payment requires browser checkout. Please follow the link to complete payment.',
                    'severity' => 'escalation',
                ),
            ),
        );
    }

    /**
     * Finalize checkout and return a Bitcoin address + amount.
     */
    public function pay_with_bitcoin($checkout_id)
    {
        $order = $this->create_order_from_token($checkout_id, 'blockonomics');
        $order_id = $order->get_id();

        $blockonomics_php = dirname(dirname(__FILE__)) . '/php/Blockonomics.php';
        if (!file_exists($blockonomics_php)) {
            throw new Exception('Blockonomics plugin class not found');
        }
        include_once $blockonomics_php;

        $blockonomics = new Blockonomics();

        // Diagnostic check: call new_address first to surface the HTTP status code.
        $addr_response = $blockonomics->new_address('btc');
        if ($addr_response->response_code != 200) {
            $order->update_status('failed', 'Blockonomics address error');
            $order->save();
            $code = !empty($addr_response->response_code) ? $addr_response->response_code : 'network_error';
            $msg  = !empty($addr_response->response_message) ? $addr_response->response_message : '(no message)';
            throw new Exception('Blockonomics new_address failed — HTTP ' . $code . ': ' . $msg);
        }

        $order_data = $blockonomics->create_new_order($order_id, 'btc');

        if (isset($order_data['error'])) {
            $order->update_status('failed', 'Blockonomics order error');
            $order->save();
            throw new Exception('Blockonomics: ' . (!empty($order_data['error']) ? $order_data['error'] : 'Unknown error'));
        }

        $blockonomics->insert_order($order_data);

        $address    = $order_data['address'];
        $satoshi    = $order_data['expected_satoshi'];
        $btc_amount = $satoshi / 1e8;

        return array(
            'order_id'       => $order_id,
            'btc_address'    => $address,
            'btc_amount'     => $btc_amount,
            'satoshi_amount' => $satoshi,
            'fiat_amount'    => $order_data['expected_fiat'],
            'currency'       => $order_data['currency'] ?? get_woocommerce_currency(),
            'payment_uri'    => 'bitcoin:' . $address . '?amount=' . $btc_amount,
            'pay_url'        => $order->get_checkout_payment_url(),
            'track_url'      => $order->get_checkout_order_received_url(),
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Decode a base64-JSON checkout token into an array.
     */
    private function decode_token($checkout_id)
    {
        $json = base64_decode($checkout_id, true);
        if ($json === false) {
            throw new Exception('Invalid checkout ID');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['items'])) {
            throw new Exception('Invalid or empty checkout token');
        }

        return $data;
    }

    /**
     * Create a WooCommerce order from a checkout token.
     */
    private function create_order_from_token($checkout_id, $payment_method)
    {
        $token_data = $this->decode_token($checkout_id);

        $order = wc_create_order();
        if (is_wp_error($order)) {
            throw new Exception('Failed to create order: ' . $order->get_error_message());
        }

        foreach ($token_data['items'] as $item) {
            $product = wc_get_product(absint($item['id']));
            if ($product) {
                $order->add_product($product, $item['quantity']);
            }
        }

        $addr = !empty($token_data['address']) ? $token_data['address'] : array();
        $billing = array(
            'first_name' => $addr['first_name'] ?? '',
            'last_name'  => $addr['last_name'] ?? '',
            'address_1'  => $addr['address_line1'] ?? '',
            'city'       => $addr['city'] ?? '',
            'state'      => $addr['region'] ?? '',
            'postcode'   => $addr['postal_code'] ?? '',
            'country'    => $addr['country'] ?? get_option('woocommerce_default_country', 'US'),
            'email'      => $addr['email'] ?? 'guest@example.com',
        );
        $order->set_address($billing, 'billing');
        $order->set_address($billing, 'shipping');

        foreach ($token_data['coupons'] ?? array() as $code) {
            $order->apply_coupon($code);
        }

        $order->calculate_totals();
        $order->set_payment_method($payment_method);
        $order->set_payment_method_title($payment_method === 'blockonomics' ? 'Bitcoin' : 'Bank Transfer');
        $order->update_status('pending', 'Order created via UCP MCP');
        $order->save();

        return $order;
    }

    /**
     * Build the standard checkout response array.
     */
    private function build_response($checkout_id, $token_data, $subtotal)
    {
        return array(
            'id'             => $checkout_id,
            'status'         => 'cart',
            'currency'       => get_woocommerce_currency(),
            'total'          => round($subtotal, 2),
            'subtotal'       => round($subtotal, 2),
            'tax_total'      => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'applied_coupons' => $token_data['coupons'] ?? array(),
            'line_items'     => array_map(function ($item) {
                return array(
                    'id'       => (string) $item['id'],
                    'name'     => $item['name'],
                    'quantity' => $item['quantity'],
                    'total'    => $item['total'],
                );
            }, $token_data['items']),
        );
    }
}
