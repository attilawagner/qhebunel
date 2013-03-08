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
	
	/**
	 * Deletes a post with all of its attachments from the database
	 * and the filesystem. Also removes thread if this was the only post in it.
	 * @param integer $post_id Post ID in the databse.
	 * @return boolean Marks whether the thread exists after the deletion.
	 * False if this was the last post, and the thread also got deleted.
	 */
	public static function delete_post($post_id) {
		global $wpdb;
		
		//Load thread data
		$thread = $wpdb->get_row(
			$wpdb->prepare(
				'select `t`.`tid`, `t`.`catid`, `t`.`postcount`
				from `qheb_posts` as `p`
				  left join `qheb_threads` as `t`
				    on (`t`.`tid`=`p`.`tid`)
				where `p`.`pid`=%d',
				$post_id
			),
			ARRAY_A
		);
		
		//Remove post and attachments
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				'select `aid` from `qheb_attachments` where `pid`=%d;',
				$post_id
			),
			ARRAY_A
		);
		foreach ($attachments as $att) {
			QhebunelFiles::delete_attachment($att['aid']);
		}
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_posts` where `pid`=%d;',
				$post_id
			)
		);
		
		//Handle thread update/deletion
		if ($thread['postcount'] > 1) {
			//Update postcount in thread
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_threads` set `postcount`=`postcount`-1 where `tid`=%d;',
					$thread['tid']
				)
			);
		
			return true; //The thread still exists
		
		} else {
			//This was the last post, delete thread
			$wpdb->query(
				$wpdb->prepare(
					'delete from `qheb_threads` where `tid`=%d;',
					$thread['tid']
				)
			);
		
			return false; //Thread got deleted too
		}
	}
	
	/**
	 * Deletes a thread and all attachments from the thread.
	 * Updates user statistics after deletion.
	 * 
	 * @param integer $thread_id
	 */
	public static function delete_thread($thread_id) {
		global $wpdb;
		
		//Remove attachments
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				'select `a`.`aid`
				from `qheb_attachments` as `a`
				  left join `qheb_posts` as `p`
				    on (`p`.`pid`=`a`.`aid`)
				where `p`.`tid`=%d;'
			),
			ARRAY_A
		);
		foreach ($attachments as $att) {
			QhebunelFiles::delete_attachment($att['aid']);
		}
		
		//Update users' post count
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_user_ext` as `u`
				set `u`.`postcount`=`u`.`postcount`-(
				  select count(`p`.`pid`)
				  from `qheb_posts` as `p`
				  where `p`.`uid`=`u`.`uid` and `p`.`tid`=%d
				);',
				$thread_id
			)
		);
		
		//Remove thread
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_threads` where `tid`=%d;',
				$thread_id
			)
		);
		
		//Remove posts
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_posts` where `tid`=%d;',
				$thread_id
			)
		);
	}
}
?>