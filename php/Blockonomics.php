<?php

class Blockonomics
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';
    const ADDRESS_URL = 'https://www.blockonomics.co/api/address?only_xpub=true&get_callback=true';
    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';

    public function __construct()
    {
    }


    public function new_address($api_key, $secret, $reset=false)
    {
        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }

        $response = wp_remote_post( Blockonomics::NEW_ADDRESS_URL.$get_params, array(
            'method' => 'POST',
            'headers' => 'Authorization: Bearer ' . $api_key,
            'body' => array(),
            'ignore_errors' => true
            )
        );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            if (!isset($responseObj)) $responseObj = new stdClass();
            $responseObj->{'response_code'} = 'HTTP/1.1 '.$response['response']['code'].' '.$response['response']['message'];
            $responseObj->{'response_message'} = $response['response']['message'];
            $responseObj->{'address'} = json_decode($response["body"])->address;
            return $responseObj;
        }
    }

    public function get_price($currency)
    {
        $response = wp_remote_get( Blockonomics::PRICE_URL. "?currency=$currency", array(
            'method' => 'GET'
            )
        );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            if (!isset($responseObj)) $responseObj = new stdClass();
            return json_decode($response["body"])->price;
        }
    }

    public function get_xpubs($api_key)
    {

        $response = wp_remote_get( Blockonomics::ADDRESS_URL, array(
            'method' => 'GET',
            'headers'  => 'Authorization: Bearer ' . $api_key,
            'body' => '',
            'ignore_errors' => true
            )
        );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            if (!isset($responseObj)) $responseObj = new stdClass();
            $responseObj = json_decode($response["body"]);
            return $responseObj;
        }
    }

    public function update_callback($api_key, $callback_url, $xpub)
    {
        $response = wp_remote_post( Blockonomics::SET_CALLBACK_URL.$get_params, array(
            'method' => 'POST',
            'headers' => 'Authorization: Bearer ' . $api_key,
            'body' => '{"callback": "'.$callback_url.'", "xpub": "'.$xpub.'"}',
            'ignore_errors' => true
            )
        );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            if (!isset($responseObj)) $responseObj = new stdClass();
            $responseObj = json_decode($response["body"]);
            return $responseObj;
        }
    }
}
