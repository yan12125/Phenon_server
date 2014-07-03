<form action="upload.php" method="post" enctype="multipart/form-data">
	
	<fieldset>
		
		<legend>選擇上傳影片</legend>
		
		<div class="formRow">
			
            <input type="hidden" name="uploader" value="kakaka">
			<label for="video">(格式為MP4)</label>
			
			<input type="file" name="video" />
			
			<?php if(isset($errors['video'])):?>
				<div class="error">
					<?php echo $errors['video'];?>
				</div>
			<?php endif;?>
			
		</div>
		
	</fieldset>
	
	<div class="submit">
		<input type="submit" name="submit" value="Upload Video!" />
	</div>
	
</form>
