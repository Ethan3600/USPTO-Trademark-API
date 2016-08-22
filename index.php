<?php 
require_once './urlupload.php';
error_reporting(E_ALL);

/*=================== EXECUTION/HANDLER CODE ======================*/

if (isset($_POST['serial'])) {
	$api = new TsdrApi();
	// $name = $_POST["name"];
	// $email = $_POST["email"];
	$serial = $_POST["serial"];
	$data = $api->getTrademarkData($serial);
	echo $api->responseForm($data, $serial);
	die();
}

?>

<?php
// print out form
echo TsdrApi::getApiForm();

/*=================== END OF EXECUTION/HANDLER CODE ===============*/

/**
* This API will allow users to create an AJAX form
* that takes a serial number of a trademark and 
* get important information about it
*
* @version 0.1 Beta
*/
class TsdrApi
{
	/**
	* This variable stores all the "important" data of the mark
	*
	* @var Array
	*/
	private $_data;

	/**
	* Name of the directory to store xml files
	*
	* @var String
	*/
	private $_dir;

	/**
	* SimpleXMLElement Object that represents the entire trademark
	* with all of its information
	*
	* @var Object
	*/
	private $_trademark;

	/**
	* returns Api Form
	*/
	public static function getApiForm()
	{
		$phpself = htmlspecialchars($_SERVER["PHP_SELF"]);
		return <<<APIFORM
<h1>Trademark API test environment</h1>
<p>Welcome to the Trademark API Test Environment!</p>
<p>This form will dynamically retrieve relative information about any existing trademark on the fly!</p>
<p>Please enter a serial number.</p>
<i>If you don't have have one, use this for an example: 85129597</i>
<form method="post" action="$phpself">  
 <!-- Name: <input type="text" name="name">
  <br><br>
  E-mail: <input type="text" name="email">
  <br><br> -->
  Serial Number: <input type="text" name="serial">
  <br><br>
  <input type="button" name="submit" value="Submit" onclick="javascript:submitSerial(event)">
  <div id='loadingmessage' style='display:none'>
  	<img src='reload.gif'/>
  </div>  
</form>

<div id="trademark"></div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script type=text/javascript>

$("input").keypress(function(event) {
   if (event.which == 13) {
   	event.preventDefault();
   	submitSerial();    
   }       
});

function submitSerial()
{
	$('#loadingmessage').show();
	$.ajax({
    type: "POST",
    data: {
    	'serial': $('input[name=serial]').val()
    },
    url: "$phpself",
    dataType: "html",
    success: function(data) {
    	$('#loadingmessage').hide();
    	var result = null;
    	result = data;
    	$('#trademark').html(result);
    }
  }); 
}   
</script>
APIFORM;
	}

	/**
	 * Return response form
	 *
	 * @param Object $data
	 * @param Int $serial
	 * @return HTML form
	 */
	public function responseForm($data, $serial)
	{
		$respForm = <<<RESPONSEFORM
		<div class="response-form" id="$serial">
	<dl class="trademark-info">
		<dt>Application Filing Date</dt>
			<dd>{$data['ApplicationFilingDate']}</dd>
		<dt>Mark Type</dt>
			<dd>{$data['MarkType']}</dd>
		<dt>Status</dt>
			<dd>{$data['Status']}</dd>
		<dt>Renewal Date</dt>
			<dd>{$data['RenewalDate']}</dd>
		<dt>Trademark Literal Elements</dt>
			<dd>{$data['MarkLiteralElements']}</dd>
		<dt>Standard Character Claim</dt>
			<dd>{$data['StandardCharacterClaim']}</dd>
RESPONSEFORM;
	
	for($i = 0; $i < count($data['ClassNumber']); $i++)	
	{
		$respForm .= <<<RESPONSEFORM
		<dt>Classification Number and Description</dt>
			<dd>{$data['ClassNumber'][$i]}: &nbsp {$data['ClassDescription'][$i]}</dd>
		<!-- <dt>Classification Description</dt> -->
			<dd></dd>
RESPONSEFORM;
	}
$respForm .= <<<RESPONSEFORM
	</dl>
</div>
RESPONSEFORM;

		return $respForm;

	}
	
	
	/**
	* Sets a dirctory path for users 
	* to save xml data
	*
	* @param String $dir
	* @deprecated If memcache is implemented, this method is pointless
	*/
	public function setXmlDirectory($dir)
	{
	/** 
	* @TODO this needs to be saved to a database so users can choose
	* the name of the directory where they want to save data
	*
	* !!THIS IS NOT READY FOR PRODUCTION!!
	*
	* By default the api will create a  
	* directory called 'status_archive'
	*/
		$dir = $this->_cleanInput($dir);
		$this->_dir = $dir;
	}

