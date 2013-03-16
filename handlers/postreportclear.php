<?php
/**
 * Qhebunel
 * Post reports remover handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;
$post_id = (int)$section_params;

if ($post_id <= 0 || QhebunelUser::is_moderator() == false) {
	Qhebunel::redirect_to_error_page();
}

/*
 * Update post
 */
$post = $wpdb->query(
	$wpdb->prepare(
		'update `qheb_posts` set `flag`=%d where `pid`=%d;',
		QhebunelPost::FLAG_NONE,
		$post_id
	),
	ARRAY_A
);

/*
 * Clear reports
 */
$post = $wpdb->query(
	$wpdb->prepare(
		'delete from `qheb_post_reports` where `pid`=%d;',
		$post_id
	),
	ARRAY_A
);

//Redirect to post
$absolute_url = QhebunelUI::get_url_for_post($post_id);
wp_redirect($absolute_url);//Temporal redirect
?>