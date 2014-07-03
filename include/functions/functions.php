<?php
/*
	additional functions for upload.php, index.php, video.php and processVideo.php
*/

require_once(realpath(dirname(__FILE__)) . '/../config/config.ini.php');

/** DEPRECATED
 * Function dbConnect()
 * creates link to mysql database with info from 
 * configuration file
 *
 * @return bool
 *
 * function dbConnect()
 * {
 *	$link = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);
 *	
 *	if (!$link) {
 *		mysqli_close($link);
 *		return false;
 *	}
 *	
 *	return true;
 *}
 */

/**
 * Function videoExists()
 * Checks whether a specific video filename already exists in DB
 * should no longer be needed if files renamed with time() func.
 * unless simultaneous upload occurs
 *
 * @param array $details data returned by VideoConverter::getDetails() method
 * @return bool
 */
function videoExists($conn, $details)
{
	$query = sprintf('select * from videos where filename = "%s" and format = "%s"',
		basename($details['outputFile']),
		$details['outputFormat']
	);
	
	$result = mysqli_query($conn, $query) or die(mysql_error($conn));
	
	return mysqli_num_rows($result) > 0;
}

/**
 * Function insertVideo()
 * Inserts video data into db table
 *
 * @param array $details data returned by VideoConverter::getDetails() method
 * @return mysqli_insert_id
 */
function insertVideo($conn, $details = array(), $uploader)
{
	if (count($details) == 0) {
		return false;
	}
	
	$query = sprintf('insert into videos (filename, title, duration, format, width, height, uploader) values ("%s", "%s", "%s", "%s", %s, %s, "%s")',
		basename($details['filename']),
		$details['title'],
		$details['duration'],
		$details['format'],
		$details['width'],
		$details['height'], 
        $uploader
	);
		
	$result = mysqli_query($conn, $query) or die(mysqli_error($conn));
	
	return mysqli_insert_id($conn);
}

/**
 * Function getVideos()
 * Selects multiple videos from table, can limit results with optional $where
 *
 * @param array $where optional array containing column/value pairs
 * @return array $items result set from db
 */
function getVideos($conn, $where = array())
{
	$query = 'select * from videos ';
	
	if (count($where) > 0) {
		
		$i = 0;
		foreach ($where as $column => $value) {
			
			if ($i == 0) {
				$query .= sprintf('where %s = "%s" ', $column, $value);
			} else {
				$query .= sprintf('and %s = "%s" ', $column, $value);
			}
			
			$i++;
		}
	}	
	
	$query .= 'order by ts_uploaded DESC';
	
	$result = mysqli_query($conn, $query);
	
	// format the return data, allows us to create key for thumbname
	$items = array();
	while ($row = mysqli_fetch_object($result)) {
		$items[$row->id]['id'] 			= $row->id;
		$items[$row->id]['filename'] 	= $row->filename;
		$items[$row->id]['status'] 		= $row->status;
		$items[$row->id]['format'] 		= $row->format;
		$items[$row->id]['title'] 		= $row->title;
		$items[$row->id]['duration'] 	= $row->duration;
		$items[$row->id]['thumbnail'] 	= getThumbname($row->filename);
		$items[$row->id]['width'] 		= $row->width;
		$items[$row->id]['height'] 		= $row->height;
        $items[$row->id]['ts_uploaded'] = $row->ts_uploaded;
	}
		
	return $items;
}

/**
 * Function getThumbname()
 * returns video thumbnail filename
 *
 * @param string $filename 
 * @return string 
 */
function getThumbname($filename)
{
	return getBasename($filename) . '.jpg';
}

/**
 * Function getBasename()
 * strips extension from filename
 *
 * @param string $filename 
 * @return string
 */
function getBasename($filename)
{
	return substr($filename, 0, strrpos($filename, '.'));
}

/**
 * Function getVideoById()
 * retrieves video data by reference id
 *
 * @param string $id 
 * @return array result row
 */
function getVideoById($conn, $id)
{
	$query = sprintf('select * from videos where id = %d', $id);
	
	$result = mysqli_query($conn, $query);
	
	return mysqli_fetch_assoc($result);
}

/**
 * Function deleteVideoById()
 * deletes video data from db by id
 *
 * @param string $id 
 * @return bool success of query
 */
function deleteVideoById($conn, $id)
{
	$query = sprintf('delete from videos where id = "%s" limit 1',
		$id
	);
	
	$result = mysqli_query($conn, $query);
	
	return mysqli_affected_rows($conn) > 0;
}

/**
 * Function updateVideo()
 * updates a videos data by referencing the id
 * only key value fields supplied by $update array will be updated
 *
 * @param array $update 
 * @return bool 
 */
function updateVideo($conn, $update = array()) 
{
	
	if (count($update) == 0) {
		return false;
	}
	
	$updates = array();
	
	$query = 'update videos set ';
	
	foreach ($update as $key => $value) {
		if ($key != 'id') {
			$updates[] = sprintf('%s = "%s" ', $key, $value);
			// $query .= implode(', ', $updates);
		}
	}
	
	$query .= implode(', ', $updates);
	$query .= sprintf(' where id = "%s"', $update['id']);
	$query .= ' limit 1';
	
	$result = mysqli_query($conn, $query);
	
	return mysqli_affected_rows($conn) > 0;
}

