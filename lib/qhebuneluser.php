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
	public static function isAdmin() {
		$udata = self::getData();
		if (isset($udata['rank'])) {
			return $udata['rank'] >= 3;
		}
		return false;
	}
	
	/**
	 * Checks whether the currently logged in user is an moderator in Qhebunel.
	 * @return boolean True if the currently logged in user has moderator privileges.
	 */
	public static function isModerator() {
		$udata = self::getData();
		if (isset($udata['rank'])) {
			return $udata['rank'] >= 2;
		}
		return false;
	}
	
	/**
	 * Loads the data of the currently logged in user and stores it in the
	 * global variable $QHEB_UDATA as an associative array.
	 * @return array $QHEB_UDATA, empty array if the user is not logged in.
	 */
	public static function getData() {
		global $QHEB_UDATA, $current_user, $wpdb;
		if (!isset($QHEB_UDATA)) {
			$QHEB_UDATA[] = array();
			if ($current_user->ID > 0) {
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
	public static function getGroups() {
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
	public static function addDefaultData($userId) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'insert into `qheb_user_ext` (`uid`) values (%d)',
				$userId
			)
		);
	}
	
	
	/**
	 * Returns the permission level of the current user
	 * for the specified category.
	 * @param integer $catId Category ID
	 * @return integer Permission level
	 */
	public static function getPermissionsForCategory($catId) {
		global $wpdb;
		if (self::isAdmin()) {
			return QHEBUNEL_PERMISSION_START;
		} else {
			$groups = self::getGroups();
			$permission = $wpdb->get_var(
				'select ifnull(max(`access`),0) from `qheb_category_permissions` where `catid`='.(int)$catId.' and `gid` in ('.implode(',', $groups).');',
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
	 * @param integer $attachmentId Attachment ID
	 * @return boolean True if the user has permission required
	 * for downloading the file.
	 */
	public static function hasPermissionForAttachment($attachmentId) {
		global $wpdb;
		if (self::isAdmin()) {
			return true;
		} else {
			$catId = $wpdb->get_var(
				$wpdb->prepare(
					'select `t`.`catid`
					from `qheb_attachments` as `a`
					left join `qheb_posts` as `p`
						on (`p`.`pid`=`a`.`pid`)
					left join `qheb_threads` as `t`
						on (`t`.`tid`=`p`.`tid`)
					where `a`.`aid`=%d limit 1;',
					$attachmentId
				),
			 	0,
				0
			);
			
			if ($catId > 0) {
				return self::getPermissionsForCategory($catId) >= QHEBUNEL_PERMISSION_READ;
			}
		}
		return false;
	}
	
	/**
	 * Returns whether the current user can upload an attachment.
	 * Only logged in users can upload.
	 * @return boolean True if upload is allowed for the user.
	 */
	public static function hasPersmissionToUpload() {
		global $current_user;
		return ($current_user->ID > 0);
	}
	
	/**
	 * 
	 * Updates the users's forum rank when it's WP rank was modified.
	 * @param $uid integer Gets it from the action hook in qhebunel.php 
	 */
	
	public static function update_user_ranks($uid) {
		global $wpdb;
		//We need to actualize the prefixes, because WP uses them in not just tablenames but in meta key names as well. 
		$usermeta_tablename 	= $wpdb->prefix . 'usermeta';
		$level_meta_key_name 	= $wpdb->prefix . 'user_level';
		$role_query = $wpdb->get_results(
		"SELECT meta_value FROM ". $usermeta_tablename ." WHERE meta_key = '".$level_meta_key_name."' AND user_id = ".$uid.";");
		$role = $role_query[0]->meta_value;
		//Updating forum roles, based on the user's wp_power_level in the wp_usermeta table. (with local prefixes)
		if ($role == 10){ 		//Admin
			$wpdb->query(
				'UPDATE `qheb_user_ext` SET `rank`= 3 WHERE `uid`='.$uid.';'
			);
		} elseif ($role == 7){ 	//Editor
			$wpdb->query(
				'UPDATE `qheb_user_ext` SET `rank`= 2 WHERE `uid`='.$uid.';'
			);
		} else {
			$wpdb->query( 		//user
				'UPDATE `qheb_user_ext` SET `rank`= 1 WHERE `uid`='.$uid.';'
			);
		}
	}
}
?>