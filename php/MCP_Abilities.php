<?php

/**
 * WooCommerce MCP (Model Context Protocol) abilities for the Blockonomics plugin.
 *
 * Registers abilities via the WordPress Abilities API so that AI clients
 * (e.g. Claude Desktop / Claude Code) can query Blockonomics payment data
 * through the WooCommerce MCP server (WooCommerce 10.3+).
 *
 * Merchants (manage_woocommerce) can query any order.
 * Logged-in customers can only query their own orders.
 *
 * @see https://developer.woocommerce.com/docs/features/mcp/
 */
class Blockonomics_MCP_Abilities {

    public static function register_category() {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category(
            'blockonomics',
            array(
                'label'       => __( 'Blockonomics', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Crypto payment data from the Blockonomics Bitcoin Payments plugin.', 'blockonomics-bitcoin-payments' ),
            )
        );
    }

    public static function register() {
        // WordPress Abilities API is only available in WP 6.8+ / WC 10.3+
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        wp_register_ability(
            'blockonomics/get-payment-status',
            array(
                'label'       => __( 'Get Blockonomics Payment Status', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the current crypto payment record(s) for a WooCommerce order — amount expected, amount paid, transaction ID, and payment status. Customers may only query their own orders.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce order ID.',
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
                'output_schema' => array(
                    'type'  => 'object',
                    'properties' => array(
                        'payments' => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'crypto'           => array( 'type' => 'string' ),
                                    'address'          => array( 'type' => 'string' ),
                                    'payment_status'   => array( 'type' => 'integer', 'description' => '0=pending, 1=unconfirmed, 2=confirmed' ),
                                    'expected_satoshi' => array( 'type' => 'integer' ),
                                    'expected_fiat'    => array( 'type' => 'number' ),
                                    'currency'         => array( 'type' => 'string' ),
                                    'paid_satoshi'     => array( 'type' => 'integer' ),
                                    'paid_fiat'        => array( 'type' => 'number' ),
                                    'txid'             => array( 'type' => 'string' ),
                                ),
                            ),
                        ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'logged_in_check' ),
                'execute_callback'    => array( __CLASS__, 'get_payment_status' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/get-order-by-address',
            array(
                'label'       => __( 'Get Order by Crypto Address', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Looks up a Blockonomics payment record by the crypto address assigned to an order. Customers may only query their own orders.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'address' => array(
                            'type'        => 'string',
                            'description' => 'The crypto address (Bitcoin, BCH, or USDT) assigned to the order.',
                        ),
                    ),
                    'required' => array( 'address' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'         => array( 'type' => 'integer' ),
                        'crypto'           => array( 'type' => 'string' ),
                        'address'          => array( 'type' => 'string' ),
                        'payment_status'   => array( 'type' => 'integer' ),
                        'expected_satoshi' => array( 'type' => 'integer' ),
                        'expected_fiat'    => array( 'type' => 'number' ),
                        'currency'         => array( 'type' => 'string' ),
                        'paid_satoshi'     => array( 'type' => 'integer' ),
                        'paid_fiat'        => array( 'type' => 'number' ),
                        'txid'             => array( 'type' => 'string' ),
                        'error'            => array( 'type' => 'string' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'logged_in_check' ),
                'execute_callback'    => array( __CLASS__, 'get_order_by_address' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/get-order-by-txid',
            array(
                'label'       => __( 'Get Order by Transaction ID', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Looks up a Blockonomics payment record by the on-chain transaction ID. Customers may only query their own orders.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'txid' => array(
                            'type'        => 'string',
                            'description' => 'The on-chain transaction ID.',
                        ),
                    ),
                    'required' => array( 'txid' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'         => array( 'type' => 'integer' ),
                        'crypto'           => array( 'type' => 'string' ),
                        'address'          => array( 'type' => 'string' ),
                        'payment_status'   => array( 'type' => 'integer' ),
                        'expected_satoshi' => array( 'type' => 'integer' ),
                        'expected_fiat'    => array( 'type' => 'number' ),
                        'currency'         => array( 'type' => 'string' ),
                        'paid_satoshi'     => array( 'type' => 'integer' ),
                        'paid_fiat'        => array( 'type' => 'number' ),
                        'txid'             => array( 'type' => 'string' ),
                        'error'            => array( 'type' => 'string' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'logged_in_check' ),
                'execute_callback'    => array( __CLASS__, 'get_order_by_txid' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/get-price',
            array(
                'label'       => __( 'Get Product Price in BTC', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the current BTC (and satoshi) price of a WooCommerce product using the live Blockonomics exchange rate.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'product_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce product ID.',
                        ),
                    ),
                    'required' => array( 'product_id' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'product_id'  => array( 'type' => 'integer', 'description' => 'WooCommerce product ID.' ),
                        'fiat_price'  => array( 'type' => 'number',  'description' => 'Product price in store currency (excl. tax).' ),
                        'currency'    => array( 'type' => 'string',  'description' => 'Store currency code, e.g. USD.' ),
                        'btc_rate'    => array( 'type' => 'number',  'description' => 'Current BTC exchange rate in store currency.' ),
                        'btc_amount'  => array( 'type' => 'number',  'description' => 'Product price expressed in BTC.' ),
                        'sats_amount' => array( 'type' => 'integer', 'description' => 'Product price expressed in satoshis (1 BTC = 100 000 000 sats).' ),
                        'error'       => array( 'type' => 'string',  'description' => 'Error message, present only on failure.' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'can_read' ),
                'execute_callback'    => array( __CLASS__, 'get_price' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/create-order',
            array(
                'label'       => __( 'Create Blockonomics Order', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Creates a WooCommerce order for a product and returns the Blockonomics Bitcoin payment address and amount due.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'product_id'     => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce product ID to order.',
                        ),
                        'customer_email' => array(
                            'type'        => 'string',
                            'format'      => 'email',
                            'description' => 'Customer email address for the order.',
                        ),
                    ),
                    'required' => array( 'product_id', 'customer_email' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'      => array( 'type' => 'integer', 'description' => 'Newly created WooCommerce order ID.' ),
                        'address'       => array( 'type' => 'string',  'description' => 'Bitcoin payment address.' ),
                        'btc_amount'    => array( 'type' => 'number',  'description' => 'Amount due in BTC.' ),
                        'sats_amount'   => array( 'type' => 'integer', 'description' => 'Amount due in satoshis.' ),
                        'expected_fiat' => array( 'type' => 'number',  'description' => 'Amount due in store currency.' ),
                        'currency'      => array( 'type' => 'string',  'description' => 'Store currency code.' ),
                        'payment_uri'   => array( 'type' => 'string',  'description' => 'BIP21 payment URI (bitcoin:address?amount=...).' ),
                        'error'         => array( 'type' => 'string',  'description' => 'Error message, present only on failure.' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'can_read' ),
                'execute_callback'    => array( __CLASS__, 'create_order' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/check-status',
            array(
                'label'       => __( 'Check Blockonomics Payment Status', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the human-readable payment status for a WooCommerce order: unpaid, partially_paid, or confirmed.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce order ID.',
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'      => array( 'type' => 'integer', 'description' => 'WooCommerce order ID.' ),
                        'status'        => array(
                            'type'        => 'string',
                            'enum'        => array( 'unpaid', 'partially_paid', 'confirmed' ),
                            'description' => 'Aggregated payment status. unpaid=no payment received, partially_paid=payment in progress or underpaid, confirmed=fully paid and confirmed.',
                        ),
                        'paid_fiat'     => array( 'type' => 'number', 'description' => 'Total amount paid across all payment records, in store currency.' ),
                        'expected_fiat' => array( 'type' => 'number', 'description' => 'Total amount expected across all payment records, in store currency.' ),
                        'currency'      => array( 'type' => 'string', 'description' => 'Store currency code.' ),
                        'payments'      => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'crypto'         => array( 'type' => 'string' ),
                                    'address'        => array( 'type' => 'string' ),
                                    'payment_status' => array( 'type' => 'integer', 'description' => '0=unpaid, 1=partially_paid/unconfirmed, 2=confirmed.' ),
                                    'txid'           => array( 'type' => 'string' ),
                                    'paid_fiat'      => array( 'type' => 'number' ),
                                    'expected_fiat'  => array( 'type' => 'number' ),
                                ),
                            ),
                        ),
                        'error' => array( 'type' => 'string', 'description' => 'Error message, present only on failure.' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'can_read' ),
                'execute_callback'    => array( __CLASS__, 'check_status' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/get-btc-rate',
            array(
                'label'       => __( 'Get Real-Time BTC Rate for Product', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the real-time Bitcoin price of a WooCommerce product in satoshis and BTC, using the live Blockonomics exchange rate.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'product_id' => array(
                            'type'        => 'integer',
                            'minimum'     => 1,
                            'description' => 'WooCommerce product ID.',
                        ),
                    ),
                    'required' => array( 'product_id' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'product_id'    => array( 'type' => 'integer' ),
                        'satoshi_price' => array( 'type' => 'integer', 'description' => 'Product price in satoshis (1 BTC = 100 000 000 sats).' ),
                        'btc_price'     => array( 'type' => 'number',  'description' => 'Product price expressed in BTC.' ),
                        'fiat_price'    => array( 'type' => 'number',  'description' => 'Product price in store currency (excl. tax).' ),
                        'currency'      => array( 'type' => 'string',  'description' => 'Store currency code, e.g. USD.' ),
                        'btc_rate'      => array( 'type' => 'number',  'description' => 'Current BTC/fiat exchange rate.' ),
                        'timestamp'     => array( 'type' => 'integer', 'description' => 'Unix timestamp when the rate was fetched.' ),
                        'error'         => array( 'type' => 'string',  'description' => 'Error message, present only on failure.' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'can_view_order' ),
                'execute_callback'    => array( __CLASS__, 'get_btc_rate' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/initiate-payment',
            array(
                'label'       => __( 'Initiate Blockonomics Bitcoin Payment', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Creates a pending WooCommerce order for a product and returns a Blockonomics Bitcoin payment address with the exact satoshi amount due.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'product_id'     => array(
                            'type'        => 'integer',
                            'minimum'     => 1,
                            'description' => 'WooCommerce product ID to order.',
                        ),
                        'customer_email' => array(
                            'type'        => 'string',
                            'format'      => 'email',
                            'maxLength'   => 254,
                            'description' => 'Customer email address for the order.',
                        ),
                    ),
                    'required' => array( 'product_id', 'customer_email' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'       => array( 'type' => 'integer', 'description' => 'Newly created WooCommerce order ID.' ),
                        'address'        => array( 'type' => 'string',  'description' => 'Bitcoin payment address.' ),
                        'satoshi_amount' => array( 'type' => 'integer', 'description' => 'Amount due in satoshis.' ),
                        'btc_amount'     => array( 'type' => 'number',  'description' => 'Amount due in BTC.' ),
                        'expected_fiat'  => array( 'type' => 'number',  'description' => 'Amount due in store currency.' ),
                        'currency'       => array( 'type' => 'string',  'description' => 'Store currency code.' ),
                        'payment_uri'    => array( 'type' => 'string',  'description' => 'BIP21 payment URI (bitcoin:address?amount=...).' ),
                        'error'          => array( 'type' => 'string',  'description' => 'Error message, present only on failure.' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'can_view_order' ),
                'execute_callback'    => array( __CLASS__, 'initiate_payment' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/confirm-settlement',
            array(
                'label'       => __( 'Confirm Blockonomics Transaction Settlement', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Checks whether an on-chain Bitcoin transaction for a WooCommerce order has been detected and confirmed by Blockonomics. Reflects the most recent Blockonomics callback received.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'minimum'     => 1,
                            'description' => 'WooCommerce order ID to check.',
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'       => array( 'type' => 'integer' ),
                        'detected'       => array( 'type' => 'boolean', 'description' => 'True when at least one transaction has been detected by Blockonomics (payment_status >= 1).' ),
                        'confirmed'      => array( 'type' => 'boolean', 'description' => 'True when the transaction has the required number of on-chain confirmations (payment_status = 2).' ),
                        'payment_status' => array( 'type' => 'integer', 'description' => '0=not detected, 1=detected/unconfirmed, 2=confirmed.' ),
                        'txid'           => array( 'type' => 'string',  'description' => 'Most recent on-chain transaction ID, empty if not yet detected.' ),
                        'address'        => array( 'type' => 'string',  'description' => 'Bitcoin address assigned to the order.' ),
                        'crypto'         => array( 'type' => 'string',  'description' => 'Crypto currency code, e.g. btc.' ),
                        'error'          => array( 'type' => 'string',  'description' => 'Error message, present only on failure.' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'can_view_order' ),
                'execute_callback'    => array( __CLASS__, 'confirm_settlement' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        wp_register_ability(
            'blockonomics/get-enabled-cryptos',
            array(
                'label'       => __( 'Get Enabled Blockonomics Cryptos', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the list of crypto currencies currently enabled in the Blockonomics plugin settings.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => new stdClass(), // no parameters required
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'enabled_cryptos' => array(
                            'type'        => 'array',
                            'items'       => array( 'type' => 'string' ),
                            'description' => 'Crypto codes that are enabled, e.g. ["btc", "usdt"].',
                        ),
                        'store_uid_configured' => array(
                            'type'        => 'boolean',
                            'description' => 'Whether a Blockonomics store UID is configured (widget checkout mode).',
                        ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'logged_in_check' ),
                'execute_callback'    => array( __CLASS__, 'get_enabled_cryptos' ),
                'meta' => array( 'mcp' => array( 'public' => true ), 'public_mcp' => true ),
            )
        );

        // Expose every registered Blockonomics ability through the WordPress MCP Adapter.
        self::register_mcp_bridge();
    }

    /**
     * Allows merchants (manage_woocommerce) or any logged-in user.
     */
    public static function logged_in_check() {
        return is_user_logged_in();
    }

    /**
     * Permission callback for the three new abilities: any user with the 'read' capability.
     * The 'read' cap is granted to all registered WordPress users (Subscriber and above).
     */
    public static function can_read() {
        return current_user_can( 'read' );
    }

    /**
     * Permission callback requiring the WooCommerce 'view_order' capability.
     * Granted to Shop Managers and Administrators; customers receive it only
     * when checked against a specific order they own (meta capability).
     */
    public static function can_view_order() {
        return current_user_can( 'view_order' );
    }

    // -------------------------------------------------------------------------
    // WordPress MCP Adapter bridge
    // -------------------------------------------------------------------------

    /**
     * Register the Blockonomics ability slugs with the WordPress MCP Adapter.
     *
     * The WP MCP Adapter reads the 'public_mcp' key from each ability's meta
     * and then exposes matching abilities through the site's official MCP REST
     * endpoint (/wp-json/mcp/v1).  Hooking into 'wp_mcp_adapter_abilities'
     * ensures agents that connect to the endpoint — rather than fetching the
     * /.well-known/mcp/server.json manifest — also see every Blockonomics tool.
     *
     * Called automatically at the end of register() so a single
     * wp_abilities_api_init hook bootstraps everything.
     */
    public static function register_mcp_bridge() {
        add_filter( 'wp_mcp_adapter_abilities', array( __CLASS__, 'expose_abilities_to_mcp' ) );
    }

    /**
     * Appends all Blockonomics ability slugs to the list the WordPress MCP
     * Adapter exposes through the site's official MCP endpoint.
     *
     * The adapter merges contributions from all plugins, so this filter only
     * adds Blockonomics entries and leaves whatever other plugins have added
     * untouched.
     *
     * @param string[] $abilities Ability slugs already registered with the adapter.
     * @return string[]
     */
    public static function expose_abilities_to_mcp( array $abilities ) {
        return array_merge( $abilities, array(
            'blockonomics/get-price',
            'blockonomics/create-order',
            'blockonomics/check-status',
            'blockonomics/get-payment-status',
            'blockonomics/get-order-by-address',
            'blockonomics/get-order-by-txid',
            'blockonomics/get-enabled-cryptos',
            'blockonomics/get-btc-rate',
            'blockonomics/initiate-payment',
            'blockonomics/confirm-settlement',
        ) );
    }

    /**
     * Returns true if the current user may access the given order.
     * Merchants can access any order; customers only their own.
     */
    private static function can_access_order( $order_id ) {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        $order = wc_get_order( $order_id );
        return $order && (int) $order->get_customer_id() === get_current_user_id();
    }

    /**
     * Returns all payment records for a given order_id.
     */
    public static function get_payment_status( $args ) {
        global $wpdb;

        $order_id = intval( $args['order_id'] );

        if ( ! self::can_access_order( $order_id ) ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to view this order.', 'blockonomics-bitcoin-payments' ) );
        }

        $table = $wpdb->prefix . 'blockonomics_payments';
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ),
            ARRAY_A
        );

        if ( $rows === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        return array( 'payments' => $rows ?: array() );
    }

    /**
     * Returns the payment record for a given crypto address.
     */
    public static function get_order_by_address( $args ) {
        global $wpdb;

        $table = $wpdb->prefix . 'blockonomics_payments';
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE address = %s", sanitize_text_field( $args['address'] ) ),
            ARRAY_A
        );

        if ( $row === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( empty( $row ) ) {
            return array( 'error' => __( 'No order found for this address.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( ! self::can_access_order( (int) $row['order_id'] ) ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to view this order.', 'blockonomics-bitcoin-payments' ) );
        }

        return $row;
    }

    /**
     * Returns the payment record for a given transaction ID.
     */
    public static function get_order_by_txid( $args ) {
        global $wpdb;

        $table = $wpdb->prefix . 'blockonomics_payments';
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE txid = %s", sanitize_text_field( $args['txid'] ) ),
            ARRAY_A
        );

        if ( $row === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( empty( $row ) ) {
            return array( 'error' => __( 'No order found for this txid.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( ! self::can_access_order( (int) $row['order_id'] ) ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to view this order.', 'blockonomics-bitcoin-payments' ) );
        }

        return $row;
    }

    /**
     * Returns the BTC/satoshi price of a WooCommerce product.
     *
     * Uses the live Blockonomics exchange-rate API. The product price is read
     * without tax (consistent with how WooCommerce displays prices).
     *
     * @param array $args { product_id: int }
     * @return array|WP_Error
     */
    public static function get_price( $args ) {
        $product_id = intval( $args['product_id'] );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return array( 'error' => __( 'Product not found.', 'blockonomics-bitcoin-payments' ) );
        }

        $fiat_price   = (float) wc_get_price_excluding_tax( $product );
        $currency     = get_woocommerce_currency();
        $blockonomics = new Blockonomics();
        $price_obj    = $blockonomics->get_price( $currency, 'btc' );

        if ( $price_obj->response_code !== 200 || empty( $price_obj->price ) ) {
            $msg = ! empty( $price_obj->response_message )
                ? $price_obj->response_message
                : __( 'Could not fetch BTC price from Blockonomics.', 'blockonomics-bitcoin-payments' );
            return array( 'error' => $msg );
        }

        $btc_rate    = (float) $price_obj->price;
        $btc_amount  = $fiat_price / $btc_rate;
        $sats_amount = (int) round( $btc_amount * 1e8 );

        return array(
            'product_id'  => $product_id,
            'fiat_price'  => $fiat_price,
            'currency'    => $currency,
            'btc_rate'    => $btc_rate,
            'btc_amount'  => $btc_amount,
            'sats_amount' => $sats_amount,
        );
    }

    /**
     * Creates a WooCommerce order for a product and assigns a Blockonomics Bitcoin
     * payment address to it.
     *
     * The order is created with status "pending" and the payment method set to
     * "blockonomics". A BTC address is requested from the Blockonomics API and
     * the payment record is stored in the blockonomics_payments table.
     *
     * @param array $args { product_id: int, customer_email: string }
     * @return array|WP_Error
     */
    public static function create_order( $args ) {
        $product_id     = intval( $args['product_id'] );
        $customer_email = sanitize_email( $args['customer_email'] );

        if ( ! is_email( $customer_email ) ) {
            return array( 'error' => __( 'Invalid customer email address.', 'blockonomics-bitcoin-payments' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return array( 'error' => __( 'Product not found.', 'blockonomics-bitcoin-payments' ) );
        }

        // Create the WooCommerce order.
        $wc_order = wc_create_order( array( 'status' => 'pending' ) );
        if ( is_wp_error( $wc_order ) ) {
            return array( 'error' => $wc_order->get_error_message() );
        }

        $wc_order->add_product( $product, 1 );
        $wc_order->set_billing_email( $customer_email );
        $wc_order->set_payment_method( 'blockonomics' );
        $wc_order->set_payment_method_title( __( 'Bitcoin', 'blockonomics-bitcoin-payments' ) );
        $wc_order->calculate_totals();
        $wc_order->save();

        $order_id     = $wc_order->get_id();
        $blockonomics = new Blockonomics();

        // Generate a Blockonomics BTC address and calculate the expected satoshi amount.
        $payment_order = $blockonomics->create_new_order( $order_id, 'btc' );
        if ( isset( $payment_order['error'] ) ) {
            $wc_order->update_status( 'failed', __( 'Blockonomics address generation failed.', 'blockonomics-bitcoin-payments' ) );
            return array( 'error' => $payment_order['error'] );
        }

        $insert_result = $blockonomics->insert_order( $payment_order );
        if ( isset( $insert_result['error'] ) ) {
            $wc_order->update_status( 'failed', __( 'Blockonomics payment record could not be stored.', 'blockonomics-bitcoin-payments' ) );
            return array( 'error' => $insert_result['error'] );
        }

        $blockonomics->record_address( $order_id, 'btc', $payment_order['address'] );

        $sats_amount = (int) $payment_order['expected_satoshi'];
        $btc_amount  = $sats_amount / 1e8;
        $crypto_data = $blockonomics->getSupportedCurrencies();
        $payment_uri = $blockonomics->get_crypto_payment_uri(
            $crypto_data['btc'],
            $payment_order['address'],
            $blockonomics->fix_displaying_small_values( 'btc', $sats_amount )
        );

        return array(
            'order_id'      => $order_id,
            'address'       => $payment_order['address'],
            'btc_amount'    => $btc_amount,
            'sats_amount'   => $sats_amount,
            'expected_fiat' => (float) $payment_order['expected_fiat'],
            'currency'      => $payment_order['currency'],
            'payment_uri'   => $payment_uri,
        );
    }

    /**
     * Returns the human-readable payment status for a WooCommerce order.
     *
     * Status mapping:
     *   - 'confirmed'     — at least one payment_status=2 row and total paid >= total expected.
     *   - 'partially_paid'— any unconfirmed payment (status=1) or a confirmed-but-underpaid order.
     *   - 'unpaid'        — no payment records exist yet, or all rows are status=0.
     *
     * @param array $args { order_id: int }
     * @return array|WP_Error
     */
    public static function check_status( $args ) {
        global $wpdb;

        $order_id = intval( $args['order_id'] );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return array( 'error' => __( 'Order not found.', 'blockonomics-bitcoin-payments' ) );
        }

        $table = $wpdb->prefix . 'blockonomics_payments';
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ),
            ARRAY_A
        );

        if ( $rows === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        $total_paid     = 0.0;
        $total_expected = 0.0;
        $currency       = '';
        $max_status     = -1; // -1 means no rows at all.

        foreach ( $rows as $row ) {
            $total_paid     += (float) $row['paid_fiat'];
            $total_expected += (float) $row['expected_fiat'];
            if ( empty( $currency ) && ! empty( $row['currency'] ) ) {
                $currency = $row['currency'];
            }
            $max_status = max( $max_status, (int) $row['payment_status'] );
        }

        if ( $max_status === 2 && $total_paid >= $total_expected ) {
            $status = 'confirmed';
        } elseif ( $max_status >= 1 || ( $max_status === 2 && $total_paid < $total_expected ) ) {
            $status = 'partially_paid';
        } else {
            $status = 'unpaid';
        }

        $decimals = wc_get_price_decimals();

        return array(
            'order_id'      => $order_id,
            'status'        => $status,
            'paid_fiat'     => round( $total_paid,     $decimals ),
            'expected_fiat' => round( $total_expected, $decimals ),
            'currency'      => $currency ?: get_woocommerce_currency(),
            'payments'      => $rows ?: array(),
        );
    }

    /**
     * Returns the real-time BTC/satoshi price for a WooCommerce product.
     *
     * Fetches the live BTC exchange rate from the Blockonomics price API and
     * converts the product's fiat price (excl. tax) into satoshis and BTC.
     *
     * @param array $args { product_id: int }
     * @return array|WP_Error
     */
    public static function get_btc_rate( $args ) {
        $product_id = intval( $args['product_id'] );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return array( 'error' => __( 'Product not found.', 'blockonomics-bitcoin-payments' ) );
        }

        $fiat_price   = (float) wc_get_price_excluding_tax( $product );
        $currency     = get_woocommerce_currency();
        $blockonomics = new Blockonomics();
        $price_obj    = $blockonomics->get_price( $currency, 'btc' );

        if ( $price_obj->response_code !== 200 || empty( $price_obj->price ) ) {
            $msg = ! empty( $price_obj->response_message )
                ? $price_obj->response_message
                : __( 'Could not fetch BTC rate from Blockonomics.', 'blockonomics-bitcoin-payments' );
            return array( 'error' => $msg );
        }

        $btc_rate      = (float) $price_obj->price;
        $btc_price     = $fiat_price / $btc_rate;
        $satoshi_price = (int) round( $btc_price * 1e8 );

        return array(
            'product_id'    => $product_id,
            'satoshi_price' => $satoshi_price,
            'btc_price'     => $btc_price,
            'fiat_price'    => $fiat_price,
            'currency'      => $currency,
            'btc_rate'      => $btc_rate,
            'timestamp'     => time(),
        );
    }

    /**
     * Creates a WooCommerce order for a product and assigns a Blockonomics Bitcoin
     * payment address to it, returning the exact satoshi amount due.
     *
     * The order is created with status "pending" and payment method "blockonomics".
     * A new BTC address is requested from the Blockonomics API and the payment
     * record is stored in the blockonomics_payments table.
     *
     * @param array $args { product_id: int, customer_email: string }
     * @return array|WP_Error
     */
    public static function initiate_payment( $args ) {
        $product_id     = intval( $args['product_id'] );
        $customer_email = sanitize_email( $args['customer_email'] );

        if ( ! is_email( $customer_email ) ) {
            return array( 'error' => __( 'Invalid customer email address.', 'blockonomics-bitcoin-payments' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return array( 'error' => __( 'Product not found.', 'blockonomics-bitcoin-payments' ) );
        }

        $wc_order = wc_create_order( array( 'status' => 'pending' ) );
        if ( is_wp_error( $wc_order ) ) {
            return array( 'error' => $wc_order->get_error_message() );
        }

        $wc_order->add_product( $product, 1 );
        $wc_order->set_billing_email( $customer_email );
        $wc_order->set_payment_method( 'blockonomics' );
        $wc_order->set_payment_method_title( __( 'Bitcoin', 'blockonomics-bitcoin-payments' ) );
        $wc_order->calculate_totals();
        $wc_order->save();

        $order_id     = $wc_order->get_id();
        $blockonomics = new Blockonomics();

        $payment_order = $blockonomics->create_new_order( $order_id, 'btc' );
        if ( isset( $payment_order['error'] ) ) {
            $wc_order->update_status( 'failed', __( 'Blockonomics address generation failed.', 'blockonomics-bitcoin-payments' ) );
            return array( 'error' => $payment_order['error'] );
        }

        $insert_result = $blockonomics->insert_order( $payment_order );
        if ( isset( $insert_result['error'] ) ) {
            $wc_order->update_status( 'failed', __( 'Blockonomics payment record could not be stored.', 'blockonomics-bitcoin-payments' ) );
            return array( 'error' => $insert_result['error'] );
        }

        $blockonomics->record_address( $order_id, 'btc', $payment_order['address'] );

        $satoshi_amount = (int) $payment_order['expected_satoshi'];
        $btc_amount     = $satoshi_amount / 1e8;
        $crypto_data    = $blockonomics->getSupportedCurrencies();
        $payment_uri    = $blockonomics->get_crypto_payment_uri(
            $crypto_data['btc'],
            $payment_order['address'],
            $blockonomics->fix_displaying_small_values( 'btc', $satoshi_amount )
        );

        return array(
            'order_id'       => $order_id,
            'address'        => $payment_order['address'],
            'satoshi_amount' => $satoshi_amount,
            'btc_amount'     => $btc_amount,
            'expected_fiat'  => (float) $payment_order['expected_fiat'],
            'currency'       => $payment_order['currency'],
            'payment_uri'    => $payment_uri,
        );
    }

    /**
     * Checks whether an on-chain Bitcoin transaction for a WooCommerce order
     * has been detected and confirmed by Blockonomics.
     *
     * Settlement state is read from the blockonomics_payments table, which is
     * kept up-to-date by the Blockonomics callback API. The most advanced
     * payment row (highest payment_status, most recent txid) is used:
     *
     *   payment_status 0 — no transaction detected yet.
     *   payment_status 1 — transaction detected, awaiting on-chain confirmations.
     *   payment_status 2 — transaction confirmed (settled).
     *
     * @param array $args { order_id: int }
     * @return array|WP_Error
     */
    public static function confirm_settlement( $args ) {
        global $wpdb;

        $order_id = intval( $args['order_id'] );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return array( 'error' => __( 'Order not found.', 'blockonomics-bitcoin-payments' ) );
        }

        $table = $wpdb->prefix . 'blockonomics_payments';

        // Fetch the most advanced row — highest status wins; break ties by txid presence.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %d ORDER BY payment_status DESC, txid DESC LIMIT 1",
                $order_id
            ),
            ARRAY_A
        );

        if ( $row === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( empty( $row ) ) {
            return array(
                'order_id'       => $order_id,
                'detected'       => false,
                'confirmed'      => false,
                'payment_status' => 0,
                'txid'           => '',
                'address'        => '',
                'crypto'         => '',
            );
        }

        $payment_status = (int) $row['payment_status'];

        return array(
            'order_id'       => $order_id,
            'detected'       => $payment_status >= 1,
            'confirmed'      => $payment_status === 2,
            'payment_status' => $payment_status,
            'txid'           => isset( $row['txid'] )    ? (string) $row['txid']    : '',
            'address'        => isset( $row['address'] ) ? (string) $row['address'] : '',
            'crypto'         => isset( $row['crypto'] )  ? (string) $row['crypto']  : '',
        );
    }

    /**
     * Returns enabled crypto codes and whether a store UID is configured.
     */
    public static function get_enabled_cryptos( $args ) {
        $raw     = get_option( 'blockonomics_enabled_cryptos', '' );
        $enabled = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

        return array(
            'enabled_cryptos'      => array_values( $enabled ),
            'store_uid_configured' => ! empty( get_option( 'blockonomics_store_uid', '' ) ),
        );
    }
}
