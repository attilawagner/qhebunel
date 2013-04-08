<?php
/**
 * Qhebunel
 * User profile page
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Show message to users who aren't logged in
if ($current_user->ID == 0) {
	echo('<div class="qheb-error-message">'.__('You must log in to gain access to user profiles.', 'qhebunel').'</div>');
	return;//stop page rendering, but create footer
}

global $section_params;
$user_id = (int)$section_params;

$ext_data = $wpdb->get_row(
	$wpdb->prepare(
		'select * from `qheb_user_ext` where `uid`=%d',
		$user_id
	),
	ARRAY_A
);

if (empty($ext_data)) {
	echo('<div class="qheb-error-message">'.__('User cannot be found.', 'qhebunel').'</div>');
	return;
}

?>
sasd