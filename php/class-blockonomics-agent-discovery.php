<?php

/**
 * Points agents at the JSON checkout flow instead of the storefront UI:
 * an instructions route, a Link header + <link> tag, and an entry in the /wp-json/ index.
 * Not /llms.txt: SEO plugins own that file.
 */
class Blockonomics_Agent_Discovery {

    public static function init() {
        add_action('send_headers', array(__CLASS__, 'send_link_header'));
        add_action('wp_head', array(__CLASS__, 'print_head_link'));
        add_filter('rest_index', array(__CLASS__, 'add_rest_index_entry'));
    }

    private static function is_active() {
        $blockonomics = new Blockonomics;
        return $blockonomics->is_agentic_checkout_active();
    }

    private static function instructions_url() {
        return rest_url(Blockonomics_Agentic_API::NAMESPACE_V1 . '/agent');
    }

    public static function serve_instructions() {
        $response = rest_ensure_response(self::build_instructions());
        $response->header('X-Robots-Tag', 'noindex');
        return $response;
    }

    public static function send_link_header() {
        if (!self::is_active() || headers_sent()) {
            return;
        }
        // RFC 8631: service-doc = human/agent docs, service-desc = machine-readable API index
        header('Link: <' . esc_url_raw(self::instructions_url()) . '>; rel="service-doc"; type="application/json"', false);
        header('Link: <' . esc_url_raw(rest_url(Blockonomics_Agentic_API::NAMESPACE_V1)) . '>; rel="service-desc"', false);
    }

    public static function print_head_link() {
        if (!self::is_active()) {
            return;
        }
        echo '<link rel="alternate" type="application/json" title="Agent checkout API" href="' . esc_url(self::instructions_url()) . '" />' . "\n";
    }

    // First key in /wp-json/: the full index is large and smaller agents only read the start
    public static function add_rest_index_entry($response) {
        if (!self::is_active()) {
            return $response;
        }
        $response->set_data(array(
            'agentic_checkout' => array(
                'description'  => 'AI agents can check out and pay in crypto on this store through JSON APIs.',
                'instructions' => self::instructions_url(),
            ),
        ) + $response->get_data());
        return $response;
    }

    private static function build_instructions() {
        $blockonomics = new Blockonomics;
        $cryptos = array_keys($blockonomics->getCachedActiveCurrencies());
        $crypto_list = $cryptos ? implode(', ', $cryptos) : 'btc';
        $example_crypto = in_array('btc', $cryptos, true) || !$cryptos ? 'btc' : $cryptos[0];

        $store = untrailingslashit(rest_url('wc/store/v1'));
        $orders = untrailingslashit(rest_url(Blockonomics_Agentic_API::NAMESPACE_V1 . '/orders'));
        $name = wp_strip_all_tags(get_bloginfo('name'));
        $currency = get_woocommerce_currency();

        return array(
            'title'       => "$name: agent checkout",
            'description' => "WooCommerce store, prices in $currency. AI agents can browse, check out and pay in crypto entirely through JSON APIs. Prefer these over driving the website UI. No API key needed. Send Content-Type: application/json on POSTs.",
            'cryptos'     => $cryptos,
            'steps'       => array(
                array(
                    'step'   => 1,
                    'do'     => 'Find products',
                    'method' => 'GET',
                    'url'    => "$store/products?search=<term>",
                    'notes'  => 'Also ?slug=, ?category=, ?per_page=. Use the product id.',
                ),
                array(
                    'step'   => 2,
                    'do'     => 'Start a cart',
                    'method' => 'GET',
                    'url'    => "$store/cart",
                    'notes'  => 'Save the Cart-Token response header and send it back as a Cart-Token request header on every later call (no nonce needed then).',
                ),
                array(
                    'step'   => 3,
                    'do'     => 'Add items',
                    'method' => 'POST',
                    'url'    => "$store/cart/add-item",
                    'body'   => array('id' => '<product id>', 'quantity' => 1),
                    'notes'  => 'Variable products also need "variation".',
                ),
                array(
                    'step'   => 4,
                    'do'     => 'Check out',
                    'method' => 'POST',
                    'url'    => "$store/checkout",
                    'body'   => array(
                        'payment_method'  => 'blockonomics',
                        'payment_data'    => array(array('key' => 'crypto', 'value' => $example_crypto)),
                        // Placeholders, not sample values: agents copied a sample address into real orders
                        'billing_address' => array(
                            'first_name' => '<first name>',
                            'last_name'  => '<last name>',
                            'email'      => '<email>',
                            'address_1'  => '<street address>',
                            'city'       => '<city>',
                            'state'      => '<state code>',
                            'postcode'   => '<postcode>',
                            'country'    => '<2-letter country code>',
                        ),
                    ),
                    'notes'  => "crypto is one of: $crypto_list. Fill billing_address with the shopper's own details; ask the shopper if you don't have them. GET $store/cart lists required address fields for the shipping country.",
                ),
                array(
                    'step'  => 5,
                    'do'    => 'Pay',
                    'notes' => 'The checkout response payment_result.payment_details (key/value list) has address, amount, payment_uri (BTC), expires_at and order_hash. Send exactly amount to address before expires_at. USDT quotes do not expire, so expires_at is empty for USDT. If you checked out without crypto, it has payment_details_url instead: GET it with ?crypto=<crypto>.',
                ),
                array(
                    'step'   => 6,
                    'do'     => 'Register the USDT transaction',
                    'method' => 'POST',
                    'url'    => "$orders/{order_hash}/txhash",
                    'body'   => array('txhash' => '0x...'),
                    'notes'  => 'USDT only, after sending. Unregistered USDT transfers are not detected.',
                ),
                array(
                    'step'   => 7,
                    'do'     => 'Check payment status',
                    'method' => 'GET',
                    'url'    => "$orders/{order_hash}?crypto=<crypto>",
                    'notes'  => 'Returns payment_status: awaiting_payment, detected, confirmed or underpaid. After expires_at it returns a fresh quote.',
                ),
            ),
            'testmode'    => array(
                'about' => 'If testmode is true in the payment details, the address is a placeholder. Do not send funds.',
                'usdt'  => 'POST the test_txhash from the payment details to the txhash URL. Blockonomics sends the payment callbacks within about 60 seconds.',
                'btc'   => 'An agent cannot complete it. The merchant simulates the payment from the Test Bench in their Blockonomics account.',
            ),
            'reference'   => 'https://developer.woocommerce.com/docs/apis/store-api/',
        );
    }
}
