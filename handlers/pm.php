<?php
/**
 * Qhebunel
 * Thread reply handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$message = $_POST['message'];
$partner_user_id = $_POST['partner'];
$user_id = @$current_user->ID;

//Clean whitespace from both ends of the message
$message = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $message);

if (empty($message) || $partner_user_id <= 0 || $user_id <= 0) {
	Qhebunel::redirect_to_error_page();	
}

//Save
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_privmessages` (`from`,`to`,`text`,`sentdate`) values (%d, %d, %s, %s);',
		$user_id,
		$partner_user_id,
		$message,
		current_time('mysql')
	)
);

//Redirect to message
$absolute_url = QhebunelUI::get_url_for_pm_user($partner_user_id);
wp_redirect($absolute_url);//Temporal redirect
?>