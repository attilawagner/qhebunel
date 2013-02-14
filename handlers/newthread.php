<?php
/**
 * Qhebunel
 * New thread form handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$topic_title = $_POST['topic_title'];
$topic_cat_id = $_POST['topic_category'];
$topic_body = $_POST['topic_message'];

if (empty($topic_body) || empty($topic_title) || empty($topic_cat_id)) {
	Qhebunel::redirect_to_error_page();
}

//Check permissions
$permission = QhebunelUser::get_permissions_for_category($topic_cat_id);
if ($permission < QHEBUNEL_PERMISSION_START) {
	Qhebunel::redirect_to_error_page();
}

//TODO check for topic with the same name (in JS too)
//Save thread to db
global $current_user;
$thread_uri = Qhebunel::get_uri_component_for_title($topic_title);
$wpdb->flush();
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_threads` (`title`, `catid`, `startdate`, `starter`, `uri`) values (%s, %d, %s, %d, %s);',
		$topic_title,
		$topic_cat_id,
		current_time('mysql'),
		$current_user->ID,
		$thread_uri
	)
);
$thread_id = $wpdb->insert_id;
if ($thread_id == 0) {
	//failed to save
	Qhebunel::redirect_to_error_page();
}

//Save opening post into db
$wpdb->flush();
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_posts` (`tid`, `uid`, `text`, `postdate`) values (%d, %d, %s, %s);',
		$thread_id,
		$current_user->ID,
		$topic_body,
		current_time('mysql')
	)
);
$post_id = $wpdb->insert_id;
if ($post_id == 0) {
	//Remove thread if op post cannot be saved
	$wpdb->query(
		$wpdb->prepare(
			'delete from `qheb_threads` where `tid`=%d limit 1;',
			$thread_id
		)
	);
	Qhebunel::redirect_to_error_page();
}

//Set last post id for the thread
$wpdb->query(
	$wpdb->prepare(
		'update `qheb_threads` set `lastpostid`=%d where `tid`=%d limit 1;',
		$post_id,
		$thread_id
	)
);

//Increment postcount for user
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

//Redirect to topic
$absolute_url = QhebunelUI::get_url_for_thread($thread_id);
wp_redirect($absolute_url);//Temporal redirect
?>