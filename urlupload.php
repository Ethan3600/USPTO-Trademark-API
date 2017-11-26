<?php
/**
 * Handles file upload from server
 * 
 * @param String $fileurl
 * @param String $url
 * @param String $unzipdir
 */
class UrlUpload {

  private $filename; //file name of the zip file
  private $url; //The url where the file is hosted
  private $unzipdir; //This is the directory where we will unzip our file
  private $localname;  // serial number portion of the zip file back from the uspto
                 
  function __construct($fileurl, $dir, $number)
  {

    if (!is_string($dir))
    { 
      $this->unzipdir = getcwd() . DIRECTORY_SEPARATOR;
    }
    else 
    {
      $this->unzipdir = $dir . DIRECTORY_SEPARATOR;
    }
                     
    $this->filename = $this->unzipdir . "number_{$number}.zip";
    $this->url = $fileurl;
    }

  public function getLocalname()
  {
     return $this->localname;
  }

  /**
  * Grabs zip file from server
  * 
  * @return Bool TRUE
  * @throws Exception
  */
  public function uploadFromUrl()
  {
    // validate the url
    if(!filter_var($this->url, FILTER_VALIDATE_URL))
    {
    	throw new Exception("The provided url is invalid");
    }
  
    $length=5120;
     

    if(!($handle = fopen($this->url,'rb')))
    {
    	throw new Exception("Url was not able to be opened");
    }

    // we need to remember the attachment filename.  requests by rn comes with
    // sn_status_st96.xml inside the zip.  
    // @TODO: find a more elegant way to do this!
    $headers = implode('!', $http_response_header);
 
    if(preg_match("/filename=(\d+)\.zip/", $headers, $matches))
       $this->localname = $matches[1];

     
    if(!($write = fopen($this->filename,'w')))
    {
    	throw new Exception("Could not open zip file");
    }
     
    while(!feof($handle))
    {
      $buffer = fread($handle,$length);
      fwrite($write,$buffer);
    }
    
    fclose ($handle);
    fclose ($write);
    $this->_unzip();
    return true;
  }

  /**
  * Unzip compressed file retireved from server
  *
  * @param Bool $newdir
  * @param Bool $delete
  * @param Bool $filename
  * @return Bool TRUE
  */
  private function _unzip($newdir=false,$delete=true,$filename=false)
  { 
  /*
  * Lets check if the user has provided a filename.
  * This is usefull if they just want to unzip an existing file
  */
        
    if (!$filename)
    {
      $filename = $this->filename;
    } 
    
    // Set directory where the file will be unzipped
    if(!$newdir)
    //if the user has not provided a directory name use the one created
    {
      $newdir = $this->unzipdir;
    }
              
    // Check to see if the zip file exists
    if (!file_exists($filename))
    {
      throw new Exception('The zip file does not exist');
    }
    
    // Lets check if the default zip class exists
           
    if(class_exists('ZipArchive'))
    {
      $zip = new ZipArchive;

      if($zip->open($filename) !== TRUE)
      {
        throw new Exception('Unable to open the zip file');
      }


    if(!($zip->extractTo($newdir)))
    {
    	throw new Exception("Unable to extract zip file");
    }
    
    $zip->close();
    }
    else
    {
      // The zip class is missing. try unix shell command
      @shell_exec('unzip -d $newdir '. $this->filename);
    }
    //If delete has been set to true then we delete the existing zip file  
    if ($delete) 
    {
      unlink($filename);
      $files = glob($this->unzipdir . "*.{css,html}", GLOB_BRACE);
      foreach ($files as $file) 
      {
      	unlink($file);
      }
      
      // additional search for extra images
      $tm5thumbnail = glob($this->unzipdir . "{markThumbnailImage,tm5Image}.*", GLOB_BRACE);
      foreach ($tm5thumbnail as $extraImages)
      {
      	unlink($extraImages);
      }

    }
      return true;
  }
} 
