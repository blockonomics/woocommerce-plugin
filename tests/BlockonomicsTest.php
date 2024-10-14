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
        $this->assertEquals("0.000095", $this->blockonomics->fix_displaying_small_values(9500));
    }

    public function testFixDisplayingSmallValuesGreaterThan10000() {
        $this->assertEquals(0.0001, $this->blockonomics->fix_displaying_small_values(10000));
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
                'uri' => 'bitcoin'
            ],
            'bch' => [
                'code' => 'bch',
                'name' => 'Bitcoin Cash',
                'uri' => 'bitcoincash'
            ]
        ];
        $actualCurrencies = $this->blockonomics->getSupportedCurrencies();
        $this->assertEquals($expectedCurrencies, $actualCurrencies, "The getSupportedCurrencies method did not return the expected array of cryptocurrencies.");
    }

    // Modified test setup tests
    public function testBTCWithIncorrectAPIKeySetup() {
        $this->blockonomics->shouldReceive('getActiveCurrencies')
            ->once()
            ->andReturn(['btc' => ['code' => 'btc', 'name' => 'Bitcoin', 'uri' => 'bitcoin']]);

        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('btc')
            ->andReturn([
                'response' => [
                    'code' => 401,
                    'message' => 'Unauthorized'
                ]
            ]);

        $result = $this->blockonomics->testSetup();
        $this->assertEquals(['btc' => 'API Key is incorrect'], $result);
    }

    public function testBTCWithNoStore() {
        $this->blockonomics->shouldReceive('getActiveCurrencies')
            ->once()
            ->andReturn(['btc' => ['code' => 'btc', 'name' => 'Bitcoin', 'uri' => 'bitcoin']]);

        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('btc')
            ->andReturn([
                'response' => [
                    'code' => 200,
                    'message' => 'OK'
                ],
                'body' => json_encode([])
            ]);

        $result = $this->blockonomics->testSetup();
        $this->assertEquals(['btc' => 'Please add a new store on blockonomics website'], $result);
    }

    public function testBTCWithOneStoreNoCallback() {
        $this->blockonomics->shouldReceive('getActiveCurrencies')
            ->once()
            ->andReturn(['btc' => ['code' => 'btc', 'name' => 'Bitcoin', 'uri' => 'bitcoin']]);

        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('btc')
            ->andReturn([
                'response' => [
                    'code' => 200,
                    'message' => 'OK'
                ],
                'body' => json_encode([['address' => 'zpub6o4sVoUnjZ8qWRtWUFHL9TWKfStSo2rquV6LsHJWNDbrEP5L2CAG849xpXJXzsm4iNTbKGS6Q4gxK6mYQqfd1JCP3KKYco2DBxTrjpywWxt', 'tag' => 't_shirt_store_wordpress', 'callback' => '']])
            ]);

        wp::userFunction('wp_remote_post', [
            'return' => [
                'response' => [
                    'code' => 200,
                    'message' => 'OK'
                ],
                'body' => ''
            ]
        ]);

        $result = $this->blockonomics->testSetup();
        $this->assertFalse($result['btc']);
    }

    // Existing callback tests
    public function testUpdateCallbackForBTC() {
        $callbackUrl = 'https://example.com/callback';
        $crypto = 'btc';
        $xpub = 'xpub12345';
        wp::userFunction('wp_remote_post', [
            'return' => [
                'response' => [
                    'code' => 200,
                    'message' => 'OK'
                ],
                'body' => json_encode(['status' => 'success'])
            ]
        ]);
        $result = $this->blockonomics->update_callback($callbackUrl, $crypto, $xpub);
        $this->assertEquals(200, $result->response_code);
    }

    public function testUpdateCallbackWithInvalidResponse() {
        $callbackUrl = 'https://example.com/callback';
        $crypto = 'btc';
        $xpub = 'xpub12345';
        wp::userFunction('wp_remote_post', [
            'return' => [
                'response' => [
                    'code' => 500,
                    'message' => 'Internal Server Error'
                ],
                'body' => ''
            ]
        ]);
        $result = $this->blockonomics->update_callback($callbackUrl, $crypto, $xpub);
        $this->assertEquals(500, $result->response_code);
    }

    public function testExamineServerCallbackUrlsNoMatchNoEmptyCallback() {
        $callbackSecret = 'secret123';
        $apiUrl = 'https://example.com/wc-api/WC_Gateway_Blockonomics';
        $wordpressCallbackUrl = $apiUrl . '?secret=' . $callbackSecret;
        $responseBody = [
            (object) ['callback' => 'https://anotherurl.com', 'address' => 'xpub1'],
            (object) ['callback' => 'https://otherurl.com', 'address' => 'xpub2']
        ];

        $crypto = 'btc';

        wp::userFunction('get_option', [
            'args' => ['blockonomics_callback_secret'],
            'return' => $callbackSecret,
        ]);

        wp::userFunction('WC', [
            'return' => (object)['api_request_url' => function() use ($apiUrl) {
                return $apiUrl;
            }]
        ]);

        wp::userFunction('add_query_arg', [
            'args' => ['secret', $callbackSecret, $apiUrl],
            'return' => $wordpressCallbackUrl,
        ]);

        $result = $this->blockonomics->examine_server_callback_urls($responseBody, $crypto);

        $this->assertEquals('Please add a new store on blockonomics website', $result);
    }

    public function testCheckCallbackUrlsOrSetOne() {
        $crypto = 'btc';
        $response = Mockery::mock('response');
        $this->blockonomics->shouldReceive('check_get_callbacks_response_code')
            ->with($response, $crypto)
            ->andReturn('');
        $this->blockonomics->shouldReceive('check_get_callbacks_response_body')
            ->with($response, $crypto)
            ->andReturn('error');
        $result = $this->blockonomics->check_callback_urls_or_set_one($crypto, $response);
        $this->assertEquals('error', $result);
    }

    public function testCheckCallbackUrlsOrSetOneWithError() {
        $crypto = 'btc';
        $response = Mockery::mock();
        $this->blockonomics->shouldReceive('check_get_callbacks_response_code')
            ->with($response, $crypto)
            ->andReturn('error_code');

        $result = $this->blockonomics->check_callback_urls_or_set_one($crypto, $response);

        $this->assertEquals('error_code', $result);
    }

    protected function tearDown(): void {
        wp::tearDown();
        parent::tearDown();
    }
}
?>