<?php
/**
 * Qhebunel
 * File and upload handler
 * For use with avatars and attachments.
 * 
 * @author Attila Wagner
 */

class QhebunelFiles {
	
	/**
	 * Handles the avatar uploading.
	 * It moves the image file to the user's folder,
	 * resizes if needed, and checks for the file size limit.
	 * @param array $fileArr A single item in $_FILES, containing the data for the avatar.
	 * @param integer $userId Optional. If not provided, the current user's id is used.
	 * @return mixed The path (relative to the avatars dir) of the processed file
	 * on success, or false if there was an error.
	 */
	public static function saveAvatar($fileArr, $userId=null) {
		global $current_user;
		
		//Return if there was an error during the upload
		if ($fileArr['error'] != 0 || !@is_uploaded_file($fileArr['tmp_name'])) {
			return false;
		}
		
		//Get user ID
		if ($userId == null) {
			$userId = $current_user->ID;
		}
		
		/*
		 * Get path to the user's avatar directory
		 * Avatars are stored inside the wp-content/forum/avatars directory,
		 * partitioned using the getUserDirPath() function. The final path of the
		 * avatar that belongs to $userId=456 will be:
		 * wp-content/forum/avatars/04/456.jpg (extension used from the original filename)
		 */
		$destPath = 'forum/avatars/'.self::getUserDirPath($userId);
		$destDir = dirname($destPath);
		if (!self::createDirStruct($destDir)) {
			return false;
		}
		
		$extension = pathinfo($fileArr['name'], PATHINFO_EXTENSION);
		if (empty($extension)) {
			$extension = 'jpg'; //Best guess...
		}
		$absPathExtless = WP_CONTENT_DIR.'/'.$destPath;//without file extension
		$absPath = $absPathExtless.'.'.$extension;
		$tempPath = $absPath.'.tmp'; //Save image temporarily to this file, so an error does not delete the current avatar.
		@unlink($tempPath); //remove previous temp file if it remained due to an error
		
		$imgResult = image_resize($fileArr['tmp_name'], QHEBUNEL_AVATAR_MAX_WIDTH, QHEBUNEL_AVATAR_MAX_HEIGHT, false, $tempPath);
		
		//Check for image_resize errors
		if (is_wp_error($imgResult)) {
			if ($imgResult->get_error_code() == 'error_getting_dimensions') {
				//No resize needed, so we just need to copy the uploaded file
				@move_uploaded_file($fileArr['tmp_name'], $tempPath);
			}
		}
		
		if (is_file($tempPath) && filesize($tempPath) <= QHEBUNEL_AVATAR_MAX_FILESIZE) {
			$stat = @stat(WP_CONTENT_DIR);
			$mode = $stat['mode'] & 0000666;
			@chmod($tempPath, $mode);
			if (@rename($tempPath, $absPath)) {
				//Remove old avatar, if it had a different file extension and remove temp files too
				$exts = array('jpg', 'jpeg', 'png', 'gif');
				foreach ($exts as $ext) {
					if ($ext != $extension) {
						@unlink($absPathExtless.'.'.$ext);
					}
					@unlink($absPathExtless.'.'.$ext.'.tmp');
				}
				
				//Success
				return self::getUserDirPath($userId).'.'.$extension;
			}
		}

		//Failure
		@unlink($tempPath);
		return false;
	}
	
	/**
	 * Removes the avatar image file that belongs to the given user.
	 * Note: the database won't get modified.
	 * @param integer $userId Optional. If not provided, the ID of the current user will be used.
	 */
	public static function deleteAvatar($userId = null) {
		global $wpdb, $current_user;
		
		//Get user ID
		if ($userId == null) {
			$userId = $current_user->ID;
		}
		
		$path = $wpdb->get_var(
			$wpdb->prepare(
				'select `avatar` from `qheb_user_ext` where `uid`=%d limit 1;',
				$userId
			)
		);
		
		if (!empty($path)) {
			@unlink(WP_CONTENT_DIR.'/forum/avatars/'.$path);
		}
	}
	
	/**
	 * Creates the directory given in $path, and creates every
	 * parent if they do not already exist.
	 * @param string $path Path of the directory to create,
	 * relative to WP_CONTENT_DIR, without slash at the beginning.
	 * @return boolean True, if the target directory exists
	 * at the end of the function, false otherwise.
	 */
	private static function createDirStruct($path) {
		//Load permissions from the wp-content directory
		$stat = @stat(WP_CONTENT_DIR);
		$mode = $stat['mode'] & 0000666;
		
		$absPath = WP_CONTENT_DIR.'/'.$path;
		@mkdir($absPath, $mode, true);
		return is_dir($absPath);
	}
	
