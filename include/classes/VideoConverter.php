<?php
/**
* This class sets up the video conversion process. It will calculate video 
* and thumbnail dimensions, generate the video thumbnail and call the 
* processVideo.php script which will perform the conversion. 
* 
* @requires ffmpeg
* @author Eric Akkerman eric.akkerman@gmail.com
*/


defined('FFMPEG_BINARY') || define('FFMPEG_BINARY', '/usr/bin/ffmpeg');

class VideoConverter
{
	/*
		These properties will be set by the $config aurgument passed to constructor
	*/
	

	protected $_uploadPath;
	protected $_outputPath;
	protected $_thumbPath;
	protected $_audioPath;
	protected $_audioSegmentPath;
	protected $_conversionLog;
	protected $_errorLog;
	protected $_conversionScript;
	protected $_outputFormat;
	protected $_bitRate = 128000;
	protected $_sampleRate = 4410;
	protected $_videoMaxWidth = 640;
	protected $_videoMaxHeight = 480;
	protected $_thumbMaxWidth = 320;
	protected $_thumbMaxHeight = 240;
	protected $_minDuration = 1;
	protected $_videoThumbDepth = 10;
	/*
		These properties will be set by the class methods
	*/
	
	protected $_uploadedFile;
	protected $_baseName;
	protected $_inputFile;
	protected $_inputFormat;
	protected $_outputFile;
	protected $_inputProperties = array();
	protected $_tmpImage;
	protected $_thumbnail;
	protected $_audiowav;
	protected $_thumbWidth;
	protected $_thumbHeight;
	protected $_videoWidth;
	protected $_videoHeight;
	protected $_videoDimensions;
	
	public $returnValues = array();

	
	public function __construct($uploadedFile = null, $config = array())
	{
		if (!$this->_setOptions($config)) {
			throw new Exception('Error setting configuration options, check error log for details');
		}
		
		if (!$this->_setUploadedFileValues($uploadedFile)) {
			throw new Exception('error setting uploaded file values');
		}
		
		if (!$this->_moveUploadedFile()) {
			throw new Exception('error moving uploaded file to ' . $this->_uploadPath);
		}
		
		if (!$this->_setInputVideoData()) {
			throw new Exception('error setting video data in ' . __CLASS__ . ':_setInputVideoData');
		}
		
		if (!$this->_setOutputVideoDimensions()) {
			throw new Exception('error setting output video dimensions' . __CLASS__ . ':_setOutputVideoDimensions');
		}
		
		if (!$this->_setThumbnailDimensions()) {
			throw new Exception('error setting thumbnail dimensions');
		}
		
		if (!$this->_fetchAudioWavFile()) {
			throw new Exception('error fetching audio part of video');
		}
		
		return true;
	}
	/*
		Work on this!!
	*/
	public function getDetails() 
	{
		// format time into mins and secs...
		$duration = round($this->_inputProperties['duration'], 2);
		$hours = floor($duration/3600);
		$mins = floor($duration/60);
		$seconds = $duration % 60;
		
		if (strlen($seconds) == 1) {
			$seconds = '0' . $seconds;
		}
		
		// if video longer than an hour, format minutes to 2 digits
		if ($hours > 0) {
			
			if (strlen($mins) == 1) {
				$mins = '0' . $mins;
			}
			
			$time = $hours . ":" . $mins . ":" . $secs;
			
		} else {
			$time = $mins . ':' . $seconds;
		}
		
		$this->returnValues['duration'] 	= $time;
		$this->returnValues['thumbnail'] 	= basename($this->_thumbnail);
		$this->returnValues['filename'] 	= $this->_outputFile;		
		$this->returnValues['format'] 		= $this->_outputFormat;
		$this->returnValues['width'] 		= $this->_videoWidth;
		$this->returnValues['height'] 		= $this->_videoHeight;

		return $this->returnValues;
	}
	
	public function processVideo()
	{
		if (!$this->_createThumbnail()) {
			throw new Exception('error creating thumbnail');
		}
		
		if (!$this->_runConversionScript()) {
			throw new Exception('error running conversion script');
		}
		
		return true;
	}
	
	protected function _setOptions($config) 
	{
		if (!count($config) > 0) {
			return false;
		}

		$properties = get_object_vars($this);
		
		foreach ($config as $property => $value) {
			
			$lazyLoader = '_' . $property;
			
			if (array_key_exists($lazyLoader, $properties)) {
				$this->$lazyLoader = $value;
			}
		}
		
		return true;
	}
	
	protected function _setUploadedFileValues($uploadedFile)
	{
		$this->_uploadedFile = $uploadedFile;
		
		if ($this->_uploadedFile == null || !isset($this->_uploadedFile)) {
			return false;
		}
		
		// extract extension
		$this->_inputFormat	= substr($uploadedFile['name'], strrpos($uploadedFile['name'], '.') + 1, 3);
		$this->_baseName 	= time();
		$this->_inputFile 	= $this->_uploadPath . $this->_baseName . '.' . $this->_inputFormat;
		$this->_outputFile 	= $this->_outputPath . $this->_baseName . '.' . $this->_outputFormat;
		$this->returnValues['title'] = substr($uploadedFile['name'], 0,  strrpos($uploadedFile['name'], '.'));
		
		return true;
	}
	
	protected function _moveUploadedFile()
	{
		if (!move_uploaded_file($this->_uploadedFile['tmp_name'], $this->_inputFile)) {
			return false;
		}
		
		return true;
	}
	
