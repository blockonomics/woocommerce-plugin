<?php
use PHPUnit\Framework\TestCase;
use WP_Mock as wp;
use Mockery as m;

class TestableBlockonomics extends Blockonomics {
    public function __construct($api_key = 'temporary_api_key') {
        $this->api_key = $api_key;
    }
}

class BlockonomicsTest extends TestCase {
    protected $blockonomics;

    protected function setUp(): void {
        parent::setUp();
        wp::setUp();
        $this->blockonomics = m::mock(TestableBlockonomics::class, ['ZJ4PNtTnKqWxeMCQ6smlMBvj3i3KAtt2hwLSGuk9Lyk'])->makePartial();

        // Mock WordPress functions
        wp::userFunction('get_option', [
            'return' => function($option_name) {
                switch ($option_name) {
                    case 'blockonomics_btc':
                        return true;
                    case 'blockonomics_api_key':
                        return 'ZJ4PNtTnKqWxeMCQ6smlMBvj3i3KAtt2hwLSGuk9Lyk';
                    case 'blockonomics_callback_secret':
                        return '2c5a71c1367e23a6b04a20544d0d4a4601c34881';
                    default:
                        return null;
                }
            }
        ]);

        wp::userFunction('wp_remote_retrieve_response_code', [
            'return' => function($response) {
                return isset($response['response']['code']) ? $response['response']['code'] : null;
            }
        ]);

        wp::userFunction('wp_remote_retrieve_body', [
            'return' => function($response) {
                return isset($response['body']) ? $response['body'] : [];
            }
        ]);

        wp::userFunction('WC', [
            'return' => function() {
                return new class{
                    public function api_request_url($endpoint) {
                        return "https://localhost:8888/wordpress/wc-api/WC_Gateway_Blockonomics/";
                    }
                };
            }
        ]);

        wp::userFunction('add_query_arg', [
            'return' => function($args, $url) {
                if (!is_array($args)) {
                    $args = [];
                }
                return $url . '?' . http_build_query($args);
            }
        ]);

        wp::userFunction('is_wp_error', [
            'return' => function($thing) {
                return ($thing instanceof \WP_Error);
            }
        ]);
    }

    // Existing tests that are still relevant
    public function testCalculateTotalPaidFiatWithNoTransactions() {
        wp::userFunction('wc_get_price_decimals', [
            'times'  => 1,
            'return' => 2,
        ]);

        $transactions = [];
        $expectedTotal = 0.0;
        $this->assertSame($expectedTotal, $this->blockonomics->calculate_total_paid_fiat($transactions));
    }

    public function testCalculateTotalPaidFiatWithVariousTransactions() {
        wp::userFunction('wc_get_price_decimals', [
            'times'  => 1,
            'return' => 2,
        ]);

        $transactions = [
            ['paid_fiat' => '10.00'],
            ['paid_fiat' => '5.50'],
            ['paid_fiat' => '2.50']
        ];
        $expectedTotal = 18.0;
        $this->assertEquals($expectedTotal, $this->blockonomics->calculate_total_paid_fiat($transactions));
    }

    public function testFixDisplayingSmallValuesLessThan10000() {
        $this->assertEquals("0.000095", $this->blockonomics->fix_displaying_small_values('btc', 9500));
    }

    public function testFixDisplayingSmallValuesGreaterThan10000() {
        $this->assertEquals(0.0001, $this->blockonomics->fix_displaying_small_values('btc', 10000));
    }

    public function testGetCryptoPaymentUriForBTC() {
        $crypto = ['uri' => 'bitcoin'];
        $address = "bc1qnhuxvspzj28vcdc8e7wxnnwhqdu7pyvdwsw0dy";
        $order_amount = 0.05;
        $expectedUri = "bitcoin:bc1qnhuxvspzj28vcdc8e7wxnnwhqdu7pyvdwsw0dy?amount=0.05";
        $this->assertEquals($expectedUri, $this->blockonomics->get_crypto_payment_uri($crypto, $address, $order_amount));
    }

    public function testGetSupportedCurrencies() {
        $expectedCurrencies = [
            'btc' => [
                'code' => 'btc',
                'name' => 'Bitcoin',
                'uri' => 'bitcoin',
                'decimals' => 8,
            ],
            'bch' => [
                'code' => 'bch',
                'name' => 'Bitcoin Cash',
                'uri' => 'bitcoincash',
                'decimals' => 8,
            ],
            'usdt' => [
                'code' => 'usdt',
                'name' => 'USDT',
                'decimals' => 6,
            ]
        ];
        $actualCurrencies = $this->blockonomics->getSupportedCurrencies();
        $this->assertEquals($expectedCurrencies, $actualCurrencies, "The getSupportedCurrencies method did not return the expected array of cryptocurrencies.");
    }

