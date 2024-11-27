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


    protected function tearDown(): void {
        wp::tearDown();
        parent::tearDown();
    }
}
?>