/**
 * Function buildFfmpegCommand()
 * builds FFmpeg command line command for video conversion to either
 * mp4 or flv format
 *
 * @param array $config configuration data from include/config/config.ini.php
 * @param array $video video result from db
 * @return string FFmpeg command
 */
function buildFfmpegCommand($config = array(), $video = array())
{
	if (count($video) == 0 || count($config) == 0) {
		return false;
	}

	
	if ($video['format'] == 'flv') {
		
		$command = FFMPEG_BINARY . " -y -i " . $config['uploadPath'] . getBasename($video['filename']) . '.* ';
		$command .= "-s " . $video['width'] . "x" . $video['height'] . " ";
		$command .= " -qscale 2 -ab " . $config['bitRate'];
		$command .= " -ar " . $config['sampleRate'];
		$command .= " -f flv " . $config['outputPath'] . $video['filename'] . ' ';
		$command .= '> ' . $config['conversionLog'] . ' 2>&1';
		
	} else if ($video['format'] == 'mp4') {
		
		$command = FFMPEG_BINARY . " -y -i ". $config['uploadPath'] . getBasename($video['filename']) . '.* ';
		# $command .= "-acodec aac -strict -2 -ab " . $config['bitRate'] . " ";
		$command .= "-acodec copy -ab " . $config['bitRate'] . " ";
		$command .= "-s " . $video['width'] . "x" . $video['height'] . " ";
		$command .= "-vcodec libx264 -preset fast -crf 22 -threads 0 -g 15 ";
		$command .= $config['outputPath'] . "temp-" . $video['filename'] . " ";
		$command .= "> " . $config['conversionLog'] . " 2>&1";
		
	}

	$command = "/usr/local/bin/transform" . " -i " . $config['uploadPath'] . $video['filename'];
	$command .= " -o " . $config['outputPath'] . "temp-" . $video['filename'] . " ";
	$command .= " -c " . "/usr/local/share/config.json" . " ";
	$command .= "> " . $config['conversionLog'] . " 2>&1";

	return $command;
}	

function buildMatlabSegmentation($config = array(), $video = array())
{
	if (count($video) == 0 || count($config) == 0) {
		return false;
	}
	$audioSegmentPath = $config['audioPath']. getBasename($video['filename']) . '/';

	$command = "matlab -nojvm -nodisplay -nosplash -r \"";
	$command .= "cd " . $config['scriptsPath'] . "; seg('"; 
	$command .= $audioSegmentPath . "'); quit;\"";

	return $command;
}

function buildGoogleSpeechCurl($config = array(), $video = array(), $name)
{
	if (count($video) == 0 || count($config) == 0) {
		return false;
	}
	$api_key = "AIzaSyDwr302FpOSkGRpLlUpPThNTDPbXcIn_FM";
	$endpoint = "https://www.google.com/speech-api/v2/recognize?output=json&lang=zh-tw&key=$api_key";

	$command = "curl -X POST --data-binary @" . $name . ".wav";
	$command .= " --header 'Content-Type: audio/l16; rate=" . $config['sampleRate'] . "' '" . $endpoint . "'"; 

	return $command;
}

	

/**
 * Function buildCGIRequest()
 *
 * @param array $config configuration data from include/config/config.ini.php
 * @param array $video video result from db
 * @return string CGI request string
 */
function buildCGIRequest($config = array(), $video = array())
{
	if (count($video) == 0 || count($config) == 0) {
		return false;
	}

	$command = "http://127.0.0.1:5000/";
	$command .= "?input_file=" . $config['uploadPath'] . $video['filename'];
	$command .= "&output_file=" . $config['outputPath'] . "temp-" . $video['filename'];
	$command .= "&config_file=" . "/srv/http/config.json";
    $command .= "&creation_time=" . urlencode($video['ts_uploaded']);

	return $command;	
}

function buildFFmpegAudioFetch($config = array(), $video = array())
{

}

/**
 * Function buildQtFaststartCommand()
 * Builds "qt-faststart" command for mooving "moov atom" meta data from
 * end of mp4 file to the front of mp4 file for progressive downloading (streaming)
 *
 * @param array $config 
 * @param array $video 
 * @return string qt-faststart command
 */
function buildQtFaststartCommand($config = array(), $video = array())
{
	if (count($video) == 0 || count($config) == 0) {
		return false;
	}
	
	return QT_FASTSTART_BINARY . ' ' . $config['outputPath'] . "temp-" . $video['filename'] 
		. ' ' . $config['outputPath'] . $video['filename'];
}

/**
 * Function build Flvtool2Command()
 * creates and returns command for adding meta data to 'flv' files
 * with flvtool2
 *
 * @param array $config 
 * @param array $video 
 * @return string flvtool2 command
 */
function buildFlvtool2Command($config = array(), $video = array())
{
	if (count($video) == 0 || count($config) == 0) {
		return false;
	}
	
	return FLVTOOL2_BINARY . ' -U ' . $config['outputPath'] . $video['filename'];
}
?>
