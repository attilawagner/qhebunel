<?php
/**
 * Qhebunel
 * Handler for awarding badges
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$badge_id = (int)$_POST['badge-id'];
$user_name = $_POST['nickname'];

if (!QhebunelUser::is_moderator() || empty($badge_id) || empty($user_name)) {
	Qhebunel::redirect_to_error_page();
}

$badge = $wpdb->get_row(
	$wpdb->prepare(
		'select `bid`
		from `qheb_badges`
		where `bid`=%d;',
		$badge_id
	),
	ARRAY_A
);
if (empty($badge)) {
	Qhebunel::redirect_to_error_page();
}

$user_id = $wpdb->get_var(
	$wpdb->prepare(
		'select `ID`
		from `qheb_wp_users`
		where `display_name`=%s limit 1;',
		$user_name
	)
);
if (empty($user_id)) {
	Qhebunel::redirect_to_error_page();
}

$wpdb->query(
	$wpdb->prepare(
		'insert ignore into `qheb_user_badge_links` (`bid`,`uid`,`startdate`) values (%d,%d,%s);',
		$badge_id,
		$user_id,
		current_time('mysql')
	)
);

//Redirect to badge page
$absolute_url = QhebunelUI::get_url_for_badge($badge_id);
wp_redirect($absolute_url);//Temporal redirect
?>