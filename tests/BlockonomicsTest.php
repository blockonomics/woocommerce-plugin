<?php
use PHPUnit\Framework\TestCase;
use WP_Mock as wp;

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
        $this->blockonomics = new TestableBlockonomics();
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

    protected function tearDown(): void {
        wp::tearDown();
        parent::tearDown();
    }

}
