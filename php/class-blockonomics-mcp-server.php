<?php

/**
 * MCP server discovery for the Blockonomics plugin.
 *
 * Provides three discovery surfaces so AI agents can find and invoke the
 * Blockonomics payment tools without any out-of-band configuration:
 *
 *  1. /.well-known/mcp/server.json   — JSON manifest (WordPress Rewrite API).
 *  2. <script> in wp_head            — WebMCP browser API hook.
 *  3. HTTP Link header               — On every front-end page for HTTP clients.
 */
class Blockonomics_MCP_Server {

    /** Query var used internally by the WordPress rewrite rule. */
    const QUERY_VAR = 'blockonomics_mcp_server';

    /**
     * Register all hooks. Call once from plugins_loaded.
     */
    public static function init() {
        add_action( 'init',              array( __CLASS__, 'register_rewrite' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_manifest' ), 1 );
        add_action( 'wp_head',           array( __CLASS__, 'inject_discovery_script' ) );
        add_action( 'wp',                array( __CLASS__, 'maybe_send_link_header' ) );
    }

    // -------------------------------------------------------------------------
    // 1. Virtual route: /.well-known/mcp/server.json
    // -------------------------------------------------------------------------

    /**
     * Register the rewrite rule and query var for /.well-known/mcp/server.json.
     *
     * This is also called directly from the plugin activation hook so the rule
     * is available immediately after flush_rewrite_rules().
     */
    public static function register_rewrite() {
        add_rewrite_tag( '%' . self::QUERY_VAR . '%', '(1)' );
        add_rewrite_rule(
            '^\.well-known/mcp/server\.json$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * If the current request matches our query var, serve the JSON manifest
     * and exit — bypassing the normal WordPress template hierarchy.
     */
    public static function maybe_serve_manifest() {
        $request_path = isset( $_SERVER['REQUEST_URI'] )
            ? parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
            : '';

        $is_mcp = ( '1' === get_query_var( self::QUERY_VAR ) )
               || ( '/.well-known/mcp/server.json' === $request_path );

        if ( ! $is_mcp ) {
            return;
        }

        $manifest = self::build_manifest();

        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'Access-Control-Allow-Origin: *' );

        echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    /**
     * Build the MCP server JSON manifest.
     *
     * The manifest advertises every Blockonomics ability registered via the
     * WordPress Abilities API so that AI agents know which tools are available
     * and what arguments each one expects.
     *
     * @return array
     */
    private static function build_manifest() {
        return array(
            'schema_version' => '1.0',
            'name'           => get_bloginfo( 'name' ),
            'description'    => __( 'Bitcoin and crypto payment capabilities powered by Blockonomics.', 'blockonomics-bitcoin-payments' ),
            'vendor'         => 'Blockonomics',
            'mcp_endpoint'   => rest_url( 'mcp/v1' ),
            'capabilities'   => array( 'tools' => true ),
            'tools'          => self::get_tool_definitions(),
        );
    }

    /**
     * Returns the tool definitions advertised in the manifest.
     *
     * Each entry mirrors the input_schema of the corresponding WP Ability so
     * an agent that only reads the manifest has enough information to call the
     * tool without a separate schema look-up.
     *
     * @return array
     */
    private static function get_tool_definitions() {
        return array(
            array(
                'name'        => 'blockonomics/get-price',
                'description' => 'Returns the current BTC and satoshi price of a WooCommerce product using the live Blockonomics exchange rate.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'product_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce product ID.',
                        ),
                    ),
                    'required' => array( 'product_id' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/create-order',
                'description' => 'Creates a WooCommerce order for a product and returns the Blockonomics Bitcoin payment address and amount due.',
                'inputSchema' => array(
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
            ),
            array(
                'name'        => 'blockonomics/check-status',
                'description' => 'Returns the human-readable payment status of a WooCommerce order: unpaid, partially_paid, or confirmed.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce order ID.',
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/get-payment-status',
                'description' => 'Returns all raw crypto payment records for a WooCommerce order.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce order ID.',
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/get-order-by-address',
                'description' => 'Looks up a Blockonomics payment record by its crypto address.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'address' => array(
                            'type'        => 'string',
                            'description' => 'The crypto address (Bitcoin, BCH, or USDT) assigned to the order.',
                        ),
                    ),
                    'required' => array( 'address' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/get-order-by-txid',
                'description' => 'Looks up a Blockonomics payment record by its on-chain transaction ID.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'txid' => array(
                            'type'        => 'string',
                            'description' => 'The on-chain transaction ID.',
                        ),
                    ),
                    'required' => array( 'txid' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/get-enabled-cryptos',
                'description' => 'Returns the list of crypto currencies currently enabled in the Blockonomics plugin settings.',
                'inputSchema' => array(
                    'type'       => 'object',
                    'properties' => new stdClass(), // no inputs required
                ),
            ),
            array(
                'name'        => 'blockonomics/get-btc-rate',
                'description' => 'Returns the live BTC exchange rate for a WooCommerce product in the store currency, plus the satoshi price.',
                'inputSchema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'product_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce product ID.',
                            'minimum'     => 1,
                        ),
                    ),
                    'required' => array( 'product_id' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/initiate-payment',
                'description' => 'Creates a WooCommerce order and returns the Bitcoin payment URI, address, and satoshi amount so the customer can pay immediately.',
                'inputSchema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'product_id'     => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce product ID to purchase.',
                            'minimum'     => 1,
                        ),
                        'customer_email' => array(
                            'type'        => 'string',
                            'format'      => 'email',
                            'description' => 'Customer email address.',
                            'maxLength'   => 254,
                        ),
                    ),
                    'required' => array( 'product_id', 'customer_email' ),
                ),
            ),
            array(
                'name'        => 'blockonomics/confirm-settlement',
                'description' => 'Checks whether a Bitcoin payment for a WooCommerce order has been detected on-chain and fully confirmed.',
                'inputSchema' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce order ID.',
                            'minimum'     => 1,
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
            ),
        );
    }

    // -------------------------------------------------------------------------
    // 2. WebMCP browser discovery via wp_head
    // -------------------------------------------------------------------------

    /**
     * Output a WebMCP discovery <script> into wp_head.
     *
     * Guards the call with a typeof check so browsers that do not yet implement
     * navigator.modelContext are not thrown a TypeError. Agents that do
     * implement the API receive the tool registration on every page load.
     */
    public static function inject_discovery_script() {
        $manifest_url = esc_url( home_url( '/.well-known/mcp/server.json' ) );
        ?>
<script>
if (typeof navigator.modelContext !== 'undefined') {
    navigator.modelContext.registerTool('blockonomics_btc_pay', {
        manifest: '<?php echo $manifest_url; ?>'
    });
}
</script>
        <?php
    }

    // -------------------------------------------------------------------------
    // 3. HTTP Link header on every front-end page
    // -------------------------------------------------------------------------

    /**
     * Send an HTTP Link header on every front-end page.
     *
     * HTTP clients (crawlers, headless agents) receive the manifest URL as a
     * response header so they can discover the MCP server on any page, without
     * parsing HTML or being limited to specific page types.
     *
     * Fires on the 'wp' action — after the main WP_Query is set up but before
     * any output — making it a reliable place to add response headers.
     */
    public static function maybe_send_link_header() {
        if ( headers_sent() ) {
            return;
        }

        $manifest_url = esc_url_raw( home_url( '/.well-known/mcp/server.json' ) );

        // Pass false so we append rather than replace any existing Link headers.
        header( sprintf( 'Link: <%s>; rel="mcp-server"', $manifest_url ), false );
    }
}
