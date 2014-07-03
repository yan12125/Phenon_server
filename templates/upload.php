<?php 
include_once('header.php');
?>
<div class="center">
	<h2 class="mainHeading">上傳影片</h2>
</div>

<div id="uploadForm">
<?php
include_once('uploadForm.php');

if ($uploadComplete) {
include_once('uploadResults.php');
}
?>
</div>
<?php
include_once('footer.php');
?>
