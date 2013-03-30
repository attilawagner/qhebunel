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
	 * @param array $file_arr A single item in $_FILES, containing the data for the avatar.
	 * @param integer $user_id Optional. If not provided, the current user's id is used.
	 * @return mixed The path (relative to the avatars dir) of the processed file
	 * on success, or false if there was an error.
	 */
	public static function save_avatar($file_arr, $user_id=null) {
		global $current_user;
		
		//Return if there was an error during the upload
		if ($file_arr['error'] != 0 || !@is_uploaded_file($file_arr['tmp_name'])) {
			return false;
		}
		
		//Get user ID
		if ($user_id == null) {
			$user_id = $current_user->ID;
		}
		
		/*
		 * Get path to the user's avatar directory
		 * Avatars are stored inside the wp-content/forum/avatars directory,
		 * partitioned using the get_user_dir_path() function. The final path of the
		 * avatar that belongs to $user_id=456 will be:
		 * wp-content/forum/avatars/04/456.jpg (extension used from the original filename)
		 */
		$dest_path = 'forum/avatars/'.self::get_user_dir_path($user_id);
		$dest_dir = dirname($dest_path);
		if (!self::create_dir_struct($dest_dir)) {
			return false;
		}
		
		$extension = pathinfo($file_arr['name'], PATHINFO_EXTENSION);
		if (empty($extension)) {
			$extension = 'jpg'; //Best guess...
		}
		$abs_path = WP_CONTENT_DIR.'/'.$dest_path.'.'.$extension;
		$temp_path = $abs_path.'.tmp'; //Save image temporarily to this file, so an error does not delete the current avatar.
		
		//Resize and save the image
		$avatar_img = wp_get_image_editor($file_arr['tmp_name']);
		if (is_wp_error($avatar_img)) {
			return false; //Cannot open file
		}
		$resize_result = $avatar_img->resize(QHEBUNEL_AVATAR_MAX_WIDTH, QHEBUNEL_AVATAR_MAX_HEIGHT, false);
		if (is_wp_error($resize_result)) {
			unset($avatar_img);
			if ($resize_result->get_error_code() == 'error_getting_dimensions') {
				//No resize needed, so we just need to copy the uploaded file
				@move_uploaded_file($file_arr['tmp_name'], $temp_path);
			} else {
				return false; //Some other error that we cannot handle
			}
		} else {
			//The resized image needs to be saved
			$save_result = $avatar_img->save($temp_path);
			unset($avatar_img);
			if (is_wp_error($save_result)) {
				return false; //Some other error that we cannot handle
			}
			$temp_path = $save_result['path']; //WP 3.5+ does not save the file at the requested path, but adds another extension at its end
		}
		
		if (is_file($temp_path) && filesize($temp_path) <= QHEBUNEL_AVATAR_MAX_FILESIZE) {
			$stat = @stat(WP_CONTENT_DIR);
			$mode = $stat['mode'] & 0000666;
			@chmod($temp_path, $mode);
			if (@rename($temp_path, $abs_path)) {
				self::delete_old_files($abs_path);
				//Success
				return self::get_user_dir_path($user_id).'.'.$extension;
			}
		}

		//Failure
		@unlink($temp_path);
		return false;
	}
	
	/**
	 * Removes the avatar image file that belongs to the given user.
	 * Note: the database won't get modified.
	 * @param integer $user_id Optional. If not provided, the ID of the current user will be used.
	 */
	public static function delete_avatar($user_id = null) {
		global $wpdb, $current_user;
		
		//Get user ID
		if ($user_id == null) {
			$user_id = $current_user->ID;
		}
		
		$path = $wpdb->get_var(
			$wpdb->prepare(
				'select `avatar` from `qheb_user_ext` where `uid`=%d limit 1;',
				$user_id
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
	private static function create_dir_struct($path) {
		//Load permissions from the wp-content directory
		$stat = @stat(WP_CONTENT_DIR);
		$mode = $stat['mode'] & 0000666;
		
		$abs_path = WP_CONTENT_DIR.'/'.$path;
		@mkdir($abs_path, $mode, true);
		return is_dir($abs_path);
	}
	
	/**
	 * Returns the segment in a path that corresponds to the user id.
	 * This sould be used when accessing an attachment or an avatar.
	 * This path segment partitions the user related content to maintain 
	 * the speed of the filesystem.
	 * @param integer $user_id
	 * @return string Path segment, eg. '01/0123' for $user_id=123
	 */
	private static function get_user_dir_path($user_id) {
		$four_digit = sprintf('%04d', $user_id);
		return substr($four_digit, 0, 2).'/'.$four_digit;
	}
	
	/**
	 * Saves multiple files uploaded as an array (eg. input name="files[]").
	 * Calls the save_attachment() function for each file, and returns
	 * the results of these calls as an array.
	 * @param array $files An array in $_FILES.
	 * @param integer $post_id Post ID this attachment belongs to.
	 * @param integer $user_id Optional. If not provided, the current user's id is used.
	 * @return array The result of each call to save_attachment().
	 */
	public static function save_attachment_array($files, $post_id, $user_id = null) {
		$ret = array();
		$saved_attachments = 0;
		foreach ($files['name'] as $id => $name) {
			//Build $file_arr as if it would be a single uploaded file
			$file_arr = array(
				'name' => $name,
				'type' => $files['type'][$id],
				'tmp_name' => $files['tmp_name'][$id],
				'error' => $files['error'][$id],
				'size' => $files['size'][$id]
			);
			
			$save_result = self::save_attachment($file_arr, $post_id, $user_id);
			$ret[] = $save_result;
			if ($save_result != false) {
				if (++$saved_attachments == QHEBUNEL_ATTACHMENT_LIMIT_PER_POST) {
					//If the maximum amount of attached files is reached, terminate the procession
					break;
				}
			}
		}
		return $ret;
	}
	
	/**
	 * Checks the type of an uploaded file.
	 * Currently only the file extension is checked.
	 * 
	 * @param array $file_arr A single item in $_FILES, containing the data for the attachment.
	 * @param string $allowed_extensions Comma separated list of allowed extensions.
	 * @param boolean $allow_extensionless Set to true if you want to allow extensionless files.
	 * @return mixed The file extension as string if the file is allowed, or false if it's not.
	 */
	private static function check_type($file_arr, $allowed_extensions, $allow_extensionless) {
		$file_path = $file_arr['name'];
		if (preg_match('/\.([^.]+)$/s', $file_path, $regs)) {
			$file_ext = $regs[1];
		} else {
			//Extensionless file
			return $allow_extensionless;
		}
		if (strpos(','.$allowed_extensions.',', ','.$file_ext.',') === false) {
			//Not allowed
			return false;
		}
		//Allowed, return the extension
		return $file_ext;
	}
	
	/**
	 * Saves a single file uploaded as an attachment
	 * into the users's directory.
	 * @param array $file_arr A single item in $_FILES, containing the data for the attachment.
	 * @param integer $post_id Post ID this attachment belongs to.
	 * @param integer $user_id Optional. If not provided, the current user's id is used.
	 * @return mixed The ID of the attachment on success, or false upon failure.
	 */
	public static function save_attachment($file_arr, $post_id, $user_id = null) {
		global $current_user, $wpdb;
		
		//Return if there was an error during the upload
		if ($file_arr['error'] != 0 || !@is_uploaded_file($file_arr['tmp_name'])) {
			return false;
		}
		
		//Get user ID
		if ($user_id == null) {
			$user_id = $current_user->ID;
		}
		
		/*
		 * Get destination path.
		 * Every user has a directory which groups the files further by
		 * using a directory for every post id.
		 * Uploaded files will retain their original names (if possible)
		 * inside these directories. Long filenames will be truncated.
		 * See also the save_avatar() function for more info.
		 */
		$dest_dir = self::get_attachment_dir_path($user_id, $post_id);
		if (!self::create_dir_struct($dest_dir)) {
			return false;
		}
		
		$safe_name = self::get_safe_name($file_arr['name']);
		
		//Size check - mods can upload larger files
		$size = filesize($file_arr['tmp_name']);
		if ($size > QHEBUNEL_ATTACHMENT_MAX_SIZE && !QhebunelUser::is_moderator()) {
			return false;
		}
		
		//Type check - admins can upload without restrictions
		if (self::check_type($file_arr, QHEBUNEL_ATTACHMENT_ALLOWED_TYPES, true) === false && !QhebunelUser::is_admin()) {
			return false;
		}
		
		//Save into the DB
		$wpdb->flush();
		$wpdb->query(
			$wpdb->prepare(
				'insert into `qheb_attachments` (`pid`, `uid`, `name`, `safename`, `size`, `upload`) values(%d, %d, %s, %s, %d, %s);',
				$post_id,
				$user_id,
				$file_arr['name'],
				$safe_name,
				$size,
				current_time('mysql')
			)
		);
		$attachment_id = $wpdb->insert_id;
		if ($attachment_id == 0) {
			return false;
		}
		
		$dest_path = WP_CONTENT_DIR.'/'.self::get_attachment_path($user_id, $post_id, $attachment_id, $safe_name);
		if (@move_uploaded_file($file_arr['tmp_name'], $dest_path)) {
			return $attachment_id;
		} else {
			$wpdb->query(
				$wpdb->prepare(
					'delete from `qheb_attachments` where `aid`=%d limit 1;',
					$attachment_id
				)
			);
		}
		return false;
	}
	
	/**
	 * Returns the path to the attachment.
	 * @param integer $user_id Uploader user, NOT the currently logged in.
	 * @param integer $post_id Post where this attachment belongs to.
	 * @param integer $attachment_id Attachment ID from the database.
	 * @param string $safe_name Attachment safe name from the database.
	 * @return string Path to the file, relative to WP_CONTENT_DIR.
	 */
	public static function get_attachment_path($user_id, $post_id, $attachment_id, $safe_name) {
		$file_name = sprintf('%04d', $attachment_id).'-'.$safe_name;
		return self::get_attachment_dir_path($user_id, $post_id).$file_name;
	}
	
	/**
	 * Returns the path for attachments that belong to the given user and post.
	 * Example:
	 * User ID: 423, Post ID: 2354
	 * Path returned: forum/attachments/04/0423/2354/
	 * @param integer $user_id
	 * @param integer $post_id
	 * @return string Path relative to WP_CONTENT_DIR or WP_CONTENT_URL.
	 */
	private static function get_attachment_dir_path($user_id, $post_id) {
		$user_dir = self::get_user_dir_path($user_id);
		$post_dir = sprintf('%04d', $post_id);
		return 'forum/attachments/'.$user_dir.'/'.$post_dir.'/';
	}
	
	/**
	 * Checks and escapes the filename to be safe to store in the filesystem.
	 * Longer filenames also get truncated.
	 * @param string $name Original file name.
	 * @return string Safe file name used to store the attachment.
	 */
	private static function get_safe_name($name) {
		//Separate name and extension, extension starts after the last dot in the filename
		if (preg_match('/^(.*)\.(.*+)$/', $name, $regs)) {
			$name = $regs[1];
			$ext = $regs[2];
		} else {
			$name = $sane_name;
			$ext = '';
		}
		
		//Remove special chars
		$sane_name = sanitize_title_with_dashes($name, null, 'save');
		$sane_ext = sanitize_title_with_dashes($ext, null, 'save');
		
		//Truncate length if needed
		$max_len = 50;
		if (strlen($sane_name) + strlen($sane_ext) + 1 < $max_len) {
			return $sane_name.'.'.$sane_ext;
		}
		
		//Extension truncated to 15 chars
		$sane_ext = substr($sane_ext, 0, 15);
		$sane_name = substr($sane_name, 0, $max_len-strlen($ext)-1);
		return $sane_name.'.'.$sane_ext;
	}
	
	/**
	 * Checks and saves the images uploaded for a badge.
	 * On success, an associative array will be returned in the following form:
	 * array(
	 *   'large' => 'badges/00/0012_large.ext',
	 *   'small' => 'badges/00/0012_small.ext'
	 * )
	 * @param integer $badge_id Badge ID.
	 * @param array $large_image A single item in $_FILES.
	 * @param array $small_image A single item in $_FILES.
	 * @return mixed An array holding the paths to the large and small images on success,
	 * or false if the files could not be saved.
	 */
	public static function save_badge_images($badge_id, $large_image, $small_image) {
		//Check uploaded files
		if ($large_image['error'] != 0 || !@is_uploaded_file($large_image['tmp_name'])) {
			return false;
		}
		$has_small_image =  ($small_image['error'] == 0 && @is_uploaded_file($small_image['tmp_name']));
		
		//Check file types and build paths
		$large_ext = self::check_type($large_image, QHEBUNEL_BADGE_FORMATS, false);
		if ($large_ext === false) {
			return false;
		}
		$large_path_segment = self::get_badge_image_path($badge_id, $large_ext, true);
		$large_path = WP_CONTENT_DIR.'/'.$large_path_segment;
		
		if ($has_small_image) {
			$small_ext = self::check_type($small_image, QHEBUNEL_BADGE_FORMATS, false);
			if ($small_ext === false) {
				$has_small_image = false;
			} else {
				$small_path_segment = self::get_badge_image_path($badge_id, $small_ext, false);
				$small_path = WP_CONTENT_DIR.'/'.$small_path_segment;
			}
		}
		if (!$has_small_image) {
			//Fallback: generate small icon from large image
			$small_path_segment = self::get_badge_image_path($badge_id, $large_ext, false);
			$small_path = WP_CONTENT_DIR.'/'.$small_path_segment;
		}
		
		/*
		 * Resize and save large image
		 */
		$temp_path = $large_path.'.tmp'; //Save image temporarily to this file, so an error does not delete the current image.
		$large_img = wp_get_image_editor($large_image['tmp_name']);
		if (is_wp_error($large_img)) {
			return false; //Cannot open file
		}
		$resize_result = $large_img->resize(QHEBUNEL_BADGE_SIZE_LARGE, QHEBUNEL_BADGE_SIZE_LARGE, false);
		if (is_wp_error($resize_result)) {
			if ($resize_result->get_error_code() == 'error_getting_dimensions') {
				//No resize needed, so we just need to copy the uploaded file
				@move_uploaded_file($large_image['tmp_name'], $temp_path);
			} else {
				return false; //Some other error that we cannot handle
			}
		} else {
			//The resized image needs to be saved
			$save_result = $large_img->save($temp_path);
			if (is_wp_error($save_result)) {
				return false; //Some other error that we cannot handle
			}
			$temp_path = $save_result['path']; //WP 3.5+ does not save the file at the requested path, but adds another extension at its end
		}
		$stat = @stat(WP_CONTENT_DIR);
		$mode = $stat['mode'] & 0000666;
		@chmod($temp_path, $mode);
		if (@rename($temp_path, $large_path)) {
			self::delete_old_files($large_path);
		} else {
			return false;
		}
		
		/*
		 * Resize and save small image
		 */
		$temp_path = $small_path.'.tmp'; //Save image temporarily to this file, so an error does not delete the current image.
		if ($has_small_image) {
			$small_img = wp_get_image_editor($small_image['tmp_name']);
		} else {
			//Use the resized large image as a fallback
			$small_img = $large_img;
		}
		if (is_wp_error($small_img)) {
			return false; //Cannot open file
		}
		$resize_result = $small_img->resize(QHEBUNEL_BADGE_SIZE_SMALL, QHEBUNEL_BADGE_SIZE_SMALL, false);
		if (is_wp_error($resize_result)) {
			if ($resize_result->get_error_code() == 'error_getting_dimensions') {
				//No resize needed, so we just need to copy the uploaded file
				if ($has_small_image) {
					@move_uploaded_file($small_image['tmp_name'], $temp_path);
				} else {
					//Copy the large image
					@copy($large_path, $temp_path);
				}
			} else {
				return false; //Some other error that we cannot handle
			}
		} else {
			//The resized image needs to be saved
			$save_result = $small_img->save($temp_path);
			if (is_wp_error($save_result)) {
				return false; //Some other error that we cannot handle
			}
			$temp_path = $save_result['path']; //WP 3.5+ does not save the file at the requested path, but adds another extension at its end
		}
		@chmod($temp_path, $mode);
		if (@rename($temp_path, $small_path)) {
			self::delete_old_files($small_path);
		} else {
			return false;
		}
		
		return array(
			'large' => $large_path_segment,
			'small' => $small_path_segment
		);
	}
	
	/**
	 * Return the path to the image.
	 * Badge images are clusterized into directories holding 100 badges each.
	 * For example: $badge_id=12, $large=true:
	 * forum/badges/00/0012_large.ext
	 * @param integer $badge_id Badge ID.
	 * @param string $file_ext File extension.
	 * @param boolean $large True for the large image, false for the small icon.
	 * @return string Path relative to WP_CONTENT_DIR or WP_CONTENT_URL.
	 */
	private static function get_badge_image_path($badge_id, $file_ext, $large) {
		$badge_num = sprintf('%04u', $badge_id);
		$variant = ($large ? 'large' : 'small');
		$dir = 'forum/badges/'.substr($badge_num, 0, 2);
		$path = $dir.'/'.$badge_num.'_'.$variant.'.'.$file_ext;
		self::create_dir_struct($dir);
		return $path;
	}
	
	/**
	 * Deletes the attachment from the filesystem and from the database
	 * @param integer $attachment_id ID in the database
	 */
	public static function delete_attachment($attachment_id) {
		global $wpdb;
		$attachment = $wpdb->get_row(
			$wpdb->prepare(
				'select `pid`,`uid`,`safename` from `qheb_attachments` where `aid`=%d;',
				$attachment_id
			),
			ARRAY_A
		);
		
		if (empty($attachment)) {
			return;
		}
		
		$path = WP_CONTENT_DIR.'/'.self::get_attachment_path($attachment['uid'], $attachment['pid'], $attachment_id, $attachment['safename']);
		@unlink($path);
		
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_attachments` where `aid`=%d;',
				$attachment_id
			)
		);
	}
	
	/**
	 * Removes previous versions of the file. Gets the current extension from $path,
	 * and removes files with the same name and different extensions.
	 * Every temporary file will be deleted too, including the one that belongs to the current file.
	 * @param string $path Absolute path to the new file.
	 * @param array $extensions Array of strings.
	 */
	private static function delete_old_files($path, $extensions = array('jpg','jpeg','png','gif','bmp')) {
		if (preg_match('/^(.*\.)([^.]*)$/', $path, $regs)) {
			$path_extless = $regs[1];
			$file_extension = $regs[2];
			foreach ($extensions as $ext) {
				if ($ext != $file_extension) {
					@unlink($path_extless.$ext);
				}
				@unlink($path_extless.$ext.'.tmp');
			}
		}
	}
	
	/**
	 * Streams the given file to the user.
	 * If http_send_file() is avaliable (pre PHP 5.3 with PECL extension, or PHP 5.3+),
	 * it will be used, or a PHP 5.0 compatible script will handle it.
	 * @param string $path File path, should be absolute.
	 * @param string $name Original file name. The browser will suggest this name in the Save As dialog.
	 */
	public static function stream_file($path, $name) {
		if (function_exists('http_send_file')) {
			self::stream_file_with_http_send_file($path, $name);
		} else {
			self::stream_file_with_script($path, $name);
		}
	}
	
	/**
	 * Streams the file with the PECL extension.
	 * (Built in function for PHP 5.3+)
	 * @param string $path File path, should be absolute.
	 * @param string $name Original file name. The browser will suggest this name in the Save As dialog.
	 */
	private static function stream_file_with_http_send_file($path, $name) {
		//TODO: option
		$throttle_sleep = 0.1;
		$throttle_buffer = 4096;
		
		$file_type = wp_check_filetype($path);
		http_send_content_disposition($name);
		http_send_content_type($file_type['type']);
		http_throttle($throttle_sleep, $throttle_buffer);
		http_send_file($path);
	}
	
	/**
	 * Streams the file with a custom handler script that
	 * provides the same functionality as the http_send_file() function.
	 * @param string $path File path, should be absolute.
	 * @param string $name Original file name. The browser will suggest this name in the Save As dialog.
	 */
	private static function stream_file_with_script($path, $name) {
		$multipart_boundary = "QHEBUNEL_MULTIPART_ATTACHMENT";
		
		//TODO: option
		$throttle_sleep = 0.1;
		$throttle_buffer = 4096;
		
		$file_size = filesize($path);
		
		/*
		 * Get range parameter
		* (Used when continuing downloads.)
		*/
		$ranges = array();
		if (isset($_SERVER['HTTP_RANGE'])) {
			if (!preg_match('^bytes=\d*-\d*(,\d*-\d*)*$', $_SERVER['HTTP_RANGE'])) {
				//Invalid request header
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes */' . $file_size); // Required in 416.
				die();
			}
			
			//Parse intervals
			$range_intervals = explode(',', substr($_SERVER['HTTP_RANGE'], 6));
			foreach ($range_intervals as $range) {
				list($start, $end) = explode('-', $range);
				if (empty($start)) {
					$start = 0;
				}
				if (empty($end) || $end > $file_size - 1) {
					$end = $file_size - 1;
				}
			
				if ($start > $end) {
					header('HTTP/1.1 416 Requested Range Not Satisfiable');
					header('Content-Range: bytes */' . $file_size); // Required in 416.
					die();
				}
				
				$ranges[] = array($start, $end);
			}
		}
		
		/*
		 * Send headers
		 */
		$file_type = wp_check_filetype($path);
		
		header('Cache-Control: max-age=30' );
		header('Content-Type: '.$file_type['type']);
		header("Content-Disposition: attachment; filename=\"{$name}\"");
		header('Content-Length: '.$file_size);
		header('Pragma: public');
		header('Accept-Ranges: bytes');
		
		if (!empty($ranges)) {
			header("HTTP/1.0 206 Partial Content");
			header("Status: 206 Partial Content");
			
			if (count($ranges) == 1) {
				//Single range
				$start = $ranges[0][0];
				$end = $ranges[0][1];
				header("Content-Range: bytes ${start}-${end}/${file_size}");
				
			} else {
				//Multiple ranges
				header('Content-Type: multipart/byteranges; boundary='.$multipart_boundary);
				
			}
		}
		
		/*
		 * Send file
		 */
		$file = @fopen($path, 'rb');
		if (empty($ranges)) {
			//Send the whole file
			self::stream_file_segment($file, 0, $file_size-1, $throttle_sleep, $throttle_buffer);
			
		} elseif (count($ranges) == 1) {
			//There's only one range, send it
			self::stream_file_segment($file, $ranges[0][0], $ranges[0][1], $throttle_sleep, $throttle_buffer);
			
		} else {
			//Multiple ranges, send as multipart
			foreach ($ranges as $range) {
				list($start, $end) = $range;
				//Part header
				echo("\n");
				echo('--'.$multipart_boundary."\n");
				echo('Content-Type: '.$file_type['type']);
				echo("Content-Range: bytes ${start}-${end}/${file_size}");
				
				//Send segment
				self::stream_file_segment($file, $start, $end, $throttle_sleep, $throttle_buffer);
				
				//Close part
				echo("\n");
				echo('--'.$multipart_boundary."--\n");
			}
			
		}
		@fclose($file);
		
	}
	
	/**
	 * Streams a part of the given file.
	 * @param resource $file File handler.
	 * @param integer $start First byte to send (start of interval, inclusive).
	 * @param integer $end Last byte to send (end of interval, inclusive).
	 * @param integer $throttle_sleep Sleep time in seconds, as used in http_throttle().
	 * @param integer $throttle_buffer Buffer size in bytes, as used in http_throttle().
	 */
	private static function stream_file_segment($file, $start, $end, $throttle_sleep, $throttle_buffer) {
		@fseek($file, $start);
		$remaining = $end - $start;
		while (!connection_aborted() && $remaining > 0) {
			$read_size = min($throttle_buffer, $remaining);
			echo @fread($file, $read_size);
			$remaining -= $read_size;
			sleep($throttle_sleep);
		}
	}
}
?>