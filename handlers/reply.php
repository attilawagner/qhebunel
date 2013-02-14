<?php
/**
 * Qhebunel
 * Thread reply handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$reply_message = $_POST['reply_message'];
$reply_thread_id = (int)$_POST['reply_thread'];

//Clean whitespace from both ends of the message
$reply_message = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $reply_message);

if (empty($reply_message) || $reply_thread_id <= 0) {
	Qhebunel::redirect_to_error_page();	
}

//Get category id
$reply_cat_id = $wpdb->get_var(
	$wpdb->prepare(
		'select `catid` from `qheb_threads` where `tid`=%d limit 1;',
		$reply_thread_id
	),
	0,
	0
);
if (empty($reply_cat_id) || $reply_cat_id <= 0) {
	Qhebunel::redirect_to_error_page();
}

//Check permissions
$permission = QhebunelUser::get_permissions_for_category($reply_cat_id);
if ($permission < QHEBUNEL_PERMISSION_WRITE) {
	Qhebunel::redirect_to_error_page();
}

//Insert into the database
global $current_user;
$wpdb->flush();
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_posts` (`tid`, `uid`, `text`, `postdate`) values (%d, %d, %s, %s);',
		$reply_thread_id,
		$current_user->ID,
		$reply_message,
		current_time('mysql')
	)
);

$post_id = $wpdb->insert_id;
if ($post_id == 0) {
	Qhebunel::redirect_to_error_page();
}

$wpdb->query(
	$wpdb->prepare(
		'update `qheb_threads` set `postcount`=`postcount`+1, `lastpostid`=%d where `tid`=%d limit 1;',
		$post_id,
		$reply_thread_id
	)
);

$wpdb->query(
	$wpdb->prepare(
		'update `qheb_user_ext` set `postcount`=`postcount`+1 where `uid`=%d limit 1;',
		$current_user->ID
	)
);

/*
 * Process attachments
* Anonymous users are forbidden to post attachments.
*/
if (QhebunelUser::has_persmission_to_upload()) {
	QhebunelFiles::save_attachment_array($_FILES['attachments'], $post_id);
}

//Redirect to post
$absolute_url = QhebunelUI::get_url_for_post($post_id);
wp_redirect($absolute_url);//Temporal redirect
?>