	protected function _setInputVideoData()
	{
		$this->_inputProperties['hasVideo']		= false;
		
		$command = FFMPEG_BINARY . ' -i ' . $this->_inputFile . ' 2>&1';
		exec($command, $shellOutput);
		
		$shellOutput = implode("\r\n", $shellOutput);
		
		// get duration information from ffmpeg output
		preg_match_all('/Duration: (.*)/', $shellOutput, $matches);
		
		if(count($matches) > 0) {
			$parts 								= explode(', ', trim($matches[1][0]));
			$timecode							= $parts[0];
			$timecode 							= explode(':', $timecode);
			$seconds 							= $timecode[0] * 60 * 60;
			$seconds 							+= $timecode[1] * 60;
			$seconds 							+= round($timecode[2]);
			$this->_inputProperties['duration'] = $seconds;
		} else {
			// no duration data, return false
			return false;
		}
		
		// get dimension and video information from ffmpeg output
		preg_match('/Stream(.*): Video: (.*)/', $shellOutput, $matches);
		
		if (count($matches) > 0) {
			
			$parts = explode(', ', trim($matches[2]));
			
			// get and process the dimensions
			$size = $parts[2];
			
			// clean the dimensions, $dimensions will conatian [0]=>wxh, [1]=>w, [2]=>h
			preg_match('/([0-9]{1,5})x([0-9]{1,5})/', $size, $dimensions);
			
			// eliminate odd numbers, ffmpeg doesn't like odd numbers :(
			$i = 0;
			foreach ($dimensions as $dimension) {
				if ($dimension % 2 != 0) {
					$dimensions[$i] = $dimension[$i] + 1;
				}
				
				$i++;
			}
			
			// set file input properties
			$this->_inputProperties['hasVideo'] = true;
			$this->_inputProperties['inputFormat'] = $parts[0];
			$this->_inputProperties['width'] = (int) $dimensions[1];
			$this->_inputProperties['height'] =(int) $dimensions[2];
			$this->_inputProperties['ratio'] = $dimensions[2] / $dimensions[1];
			
		} else {
			// No Stream matches, return false
			return false;
		}
		
		return true;
	}
	
	protected function _setOutputVideoDimensions() 
	{
		if ($this->_inputProperties['width'] < $this->_videoMaxWidth) {
			
			if ($this->_inputProperties['height'] < $this->_videoMaxHeight) {
				
				$this->_videoHeight = (int) $this->_inputProperties['height'];
				
			} else {
				
				$this->_videoHeight = (int) round($this->_inputProperties['height'] * $this->_inputProperties['ratio']);
			}
			
			$this->_videoWidth = (int) $this->_inputProperties['width'];
			return true;
		}
		
		$this->_videoWidth = (int) $this->_videoMaxWidth;
		$this->_videoHeight = (int) round($this->_videoWidth * $this->_inputProperties['ratio']);
		
		return true;
	}
	
	protected function _setThumbnailDimensions()
	{
		// calculate thumbnail dimensions
		if ($this->_inputProperties['width'] < $this->_thumbMaxWidth) {
			
			if ($this->_inputProperties['height'] < $this->_thumbMaxHeight) {
				
				$this->_thumbHeight = $this->_inputProperties['height'];
				
			} else {
				
				$this->_thumbHeight = $this->_inputProperties['height'] * $this->_inputProperties['ratio'];
			}
			
			$this->thumbWidth = $this->_inputProperties['width'];
		}
		
		$this->_thumbWidth = $this->_thumbMaxWidth;
		$this->_thumbHeight = $this->_thumbWidth * $this->_inputProperties['ratio'];
		
		// call method to set up thumbnail paths
		$this->_setThumbPaths();
		
		return true;
	}
	
	protected function _setThumbPaths() 
	{
		$this->_thumbnail = $this->_thumbPath . $this->_baseName .  '.jpg';
		$this->_tmpImage = $this->_thumbPath . $this->_baseName .  '.tmp.jpg';
		return true;
	}
	
	protected function _createThumbnail() 
	{
		// calculate thumbnail grabbing point
		$depth = ceil($this->_inputProperties['duration'] * ($this->_videoThumbDepth / 100));
		
		// execute ffmpeg command to create thumbnail and redirect output (2>&1) so script doesn't wait
		$command = FFMPEG_BINARY . " -i  $this->_inputFile  -f image2 -vframes 1 -ss  $depth  $this->_tmpImage 2>&1";
		
		if(!exec($command))
			return false;
		
		// create the blank image holder with correct dimemsions (requires GD library)
		$destImage = imagecreatetruecolor($this->_thumbWidth, $this->_thumbHeight);
		
		// get the temporary image  as the source
		$source = imagecreatefromjpeg($this->_tmpImage);
		
		// resize the temporary image into the place holder
		imagecopyresized($destImage, $source, 0, 0, 0, 0, 
			$this->_thumbWidth, $this->_thumbHeight, 
			$this->_inputProperties['width'], $this->_inputProperties['height']);
			
		// now save it to the correct location
		if ($resizedImage = imagejpeg($destImage, $this->_thumbnail)) {
			// delete the temporary image
			unlink($this->_tmpImage);
			return true;
		} else {
			return false;
		}
	}

	protected function _fetchAudioWavFile()
	{
		$this->_audioSegmentPath = $this->_audioPath . $this->_baseName . '/'; 
		$this->_audiowav = $this->_audioSegmentPath . 'test.wav';
		$command = FFMPEG_BINARY . " -i  $this->_inputFile  -ab $this->_bitRate -ac 2 -ar $this->_sampleRate -vn $this->_audiowav 2>&1";
		
		$oldmask = umask(0);
		$make_audio_directory = mkdir($this->_audioSegmentPath, 0777);
		umask($oldmask);
		if ($make_audio_directory) {
			if (exec($command))
				return true;
			else
				return false;
		}
		else
			return false;
	}

	
	protected function _runConversionScript()
	{
		if(!exec("php -f '" . $this->_conversionScript . "' > /dev/null &"))
			return true;
	}
}
