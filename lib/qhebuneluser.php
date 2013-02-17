<?php
/**
 * Qhebunel
 * User data management
 * 
 * @author Attila Wagner
 */
class QhebunelUser {
	/**
	 * Checks whether the currently logged in user is an admin in Qhebunel.
	 * @return boolean True if the currently logged in user has admin privileges.
	 */
	public static function is_admin() {
		return current_user_can('edit_users');
	}
	
	/**
	 * Checks whether the currently logged in user is an moderator in Qhebunel.
	 * @return boolean True if the currently logged in user has moderator privileges.
	 */
	public static function is_moderator() {
		$ugroups = self::get_groups();
		if (in_array(3, $ugroups)) { //Check for the built in Moderators group
			return true;
		}
		return false;
	}
	
	/**
	 * Loads the data of the currently logged in user and stores it in the
	 * global variable $QHEB_UDATA as an associative array.
	 * @return array $QHEB_UDATA, empty array if the user is not logged in.
	 */
	public static function get_data() {
		global $QHEB_UDATA, $current_user, $wpdb;
		if (!isset($QHEB_UDATA)) {
			$QHEB_UDATA[] = array();
			if (isset($current_user) && $current_user instanceof WP_User) {
				$udata = $wpdb->get_row(
					$wpdb->prepare(
						"select * from `qheb_user_ext` where `uid`=%d;",
						$current_user->ID
					),
					ARRAY_A
				);
				$QHEB_UDATA = $udata;
			}
		}
		return $QHEB_UDATA;
	}
	
	/**
	 * Loads the list of user groups for the currently logged in user,
	 * and stores it in the global $QHEB_UGROUPS variable.
	 * @return array $QHEB_UGROUPS
	 */
	public static function get_groups() {
		global $QHEB_UGROUPS, $wpdb, $current_user;
		if (!isset($QHEB_UGROUPS)) {
			$QHEB_UGROUPS = array();
			$QHEB_UGROUPS[] = 1;//Everyone
			if ($current_user->ID > 0) {
				$QHEB_UGROUPS[] = 2;//Registered users
				$res = $wpdb->get_results(
					$wpdb->prepare(
						"select `gid` from `qheb_user_group_links` where `uid`=%d;",
						$current_user->ID
					),
					ARRAY_N
				);
				foreach ($res as $g) {
					$QHEB_UGROUPS[] = $g[0];
				}
			}
		}
		return $QHEB_UGROUPS;
	}
	
	/**
	 * Adds the default extended data to the specified user.
	 * Should be called upon a registration.
	 */
	public static function add_default_data($user_id) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'insert into `qheb_user_ext` (`uid`) values (%d)',
				$user_id
			)
		);
	}
	
	
	/**
	 * Returns the permission level of the current user
	 * for the specified category.
	 * @param integer $cat_id Category ID
	 * @return integer Permission level
	 */
	public static function get_permissions_for_category($cat_id) {
		global $wpdb;
		if (self::is_admin()) {
			return QHEBUNEL_PERMISSION_START;
		} else {
			$groups = self::get_groups();
			$permission = $wpdb->get_var(
				'select ifnull(max(`access`),0) from `qheb_category_permissions` where `catid`='.(int)$cat_id.' and `gid` in ('.implode(',', $groups).');',
				0,
				0
			);
			return $permission;
		}
	}
	
	/**
	 * Returns the permission level of the current user for the specified attachment.
	 * Should be called when handling the download request.
	 * The user has permission to download, if he can read the topics of the category
	 * the file was posted in.
	 * @param integer $attachment_id Attachment ID
	 * @return boolean True if the user has permission required
	 * for downloading the file.
	 */
	public static function has_permission_for_attachment($attachment_id) {
		global $wpdb;
		if (self::is_admin()) {
			return true;
		} else {
			$cat_id = $wpdb->get_var(
				$wpdb->prepare(
					'select `t`.`catid`
					from `qheb_attachments` as `a`
					left join `qheb_posts` as `p`
						on (`p`.`pid`=`a`.`pid`)
					left join `qheb_threads` as `t`
						on (`t`.`tid`=`p`.`tid`)
					where `a`.`aid`=%d limit 1;',
					$attachment_id
				),
			 	0,
				0
			);
			
			if ($cat_id > 0) {
				return self::get_permissions_for_category($cat_id) >= QHEBUNEL_PERMISSION_READ;
			}
		}
		return false;
	}
	
	/**
	 * Returns whether the current user can upload an attachment.
	 * Only logged in users can upload.
	 * @return boolean True if upload is allowed for the user.
	 */
	public static function has_persmission_to_upload() {
		global $current_user;
		return ($current_user->ID > 0);
	}
	
	/**
	 * This function is registered as an init action hook.
	 * Checks whether the current user is banned, and if that's the case,
	 * terminates further loading.
	 */
	public static function block_banned_user() {
		$user_data = self::get_data();
		if (@$user_data['banned'] == 1) {
			//Set cookie
			/*setcookie(self::BANNED_COOKIE_NAME, '1', 0, COOKIEPATH, COOKIE_DOMAIN, false, true);
			if (COOKIEPATH != SITECOOKIEPATH) {
				setcookie(self::BANNED_COOKIE_NAME, '1', 0, SITECOOKIEPATH, COOKIE_DOMAIN, false, true);
			}*/
			
			//Terminate further loading
			die(__('You are banned from this site.', 'qhebunel'));
		}
	}
}
?>