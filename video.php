<?php
require_once('include/config/config.ini.php');

$conn = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);	
if (mysqli_connect_errno())
{
	echo 'Error connecting to DB';
	exit;
}

$videoId = isset($_GET['video']) ? $_GET['video'] : null;

if ($videoId == null) {
	// no video requested
	header('location: index.php');
	exit;
}

$video = getVideoById($conn, $videoId);

$pageTitle = 'Watching: ' . $video['title'];

include_once('templates/video.php');

mysqli_close($conn);
?>
