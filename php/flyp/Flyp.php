<?php
namespace FlypMe;

class FlypMe
{
    private static $endpoint = "https://flyp.me/api/v1/";
    private static $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ];
    public function __construct($endpoint = '', $headers = [])
    {
        if (!empty($endpoint)) {
            self::$endpoint = $endpoint;
        }
        if (!empty($headers)) {
            self::$headers = $headers;
        }
    }
    // public methods
    /**
     * @return mixed
     * @throws Exception
     */
    public function currencies()
    {
        return $this->get('currencies');
    }
    /**
     * @return mixed
     * @throws Exception
     */
    public function dataExchangeRates()
    {
        return $this->get('data/exchange_rates');
    }
    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return mixed
     * @throws Exception
     */
    public function orderLimits($fromCurrency = 'BTC', $toCurrency = 'ETH')
    {
        return $this->get("order/limits/{$fromCurrency}/{$toCurrency}");
    }
    /**
     * @param $from_currency
     * @param $to_currency
     * @param $amount
     * @param string $destination
     * @param string $refund_address
     * @param string $type
     * @return mixed
     * @throws Exception
     */
    public function orderNew($from_currency, $to_currency, $amount, $destination = '', $refund_address = '', $type = "ordered_amount")
    {
        $body = [
            "order" => [
                "from_currency" => $from_currency,
                "to_currency" => $to_currency,
                $type => $amount
            ]
        ];
        if (!empty($destination)) {
            $body["order"]["destination"] = $destination;
        }
        if (!empty($refund_address)) {
            $body["order"]["refund_address"] = $refund_address;
        }
        return $this->post('order/new', $body, 'json');
    }
    /**
     * @param $uuid
     * @param $from_currency
     * @param $to_currency
     * @param $amount
     * @param string $destination
     * @param string $refund_address
     * @param string $type
     * @return mixed
     * @throws Exception
     */
    public function orderUpdate($uuid, $from_currency, $to_currency, $amount, $destination = '', $refund_address = '', $type = "invoiced_amount")
    {
        $body = [
            "order" => [
                "uuid" => $uuid,
                "from_currency" => $from_currency,
                "to_currency" => $to_currency,
                $type => $amount
            ]
        ];
        if (!empty($destination)) {
            $body["order"]["destination"] = $destination;
        }
        if (!empty($refund_address)) {
            $body["order"]["refund_address"] = $refund_address;
        }
        return $this->post('order/update', $body, 'json');
    }
    /**
     * @param $uuid
     * @return mixed
     * @throws Exception
     */
    public function orderAccept($uuid)
    {
        $body = [
            "uuid" => $uuid
        ];
        return $this->post('order/accept', $body, 'json');
    }
    /**
     * @param $uuid
     * @return mixed
     * @throws Exception
     */
    public function orderCheck($uuid)
    {
        $body = [
            "uuid" => $uuid
        ];
        return $this->post('order/check', $body, 'json');
    }
    /**
     * @param $uuid
     * @return mixed
     * @throws Exception
     */
    public function orderInfo($uuid)
    {
        $body = [
            "uuid" => $uuid
        ];
        return $this->post('order/info', $body, 'json');
    }
    /**
     * @param $uuid
     * @return mixed
     * @throws Exception
     */
    public function orderCancel($uuid)
    {
        $body = [
            "uuid" => $uuid
        ];
        return $this->post('order/cancel', $body, 'json');
    }
    // private methods
    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws Exception
     */
    private function get($method, $parameters = [])
    {
        $apiCall = self::$endpoint . $method;

        // Create a stream
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "Content-Type: application/json\r\n"
            ]
        ];

        $context = stream_context_create($opts);

        // Open the file using the HTTP headers set above
        $response = file_get_contents($apiCall, false, $context);
        
        $response = json_decode($response);
        
        //var_dump($response);
        if(isset($response->errors)){
            $responseString = "";
            foreach ($response->errors as $key => $value) {
                return $responseString .= "Error: ". $key . " - ". $value[0];
            }
        }elseif(isset($response)){
            return $response;
        }else{
            return;
        }
    }
    /**
     * @param string $method
     * @param array $body
     * @param string $type
     * @return mixed
     * @throws Exception
     */
    private function post($method, $body = [], $type = '')
    {
        $apiCall = self::$endpoint . $method;
        $post = json_encode($body);
        // Create a stream
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json\r\n",
                "content" => $post
            ]
        ];

        $context = stream_context_create($opts);

        // Open the file using the HTTP headers set above
        $response = file_get_contents($apiCall, false, $context);
        
        $response = json_decode($response);

        if(isset($response->errors)){
            $responseString = "";
            foreach ($response->errors as $key => $value) {
                return $responseString .= "Error: ". $key . " - ". $value[0];
            }
        }elseif(isset($response)){
            return $response;
        }else{
            return;
        }
    }
}