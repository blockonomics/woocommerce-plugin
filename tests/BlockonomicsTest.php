<?php
use PHPUnit\Framework\TestCase;
use WP_Mock as wp;
use Mockery as m;

class TestableBlockonomics extends Blockonomics {
    public function __construct($api_key = 'temporary_api_key') {
        // Directly use the provided API key or a default one, bypassing get_option
        $this->api_key = $api_key;
    }
}

class BlockonomicsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        wp::setUp();
        // $this->blockonomics = new TestableBlockonomics();
        $this->blockonomics = m::mock(TestableBlockonomics::class, ['ZJ4PNtTnKqWxeMCQ6smlMBvj3i3KAtt2hwLSGuk9Lyk'])->makePartial();
        // Mock WordPress get_option function
        wp::userFunction('get_option', [
            'return' => function($option_name) {
                switch ($option_name) {
                    case 'blockonomics_bch':
                        return true; // Assume BCH is enabled
                    case 'blockonomics_btc':
                        return true; // Assume BTC is disabled
                    case 'blockonomics_api_key':
                        return 'ZJ4PNtTnKqWxeMCQ6smlMBvj3i3KAtt2hwLSGuk9Lyk'; // Dummy API key
                    case 'blockonomics_callback_secret':
                        return '2c5a71c1367e23a6b04a20544d0d4a4601c34881'; // Dummy callback secret
                    default:
                        return null;
                }
            }
        ]);
        // Mock wp_remote_retrieve_response_code and wp_remote_retrieve_body
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
        // Mock WC() function
        wp::userFunction('WC', [
            'return' => function() {
                return new class{
                    public function api_request_url($endpoint) {
                        return "https://localhost:8888/wordpress/wc-api/WC_Gateway_Blockonomics/";
                        //TODO: look for variations in http cases
                    }
                    };
            }
        ]);

        // Mock add_query_arg function i.e. appends query arguments to a URL
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

    public function testCalculateTotalPaidFiatWithNoTransactions() {
        // Mocking wc_get_price_decimals to return 2 decimals
        wp::userFunction('wc_get_price_decimals', [
            'times'  => 1, // Ensure this function is called exactly once
            'return' => 2,
        ]);

        $transactions = [];
        $expectedTotal = 0.0;
        $this->assertSame($expectedTotal, $this->blockonomics->calculate_total_paid_fiat($transactions));
    }

    public function testCalculateTotalPaidFiatWithVariousTransactions() {
        // Mocking wc_get_price_decimals to return 2 decimals
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

    public function testGetCryptoPaymentUriForBCH() {
        $crypto = ['uri' => 'bitcoincash'];
        $address = "qr3fmxznghk8h0af7mpj0ay590ev5js72chef5md3w";
        $order_amount = 0.1;
        $expectedUri = "bitcoincash:qr3fmxznghk8h0af7mpj0ay590ev5js72chef5md3w?amount=0.1";
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

    public function testSetHeadersWithEmptyApiKey() {
        $reflection = new \ReflectionClass($this->blockonomics);
        $method = $reflection->getMethod('set_headers');
        $method->setAccessible(true); // Makes private method accessible
    
        $result = $method->invokeArgs($this->blockonomics, ['']);
        $this->assertEquals('', $result);
    }
    
    public function testSetHeadersWithNonEmptyApiKey() {
        $reflection = new \ReflectionClass($this->blockonomics);
        $method = $reflection->getMethod('set_headers');
        $method->setAccessible(true); // Makes private method accessible
    
        $apiKey = "your_api_key";
        $expectedHeader = 'Authorization: Bearer ' . $apiKey;
        $result = $method->invokeArgs($this->blockonomics, [$apiKey]);
        $this->assertEquals($expectedHeader, $result);
    }
    
    private function mockActiveCurrencies(array $cryptos) {
        $activeCurrencies = [];
        foreach ($cryptos as $crypto) {
            switch ($crypto) {
                case 'btc':
                    $activeCurrencies['btc'] = ['code' => 'btc', 'name' => 'Bitcoin', 'uri' => 'bitcoin'];
                    break;
                case 'bch':
                    $activeCurrencies['bch'] = ['code' => 'bch', 'name' => 'Bitcoin Cash', 'uri' => 'bitcoincash'];
                    break;
            }
        }
        $this->blockonomics->shouldReceive('getActiveCurrencies')
            ->once()
            ->andReturn($activeCurrencies);
    }

    // Define different mock responses for callback API
    private function getMockResponse($type) {
        switch ($type) {
            case 'incorrect_api_key':
                return [
                    'response' => [
                        'code' => 401,
                        'message' => 'Unauthorized'
                    ]
                ];
            case 'no_stores_added':
                return [
                    'response' => [
                        'code' => 200,
                        'message' => 'OK'
                    ],
                    'body' => json_encode([])  // no stores added
                ];
            case 'one_store_different_callback':
                return [
                    'response' => [
                        'code' => 200,
                        'message' => 'OK'
                    ],
                    'body' => json_encode([['address' => 'zpub6o4sVoUnjZ8qWRtWUFHL9TWKfStSo2rquV6LsHJWNDbrEP5L2CAG849xpXJXzsm4iNTbKGS6Q4gxK6mYQqfd1JCP3KKYco2DBxTrjpywWxt', 'tag' => 't_shirt_store_wordpress', 'callback' => 'http://localhost:8888/wordpress/wc-api/WC_Gateway_Blockonomics/?secret=30d8ea3494a820e37ffb46c801a6cce96cc2023e']])
                ];
            case 'one_store_no_callback':
                return [
                    'response' => [
                        'code' => 200,
                        'message' => 'OK'
                    ],
                    'body' => json_encode([['address' => 'zpub6o4sVoUnjZ8qWRtWUFHL9TWKfStSo2rquV6LsHJWNDbrEP5L2CAG849xpXJXzsm4iNTbKGS6Q4gxK6mYQqfd1JCP3KKYco2DBxTrjpywWxt', 'tag' => 't_shirt_store_wordpress', 'callback' => '']])
                ];
            default:
                return [
                    'response' => [
                        'code' => 400,
                        'body' => 'Bad Request'
                    ]
                ];
        }
    }

    private function mockGetCallbacks(array $cryptos, $responseType){
        $mockedResponse = $this->getMockResponse($responseType);
        foreach ($cryptos as $crypto) {
            $this->blockonomics->shouldReceive('get_callbacks')
                ->with($crypto)
                ->andReturn($mockedResponse);
        }
    }
    private function mockUpdateCallbackResponse() {
        wp::userFunction('wp_remote_post', [
            'return' => [
                'response' => [
                    'code' => 200,
                    'message' => 'OK'
                ],
                'body' => ''
            ]
        ]);
    }

    // Testing Test Setup functionality
    // Case 1: Incorrect API Key is set and only BCH is enabled
    public function testBCHWithIncorrectAPIKeySetup() {
        // Mock getActiveCurrencies to return only BCH
        $this->mockActiveCurrencies(['bch']);
        // Use getMockResponse to get the mock response for incorrect API key
        $mockedResponse = $this->getMockResponse('incorrect_api_key');
        // Mock the response for get_callbacks to simulate incorrect API key
        $this->mockGetCallbacks(['bch'], 'incorrect_api_key');
        // Execute testSetup and capture the results
        $result = $this->blockonomics->testSetup();
        // Assert that the error "API Key is incorrect" is returned for BCH
        $this->assertEquals(['bch' => 'API Key is incorrect'], $result);
    }

    // Case 2: Incorrect API Key is set and only BTC is enabled
    public function testBTCWithIncorrectAPIKeySetup() {
        // Mock getActiveCurrencies to return only BTC
        $this->mockActiveCurrencies(['btc']);
        $mockedResponse = $this->getMockResponse('incorrect_api_key');        
        $this->mockGetCallbacks(['btc'], 'incorrect_api_key');
        $result = $this->blockonomics->testSetup();
        $this->assertEquals(['btc' => 'API Key is incorrect'], $result);
    }

    // Case 3: BTC & BCH are enabled and API key set is incorrect
    public function testBTCandBCHWithIncorrectAPIKeySetup() {
        // Mock getActiveCurrencies to return both BTC and BCH
        $this->mockActiveCurrencies(['btc', 'bch']);
        $mockedResponse = $this->getMockResponse('incorrect_api_key');        
        $this->mockGetCallbacks(['btc','bch'], 'incorrect_api_key');
        $result = $this->blockonomics->testSetup();
        // Assert that the error "API Key is incorrect" is returned for both BTC and BCH
        $this->assertEquals(['btc' => 'API Key is incorrect', 'bch' => 'API Key is incorrect'], $result);
    }

    //Case 4: only BCH is enabled, API Key is correct but no xpub is added
    public function testBCHWithNoStore() {
        // Mock getActiveCurrencies to return only BCH
        $this->mockActiveCurrencies(['bch']);
        // Use getMockResponse to simulate no stores added with a correct API key
        $mockedResponse = $this->getMockResponse('no_stores_added');
        $this->mockGetCallbacks(['bch'], 'no_stores_added');
        // Execute testSetup and capture the results
        $result = $this->blockonomics->testSetup();
        // Assert that the error message for no store added is returned for BCH
        $this->assertEquals(['bch' => 'Please add a new store on blockonomics website'], $result);
    }

    //Case 5: only BTC is enabled, API Key is correct but no xpub is added
    public function testBTCWithNoStore() {
        // Mock getActiveCurrencies to return only BTC
        $this->mockActiveCurrencies(['btc']);
        // Use getMockResponse to simulate no stores added with a correct API key
        $mockedResponse = $this->getMockResponse('no_stores_added');
        $this->mockGetCallbacks(['btc'], 'no_stores_added');
        // Execute testSetup and capture the results
        $result = $this->blockonomics->testSetup();
        // Assert that the error message for no store added is returned for BTC
        $this->assertEquals(['btc' => 'Please add a new store on blockonomics website'], $result);
    }

    // Case 6: both BTC, BCH enabled , API Key is correct but no xpub added for any of BTC/BCH
    public function testBTCandBCHWithNoStore() {
        // Mock active currencies for both BTC and BCH
        $this->mockActiveCurrencies(['btc', 'bch']);
        // Use getMockResponse to simulate no stores added with a correct API key
        $mockedResponse = $this->getMockResponse('no_stores_added');
        $this->mockGetCallbacks(['btc', 'bch'], 'no_stores_added');
        $result = $this->blockonomics->testSetup();
        // Assert that the error message for no store added is returned for both BTC and BCH
        $this->assertEquals(['btc' => 'Please add a new store on blockonomics website', 'bch' => 'Please add a new store on blockonomics website'], $result);
    }

    // API key is correct, 1 xpub is added and callback url is not set
    public function testBTCWithOneStoreNoCallback() {
        // Mock active currencies to return only BTC
        $this->mockActiveCurrencies(['btc']);

        // Simulate the response where one store is added without any callback URL
        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('btc')
            ->andReturn($this->getMockResponse('one_store_no_callback'));

        // Mock the update callback response
        $this->mockUpdateCallbackResponse();

        // Execute testSetup and capture the results
        $result = $this->blockonomics->testSetup();

        // Assert that no errors are returned
        $this->assertFalse($result['btc']);
    }

    public function testBCHWithOneStoreNoCallback() {
        // Mock active currencies to return only BCH
        $this->mockActiveCurrencies(['bch']);

        // Simulate the response where one store is added without any callback URL
        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('bch')
            ->andReturn($this->getMockResponse('one_store_no_callback'));

        // Mock the update callback response
        $this->mockUpdateCallbackResponse();

        // Execute testSetup and capture the results
        $result = $this->blockonomics->testSetup();

        // Assert that no errors are returned
        $this->assertFalse($result['bch']);
    }

    public function testBTCandBCHWithOneStoreNoCallback() {
        // Mock active currencies to return only BTC
        $this->mockActiveCurrencies(['btc','bch']);

        // Simulate the response where one store is added without any callback URL
        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('btc')
            ->andReturn($this->getMockResponse('one_store_no_callback'));

        $this->blockonomics->shouldReceive('get_callbacks')
            ->with('bch')
            ->andReturn($this->getMockResponse('one_store_no_callback'));

        // Mock the update callback response
        $this->mockUpdateCallbackResponse();

        // Execute testSetup and capture the results
        $result = $this->blockonomics->testSetup();

        // Assert that no errors are returned
        $this->assertFalse($result['btc']);
        $this->assertFalse($result['bch']);
    }

    // Tests focused on Callbacks functionality

    public function testUpdateCallbackForBTC() {
        $callbackUrl = 'https://example.com/callback';
        $crypto = 'btc';
        $xpub = 'xpub12345';
        $expectedUrl = Blockonomics::SET_CALLBACK_URL;
        $expectedBody = json_encode(['callback' => $callbackUrl, 'xpub' => $xpub]);
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

    public function testUpdateCallbackForBCH() {
        $callbackUrl = 'https://example.com/callback';
        $crypto = 'bch';
        $xpub = 'xpub12345';
        $expectedUrl = Blockonomics::BCH_SET_CALLBACK_URL;
        $expectedBody = json_encode(['callback' => $callbackUrl, 'xpub' => $xpub]);
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
        $expectedUrl = Blockonomics::SET_CALLBACK_URL;
        $expectedBody = json_encode(['callback' => $callbackUrl, 'xpub' => $xpub]);
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

        // Mock WordPress get_option function
        wp::userFunction('get_option', [
            'args' => ['blockonomics_callback_secret'],
            'return' => $callbackSecret,
        ]);

        // Mock WordPress WC function
        wp::userFunction('WC', [
            'return' => (object)['api_request_url' => function() use ($apiUrl) {
                return $apiUrl;
            }]
        ]);

        // Mock WordPress add_query_arg function
        wp::userFunction('add_query_arg', [
            'args' => ['secret', $callbackSecret, $apiUrl],
            'return' => $wordpressCallbackUrl,
        ]);

        // Directly call the examine_server_callback_urls function
        $result = $this->blockonomics->examine_server_callback_urls($responseBody, $crypto);

        // Assert the expected error
        $this->assertEquals('Please add a new store on blockonomics website', $result);
    }

    public function testExamineServerCallbackUrlsWithNoMatch() {
        $callbackSecret = 'secret123';
        $apiUrl = 'https://example.com/wc-api/WC_Gateway_Blockonomics';
        $wordpressCallbackUrl = $apiUrl . '?secret=' . $callbackSecret;
        $responseBody = [
            (object) ['callback' => 'https://otherurl.com', 'address' => 'xpub1'],
            (object) ['callback' => 'https://anotherurl.com', 'address' => 'xpub2']
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
