<?php 
include_once('header.php');
?>
<div id="videoWindow">
	<!-- <h2 class="videoTitle">正在播放影片：<?php echo $video['title']; ?></h2>
	-->
	<?php if ($video['status'] != 'finished'):?>
		
	<div class="videoThumbnail">
		<a href="video.php?video=<?php echo $video['id'];?>">
			<img src="/data/video/thumbnails/<?php echo getThumbname($video['filename']);?>" 
				title="<?php echo $video['title'];?>" />
		</a>
		
		<div class="processing">
			<img src="/images/loading.gif" /> <br />
			影片仍在處理中
		</div>

	</div>
	
	<?php else: ?>
		
	<div id="player">
	
	</div>
	
	<?php endif;?>
	
	<div class="videoLinks">
		<a href="index.php" class="linkButton">回到首頁</a> 
	</div>
</div>

<script>
	<?php if ($video['status'] == 'finished'):?>

	$f("player", "/swf/flowplayer-3.2.5.swf", {

		// configure clip to use "pseudostreaming" plugin below for providing video data
		clip: {
			url: '/data/video/output/<?php echo $video['filename'];?>',
			provider: 'pseudostreaming'
		},

		// streaming plugins are configured normally under the plugins node
		plugins: {
			pseudostreaming: {
				url: '/swf/flowplayer.pseudostreaming-3.2.5.swf',
			}
		}
	});

	<?php endif;?>
	
</script>
<?php 
include_once('footer.php');
?>
