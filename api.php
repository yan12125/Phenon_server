<?php
require_once('include/config/config.ini.php');
$conn = mysqli_connect(HOST_NAME, USER_NAME, USER_PASS, DB_NAME);	

switch($_GET['action'])
{
	case 'getVideos':
        $where = array();
        if(isset($_GET['username']))
        {
            $where['uploader'] = $_GET['username'];
        }
		$videos = getVideos($conn, $where);
		$video_ids = array();
		foreach($videos as $video_id => $item)
		{
			$video_ids[] = $video_id;
		}
		$videos["ids"] = $video_ids;
		echo json_encode($videos);
		break;
    case 'getAllUsers':
        $result = mysqli_query($conn, 'SELECT DISTINCT uploader FROM videos');
        $data = mysqli_fetch_all($result);
        $data2 = array();
        for($i = 0; $i < count($data); $i++)
        {
            $data2[] = $data[$i][0];
        }
        echo json_encode($data2);
        break;
}

?>
