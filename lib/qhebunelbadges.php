<?php
/**
 * Qhebunel
 * Badge handler
 */
class QhebunelBadges {
	
	/**
	 * Holds information on the displayed badges of users.
	 * @var array
	 */
	private static $badge_link_cache = array();
	
	/**
	 * Holds the loaded badges.
	 * @var array
	 */
	private static $badge_cache = array();
	
	/**
	 * Saves or updates a badge.
	 * No checks are run on the image size, but file types are checked.
	 * 
	 * @param integer $badge_id ID of the badge to update. Pass NULL to create a new badge.
	 * @param integer $group_id Badge group ID.
	 * @param string $name Name of the badge.
	 * @param string $description Description of the badge.
	 * @param integer $points Value of the badge.
	 * @param array $large_image A single item in $_FILES.
	 * @param array $small_image A single item in $_FILES.
	 * @return boolean True if the badge was saved successfully.
	 */
	public static function save_badge($badge_id, $group_id, $name, $description, $points, $large_image, $small_image) {
		global $wpdb;
		$new_badge = false;
		//Create row in DB to get an ID
		if ($badge_id == null) {
			$wpdb->query(
				$wpdb->prepare(
					'insert into `qheb_badges` (`bgid`,`name`,`description`)
					values (%d, %s, %s);',
					$group_id,
					$name,
					$description
				)
			);
			if (($badge_id = $wpdb->insert_id) == 0) {
				return false;
			}
			$new_badge = true;
		}
		
		//Save the images
		$saved_paths = QhebunelFiles::save_badge_images($badge_id, $large_image, $small_image);
		
		if ($new_badge) {
			if ($saved_paths === false) {
				//Delete the DB record for new badges that doesn't have an image
				$wpdb->query(
					$wpdb->prepare(
						'delete from `qheb_badges` where `bid`=%d;',
						$badge_id
					)
				);
				return false;
			} else {
				
				//Update the row
				$wpdb->query(
					$wpdb->prepare(
						'update `qheb_badges` set `largeimage`=%s, `smallimage`=%s, `points`=%d where `bid`=%d;',
						$saved_paths['large'],
						$saved_paths['small'],
						$points,
						$badge_id
					)
				);
				return true;
			}
		} else {
			//Badge update
			if ($saved_paths === false) {
				//It's a badge update WITHOUT new images
				$wpdb->query(
					$wpdb->prepare(
						'update `qheb_badges` set `name`=%s, `description`=%s, `points`=%d where `bid`=%d;',
						$name,
						$description,
						$points,
						$badge_id
					)
				);
				return true;
			} else {
				
				//It's a badge update WITH new images
				$wpdb->query(
					$wpdb->prepare(
						'update `qheb_badges` set `largeimage`=%s, `smallimage`=%s, `name`=%s, `description`=%s, `points`=%d where `bid`=%d;',
						$saved_paths['large'],
						$saved_paths['small'],
						$name,
						$description,
						$points,
						$badge_id
					)
				);
				return true;
			}
		}
	}
	
