<?php

/**
 * This class is responsible for communicating with the Blockonomics API
 */
class Blockonomics
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const PRICE_URL = 'https://www.blockonomics.co/api/price';
    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    const GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    const TEMP_API_KEY_URL = 'https://www.blockonomics.co/api/temp_wallet';
    const TEMP_WITHDRAW_URL = 'https://www.blockonomics.co/api/temp_withdraw_request';

    public function __construct()
    {
        $this->api_key = $this->get_api_key();
    }

    public function get_api_key()
    {
        $api_key = get_option("blockonomics_api_key");
        if (!$api_key)
        {
            $api_key = get_option("blockonomics_temp_api_key");
        }
        return $api_key;
    }


    public function new_address($secret, $reset=false)
    {
        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }
        $url = Blockonomics::NEW_ADDRESS_URL.$get_params;
        $response = $this->post($url, $this->api_key);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = $body->message;
          $responseObj->{'address'} = $body->address;
        }
        return $responseObj;
    }

    public function get_price($currency)
    {
        $url = Blockonomics::PRICE_URL. "?currency=$currency";
        $response = $this->get($url);
        return json_decode(wp_remote_retrieve_body($response))->price;
    }

    public function update_callback($callback_url, $xpub)
    {
        $url = Blockonomics::SET_CALLBACK_URL;
        $body = json_encode(array('callback' => $callback_url, 'xpub' => $xpub));
        $response = $this->post($url, $this->api_key, $body);
        return json_decode(wp_remote_retrieve_body($response));
    }

    public function get_callbacks()
    {
        $url = Blockonomics::GET_CALLBACKS_URL;
        $response = $this->get($url, $this->api_key);
        return $response;
    }

    public function get_temp_api_key($callback_url)
    {
        $url = Blockonomics::TEMP_API_KEY_URL;
        $body = json_encode(array('callback' => $callback_url));
        $response = $this->post($url, '', $body);
        $responseObj = json_decode(wp_remote_retrieve_body($response));
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        return $responseObj;
    }

    public function make_withdraw()
    {
        $api_key = $this->api_key;
        $temp_api_key = get_option('blockonomics_temp_api_key');
        if (!$api_key || !$temp_api_key || $temp_api_key == $api_key) {
            return null;
        }
        if (get_option('blockonomics_temp_withdraw_amount') > 0)
        {
            $url = Blockonomics::TEMP_WITHDRAW_URL.'?tempkey='.$temp_api_key;
            $response = $this->post($url, $api_key);
            $responseObj = json_decode(wp_remote_retrieve_body($response));
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code != 200)
            {
                $message = __('Error while making withdraw: '.$responseObj->message, 'blockonomics-bitcoin-payments');
                return [$message, 'error'];
            }
            update_option("blockonomics_temp_api_key", null);
            update_option('blockonomics_temp_withdraw_amount', 0);
            $message = __('Your funds withdraw request has been submitted. Please check your Blockonomics registered emailid for details', 'blockonomics-bitcoin-payments');
            return [$message, 'updated'];
        }
        update_option("blockonomics_temp_api_key", null);
        return null;
    }

    private function get($url, $api_key = '')
    {
        $headers = $this->set_headers($api_key);

        $response = wp_remote_get( $url, array(
            'method' => 'GET',
            'headers' => $headers
            )
        );

        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            return $response;
        }
    }

    private function post($url, $api_key = '', $body = '', $type = '')
    {
        $headers = $this->set_headers($api_key);

        $response = wp_remote_post( $url, array(
            'method' => 'POST',
            'timeout' => 8,
            'headers' => $headers,
            'body' => $body
            )
        );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo "Something went wrong: $error_message";
        }else{
            return $response;
        }
    }

    private function set_headers($api_key)
    {
        if($api_key){
            return 'Authorization: Bearer ' . $api_key;
        }else{
            return '';
        }
    }

    public function testSetup()
    {
        $response = $this->get_callbacks();
        $error_str = '';
        $responseBody = json_decode(wp_remote_retrieve_body($response));
        $callback_secret = get_option('blockonomics_callback_secret');
        $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $api_url);
        // Remove http:// or https:// from urls
        $api_url_without_schema = preg_replace('/https?:\/\//', '', $api_url);
        $callback_url_without_schema = preg_replace('/https?:\/\//', '', $callback_url);
        $response_callback_without_schema = preg_replace('/https?:\/\//', '', $responseBody[0]->callback);
        //TODO: Check This: WE should actually check code for timeout
        if (!wp_remote_retrieve_response_code($response)) {
            $error_str = __('Your server is blocking outgoing HTTPS calls', 'blockonomics-bitcoin-payments');
        }
        elseif (wp_remote_retrieve_response_code($response)==401)
            $error_str = __('API Key is incorrect', 'blockonomics-bitcoin-payments');
        elseif (wp_remote_retrieve_response_code($response)!=200)  
            $error_str = $response->data;
        elseif (!isset($responseBody) || count($responseBody) == 0)
        {
            $error_str = __('You have not entered an xpub', 'blockonomics-bitcoin-payments');
        }
        elseif (count($responseBody) == 1)
        {
            if(!$responseBody[0]->callback || $responseBody[0]->callback == null)
            {
              //No callback URL set, set one 
              $this->update_callback($callback_url, $responseBody[0]->address);   
            }
            elseif($response_callback_without_schema != $callback_url_without_schema)
            {
              $base_url = get_bloginfo('wpurl');
              $base_url = preg_replace('/https?:\/\//', '', $base_url);
              // Check if only secret differs
              if(strpos($responseBody[0]->callback, $base_url) !== false)
              {
                //Looks like the user regenrated callback by mistake
                //Just force Update_callback on server
                $this->update_callback($callback_url, $responseBody[0]->address);  
              }
              else
              {
                $error_str = __("You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
              }
            }
        }
        else 
        {
            $error_str = __("You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
            // Check if callback url is set
            foreach ($responseBody as $resObj)
             if(preg_replace('/https?:\/\//', '', $resObj->callback) == $callback_url_without_schema)
                $error_str = "";
        }  
        if (!$error_str)
        {
            //Everything OK ! Test address generation
            $response= $this->new_address($callback_secret, true);
            if ($response->response_code!=200){
              $error_str = $response->response_message;
            }
        }
        if($error_str) {
            $error_str = $error_str . __('<p>For more information, please consult <a href="https://blockonomics.freshdesk.com/support/solutions/articles/33000215104-troubleshooting-unable-to-generate-new-address" target="_blank">this troubleshooting article</a></p>', 'blockonomics-bitcoin-payments');
            return $error_str;
        }
        // No errors
        return false;
    }
}