	/**
	* Retrieves Trademark information
	* 
	* @param String $serial serial number
	* @return Array $data
	*/
	public function	getTrademarkData($serial)
	{
		if (empty($this->_dir) || $this->_dir == NULL)
		{
			$this->_makeStatusDir();
		}
		else
		{
			// @TODO grab directory name from a database
			continue;
		}

		// Clean serial number input
		$serial = $this->_cleanInput($serial);

		// @TODO create logic for users who want to retireve
		// their trademark via registration number

		$this->_getTrademark_serial($serial, $this->_dir);
		$this->_mapImportantData($this->_trademark);
		return $this->_data;
	}

	/**
	* Removes all files in archive directory
	*
	* @param String $dir name of directory
	* @deprecated If memcache is implemented, data will be stored in memory rather than a directory
	*/
	public function flushArchive($dir = 'status_archive')
	{
		// @TODO if directory name exists in database retrive it and insert in $dir

		// Recursively remove all files if subdirectories exist
		$path = getcwd() . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
		foreach (glob("{$path}*") as $file) 
		{
			if(is_dir($file)) 
			{ 
            	flushArchive($file);
        	} 
        	else 
        	{
            	unlink($file);
        	}
		}
	}

	/*=================== UTILITIES ======================*/

	/**
	* Generates trademark Object via Serial number
	* 
	* @param String $serial serial number
	* @param String $dir directory name to store xml
	*/
	private function _getTrademark_serial($serial, $dir)
	{
		$url = "https://tsdrsec.uspto.gov/ts/cd/casedocs/sn{$serial}/zip-bundle-download?case=true&docs=&assignments=&prosecutionHistory=";

		$upload = new urluploader($url, $dir, $serial);
		       if($upload->uploadFromUrl())
		       {$upload->unzip();}
		       else {echo "We could not upload the file";} 
		
		$xml = simplexml_load_file($dir. DIRECTORY_SEPARATOR . "{$serial}_status_st96.xml", NULL, NULL, 'ns2', True);

		$trademark = $xml->TrademarkTransactionBody->TransactionContentBag->TransactionData->TrademarkBag->Trademark;

		$this->_trademark = $trademark;
	}

	/**
	* Maps specific data from Trademark object
	* for ease of use
	*
	* @param Object $trademark
	*/
	private function _mapImportantData($trademark)
	{
		$counter = 0;
		$data = array();
		$data['ApplicationFilingDate'] 	= $trademark->ApplicationDate
											? $trademark->ApplicationDate: "No Information found.";
		$data['MarkType'] 				= $trademark->MarkCategory
											? $trademark->MarkCategory: "No Information found.";
		$data['Status']					= $trademark->NationalTrademarkInformation->MarkCurrentStatusExternalDescriptionText
											? $trademark->NationalTrademarkInformation->MarkCurrentStatusExternalDescriptionText: "No Information found.";
		$data['RenewalDate']			= $trademark->NationalTrademarkInformation->RenewalDate 
											? $trademark->NationalTrademarkInformation->RenewalDate: "No Information found.";
		$data['MarkLiteralElements']	= $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkVerbalElementText
											? $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkVerbalElementText: "No Information found.";
		$data['StandardCharacterClaim']	= $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkStandardCharacterIndicator
											? $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkStandardCharacterIndicator: "No Information found.";
		
		// Accounts for multiple classes and descriptions in a trademark 
		foreach ($trademark->GoodsServicesBag->GoodsServices as $GoodsServices) 
		{
			
			$data['ClassNumber'][$counter]			= $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->ClassNumber
														? $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->ClassNumber: "No Information found.";
			$data['ClassDescription'][$counter]		= $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->GoodsServicesDescriptionText
														? $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->GoodsServicesDescriptionText: "No Information found.";
			$counter++;
		}

		$this->_data = $data;
	}

	/**
	* Returns directory name and creates
	* a path if it doesn't exist
	* 
	*/
	private function _makeStatusDir()
	{
		$dir = 'status_archive';

		if (!file_exists($dir) && !is_dir($dir)) 
		{
	    	mkdir($dir);         
		}
		$this->_dir = $dir;
	}

	/**
	* Clean up user input for security purposes
	* 
	* @param String $data
	* @return String $data cleaned up user input
	*/
	private function _cleanInput($userInput) {
	  $userInput = trim($userInput);
	  $userInput = stripslashes($userInput);
	  $userInput = htmlspecialchars($userInput);
	  return $userInput;
	}
}