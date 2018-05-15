<?php
/*
This function can be used to create a backup of a given file.
The full path of the file should be provided.
The function will create a backup directory in the same directory where the file is residing.
The file will be copied into the backup directorry.
*/
function create_file_backup($src) {

	if (file_exists($src)) {
		
		$path_parts = pathinfo($src);

		$dir_name = $path_parts['dirname'];
		
		$backup_directory = $dir_name."\\backup";
		
		$filename = $path_parts['filename'];
		
		$ext = $path_parts['extension'];
		
		if (is_dir($backup_directory)) {
							
		} else {
			
			mkdir($backup_directory);
					
		}
		
		$back_up_file = $backup_directory."\\".$filename."_".date('M_j').".".$ext;
		
		copy($src, $back_up_file);
		
		if (file_exists($back_up_file)) { 
			echo $back_up_file." file created.\n";
		}
		
	} else {
		echo "File does not exist: ".$src;
	}
}