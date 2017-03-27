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
        $options = array(
      'http' => array(
        'header'  => 'Authorization: Bearer '.$api_key,
        'method'  => 'POST',
        'content' => ''
      )
    );
        $context = stream_context_create($options);
        $contents = file_get_contents(Blockonomics::NEW_ADDRESS_URL."?match_callback=$secret", false, $context);
        $new_address = json_decode($contents);
        return $new_address->address;
    }

    public function get_price($currency)
    {
        $options = array( 'http' => array( 'method'  => 'GET') );
        $context = stream_context_create($options);
        $contents = file_get_contents(Blockonomics::PRICE_URL. "?currency=$currency", false, $context);
        $price = json_decode($contents);
        return $price->price;
    }
}
