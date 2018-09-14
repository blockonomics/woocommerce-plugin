<?php
include('Flyp.php');
//Access WP Database functions
require_once("../../../../../wp-load.php");
$data               = file_get_contents("php://input");
$dataJsonDecode     = json_decode($data);
$flypFrom           = $dataJsonDecode->altcoin;
$flypTo             = "BTC";

$flypme = new FlypMe\FlypMe();
$limits = $flypme->orderLimits($flypFrom, $flypTo);
//Check order uuid exists
if(isset($limits)){
	//print(json_encode($limits));
	//print($flypFrom . " " . $flypTo);
}
 //Save UUID
 global $wpdb;
 $results = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_type = 'shop_order'");
 var_dump($results);