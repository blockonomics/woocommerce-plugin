<?php

if(!function_exists('curl_init')) {
    throw new Exception('The Blockonomics client library requires the CURL PHP extension.');
}

require_once(dirname(__FILE__) . '/Blockonomics/Exception.php');
require_once(dirname(__FILE__) . '/Blockonomics/ApiException.php');
require_once(dirname(__FILE__) . '/Blockonomics/ConnectionException.php');
require_once(dirname(__FILE__) . '/Blockonomics/Coinbase.php');
require_once(dirname(__FILE__) . '/Blockonomics/Requestor.php');
require_once(dirname(__FILE__) . '/Blockonomics/Rpc.php');
require_once(dirname(__FILE__) . '/Blockonomics/OAuth.php');
require_once(dirname(__FILE__) . '/Blockonomics/TokensExpiredException.php');
require_once(dirname(__FILE__) . '/Blockonomics/Authentication.php');
require_once(dirname(__FILE__) . '/Blockonomics/SimpleApiKeyAuthentication.php');
require_once(dirname(__FILE__) . '/Blockonomics/OAuthAuthentication.php');
require_once(dirname(__FILE__) . '/Blockonomics/ApiKeyAuthentication.php');
