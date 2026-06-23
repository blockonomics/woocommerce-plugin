<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UCP Cart Manager.
 * Handles stateless cart management via tokens (WC_Session).
 */
class UCP_Cart_Manager
{
    private $auth_token = null;

    /**
     * Start/Resume a session from a token.
     *
     * @param string|null $token Existing cart token/Customer ID.
     * @return string The valid cart token.
     */
    public function start_session($token = null)
    {
        if (empty($token)) {
            $token = $this->generate_token();
        }

        $this->auth_token = $token;

        // Force WooCommerce to use this session
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        // Trick: We hook into the cookie setting to override the ID
        // Since we are CLI/API, we manually set the customer ID in the session handler context if possible.
        // A robust way in "Headless" mode:
        // 1. Set the cookie global so WC thinks it exists.
        // 2. Init the session.

        $_COOKIE['wp_woocommerce_session_' . COOKIEHASH] = $token;
        $_COOKIE['woocommerce_items_in_cart'] = 1; // Fake to ensure it looks

        // This effectively loads the session from the DB matching our 'cookie' ($token)
        WC()->session->set_customer_session_cookie(true);

        // Ensure cart is loaded
        if (!WC()->cart) {
            wc_load_cart();
        }

        WC()->cart->get_cart_from_session();

        return $token;
    }

    /**
     * Clear current cart and add items.
     */
    public function set_items($items)
    {
        WC()->cart->empty_cart();

        foreach ($items as $item) {
            $product_id = isset($item['id']) ? absint($item['id']) : 0;
            $quantity = isset($item['quantity']) ? absint($item['quantity']) : 1;

            if ($product_id) {
                WC()->cart->add_to_cart($product_id, $quantity);
            }
        }

        // Explicitly save the session
        if (WC()->session) {
            WC()->session->save_data();
        }
    }

    /**
     * Apply coupons.
     */
    public function set_coupons($codes)
    {
        // Remove all first
        foreach (WC()->cart->get_applied_coupons() as $code) {
            WC()->cart->remove_coupon($code);
        }

        foreach ($codes as $code) {
            WC()->cart->apply_coupon($code);
        }

        if (WC()->session) {
            WC()->session->save_data();
        }
    }

    /**
     * Convert the active cart to a real Order.
     * 
     * @return WC_Order
     */
    public function checkout()
    {
        if (WC()->cart->is_empty()) {
            throw new Exception("Cart is empty. Session may have expired or not persisted.");
        }

        // Ensure billing address matches shipping if not already set
        $customer = WC()->customer;
        if (empty($customer->get_billing_email())) {
            $customer->set_billing_email('guest@example.com'); // Placeholder for guest checkout
        }
        if (empty($customer->get_billing_first_name())) {
            $customer->set_billing_first_name($customer->get_shipping_first_name());
        }
        if (empty($customer->get_billing_last_name())) {
            $customer->set_billing_last_name($customer->get_shipping_last_name());
        }
        if (empty($customer->get_billing_address_1())) {
            $customer->set_billing_address_1($customer->get_shipping_address_1());
        }
        if (empty($customer->get_billing_city())) {
            $customer->set_billing_city($customer->get_shipping_city());
        }
        if (empty($customer->get_billing_state())) {
            $customer->set_billing_state($customer->get_shipping_state());
        }
        if (empty($customer->get_billing_postcode())) {
            $customer->set_billing_postcode($customer->get_shipping_postcode());
        }
        if (empty($customer->get_billing_country())) {
            $customer->set_billing_country($customer->get_shipping_country());
        }

        // Create order manually
        $order = wc_create_order();

        if (is_wp_error($order)) {
            throw new Exception("Failed to create order: " . $order->get_error_message());
        }

        // Add cart items to order
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            $product = $values['data'];
            $order->add_product($product, $values['quantity']);
        }

