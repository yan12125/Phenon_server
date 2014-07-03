<?php
include_once('header.php');
?>
<div class="center">
	<h2 class="mainheading">所有影片</h2>
</div>

<div id="videoGallery">
	<?php if (count($videos) > 0): ?>
		
	<table>
		<?php 
		$i = 0;
		foreach( $videos as $video):
		
		?>
		
		<?php if ($i == 0):?>
		<tr>
		<?php endif; ?>
		
		<td>
			<div class="videoThumbnail">
				<a href="video.php?video=<?php echo $video['id'];?>">
					<img src="/data/video/thumbnails/<?php echo $video['thumbnail'];?>" 
						title="<?php echo $video['title'];?>" />
				</a>
				
				<div class="videoLinks">
					<a href="video.php?video=<?php echo $video['id'];?>" class="linkButton">
						長度：<?php echo $video['duration'];?>
					</a>  
				</div>
				
				<?php if ($video['status'] != 'finished'):?>
				<div class="processing">
					<img src="/images/loading.gif" /> <br />
					影片處理中
				</div>
				<?php endif;?>
				
			</div>
			
		</td>
		
		<?php $i++; ?>


		<?php if($i % 3 == 0): ?>
		</tr>
		<?php 
		$i = 0; 
		endif; ?>

		
		<?php endforeach; ?>
		

		<?php if ($i != 0):?>
		</tr>
		<?php endif;?>
	</table>
	
	<?php else: ?>
		抱歉，沒有任何影片！
	<?php endif;?>
</div>
<?php include_once('footer.php');?>