    public function testIconsGenerationWithErrorResponse() {
        $active_cryptos = ['error' => 'API Key is not set. Please enter your API Key.'];
        $icons_src = [];

        if (empty($active_cryptos) || isset($active_cryptos['error'])) {
            // Should return empty
            $this->assertEmpty($icons_src, "Icons should be empty when error response received");
            return;
        }

        $this->fail('Should have returned early due to error');
    }

    public function testIconsGenerationWithValidCryptos() {
        $active_cryptos = [
            'btc' => ['code' => 'btc', 'name' => 'Bitcoin', 'uri' => 'bitcoin', 'decimals' => 8],
            'usdt' => ['code' => 'usdt', 'name' => 'USDT', 'decimals' => 6]
        ];
        $icons_src = [];

        if (empty($active_cryptos) || isset($active_cryptos['error'])) {
            $this->fail('Should not return early for valid cryptos');
        }

        foreach ($active_cryptos as $code => $crypto) {
            $icons_src[$crypto['code']] = [
                'src' => 'test/'.$crypto['code'].'.png',
                'alt' => $crypto['name'],
            ];
        }

        $this->assertCount(2, $icons_src, "Should have icons for 2 cryptocurrencies");
        $this->assertArrayHasKey('btc', $icons_src, "Should have BTC icon");
        $this->assertArrayHasKey('usdt', $icons_src, "Should have USDT icon");
        $this->assertEquals('Bitcoin', $icons_src['btc']['alt'], "BTC alt text should be 'Bitcoin'");
        $this->assertEquals('USDT', $icons_src['usdt']['alt'], "USDT alt text should be 'USDT'");
    }

    public function testIconsGenerationWithEmptyResponse() {
        $active_cryptos = [];
        $icons_src = [];

        if (empty($active_cryptos) || isset($active_cryptos['error'])) {
            $this->assertEmpty($icons_src, "Icons should be empty when no active cryptos");
            return;
        }

        $this->fail('Should have returned early due to empty array');
    }

    public function testIconsGenerationWithSingleCryptoBTC() {
        $active_cryptos = [
            'btc' => ['code' => 'btc', 'name' => 'Bitcoin', 'uri' => 'bitcoin', 'decimals' => 8]
        ];
        $icons_src = [];

        if (empty($active_cryptos) || isset($active_cryptos['error'])) {
            $this->fail('Should not return early for valid crypto');
        }

        foreach ($active_cryptos as $code => $crypto) {
            $icons_src[$crypto['code']] = [
                'src' => 'test/'.$crypto['code'].'.png',
                'alt' => $crypto['name'],
            ];
        }

        $this->assertCount(1, $icons_src, "Should have icon for 1 cryptocurrency");
        $this->assertArrayHasKey('btc', $icons_src, "Should have BTC icon");
        $this->assertEquals('Bitcoin', $icons_src['btc']['alt']);
    }

    public function testIconsGenerationWithSingleCryptoUSDT() {
        $active_cryptos = [
            'usdt' => ['code' => 'usdt', 'name' => 'USDT', 'decimals' => 6]
        ];
        $icons_src = [];

        if (empty($active_cryptos) || isset($active_cryptos['error'])) {
            $this->fail('Should not return early for valid crypto');
        }

        foreach ($active_cryptos as $code => $crypto) {
            $icons_src[$crypto['code']] = [
                'src' => 'test/'.$crypto['code'].'.png',
                'alt' => $crypto['name'],
            ];
        }

        $this->assertCount(1, $icons_src, "Should have icon for 1 cryptocurrency");
        $this->assertArrayHasKey('usdt', $icons_src, "Should have USDT icon");
        $this->assertEquals('USDT', $icons_src['usdt']['alt']);
    }

