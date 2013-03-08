<?php
/**
 * Qhebunel
 * Thread deletion special section (handler)
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$thread_id = $_POST['thread-id'];

//Check permissions
if (QhebunelUser::is_moderator() == false) {
	Qhebunel::redirect_to_error_page();
}

//Load category ID from database
$category_id = $wpdb->get_var(
	$wpdb->prepare(
		'select `catid` from `qheb_threads` where `tid`=%d;',
		$thread_id
	)
);
if (empty($category_id)) {
	Qhebunel::redirect_to_error_page();
}

QhebunelPost::delete_thread($thread_id);

//Redirect to category
$absolute_url = QhebunelUI::get_url_for_category($category_id);
wp_redirect($absolute_url);//Temporal redirect
?>