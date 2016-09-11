<?php
/**
* This API will allow users to create an AJAX form
* that takes a serial number of a trademark and 
* get important information about it
* 
* @version 0.3 Beta
*/
class TsdrApi
{
	const NO_INFO_FOUND = "No Information found.";
	
	const STATUS_ABANDONED = "Abandoned";
	
	const STATUS_CANCELLED = "Registration cancelled";
	
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
		$reloadGif = 'reload.gif';
		$phpself = "Response.php";
		$form = <<<APIFORM
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
  <input type="button" name="submit" value="Submit" onclick="javascript:submitSerial(event)"> <!-- AJAX CALL -->
  <div id='loadingmessage' style='display:none'>
  	<img src=$reloadGif/>
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
	if($('input[name=serial]').val() == "")
	{
		$( ".response-form" ).remove();
		$( ".error" ).remove();
		$('#trademark').append('<div class="error">The field cannot be empty. </br> Please insert a valid serial number</div>');
		$('#loadingmessage').hide();
	}
	else
	{
		$( ".error" ).remove();
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
}   
</script>
APIFORM;
		echo $form;
	}

	 /**
	 * Return response form
	 *
	 * @TODO Do I need to handle errored responses from here?
	 * @param Object $data
	 * @param Int $serial
	 * @return HTML form
	 */
	public function responseForm($data, $serial)
	{
		$respForm = <<<RESPONSEFORM
		<div class="response-form" id="$serial">
	<dl class="trademark-info">
		<dt>Trademark</dt>
			<dd><img src="status_archive/$serial.png" style="width: 170px;" alt="trademark image"></dd>
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

		echo $respForm;

	}

	/**
	* Retrieves Trademark information
	* 
	* @param String $serial serial number
	* @return Array $data|Bool false
	*/
	public function	getTrademarkData($serial)
	{
		// Check if status archive exists
		$this->_makeStatusDir();
	
		// Clean serial number input
		$serial = $this->_cleanInput($serial);

		// @TODO create logic for users who want to retireve
		// their trademark via registration number
		
		// Check if exception was thrown
		if(!$this->_getTrademark_serial($serial, $this->_dir))
		{
			return False;
		}
		
		$this->_mapImportantData($this->_trademark);
		return $this->_data;
	}

	/**
	* Removes all files in archive directory
	* This method should be called at EOD or 12 AM
	* 
	* This method should be called from crontab
	*
	* @param String $dir name of directory
	*/
	public function flushArchive($dir = 'status_archive')
	{
		// Recursively remove all files if subdirectories exist
		$path = getcwd() . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
		foreach (glob("{$path}*") as $file) 
		{
			if(is_dir($file)) 
			{
				if(is_dir_empty($file))
				{
					rmdir($file);
				}
				else 
				{
					flushArchive($file);	
				}
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
	* If an xml file already exists with the corresponding serial number,
	* it will parse it from disk rather than obtain it from TSDR system
	* 
	* @param String $serial serial number
	* @param String $dir directory name to store xml
	* @return Bool False if exception caught
	*/
	private function _getTrademark_serial($serial)
	{
		// Check if status file exists
		if(!file_exists($this->_dir. DIRECTORY_SEPARATOR ."{$serial}_status_st96.xml"))
		{
			// @TODO Make abstraction for $url
			$url = "https://tsdrsec.uspto.gov/ts/cd/casedocs/sn{$serial}/zip-bundle-download?case=true&docs=&assignments=&prosecutionHistory=";
	
			try 
			{
				// @TODO decouple urluploader class and use dependency injection principles
				$upload = new urluploader($url, $this->_dir, $serial);
				$upload->uploadFromUrl();     	
			}
			catch (Exception $e)
			{
				// Development
				echo "ERROR: ". $e->getMessage()."</br>";
				echo "<pre>";
				echo print_r($e->getFile())."\n";
				echo "Line: ".$e->getLine();
				echo "</pre>";
				// Production 
				// echo "<div class=\"error\">Sorry, but the provided serial number was not found </br> Please make sure you inserted the correct serial number</div>";
				return false;
			}
		}
		
		$xml = simplexml_load_file($this->_dir. DIRECTORY_SEPARATOR . "{$serial}_status_st96.xml", NULL, NULL, 'ns2', True);

		$trademark = $xml->TrademarkTransactionBody->TransactionContentBag->TransactionData->TrademarkBag->Trademark;

		$this->_trademark = $trademark;
		return true;
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
											? $trademark->ApplicationDate: self::NO_INFO_FOUND;
											
		$data['MarkType'] 				= $trademark->MarkCategory
											? $trademark->MarkCategory: self::NO_INFO_FOUND;
											
		$data['Status']					= $trademark->NationalTrademarkInformation->MarkCurrentStatusExternalDescriptionText
											? $trademark->NationalTrademarkInformation->MarkCurrentStatusExternalDescriptionText: self::NO_INFO_FOUND;
		// Special logic for status									
		$data['Status'] = $this->_cleanUpStatus($data['Status']);
											
		$data['RenewalDate']			= $trademark->NationalTrademarkInformation->RenewalDate
											? $trademark->NationalTrademarkInformation->RenewalDate: self::NO_INFO_FOUND;
											
		$data['MarkLiteralElements']	= $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkVerbalElementText
											? $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkVerbalElementText: self::NO_INFO_FOUND;
											
		$data['StandardCharacterClaim']	= $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkStandardCharacterIndicator
											? $trademark->MarkRepresentation->MarkReproduction->WordMarkSpecification->MarkStandardCharacterIndicator: self::NO_INFO_FOUND;
		
		// Accounts for multiple classes and descriptions in a trademark 
		foreach ($trademark->GoodsServicesBag->GoodsServices as $GoodsServices) 
		{
			
			$data['ClassNumber'][$counter]			= $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->ClassNumber
														? $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->ClassNumber: self::NO_INFO_FOUND;
														
			$data['ClassDescription'][$counter]		= $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->GoodsServicesDescriptionText
														? $GoodsServices[$counter]->ClassDescriptionBag->ClassDescription[0]->GoodsServicesDescriptionText: self::NO_INFO_FOUND;
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
	
	
	/**
	 * Removes indication of TSDR website for applications
	 *
	 * @param String $status
	 */
	private function _cleanUpStatus($status)
	{
		// @TODO find a more elegent solution to substitute typecast
		$status = (string)$status;
		
		if($status === self::NO_INFO_FOUND)
		{
			return $status; // No modifications on status
		}
		
		// Check for status
		$arr = explode(' ',trim($status));
		
		if ($arr[0] == 'Registration')
		{
			$arr = $arr[0]." ".$arr[1];
		}
		else 
		{
			$arr = $arr[0];	
		}
				
		switch ($arr)
		{
			case self::STATUS_ABANDONED:
				$status = explode(". ", $status);
				$status = $status[0];
				return $status;
			case self::STATUS_CANCELLED:
				$status = explode(". ", $status);
				$status = $status[0];
				return $status;
			default:
				return $status; // No modifications on status
		}
		
		
		 	
	}
	
}
?>