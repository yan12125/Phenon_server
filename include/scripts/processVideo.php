<?php
/**
 * This script will be called by VideoConverter.php class
 * and ran through command line exec() function. It will be responsible for the
 * actual conversion of video files
 *
 * @author Eric Akkerman
 */


require_once(realpath(dirname(__FILE__)) . '/../config/config.ini.php');
require_once(BASE_PATH . 'include/functions/functions.php');

 $conn = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);	
if (mysqli_connect_errno())
{
	echo 'Err establishing connection';
}

// we want to make sure we only process videos that haven't already
// been or are being processed
$where = array(
	'status' => 'queued'
);

$videos = getVideos($conn, $where);

print_r($videos);

foreach($videos as $video) {
	
	// update database to show that these videos are being processed
	$update = array(
		'id' => $video['id'],
		'status' 		=> 'started'
	);
	
	// execute update
	updateVideo($conn, $update);

	$command = buildMatlabSegmentation($config, $video);
	print($command);
	$result = exec($command, $output, $ret_var);
    file_put_contents("matlab_output.log", implode("\n", $output) . "\n", FILE_APPEND);
    $output = array();

	if ($ret_var == 0) {
		$update = array(
			'id' => $video['id'],
			'status' 		=> 'segmented'
		);
		updateVideo($conn, $update);

		exec('rm ' . $config['audioPath'] . getBasename($video['filename']) . '/test.wav');
	
		$audioSegmentPath = $config['audioPath'] . getBasename($video['filename']) . '/';	
        $curl_outputs = array();
		foreach (glob("$audioSegmentPath" . "*.wav") as $wavfile) {
            $output = array();
			file_put_contents("test.log", $wavfile . "\n", FILE_APPEND);
			$command = buildGoogleSpeechCurl($config, $video, getBasename($wavfile));
			$result = exec($command, $output, $ret_var);
            if(count($output) == 2)
            {
                $curl_outputs = array_merge($curl_outputs, array($output[1]));
            }
            else
            {
                $curl_outputs[] = "";
            }
		}
	
        $curl_full_str = implode("\n", $curl_outputs);
        file_put_contents("curl_output.log", $curl_full_str);
        $lines = $curl_outputs;
        $speech_results = array();
        foreach($lines as $line)
        {
            if($line == "")
            {
                $speech_results[] = "";
                continue;
            }
            $obj = json_decode($line, true);
            if(count($obj['result']) == 0)
            {
                $speech_results[] = "";
                continue;
            }
            $speech_results[] = $obj['result'][0]['alternative'][0]['transcript'];
        }
        $seg_times = explode(",", file_get_contents($audioSegmentPath . "seg_time.txt"));
        $config_obj = array("texts" => array(), "images" => array());
        for($i = 0; $i < count($speech_results); $i++)
        {
            // $speech_results[$i] = sprintf("%f-%f %s", $seg_times[2*$i], $seg_times[2*$i+1], $speech_results[$i]);
            $config_obj['texts'][] = array(
                'start' => (float) $seg_times[2*$i], 
                'end' => (float) $seg_times[2*$i+1], 
                'x' => 63, 
                'y' => 414, 
                'fontsize' => 35, 
                'fontcolor' => 'white', 
                'fontfile' => '/usr/share/fonts/wenquanyi/wqy-zenhei/wqy-zenhei.ttc', 
                'text' => $speech_results[$i]
            );
        }
        $config_obj['texts'][] = array(
            "fontsize" => 20, 
            "fontcolor" => "red", 
            "x" => 521, 
            "y" => 460, 
            "is_timestamp" => 1
        );

        $config_obj['images'][] = array(
            'x' => 0, 
            'y' => 0, 
            'source' => '/srv/http/phenon_newest.png'
        );
        file_put_contents("speech_results.log", implode("\n", $speech_results));
        file_put_contents("config.json", json_encode($config_obj, JSON_PRETTY_PRINT));

		// generate CGI Request with video and configuration information
		$command = buildCGIRequest($config, $video);
		print($command);
	
		// execute CGI Request
		$result = file_get_contents($command);
	
		// update to show converted, awaiting meta-data
		$update = array(
			'id' 		=> $video['id'],
			'status' 	=> 'converted',
		);
		// execute update
		updateVideo($conn, $update);
		
		// depending on format switch/type, generate appropriate command
		if ($video['format'] == 'flv') {
			// flvtool2 will add appropriate meta data to our flv video
			$command = buildFlvtool2Command($config, $video);
			
		} else if ($video['format'] == 'mp4') {
			// mp4's need meta data at begining of the file, qt-faststart does this
			$command = buildQtFaststartCommand($config, $video);
			
		}
		
		// execute command
		$result = exec($command, $output, $ret_var);
		
		// if successfulle
		if ($ret_var == 0) {
			// update database to show video conversion and meta data is complete
			$update = array(
				'id' 		=> $video['id'],
				'status' 	=> 'finished',
			);
			// execute db update
			updateVideo($conn, $update);
			
			// remove temporary files created (mp4) and the 
			// original uploaded file to save space on HD
			exec('rm ' . $config['outputPath'] . 'temp-' . $video['filename']);
			exec('rm ' . $config['uploadPath'] . getBasename($video['filename']) . '*');
		}
	}
		
}

mysqli_close($conn);

// all finished, lets exit!
exit;

?>
