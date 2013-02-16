<?php
/**
 * Qhebunel
 * Post related functions and constants
 * 
 * @author Attila Wagner
 */
class QhebunelPost {
	const FLAG_NONE = 0;
	const FLAG_DELETION_UNCONFIRMED = 1;
	const FLAG_REPORTED = 2;
	
	/**
	 * Loads the post from the database and
	 * creates a BBCode quote from it.
	 *
	 * @param integer $post_id Post ID.
	 * @return string Post as a quote. Empty string if the post does not exists.
	 */
	public static function get_quote_for_post($post_id) {
		global $wpdb;
		$post_data = $wpdb->get_row(
			$wpdb->prepare(
				'select `p`.`text`, `u`.`display_name` as `name`
				from `qheb_posts` as `p`
				  left join `qheb_wp_users` as `u`
				    on (`u`.`ID`=`p`.`uid`)
				where `pid`=%d;',
				$post_id
			),
			ARRAY_A
		);
		if (empty($post_data)) {
			return '';
		}
	
		return '[quote="'.$post_data['name'].'" post="'.$post_id.'"]'.$post_data['text'].'[/quote]';
	}
}
?>