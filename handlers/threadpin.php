<?php
/**
 * Qhebunel
 * Thread pinning and unpinning special section (handler)
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;
if (preg_match('%(\d+)(/unpin)?%', $section_params, $regs)) {
	$thread_id = $regs[1];
	$unpin = (isset($regs[2]) ? $regs['2'] == '/unpin' : false);
} else {
	Qhebunel::redirect_to_error_page();
}

//Check permissions
if (QhebunelUser::is_moderator() == false) {
	Qhebunel::redirect_to_error_page();
}

if ($unpin) {
	//Remove pinned flag
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_threads` set `pinned`=0 where `tid`=%d;',
			$thread_id
		)
	);
} else {
	//Add pinned flag
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_threads` set `pinned`=1 where `tid`=%d;',
			$thread_id
		)
	);
}

//Redirect to topic
$absolute_url = QhebunelUI::get_url_for_thread($thread_id);
wp_redirect($absolute_url);//Temporal redirect
?>