	/**
	 * Returns the segment in a path that corresponds to the user id.
	 * This sould be used when accessing an attachment or an avatar.
	 * This path segment partitions the user related content to maintain 
	 * the speed of the filesystem.
	 * @param integer $userId
	 * @return string Path segment, eg. '01/0123' for $userId=123
	 */
	private static function getUserDirPath($userId) {
		$fourDigit = sprintf('%04d', $userId);
		return substr($fourDigit, 0, 2).'/'.$fourDigit;
	}
	
	/**
	 * Saves multiple files uploaded as an array (eg. input name="files[]").
	 * Calls the saveAttachment() function for each file, and returns
	 * the results of these calls as an array.
	 * @param array $files An array in $_FILES.
	 * @param integer $postId Post ID this attachment belongs to.
	 * @param integer $userId Optional. If not provided, the current user's id is used.
	 * @return array The result of each call to saveAttachment().
	 */
	public static function saveAttachmentArray($files, $postId, $userId = null) {
		$ret = array();
		$savedAttachments = 0;
		foreach ($files['name'] as $id => $name) {
			//Build $fileArr as if it would be a single uploaded file
			$fileArr = array(
				'name' => $name,
				'type' => $files['type'][$id],
				'tmp_name' => $files['tmp_name'][$id],
				'error' => $files['error'][$id],
				'size' => $files['size'][$id]
			);
			
			$saveResult = self::saveAttachment($fileArr, $postId, $userId);
			$ret[] = $saveResult;
			if ($saveResult != false) {
				if (++$savedAttachments == QHEBUNEL_ATTACHMENT_LIMIT_PER_POST) {
					//If the maximum amount of attached files is reached, terminate the procession
					break;
				}
			}
		}
		return $ret;
	}
	
	/**
	 * Checks the type of an uploaded file.
	 * Currently only the file extension is checked. Extensionless files are allowed.
	 * @param array $fileArr A single item in $_FILES, containing the data for the attachment.
	 * @return boolean True if the attachment can be saved.
	 */
	private static function checkAttachmentType($fileArr) {
		$filePath = $fileArr['name'];
		if (preg_match('/\.([^.]+)$/s', $filePath, $regs)) {
			$fileExt = $regs[1];
		} else {
			//Extensionless file
			return true;
		}
		return (strpos(','.QHEBUNEL_ATTACHMENT_ALLOWED_TYPES.',', ','.$fileExt.',') !== false);
	}
	
	/**
	 * Saves a single file uploaded as an attachment
	 * into the users's directory.
	 * @param array $fileArr A single item in $_FILES, containing the data for the attachment.
	 * @param integer $postId Post ID this attachment belongs to.
	 * @param integer $userId Optional. If not provided, the current user's id is used.
	 * @return mixed The ID of the attachment on success, or false upon failure.
	 */
	public static function saveAttachment($fileArr, $postId, $userId = null) {
		global $current_user, $wpdb;
		
		//Return if there was an error during the upload
		if ($fileArr['error'] != 0 || !@is_uploaded_file($fileArr['tmp_name'])) {
			return false;
		}
		
		//Get user ID
		if ($userId == null) {
			$userId = $current_user->ID;
		}
		
		/*
		 * Get destination path.
		 * Every user has a directory which groups the files further by
		 * using a directory for every post id.
		 * Uploaded files will retain their original names (if possible)
		 * inside these directories. Long filenames will be truncated.
		 * See also the saveAvatar() function for more info.
		 */
		$destDir = self::getAttachmentDirPath($userId, $postId);
		if (!self::createDirStruct($destDir)) {
			return false;
		}
		
		$safeName = self::getSafeName($fileArr['name']);
		
		//Size check - mods can upload larger files
		$size = filesize($fileArr['tmp_name']);
		if ($size > QHEBUNEL_ATTACHMENT_MAX_SIZE && !QhebunelUser::isModerator()) {
			return false;
		}
		
		//Type check - admins can upload without restrictions
		if (!self::checkAttachmentType($fileArr) && !QhebunelUser::isAdmin()) {
			return false;
		}
		
		//Save into the DB
		$wpdb->flush();
		$wpdb->query(
			$wpdb->prepare(
				'insert into `qheb_attachments` (`pid`, `uid`, `name`, `safename`, `size`, `upload`) values(%d, %d, %s, %s, %d, %s);',
				$postId,
				$userId,
				$fileArr['name'],
				$safeName,
				$size,
				current_time('mysql')
			)
		);
		$attachmentId = $wpdb->insert_id;
		if ($attachmentId == 0) {
			return false;
		}
		
		$destPath = WP_CONTENT_DIR.'/'.self::getAttachmentPath($userId, $postId, $attachmentId, $safeName);
		if (@move_uploaded_file($fileArr['tmp_name'], $destPath)) {
			return $attachmentId;
		} else {
			$wpdb->query(
				$wpdb->prepare(
					'delete from `qheb_attachments` where `aid`=%d limit 1;',
					$attachmentId
				)
			);
		}
		return false;
	}
	
