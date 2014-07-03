<?php
require_once(realpath(dirname(__FILE__)) . '/../config/config.ini.php');
require_once(BASE_PATH . 'include/functions/functions.php');

$conn = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);	
if (mysqli_connect_errno())
{
	echo 'Err establishing connection';
	exit(1);
}

$videos = getVideos($conn);
print_r($videos);

foreach($videos as $video)
{
	$command = 'ffmpeg -i ' + $video['uploadPath'] + basename($video['filename']) + ' test.wav';
}
?>
