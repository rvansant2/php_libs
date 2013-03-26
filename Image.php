<?php
/*
* Image Class to handle uploaded images, renames the images and creates appropriate thumbnail images
* @author Robert Van Sant
* @copyright 2009 (ver. 1.0)
* @version 1.0
* @TODO Add additional methods and unit testing, class property for MySQL BLOB, refactor and optimize
*/

//CONFIG SETTINGS
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('MAX_FILE_SIZE', (5 * 1024) * 1024);//in MB
define('MAX_IMAGE_WIDTH', 150);
define('MAX_IMAGE_HEIGHT', 150);
define('IMAGE_SAVE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/imgs/');
define('IMAGE_URL_PATH', BASE_URL . 'imgs/');
define('THUMBS_IMAGE_URL_PATH', BASE_URL . 'imgs/thumbs/');

class Image
{
	public $_file;
	public $_filePath;
	public $_imageUrlPath;
	public $_width;
	public $_height;
	public $_resizeHeight;
	public $_resizeWidth;
	public $_fieldName;
	public $_renamedFile;
	public $_extension;
	public $_mimeType;
	public $_fileSize;
	public $_maxFileSize;
	public $_thumbsFileName;
	public $_thumbsSavePath;
	public $_thumbsWidth;
	public $_thumbsHeight;
	public $_thumbsImageUrlPath;
	public $_validationError = array();
	public $_saveData = array();
	
	/*
	* Constructor
	*/
	public function __construct() {
		$this->_file 				= $_FILES;
		$this->_maxFileSize 		= MAX_FILE_SIZE;
		$this->_imageUrlPath 		= IMAGE_URL_PATH;
		$this->_thumbsImageUrlPath 	= THUMBS_IMAGE_URL_PATH;
	}
	
	/*
	* Method to process Uploaded Image Files
	* @access public
	* @param string $fieldName (input field name)
	* @param string $filePath (optional - file save path definition)
	* @param integer $newWidth (optional - file thumb width size)
	* @param integer $newHeight (optional - file thumb height size)
	* @TODO Refactor to remove dependency of the fieldname.
	*/
	public function processImageFile($fieldName, $filePath = IMAGE_SAVE_PATH, $newWidth = MAX_IMAGE_WIDTH, $newHeight = MAX_IMAGE_HEIGHT) {
		if (!empty($this->_file)) {
			$this->_fieldName 	= $fieldName;
			$this->_filePath 	= $filePath;
			$this->_extension 	= strstr($this->_file[$this->_fieldName]['name'], '.', false);
			$this->_mimeType 	= $this->_file[$this->_fieldName]['type'];
			$this->_fileSize 	= $this->_file[$this->_fieldName]['size'];
			if ($this->_validateFile()) {
				$this->_renamedFile = 'image_' . self::genRandString(10) . $this->_extension;
				
				if (move_uploaded_file($this->_file[$this->_fieldName]['tmp_name'], $this->_filePath . $this->_renamedFile)) {
					chmod($this->_filePath . $this->_renamedFile, 0777);
					$this->_thumbsSavePath = $this->_filePath . 'thumbs/';
					$image = $this->_filePath . $this->_renamedFile;
					$thumbsCreated = $this->createThumbs($image, $newWidth, $newHeight);
					if ($thumbsCreated) {
						return $this->_createserializedData();
					} else {
						error_log('Error - ImageHandler::_handleFile - could not create thumbails.');
						return false;
					}
				} else {
					//set user error to be displayed.
					error_log('Error - imageHandler::_handleFile - there was a problem with moving the file.');
					return false;
				}
			} else {
				//set user error to be displayed.
				$this->_validationError[] = "File type does not match extension - {$this->_extension}.";
				error_log('Error - ImageHandler::_handleFile - mimeType and extension did not match.');
				return false;
			}
		} else {
			$this->_validationError[] = "No image was provided.";
			return false;
		}
	}
	
	/*
	* Method to validate mime types
	* @access protected
	*/
	protected function _validateFile() {
		switch ($this->_mimeType) {
			case 'image/jpeg':
				if ($this->_extension == '.jpg' || $this->_extension == '.jpeg' && $this->_chkFileSize()) {
					return true;
				} else {
					return false;
				}
				break;
			case 'image/gif':
				if ($this->_extension == '.gif' && $this->_chkFileSize()) {
					return true;
				} else {
					return false;
				}
				break;
			case 'image/png':
				if ($this->_extension == '.png' && $this->_chkFileSize()) {
					return true;
				} else {
					return false;
				}
				break;
			default:
				return false;
		}
	}
	
