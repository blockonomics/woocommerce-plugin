<?php

class Blockonomics
{
  const BASE URL = 'https://www.blockonomics.co';
  const NEW_ADDRESS_URL = $BASE_URL. '/api/new_address';
  const PRICE_URL = $BASE_URL.'/api/price';

  public function __construct()
  {
  }


  public function new_address($api_key)
  {

    $options = array( 
      'http' => array(
        'header'  => 'Authorization: Bearer '.$api_key,
        'method'  => 'POST',
        'content' => ''
      )   
    );  
    $context = stream_context_create($options);
    $contents = file_get_contents(Blockonomics::NEW_ADDRESS_URL, false, $context);
    $new_address = json_decode($contents);
    return $new_address;
  }


}
