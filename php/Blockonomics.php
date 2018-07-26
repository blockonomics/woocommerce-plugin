<?php

class Blockonomics
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';

    public function __construct()
    {
    }

    public function new_address($api_key, $secret)
    {
        $url = Blockonomics::NEW_ADDRESS_URL . "?match_callback=" . $secret;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-type: application/x-www-form-urlencoded'
            ));
        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $responseObj = json_decode($data);
        if (!isset($responseObj)) {
            $responseObj = new stdClass();
        }
        $responseObj->{'response_code'} = $httpcode;
        return $responseObj;
    }

    public function get_price($currency)
    {
        $url = Blockonomics::PRICE_URL. "?currency=$currency";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $data = curl_exec($ch);
        curl_close($ch);
        $price = json_decode($data);
        return $price->price;
    }
}