	/**
	 * Returns the path to the attachment.
	 * @param integer $userId Uploader user, NOT the currently logged in.
	 * @param integer $postId Post where this attachment belongs to.
	 * @param integer $attachmentId Attachment ID from the database.
	 * @param string $safeName Attachment safe name from the database.
	 * @return string Path to the file, relative to WP_CONTENT_DIR.
	 */
	public static function getAttachmentPath($userId, $postId, $attachmentId, $safeName) {
		$fileName = sprintf('%04d', $attachmentId).'-'.$safeName;
		return self::getAttachmentDirPath($userId, $postId).$fileName;
	}
	
	/**
	 * Returns the path for attachments that belong to the given user and post.
	 * Example:
	 * User ID: 423, Post ID: 2354
	 * Path returned: forum/attachments/04/0423/2354/
	 * @param integer $userId
	 * @param integer $postId
	 * @return string Path relative to WP_CONTENT_DIR or WP_CONTENT_URL.
	 */
	private static function getAttachmentDirPath($userId, $postId) {
		$userDir = self::getUserDirPath($userId);
		$postDir = sprintf('%04d', $postId);
		return 'forum/attachments/'.$userDir.'/'.$postDir.'/';
	}
	
	/**
	 * Checks and escapes the filename to be safe to store in the filesystem.
	 * Longer filenames also get truncated.
	 * @param string $name Original file name.
	 * @return string Safe file name used to store the attachment.
	 */
	private static function getSafeName($name) {
		//Separate name and extension, extension starts after the last dot in the filename
		if (preg_match('/^(.*)\.(.*+)$/', $name, $regs)) {
			$name = $regs[1];
			$ext = $regs[2];
		} else {
			$name = $saneName;
			$ext = '';
		}
		
		//Remove special chars
		$saneName = sanitize_title_with_dashes($name, null, 'save');
		$saneExt = sanitize_title_with_dashes($ext, null, 'save');
		
		//Truncate length if needed
		$maxLen = 50;
		if (strlen($saneName) + strlen($saneExt) + 1 < $maxLen) {
			return $saneName.'.'.$saneExt;
		}
		
		//Extension truncated to 15 chars
		$saneExt = substr($saneExt, 0, 15);
		$saneName = substr($saneName, 0, $maxLen-strlen($ext)-1);
		return $saneName.'.'.$saneExt;
	}
	
	/**
	 * Streams the given file to the user.
	 * If http_send_file() is avaliable (pre PHP 5.3 with PECL extension, or PHP 5.3+),
	 * it will be used, or a PHP 5.0 compatible script will handle it.
	 * @param string $path File path, should be absolute.
	 * @param string $name Original file name. The browser will suggest this name in the Save As dialog.
	 */
	public static function streamFile($path, $name) {
		if (function_exists('http_send_file')) {
			self::streamFileWithHttpSendFile($path, $name);
		} else {
			self::streamFileWithScript($path, $name);
		}
	}
	
	/**
	 * Streams the file with the PECL extension.
	 * (Built in function for PHP 5.3+)
	 * @param string $path File path, should be absolute.
	 * @param string $name Original file name. The browser will suggest this name in the Save As dialog.
	 */
	private static function streamFileWithHttpSendFile($path, $name) {
		//TODO: option
		$throttleSleep = 0.1;
		$throttleBuffer = 4096;
		
		$fileType = wp_check_filetype($path);
		http_send_content_disposition($name);
		http_send_content_type($fileType['type']);
		http_throttle($throttleSleep, $throttleBuffer);
		http_send_file($path);
	}
	