	/*
	* Method to create thumbnail images
	* @access public
	* @param string $image (path to saved image)
	* @param integer $width (optional - file thumb width size)
	* @param integer $height (optional - file thumb height size)
	*/
	public function createThumbs($image, $width = MAX_IMAGE_WIDTH, $height = MAX_IMAGE_HEIGHT) {
		$createdFile 			= false;
		$imageData 				= getimagesize($image);
		$this->_width 			= $imageData[0];
		$this->_height 			= $imageData[1];
		$this->_thumbsFileName 	= 'thumbs_' . $this->_renamedFile;
		
		if ($this->_width > $this->_height) {
			$newWidth = $width;
			$newHeight = $this->_height * ($newWidth / $this->_width);
		} else {
			$newHeight = $height;
			$newWidth = $this->_width * ($newHeight / $this->_height);
		}
		
		switch($this->_mimeType) {
			case 'image/jpeg':
			$image = @imagecreatefromjpeg($image);
			$thumb = @imagecreatetruecolor($newWidth, $newHeight);
			imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $this->_width, $this->_height);
			imagejpeg($thumb, $this->_thumbsSavePath . $this->_thumbsFileName, 90);
			$createdFile = true;
			break;
			
			case 'image/gif':
			$image = @imagecreatefromgif($image);
			$thumb = @imagecreatetruecolor($newWidth, $newHeight);
			imagealphablending($thumb, false);
			imagesavealpha($thumb, true);
			imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $this->_width, $this->_height);
			imagegif($thumb, $this->_thumbsSavePath . $this->_thumbsFileName, 90);
			$createdFile = true;
			break;
			
			case 'image/png':
			$image = @imagecreatefrompng($image);
			$thumb = @imagecreatetruecolor($newWidth, $newHeight);
			imagealphablending($thumb, false);
			imagesavealpha($thumb, true);
			imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $this->_width, $this->_height);
			imagepng($thumb, $this->_thumbsSavePath . $this->_thumbsFileName, 90);
			$createdFile = true;
			break;
			
			default:
			$type = image_type_to_mime_type($imageData[2]);
			$this->_validationError[] = "The file type {$type} is not supported, please use either jpeg, gif, or png";
			echo "The file type {$type} is not supported, please use either jpeg, gif, or png";
			break;
		}
		$thumbImageData = getimagesize($this->_thumbsSavePath . $this->_thumbsFileName);
		$this->_thumbsWidth = $thumbImageData[0];
		$this->_thumbsHeight = $thumbImageData[1];
		imagedestroy($image);
		imagedestroy($thumb);
		return $createdFile;
	}
	
	/*
	* Method to generate a random string
	* @access public static
	* @TODO: Move this method to a utils file.
	*/
	public static function genRandString($length)
	{
		$chars='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$string = '';
		for($i = 0; $i <= $length-1; $i++) {
			$string .= $chars[rand(0,strlen($chars)-1)];
		}
		
		return $string;
	}
	
	/*
	* Method check the file size against the max allowed for this class
	* @access protected
	* @TODO: Add proper Exceptions
	*/
	protected function _chkFileSize() {
		if ($this->_fileSize > 0 && $this->_fileSize < $this->_maxFileSize) {
			return true;
		}
		$this->_validationError[] = "The file size is too large, the limit is " . MAX_FILE_SIZE . "MB.";
		return false;
	}
	
	/*
	* Method to create a serialized date for the processed image
	* @access protected
	*/
	protected function _createserializedData() {
		$this->_saveData['file_name'] 			= 		$this->_renamedFile;
		$this->_saveData['file_path'] 			= 		$this->_filePath;
		$this->_saveData['url_path'] 			= 		$this->_imageUrlPath;
		$this->_saveData['width']				=		$this->_width;
		$this->_saveData['height']				=		$this->_height;
		$this->_saveData['thumb_file_path'] 	= 		$this->_thumbsSavePath;
		$this->_saveData['thumbs_file_name'] 	= 		$this->_thumbsFileName;
		$this->_saveData['thumbs_width'] 		= 		$this->_thumbsWidth;
		$this->_saveData['thumbs_height'] 		= 		$this->_thumbsHeight;
		$this->_saveData['thumbs_url_path'] 	= 		$this->_thumbsImageUrlPath;

		return htmlspecialchars(serialize($this->_saveData));
	}

	/*
	* Method to process Images sans uploading
	* @access public
	* @TODO: Create a method to process regular images or BLOBs
	*/
	public function processImage()
	{}

	/*
	* Method to dynamically resize images
	* @access public
	* @TODO: Create a method to reize images and same them
	*/
	public function resizeImage($resizeWidth, $resizeHeight)
	{}
}