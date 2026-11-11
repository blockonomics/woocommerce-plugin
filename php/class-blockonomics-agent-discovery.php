<?php

/**
 * Points agents at the JSON checkout flow instead of the storefront UI:
 * an instructions route, a Link header + <link> tag, and a visually hidden footer note.
 * Not /llms.txt: SEO plugins own that file.
 */
class Blockonomics_Agent_Discovery {

    const INSTRUCTIONS_ROUTE = '/blockonomics/v1/agent';

    public static function init() {
        add_action('send_headers', array(__CLASS__, 'send_link_header'));
        add_action('wp_head', array(__CLASS__, 'print_head_link'));
        add_action('wp_footer', array(__CLASS__, 'print_footer_hint'));
    }

    private static function is_active() {
        return (bool) get_option('blockonomics_agentic_checkout', false);
    }

    private static function instructions_url() {
        return rest_url(ltrim(self::INSTRUCTIONS_ROUTE, '/'));
    }

    public static function serve_instructions() {
        return new WP_REST_Response(self::build_instructions());
    }

    // REST responses are JSON by default; send the instructions as plain markdown
    public static function serve_markdown($served, $result, $request) {
        if ($served || $request->get_route() !== self::INSTRUCTIONS_ROUTE || $result->is_error()) {
            return $served;
        }
        header('Content-Type: text/markdown; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $result->get_data();
        return true;
    }

    public static function send_link_header() {
        if (!self::is_active() || headers_sent()) {
            return;
        }
        // RFC 8631: service-doc = human/agent docs, service-desc = machine-readable API index
        header('Link: <' . esc_url_raw(self::instructions_url()) . '>; rel="service-doc"; type="text/markdown"', false);
        header('Link: <' . esc_url_raw(rest_url('blockonomics/v1')) . '>; rel="service-desc"', false);
    }

    public static function print_head_link() {
        if (!self::is_active()) {
            return;
        }
        echo '<link rel="alternate" type="text/markdown" title="Agent checkout API" href="' . esc_url(self::instructions_url()) . '" />' . "\n";
    }

    // Clip-hidden, not display:none, so it stays in the accessibility tree browser agents read
    public static function print_footer_hint() {
        if (!self::is_active()) {
            return;
        }
        $store = rest_url('wc/store/v1');
        printf(
            '<p style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">%s <a href="%s">%s</a>. %s: %s/products, %s/cart, %s/checkout.</p>' . "\n",
            esc_html__('Note for AI agents: this store can be shopped and paid in crypto through JSON APIs, no browser needed. Instructions:', 'blockonomics-bitcoin-payments'),
            esc_url(self::instructions_url()),
            esc_html(self::instructions_url()),
            esc_html__('Endpoints', 'blockonomics-bitcoin-payments'),
            esc_html($store),
            esc_html($store),
            esc_html($store)
        );
    }

    private static function build_instructions() {
        $blockonomics = new Blockonomics;
        $cryptos = array_keys($blockonomics->getCachedActiveCurrencies());
        $crypto_list = $cryptos ? implode(', ', $cryptos) : 'btc';
        $example_crypto = in_array('btc', $cryptos, true) || !$cryptos ? 'btc' : $cryptos[0];

        $store = untrailingslashit(rest_url('wc/store/v1'));
        $orders = untrailingslashit(rest_url('blockonomics/v1/orders'));
        $name = wp_strip_all_tags(get_bloginfo('name'));
        $tagline = wp_strip_all_tags(get_bloginfo('description'));
        $currency = get_woocommerce_currency();

        $checkout_body = wp_json_encode(array(
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
        ), JSON_UNESCAPED_SLASHES);

        $lines = array(
            "# $name",
            '',
            '> ' . ($tagline ? "$tagline. " : '') . "WooCommerce store, prices in $currency. AI agents can browse, check out and pay in crypto ($crypto_list) entirely through JSON APIs. Prefer these over driving the website UI.",
            '',
            '## Agent checkout',
            '',
            'No API key needed. Send `Content-Type: application/json` on POSTs.',
            '',
            "1. Find products: `GET $store/products?search=<term>` (also `?slug=`, `?category=`, `?per_page=`). Use the product `id`.",
            "2. Start a cart: `GET $store/cart`. Save the `Cart-Token` response header and send it back as a `Cart-Token` request header on every later call (no nonce needed then).",
            "3. Add items: `POST $store/cart/add-item` with `{\"id\": <product id>, \"quantity\": 1}`. Variable products also need `\"variation\"`.",
            "4. Check out: `POST $store/checkout` with:",
            '',
            '   ```json',
            "   $checkout_body",
            '   ```',
            '',
            "   `crypto` is one of: $crypto_list. Fill `billing_address` with the shopper's own details; ask the shopper if you don't have them. `GET $store/cart` lists required address fields for the shipping country.",
            '5. Pay: the response `payment_result.payment_details` (key/value list) has `address`, `amount`, `payment_uri` (BTC), `expires_at` and `order_hash`. Send exactly `amount` to `address` before `expires_at`.',
            "6. USDT only: after sending, `POST $orders/{order_hash}/txhash` with `{\"txhash\": \"0x...\"}`. Unregistered USDT transfers are not detected. If `testmode` is true send nothing, just POST the `test_txhash` from the payment details.",
            "7. Status: `GET $orders/{order_hash}?crypto=<crypto>` returns `payment_status` (`awaiting_payment`, `detected`, `confirmed`, `underpaid`). Calling it after `expires_at` returns a fresh quote.",
            '',
            '## Reference',
            '',
            '- WooCommerce Store API: https://developer.woocommerce.com/docs/apis/store-api/',
            '',
        );
        return implode("\n", $lines);
    }
}
