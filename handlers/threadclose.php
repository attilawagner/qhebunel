<?php
/**
 * Qhebunel
 * Thread closing and reopenging special section (handler)
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;
if (preg_match('%(\d+)(/reopen)?%', $section_params, $regs)) {
	$thread_id = $regs[1];
	$reopen = (isset($regs[2]) ? $regs['2'] == '/reopen' : false);
} else {
	Qhebunel::redirect_to_error_page();
}

//Check permissions
if (QhebunelUser::is_moderator() == false) {
	Qhebunel::redirect_to_error_page();
}

if ($reopen) {
	//Clear close fields
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_threads` set `closedate`=null, `closer`=null where `tid`=%d;',
			$thread_id
		)
	);
} else {
	//Close thread in db
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_threads` set `closedate`=%s, `closer`=%d where `tid`=%d;',
			current_time('mysql'),
			$current_user->ID,
			$thread_id
		)
	);
}

//Redirect to topic
$absolute_url = QhebunelUI::get_url_for_thread($thread_id);
wp_redirect($absolute_url);//Temporal redirect
?>