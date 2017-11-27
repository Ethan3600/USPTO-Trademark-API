<?php 
include_once 'TsdrApi.php';

/*=================== EXECUTION/HANDLER CODE ======================*/

if (isset($_POST['number'])) {
	$api = new TsdrApi();
	$number = $_POST["number"];
        $type = $_POST["type"];

	$data = $api->getTrademarkData($number, $type);
	if($data)
	{
		$api->responseForm($data, $number);
	}
}
?>
