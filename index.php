<?php
require_once('include/config/config.ini.php');

$conn = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);	
if (mysqli_connect_errno())
{
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

// un-comment if you dont want to show videos that haven't finished processing
// $where = array(
// 	'status' => 'finished'
// );

$videos = getVideos($conn);

$pageTitle = 'Video Gallery';

require_once('templates/index.php');

mysqli_close($conn);
?>