        // Set addresses
        $order->set_address(array(
            'first_name' => $customer->get_billing_first_name(),
            'last_name' => $customer->get_billing_last_name(),
            'email' => $customer->get_billing_email(),
            'address_1' => $customer->get_billing_address_1(),
            'city' => $customer->get_billing_city(),
            'state' => $customer->get_billing_state(),
            'postcode' => $customer->get_billing_postcode(),
            'country' => $customer->get_billing_country(),
        ), 'billing');

        $order->set_address(array(
            'first_name' => $customer->get_shipping_first_name(),
            'last_name' => $customer->get_shipping_last_name(),
            'address_1' => $customer->get_shipping_address_1(),
            'city' => $customer->get_shipping_city(),
            'state' => $customer->get_shipping_state(),
            'postcode' => $customer->get_shipping_postcode(),
            'country' => $customer->get_shipping_country(),
        ), 'shipping');

        // Apply coupons
        foreach (WC()->cart->get_applied_coupons() as $code) {
            $order->apply_coupon($code);
        }

        // Calculate totals
        $order->calculate_totals();

        // Set order status to pending payment
        $order->update_status('pending', 'Order created via UCP API');

        // Save the order
        $order->save();

        if (!$order->get_id()) {
            throw new Exception("Failed to save order.");
        }

        return $order;
    }

    /**
     * Get response data from current cart.
     */
    /**
     * Set shipping address to calculate taxes and shipping.
     */
    public function set_shipping_address($address)
    {
        $customer = WC()->customer;

        if (isset($address['first_name']))
            $customer->set_shipping_first_name($address['first_name']);
        if (isset($address['last_name']))
            $customer->set_shipping_last_name($address['last_name']);
        if (isset($address['address_line1']))
            $customer->set_shipping_address_1($address['address_line1']);
        if (isset($address['address_line2']))
            $customer->set_shipping_address_2($address['address_line2']);
        if (isset($address['city']))
            $customer->set_shipping_city($address['city']);
        if (isset($address['region']))
            $customer->set_shipping_state($address['region']);
        if (isset($address['postal_code']))
            $customer->set_shipping_postcode($address['postal_code']);
        if (isset($address['country']))
            $customer->set_shipping_country($address['country']);

        // Also set billing to match if not provided (simplification for UCP)
        if (isset($address['country']))
            $customer->set_billing_country($address['country']);
        if (isset($address['postal_code']))
            $customer->set_billing_postcode($address['postal_code']);

        $customer->save();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        if (WC()->session) {
            WC()->session->save_data();
        }
    }

    /**
     * Get response data from current cart.
     */
    public function get_cart_response()
    {
        WC()->cart->calculate_totals();

        // Ensure shipping packages are calculated if address is present
        if (WC()->customer->get_shipping_country()) {
            WC()->cart->calculate_shipping();
        }

        return array(
            'id' => base64_encode($this->auth_token),
            'status' => 'cart',
            'currency' => get_woocommerce_currency(),
            'total' => (float) strip_tags(html_entity_decode(WC()->cart->get_total())),
            'subtotal' => (float) WC()->cart->get_subtotal(),
            'tax_total' => (float) WC()->cart->get_total_tax(),
            'shipping_total' => (float) WC()->cart->get_shipping_total(),
            'discount_total' => (float) WC()->cart->get_discount_total(),
            'applied_coupons' => WC()->cart->get_applied_coupons(),
            'payment_url' => '',
            'line_items' => $this->get_cart_items(),
            'shipping_address' => array(
                'city' => WC()->customer->get_shipping_city(),
                'country' => WC()->customer->get_shipping_country(),
                'postal_code' => WC()->customer->get_shipping_postcode(),
            )
        );
    }

    private function get_cart_items()
    {
        $items = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            $product = $values['data'];
            $items[] = array(
                'id' => (string) $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $values['quantity'],
                'total' => (float) $values['line_total'],
            );
        }
        return $items;
    }

    private function generate_token()
    {
        // Format: t_{timestamp}_{random}
        return 't_' . time() . '_' . wp_generate_password(16, false);
    }
}