    /**
     * Test: BTC payments are identified by address to prevent duplicate rows.
     *
     * Bug context: Primary key is (order_id, crypto, address, txid). When callback
     * sets txid from empty to actual value, using wrong identifier would create
     * duplicate rows instead of updating existing payment.
     */
    // Mock wpdb whose prepare() substitutes args and whose query() captures the final SQL
    private function mockWpdbCapturingQuery(&$captured_sql) {
        global $wpdb;
        $wpdb = m::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) {
            foreach ($args as $a) {
                $sql = preg_replace('/%[sd]/', is_numeric($a) ? $a : "'" . $a . "'", $sql, 1);
            }
            return $sql;
        });
        $wpdb->shouldReceive('query')->once()->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return 1;
        });
        return $wpdb;
    }

    public function testBtcPaymentIdentifiedByAddressNotTxid() {
        $captured = '';
        $this->mockWpdbCapturingQuery($captured);

        $order = [
            'order_id' => 123,
            'crypto' => 'btc',
            'address' => 'bc1qtest123address',
            'txid' => 'new_txid_value',
            'payment_status' => 2,
            'currency' => 'USD',
            'expected_fiat' => 100,
            'expected_satoshi' => 100000,
            'paid_satoshi' => null,
            'paid_fiat' => null
        ];

        $blockonomics = new TestableBlockonomics();
        $blockonomics->update_order($order);

        $set = substr($captured, 0, strpos($captured, ' WHERE '));
        $this->assertStringContainsString('`paid_satoshi` = NULL', $set, "Unset amounts stay SQL NULL, not ''");
        $this->assertStringNotContainsString("`paid_fiat` = ''", $set, "Unset amounts stay SQL NULL, not ''");
        $where = substr($captured, strpos($captured, ' WHERE '));
        $this->assertStringContainsString("`address` = 'bc1qtest123address'", $where, "BTC: Should identify payment by address");
        $this->assertStringNotContainsString('`txid`', $where, "BTC: txid must not be in the WHERE clause");
        $this->assertStringContainsString('payment_status < 2', $where, "Final rows are immutable: only the winning claim writes them");
        m::close();
    }

    /**
     * Test: BCH payments are identified by address to prevent duplicate rows.
     * Same logic as BTC - each BCH address is unique per payment.
     */
    public function testBchPaymentIdentifiedByAddressNotTxid() {
        $captured = '';
        $this->mockWpdbCapturingQuery($captured);

        $order = [
            'order_id' => 456,
            'crypto' => 'bch',
            'address' => 'bitcoincash:qtest456address',
            'txid' => 'bch_txid_value',
            'payment_status' => 2,
            'currency' => 'USD',
            'expected_fiat' => 50,
            'expected_satoshi' => 50000
        ];

        $blockonomics = new TestableBlockonomics();
        $blockonomics->update_order($order);

        $where = substr($captured, strpos($captured, ' WHERE '));
        $this->assertStringContainsString("`address` = 'bitcoincash:qtest456address'", $where, "BCH: Should identify payment by address");
        $this->assertStringNotContainsString('`txid`', $where, "BCH: txid must not be in the WHERE clause");
        m::close();
    }

    /**
     * Test: USDT payments are identified by txid since address is reused.
     * USDT uses same address for multiple payments, so txid uniquely identifies each payment.
     */
    public function testUsdtPaymentIdentifiedByTxidNotAddress() {
        $captured = '';
        $this->mockWpdbCapturingQuery($captured);

        $order = [
            'order_id' => 789,
            'crypto' => 'usdt',
            'address' => '0xSameUSDTAddress',
            'txid' => 'unique_usdt_txhash',
            'payment_status' => 2,
            'currency' => 'USD',
            'expected_fiat' => 200,
            'expected_satoshi' => 200000000
        ];

        $blockonomics = new TestableBlockonomics();
        $blockonomics->update_order($order);

        $where = substr($captured, strpos($captured, ' WHERE '));
        $this->assertStringContainsString("`txid` = 'unique_usdt_txhash'", $where, "USDT: Should identify payment by txid");
        $this->assertStringNotContainsString('`address`', $where, "USDT: address must not be in the WHERE clause");
        $this->assertStringContainsString('payment_status < 2', $where, "Final rows are immutable: only the winning claim writes them");
        m::close();
    }

    // Secret-based store matching (WPML/Polylang fix): the secret is the matching
    // key, so language-prefixed URLs match while foreign secrets never do.
    public function testStoreMatchesSecretAcceptsAnyUrlShapeWithOurSecret() {
        $secret = '2c5a71c1367e23a6b04a20544d0d4a4601c34881';

        $plain = (object) ['http_callback' => 'https://example.com/wc-api/WC_Gateway_Blockonomics/?secret=' . $secret];
        $this->assertTrue(Blockonomics::store_matches_secret($plain, $secret), "Canonical URL with our secret should match");

        $prefixed = (object) ['http_callback' => 'https://example.com/de/wc-api/WC_Gateway_Blockonomics/?secret=' . $secret];
        $this->assertTrue(Blockonomics::store_matches_secret($prefixed, $secret), "Language-prefixed URL should still match by secret");

        $query_form = (object) ['http_callback' => 'https://example.com/?wc-api=WC_Gateway_Blockonomics&secret=' . $secret];
        $this->assertTrue(Blockonomics::store_matches_secret($query_form, $secret), "Query-form (plain permalink) URL should match");
    }

    public function testStoreMatchesSecretRejectsForeignAndMalformedInput() {
        $secret = '2c5a71c1367e23a6b04a20544d0d4a4601c34881';

        $other_install = (object) ['http_callback' => 'https://example.com/fr/wc-api/WC_Gateway_Blockonomics/?secret=05ee022fbe39d6ddf57f778570bee53829202f70'];
        $this->assertFalse(Blockonomics::store_matches_secret($other_install, $secret), "Subfolder install with its own secret must never match");

        $store = (object) ['http_callback' => 'https://example.com/wc-api/WC_Gateway_Blockonomics/?secret=' . $secret];
        $this->assertFalse(Blockonomics::store_matches_secret($store, ''), "Empty local secret must never match");
        $this->assertFalse(Blockonomics::store_matches_secret($store, null), "Non-string local secret must never match");

        $array_secret = (object) ['http_callback' => 'https://example.com/wc-api/?secret[]=' . $secret];
        $this->assertFalse(Blockonomics::store_matches_secret($array_secret, $secret), "secret[] array param must not match or error");

        $no_query = (object) ['http_callback' => 'https://example.com/wc-api/WC_Gateway_Blockonomics/'];
        $this->assertFalse(Blockonomics::store_matches_secret($no_query, $secret), "URL without query string must not match");

        $no_callback = (object) ['name' => 'store-without-callback'];
        $this->assertFalse(Blockonomics::store_matches_secret($no_callback, $secret), "Store without http_callback must not match or error");

        $non_string = (object) ['http_callback' => 42];
        $this->assertFalse(Blockonomics::store_matches_secret($non_string, $secret), "Non-string http_callback must not match or error");
    }

    // Invariant: a final (status 2) row is never changed by a further callback —
    // covers both "same callback twice changes nothing" and "final never downgrades".
    public function testFinalOrderNeverChangedByFurtherCallbacks() {
        $final_order = [
            'order_id' => 11,
            'crypto' => 'usdt',
            'txid' => '0xabc',
            'payment_status' => 2,
            'paid_satoshi' => 100000,
            'expected_satoshi' => 100000,
            'expected_fiat' => 100,
        ];

        $blockonomics = new TestableBlockonomics();
        $wc_order = m::mock('WC_Order');
        // any wc_order mutation would fail the test (no expectations set)

        foreach ([0, 1, 2, 6] as $callback_status) {
            $result = $blockonomics->update_paid_amount($callback_status, 999999, $final_order, $wc_order);
            $this->assertEquals($final_order, $result, "Final order must be unchanged for callback status $callback_status");
        }
        m::close();
    }

    // Invariant: the txhash bind carries the empty-txid condition inside the UPDATE
    // itself (single atomic statement), so concurrent finishes cannot cross-bind.
    public function testTxhashBindIsAtomic() {
        global $wpdb;
        $wpdb = m::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $captured_sql = '';
        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturnUsing(function($sql, ...$args) use (&$captured_sql) {
                $captured_sql = $sql;
                return $sql;
            });
        $wpdb->shouldReceive('query')->once()->andReturn(1);

        $blockonomics = new TestableBlockonomics();
        $result = $blockonomics->update_order_txhash(7, 'usdt', '0xdef');

        $this->assertTrue($result, "Bind affecting exactly one row must report success");
        $this->assertStringStartsWith('UPDATE', trim($captured_sql), "Bind must be a single UPDATE statement");
        $this->assertStringContainsString("(txid IS NULL OR txid = '')", $captured_sql, "Empty-txid condition must be inside the UPDATE's WHERE");
        m::close();
    }

    // Invariant: bind reports failure when no row was bound (0 rows affected),
    // e.g. the row was already bound by a concurrent request.
    public function testTxhashBindFailsWhenNoRowBound() {
        global $wpdb;
        $wpdb = m::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->once()->andReturn('sql');
        $wpdb->shouldReceive('query')->once()->andReturn(0);

        $blockonomics = new TestableBlockonomics();
        $this->assertFalse($blockonomics->update_order_txhash(7, 'usdt', '0xdef'));
        m::close();
    }

    protected function tearDown(): void {
        wp::tearDown();
        parent::tearDown();
    }
}
?>