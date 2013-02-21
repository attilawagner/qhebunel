<?php
/**
 * Qhebunel
 * Statistics
 * 
 * @author Attila Wagner
 */

class QhebunelStats {
	
	/**
	 * Time since the last visit in seconds, that must have passed by to treat this as a different visit
	 * @var integer
	 */
	const VISIT_EXPIRATION = 300;
	
	/**
	 * Logs a user visit into the database.
	 * 
	 * @param integer $thread_id Thread ID.
	 */
	public static function log_visit($thread_id) {
		global $wpdb, $current_user;
		
		if (@$current_user->ID > 0) {
			
			$wpdb->query(
				$wpdb->prepare(
					'call qheb_log_user_visit(%d,%d,%s,%d);',
					$thread_id,
					$current_user->ID,
					current_time('mysql'),
					self::VISIT_EXPIRATION
				)
			);
		}
	}
}
?>