<?php
// @TODO Clean up errors by adding exceptions so you can check for them in the client code
class urluploader {

  private $filename; //file name of the zip file
  private $url; //The url where the file is hosted
  private $unzipdir; //This is the directory where we will unzip our file
                 
                 
  function __construct($fileurl,$dir=0, $serial)
  {

    if (!is_string($dir))
    { 
      //the user has not provided any directory so we will use the current one
      $this->unzipdir=getcwd() . DIRECTORY_SEPARATOR;
       
      //this is where our file wiil be uploaded and unzipped
    }
    else 
    {
      $this->unzipdir=$dir . DIRECTORY_SEPARATOR;
    }
                     
    $this->filename=$this->unzipdir . "serialNum_{$serial}.zip";
    $this->url=$fileurl;
    }

  /**
  * Grabs zip file from server
  * 
  * @return Bool TRUE
  */
  public function uploadFromUrl()
  {
    //lets validate the url
    if(!filter_var($this->url, FILTER_VALIDATE_URL))
    {
      throw New Exception("The provided url is invalid");
    }
  
      $length=5120; //to save on server load we will have to do this in turns
     
    try
    {
      $handle=fopen($this->url,'rb');
    }
    catch (Exception $e)
    {
      echo 'ERROR: '.$e->getMessage;
    }
     
    $write=fopen ( $this->filename,'w');
     
    while ( !feof($handle))
    {
      $buffer=fread ( $handle,$length );
      fwrite ( $write,$buffer);
    }
    fclose ( $handle);
    fclose ($write);
    //echo "<br>successfully uploaded the file:" . basename($this->filename) . "<br>" ;
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
  public function unzip($newdir=false,$delete=true,$filename=false)
  { 
  /*
  * Lets check if the user has provided a filename.
  * This is usefull if they just want to unzip a n existing file
  */
        
    if (!$filename)
    {
      $filename=$this->filename;
    } 
    
    //Set directory where the file will be unzipped
    if(!$newdir)
    //if the user has not provided a directory name use the one created
    {
      $newdir=$this->unzipdir;
    }
              
    //Check to see if the zip file exists
    if (!file_exists($filename))
    {
      throw new Exception('The zip file does not exist');
    }
    
    //Lets check if the default zip class exists
           
     if (class_exists('ZipArchive'))
    {
      $zip = new ZipArchive;

      if($zip->open($filename) !== TRUE)
      {
        throw new Exception('Unable to open the zip file');
      }


    $zip->extractTo($newdir) or die ('Unable to extract the file');

      //echo "<br>Extracted the zip file<br>";
    $zip->close();
    }
    else
    {
      // the zip class is missing. try unix shell command
      @shell_exec('unzip -d $newdir '. $this->filename);
      // echo "<br>Unzipped the file using shell command<br>";
    }
      //If delete has been set to true then we delete the existing zip file  
    if ($delete) 
    {
      unlink($filename);
      $files = glob($this->unzipdir . "*.{css,html,png,jpg,jpeg}", GLOB_BRACE);
      foreach ($files as $file) 
      {
          unlink($file);
      }

    }
      return true;
  }



} 