	/**
	 * Streams the file with a custom handler script that
	 * provides the same functionality as the http_send_file() function.
	 * @param string $path File path, should be absolute.
	 * @param string $name Original file name. The browser will suggest this name in the Save As dialog.
	 */
	private static function streamFileWithScript($path, $name) {
		$multipartBoundary = "QHEBUNEL_MULTIPART_ATTACHMENT";
		
		//TODO: option
		$throttleSleep = 0.1;
		$throttleBuffer = 4096;
		
		$fileSize = filesize($path);
		
		/*
		 * Get range parameter
		* (Used when continuing downloads.)
		*/
		$ranges = array();
		if (isset($_SERVER['HTTP_RANGE'])) {
			if (!preg_match('^bytes=\d*-\d*(,\d*-\d*)*$', $_SERVER['HTTP_RANGE'])) {
				//Invalid request header
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes */' . $fileSize); // Required in 416.
				die();
			}
			
			//Parse intervals
			$rangeIntervals = explode(',', substr($_SERVER['HTTP_RANGE'], 6));
			foreach ($rangeIntervals as $range) {
				list($start, $end) = explode('-', $range);
				if (empty($start)) {
					$start = 0;
				}
				if (empty($end) || $end > $fileSize - 1) {
					$end = $fileSize - 1;
				}
			
				if ($start > $end) {
					header('HTTP/1.1 416 Requested Range Not Satisfiable');
					header('Content-Range: bytes */' . $fileSize); // Required in 416.
					die();
				}
				
				$ranges[] = array($start, $end);
			}
		}
		
		/*
		 * Send headers
		 */
		$fileType = wp_check_filetype($path);
		
		header('Cache-Control: max-age=30' );
		header('Content-Type: '.$fileType['type']);
		header("Content-Disposition: attachment; filename=\"{$name}\"");
		header('Content-Length: '.$fileSize);
		header('Pragma: public');
		header('Accept-Ranges: bytes');
		
		if (!empty($ranges)) {
			header("HTTP/1.0 206 Partial Content");
			header("Status: 206 Partial Content");
			
			if (count($ranges) == 1) {
				//Single range
				$start = $ranges[0][0];
				$end = $ranges[0][1];
				header("Content-Range: bytes ${start}-${end}/${fileSize}");
				
			} else {
				//Multiple ranges
				header('Content-Type: multipart/byteranges; boundary='.$multipartBoundary);
				
			}
		}
		
		/*
		 * Send file
		 */
		$file = @fopen($path, 'rb');
		if (empty($ranges)) {
			//Send the whole file
			self::streamFileSegment($file, 0, $fileSize-1, $throttleSleep, $throttleBuffer);
			
		} elseif (count($ranges) == 1) {
			//There's only one range, send it
			self::streamFileSegment($file, $ranges[0][0], $ranges[0][1], $throttleSleep, $throttleBuffer);
			
		} else {
			//Multiple ranges, send as multipart
			foreach ($ranges as $range) {
				list($start, $end) = $range;
				//Part header
				echo("\n");
				echo('--'.$multipartBoundary."\n");
				echo('Content-Type: '.$fileType['type']);
				echo("Content-Range: bytes ${start}-${end}/${fileSize}");
				
				//Send segment
				self::streamFileSegment($file, $start, $end, $throttleSleep, $throttleBuffer);
				
				//Close part
				echo("\n");
				echo('--'.$multipartBoundary."--\n");
			}
			
		}
		@fclose($file);
		
	}
	
	/**
	 * Streams a part of the given file.
	 * @param resource $file File handler.
	 * @param integer $start First byte to send (start of interval, inclusive).
	 * @param integer $end Last byte to send (end of interval, inclusive).
	 * @param integer $throttleSleep Sleep time in seconds, as used in http_throttle().
	 * @param integer $throttleBuffer Buffer size in bytes, as used in http_throttle().
	 */
	private static function streamFileSegment($file, $start, $end, $throttleSleep, $throttleBuffer) {
		@fseek($file, $start);
		$remaining = $end - $start;
		while (!connection_aborted() && $remaining > 0) {
			$readSize = min($throttleBuffer, $remaining);
			echo @fread($file, $readSize);
			$remaining -= $readSize;
			sleep($throttleSleep);
		}
	}
}
?>