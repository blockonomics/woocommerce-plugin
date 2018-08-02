<?php

class Blockonomics
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';

    public function __construct()
    {
    }


    public function new_address($api_key, $secret, $reset=false)
    {
        $options = array(
            'http' => array(
                'header'  => 'Authorization: Bearer ' . $api_key,
                'method'  => 'POST',
                'content' => '',
                'ignore_errors' => true
            )
        );

        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }
        
        $context = stream_context_create($options);
        $contents = file_get_contents(Blockonomics::NEW_ADDRESS_URL.$get_params, false, $context);
        $responseObj = json_decode($contents);

        //Create response object if it does not exist
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = $http_response_header[0];

        return $responseObj;
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