	/**
	 * Deletes a badge from the database and the filesystem.
	 * 
	 * @param integer $badge_id ID of the badge in the database.
	 */
	public static function delete_badge($badge_id) {
		global $wpdb;
		
		//Load badge
		$badge = $wpdb->get_row(
			$wpdb->prepare(
				'select * from `qheb_badges` where `bid`=%d;',
				$badge_id
			),
			ARRAY_A
		);
		if (empty($badge)) {
			return;
		}
		
		//Remove links to the badge
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_user_badge_links` where `bid`=%d;',
				$badge_id
			)
		);
		
		//Remove db row
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_badges` where `bid`=%d;',
				$badge_id
			)
		);
		
		//Delete images
		@unlink(WP_CONTENT_DIR.'/'.$badge['largeimage']);
		@unlink(WP_CONTENT_DIR.'/'.$badge['smallimage']);
	}
	
	/**
	 * Renders a badge as a &lt;li&gt; list item inside a badge list.
	 * 
	 * @param array|integer $badge Badge as an array or Badge ID.
	 * @param boolean $own_badge True if the badge status is that of the current user.
	 * @param array $group Optional. The badge group data where this badge belongs.
	 * If not provided, and the badge is passed as an array, it must contain
	 * the 'awarded' flag itself.
	 */
	public static function render_badge($badge, $own_badge = true, $group = null) {
		//Load badge if only an ID is given
		if (is_numeric($badge)) {
			if (($badge = self::load_badge($badge)) === null) {
				return;
			}
		}
		
		$awarded = empty($group) ? $badge['awarded'] : $group['awarded'];
		$status = self::get_badge_status_message($badge, $awarded, $own_badge);
		
		$url = QhebunelUI::get_url_for_badge($badge['bid']);
		$class = empty($badge['startdate']) ?  '' : ' owned';
		echo('<li class="badge-frame'.$class.'">');
		echo('<div class="img"><a href="'.$url.'"><img src="'.WP_CONTENT_URL.'/'.$badge['largeimage'].'" alt="'.$badge['name'].'" /></a></div>');
		echo('<div class="name"><a href="'.$url.'">'.$badge['name'].'</a></div>');
		echo('<div class="description">'.$badge['description'].'</div>');
		echo('<div class="status">'.$status.'</div>');
		echo('</li>');
	}
	
	/**
	 * Loads a badge from the database for the render_badge() method.
	 * 
	 * @param integer $badge_id Badge ID.
	 * @return array If the badge exists in the database, its data is
	 * returned as an array. If there was an error, null is returned.
	 */
	private static function load_badge($badge_id) {
		die('TODO - UNIMPLEMENTED METHOD: QhebunelBadges::load_badge()');
	}
	
	/**
	 * Returns the status message for a badge.
	 * 
	 * @param array $badge The badge as an array.
	 * @param boolean $awarded True if the badge cannot be claimed.
	 * @param boolean $own_badge See render_badge().
	 * @return string The string to display as the status.
	 */
	private static function get_badge_status_message($badge, $awarded, $own_badge) {
		if ($own_badge) {
			if (empty($badge['startdate'])) {
				return __('You do not have this badge.','qhebunel');
			} else {
				if ($awarded) {
					return sprintf(__('This badge was awarded to you on %s.','qhebunel'), QhebunelDate::get_short_date($badge['startdate']));
				} else {
					return sprintf(__('You\'ve claimed this badge on %s.','qhebunel'), QhebunelDate::get_short_date($badge['startdate']));
				}
			}
		} else {
			if (empty($badge['startdate'])) {
				return __('User does not have this badge.','qhebunel');
			} else {
				if ($awarded) {
					return sprintf(__('This badge was awarded to the user on %s.','qhebunel'), QhebunelDate::get_short_date($badge['startdate']));
				} else {
					return sprintf(__('The user claimed this badge on %s.','qhebunel'), QhebunelDate::get_short_date($badge['startdate']));
				}
			}
		}
	}
	
	/**
	 * Returns the badges that should be displayed under the user's avatar.
	 * 
	 * @param integer $user_id User ID.
	 * @return array Badge rows from the database.
	 */
	public static function get_displayed_badges($user_id) {
		$ret = array();
		
		if (empty(self::$badge_link_cache[$user_id])) {
			self::preload_displayed_badges(array($user_id));
		}
		if (!array_key_exists($user_id, self::$badge_link_cache)) {
			return $ret;
		}
		
		$badge_list = self::$badge_link_cache[$user_id];
		foreach ($badge_list as $badge_id) {
			$ret[] = self::$badge_cache[$badge_id];
		}
		return $ret;
	}
	
	/**
	 * Loads the badges for the given users that should be displayed
	 * next to their avatars. Used in thread rendering to reduce the
	 * number of database queries.
	 * 
	 * @param array $user_ids Array of integers.
	 */
	public static function preload_displayed_badges($user_ids) {
		global $wpdb;
		//Skip loaded users.
		$loaded_user_ids = array_keys(self::$badge_link_cache);
		$user_ids = array_diff($user_ids, $loaded_user_ids);
		if (empty($user_ids)) {
			return;
		}
		
		$badge_links = $wpdb->get_results(
			'select `l`.`uid`,`l`.`bid`
			from `qheb_user_badge_links` as `l`
			where `uid` in ('.implode(',', $user_ids).');',
			ARRAY_A
		);
		if (empty($badge_links)) {
			return;
		}
		
		$badge_ids = array();
		foreach ($badge_links as $link) {
			$badge_ids[] = $link['bid'];
			if (!array_key_exists($link['uid'], self::$badge_link_cache)) {
				self::$badge_link_cache[$link['uid']] = array();
			}
			self::$badge_link_cache[$link['uid']][] = $link['bid'];
		}
		$badge_ids = array_unique($badge_ids);
		//Skip already loaded badges
		$loaded_badge_ids = array_keys(self::$badge_cache);
		$badge_ids = array_diff($badge_ids, $loaded_badge_ids);
		
		$badges = $wpdb->get_results(
			'select *
			from `qheb_badges`
			where `bid` in ('.implode(',',$badge_ids).');',
			ARRAY_A
		);
		foreach ($badges as $badge) {
			self::$badge_cache[$badge['bid']] = $badge;
		}
	}
}
?>