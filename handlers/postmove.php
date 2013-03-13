<?php
/**
 * Qhebunel
 * Special section handler for post deletion
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$post_id = $_POST['post'];
$category_id = $_POST['category'];
$thread_id = $_POST['thread'];
$thread_title = $_POST['thread-title'];

if (!QhebunelUser::is_moderator()) {
	Qhebunel::redirect_to_error_page();
}

/*
 * Load post
 */
$post = $wpdb->get_row(
	$wpdb->prepare(
		'select `p`.`uid`, `p`.`tid`, `p`.`postdate`, `t`.`closedate`
		from `qheb_posts` as `p`
		  left join `qheb_threads` as `t`
		    on (`t`.`tid`=`p`.`tid`)
		where `pid`=%d',
		$post_id
	),
	ARRAY_A
);
if (empty($post)) {
	Qhebunel::redirect_to_error_page();
}

if ($post['tid'] != $thread_id) {
	/*
	 * Needed permissions checks
	 *  - No post can be moved from a closed thread.
	 *  - No post can be moved into a closed thread.
	 *  - Post can be moved into a thread that the user who wrote the post has permission to send it there too.
	 */
	if ($post['closedate'] != null) {
		Qhebunel::redirect_to_error_page();
	}
	
	$groups = QhebunelUser::get_groups($post['uid']);
	
	if ($thread_id == 'new') {
		/*
		 * Move into a new thread
		 */
		$permissions = $wpdb->get_var(
			$wpdb->prepare(
				'select max(`cp`.`access`) as `permission`
				from `qheb_category_permissions` as `cp`
				where `cp`.`catid`=%d and `cp`.`gid` in ('.implode(',',$groups).');',
				$category_id
			)
		);
		if (empty($permissions) || $permissions < QHEBUNEL_PERMISSION_START) {
			Qhebunel::redirect_to_error_page();
		}
		
		$thread_title = trim($thread_title);
		$thread_uri = Qhebunel::get_uri_component_for_title($thread_title);
		$wpdb->query(
			$wpdb->prepare(
				'insert into `qheb_threads` (`title`, `catid`, `startdate`, `starter`, `uri`, `postcount`) values (%s, %d, %s, %d, %s, 0);',
				$thread_title,
				$category_id,
				$post['postdate'],
				$post['uid'],
				$thread_uri
			)
		);
		$thread_id = $wpdb->insert_id;
		if ($thread_id == 0) {
			//failed to save
			Qhebunel::redirect_to_error_page();
		}
		
	} else {
		/*
		 * Move into existing thread
		 */
		$thread = $wpdb->get_row(
			$wpdb->prepare(
				'select `t`.`closedate`, max(`cp`.`access`) as `permission`
				from `qheb_threads` as `t`
				  left join `qheb_category_permissions` as `cp`
			        on (`cp`.`catid`=`t`.`catid`)
				where `t`.`tid`=%d and (`cp`.`gid` in ('.implode(',',$groups).');',
				$thread_id
			),
			ARRAY_A
		);
		if ($thread['closedate'] != null || $thread['permission'] < QHEBUNEL_PERMISSION_WRITE) {
			Qhebunel::redirect_to_error_page();
		}
	}
	
	/*
	 * Update post and threads
	 */
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_posts` set `tid`=%d where `pid`=%d and `tid`=%d;',
			$thread_id,
			$post_id,
			$post['tid'] //Old thread ID is inserted, so a double move cannot happen
		)
	);
	//Check whether the move happened or not
	if ($wpdb->rows_affected > 0) {
		//Update old thread
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_threads` set `postcount`=`postcount`-1 where `tid`=%d;',
				$post['tid']
			)
		);
		//Update new thread
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_threads` as `t` set `t`.`postcount`=`t`.`postcount`+1, `t`.`lastpostid`=(select max(`pp`.`pid`) from `qheb_posts` as `pp` where `pp`.`tid`=`t`.`tid`) where `t`.`tid`=%d;',
				$thread_id
			)
		);
		//Delete old thread if it's empty
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_threads` where `postcount`=0 and `tid`=%d;',
				$post['tid']
			)
		);
	}
}

//Redirect to post
$absolute_url = QhebunelUI::get_url_for_post($post_id);
wp_redirect($absolute_url);//Temporal redirect
die();
?>