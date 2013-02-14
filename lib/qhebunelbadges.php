<?php
/**
 * Qhebunel
 * Badge handler
 */
class QhebunelBadges {
	
	/**
	 * Saves or updates a badge.
	 * No checks are run on the image size, but file types are checked.
	 * 
	 * @param integer $badge_id ID of the badge to update. Pass NULL to create a new badge.
	 * @param integer $group_id Badge group ID.
	 * @param string $name Name of the badge.
	 * @param string $description Description of the badge.
	 * @param array $large_image A single item in $_FILES.
	 * @param array $small_image A single item in $_FILES.
	 * @return boolean True if the badge was saved successfully.
	 */
	public static function save_badge($badge_id, $group_id, $name, $description, $large_image, $small_image) {
		global $wpdb;
		
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
		}
		
		//Save the images
		$saved_paths = QhebunelFiles::save_badge_images($badge_id, $large_image, $small_image);
		if ($saved_paths === false) {
			$wpdb->query(
				$wpdb->prepare(
					'delete from `qheb_badges` where `bid`=%d;',
					$badge_id
				)
			);
			return false;
		}
		
		//Update the row
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_badges` set `largeimage`=%s, `smallimage`=%s where `bid`=%d;',
				$saved_paths['large'],
				$saved_paths['small'],
				$badge_id
			)
		);
		return true;
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
}
?>