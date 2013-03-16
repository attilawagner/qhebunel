<?php
/**
 * Qhebunel
 * Edit post handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$reason = trim(@$_POST['reason']);
$post_id = (int)@$_POST['post'];
if (empty($reason) || $post_id <= 0 || QhebunelUser::has_permission_to_report() == false) {
	Qhebunel::redirect_to_error_page();
}

/*
 * Update post
 */
$post = $wpdb->query(
	$wpdb->prepare(
		'update `qheb_posts` set `flag`=%d where `pid`=%d and `flag`=%d;',
		QhebunelPost::FLAG_REPORTED,
		$post_id,
		QhebunelPost::FLAG_NONE
	),
	ARRAY_A
);

if ($wpdb->rows_affected == 1) {
	/*
	 * Save report only if the post flag was modified
	 */
	$wpdb->query(
		$wpdb->prepare(
			'insert into `qheb_post_reports` (`pid`,`uid`,`reason`,`reportdate`) values (%d, %d, %s, %s);',
			$post_id,
			$current_user->ID,
			$reason,
			current_time('mysql')
		)
	);
}

//Redirect to post
$absolute_url = QhebunelUI::get_url_for_post($post_id);
wp_redirect($absolute_url);//Temporal redirect
?>