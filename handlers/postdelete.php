<?php
/**
 * Qhebunel
 * Special section handler for post deletion
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;
if (preg_match('%(\d+)(/confirm|/cancel)?%', $section_params, $regs)) {
	$post_id = $regs[1];
	$cancelled = (isset($regs[2]) ? $regs['2'] == '/cancel' : false);
	$confirmed = (isset($regs[2]) ? $regs['2'] == '/confirm' : false);
} else {
	Qhebunel::redirect_to_error_page();
}

/*
 * Load post
 */
$post = $wpdb->get_row(
	$wpdb->prepare(
		'select `p`.`uid`, `p`.`flag`, `p`.`tid`, `t`.`catid`, `t`.`closedate`
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
/*
 * Check permissions
 *  - No post can be edited in a closed thread.
 *  - Everyone can edit only their own posts.
 *  - Moderators can edit other users' posts.
 */
if ($post['closedate'] != null) {
	Qhebunel::redirect_to_error_page();
}
if ($post['uid'] != $current_user->ID && !QhebunelUser::is_moderator()) {
	Qhebunel::redirect_to_error_page();
}

if ($post['flag'] == QhebunelPost::FLAG_DELETION_UNCONFIRMED && $confirmed) {
	//Remove post
	$thread_exists = QhebunelPost::delete_post($post_id);
	
	if ($thread_exists) {
		//Redirect to thread
		$absolute_url = QhebunelUI::get_url_for_thread($post['tid'], -1);
		wp_redirect($absolute_url);//Temporal redirect
		die();
		
	} else {
		//Redirect to category
		$absolute_url = QhebunelUI::get_url_for_category($post['catid']);
		wp_redirect($absolute_url);//Temporal redirect
		die();
	}
	
} elseif ($post['flag'] != QhebunelPost::FLAG_DELETION_UNCONFIRMED && !$confirmed) {
	//Mark for confirmation
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_posts` set `flag`=%d where `pid`=%d;',
			QhebunelPost::FLAG_DELETION_UNCONFIRMED,
			$post_id
		)
	);
	//Redirect to post
	$absolute_url = QhebunelUI::get_url_for_post($post_id);
	wp_redirect($absolute_url);//Temporal redirect
	die();
	
} elseif ($post['flag'] == QhebunelPost::FLAG_DELETION_UNCONFIRMED && $cancelled) {
	//Remove mark
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_posts` set `flag`=%d where `pid`=%d;',
			QhebunelPost::FLAG_NONE,
			$post_id
		)
	);
	//Redirect to post
	$absolute_url = QhebunelUI::get_url_for_post($post_id);
	wp_redirect($absolute_url);//Temporal redirect
	die();
}

Qhebunel::redirect_to_error_page();
?>