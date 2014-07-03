<?php
require_once('include/classes/VideoConverter.php');
require_once('include/config/config.ini.php');

$errors = array();
$uploadComplete = false;

if (isset($_POST['submit'])) {
    if(!isset($_POST['uploader']))
    {
        $uploader = 'unknown';
    }
    else
    {
        $uploader = $_POST['uploader'];
    }

	$post = $_POST;
	$file = isset($_FILES['video']) ? $_FILES['video'] : null;
	
	if ($file['name'] == null)
		$errors['video'] = 'you must select a video to upload';
	
	$conn = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);	
	if (mysqli_connect_errno())
	{
		throw new Exception('Database error: ' . mysqli_connect_error());
	}
	
	// if no errors, process that shizzle
	if (count($errors) == 0) {
		
		// instantiate VideoConverter class
		$converter = new VideoConverter($file, $config);
		
		$details = $converter->getDetails();
		
		if ($videoId = insertVideo($conn, $details, $uploader)) {
			
			if ($converter->processVideo()) {
				
				$uploadComplete = true;
				
			} else {
				
				deleteVideoById($conn, $videoId);
				
			}
		}
	}

	mysqli_close($conn);
}

$pageTitle = "Upload A Video";

require_once('templates/upload.php');

?>
