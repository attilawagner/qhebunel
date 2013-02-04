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
	 * @param integer $badgeId ID of the badge to update. Pass NULL to create a new badge.
	 * @param integer $groupId Badge group ID.
	 * @param string $name Name of the badge.
	 * @param string $description Description of the badge.
	 * @param array $largeImage A single item in $_FILES.
	 * @param array $smallImage A single item in $_FILES.
	 * @return boolean True if the badge was saved successfully.
	 */
	public static function saveBadge($badgeId, $groupId, $name, $description, $largeImage, $smallImage) {
		global $wpdb;
		
		//Create row in DB to get an ID
		if ($badgeId == null) {
			$wpdb->query(
				$wpdb->prepare(
					'insert into `qheb_badges` (`bgid`,`name`,`description`)
					values (%d, %s, %s);',
					$groupId,
					$name,
					$description
				)
			);
			if (($badgeId = $wpdb->insert_id) == 0) {
				return false;
			}
		}
		
		//Save the images
		$savedPaths = QhebunelFiles::saveBadgeImages($badgeId, $largeImage, $smallImage);
		if ($savedPaths === false) {
			$wpdb->query(
				$wpdb->prepare(
					'delete from `qheb_badges` where `bid`=%d;',
					$badgeId
				)
			);
			return false;
		}
		
		//Update the row
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_badges` set `largeimage`=%s, `smallimage`=%s where `bid`=%d;',
				$savedPaths['large'],
				$savedPaths['small'],
				$badgeId
			)
		);
		return true;
	}
	
	/**
	 * Deletes a badge from the database and the filesystem.
	 * 
	 * @param integer $badgeId ID of the badge in the database.
	 */
	public static function deleteBadge($badgeId) {
		global $wpdb;
		
		//Load badge
		$badge = $wpdb->get_row(
			$wpdb->prepare(
				'select * from `qheb_badges` where `bid`=%d;',
				$badgeId
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
				$badgeId
			)
		);
		
		//Remove db row
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_badges` where `bid`=%d;',
				$badgeId
			)
		);
		
		//Delete images
		@unlink(WP_CONTENT_DIR.'/'.$badge['largeimage']);
		@unlink(WP_CONTENT_DIR.'/'.$badge['smallimage']);
	}
}
?>