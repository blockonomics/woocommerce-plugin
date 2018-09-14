<?php
include('Flyp.php');
$data               = file_get_contents("php://input");
$dataJsonDecode     = json_decode($data);
$flypID 			= $dataJsonDecode->uuid; //Fetch from $_POST

$flypme = new FlypMe\FlypMe();
$order = $flypme->orderCheck($flypID);
//Check order uuid exists
if(isset($order)){
	print(json_encode($order));
}