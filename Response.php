<?php 
require_once '.'.DIRECTORY_SEPARATOR.'UrlUpload.php';
require_once '.'.DIRECTORY_SEPARATOR.'TsdrApi.php';

/*=================== EXECUTION/HANDLER CODE ======================*/

if (isset($_POST['serial'])) {
	$api = new TsdrApi();
	$serial = $_POST["serial"];
	$data = $api->getTrademarkData($serial);
	if($data)
	{
		$api->responseForm($data, $serial);
	}
}
?>