<?php
include('Flyp.php');
//Access WP Database functions
require_once("../../../../../wp-load.php");
$data               = file_get_contents("php://input");
$dataJsonDecode     = json_decode($data);
$flypFrom           = $dataJsonDecode->altcoin;
$flypAmount         = $dataJsonDecode->amount;
$flypDestination    = $dataJsonDecode->address;
$flypTo             = "BTC";
//$flypReturn = ; //Optional return address)
//$invoiceType = ;//"invoiced_amount" or "ordered_amount" 

$flypme = new FlypMe\FlypMe();
$order = $flypme->orderNew($flypFrom, $flypTo, $flypAmount, $flypDestination);
//Check order uuid exists
if(isset($order->order->uuid)){
	$order = $flypme->orderAccept($order->order->uuid);
	if(isset($order->deposit_address)){
		print(json_encode($order));
	}
}