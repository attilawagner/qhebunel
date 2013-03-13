<?php
/**
 * Qhebunel
 * Thread moving special section (handler)
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$thread_id = $_POST['thread-id'];
$target_category_id = $_POST['target-category'];

//Check permissions
if (QhebunelUser::is_moderator() == false) {
	Qhebunel::redirect_to_error_page();
}

//Load original category ID from database
$original_category_id = $wpdb->get_var(
	$wpdb->prepare(
		'select `catid` from `qheb_threads` where `tid`=%d;',
		$thread_id
	)
);
if (empty($original_category_id)) {
	Qhebunel::redirect_to_error_page();
}

//Check target category and permissions
$groups = QhebunelUser::get_groups();
$target_category = $wpdb->get_results(
	$wpdb->prepare(
		'select `c`.`catid`, max(`cp`.`access`) as `permission`
		from `qheb_categories` as `c`
		  left join `qheb_category_permissions` as `cp`
		    on (`cp`.`catid`=`c`.`catid`)
		where `c`.`catid`=%d and (`cp`.`gid` in ('.implode(',',$groups).') or `cp`.`gid` is null)
		group by `c`.`catid`;',
		$target_category_id
	),
	ARRAY_A
);
if (empty($target_category)) {
	Qhebunel::redirect_to_error_page(); //Target category does not exist
}
if ($target_category['permission'] < QHEBUNEL_PERMISSION_START && !QhebunelUser::is_admin()) {
	Qhebunel::redirect_to_error_page(); //Current user cannot start/move a thread in this category
}

if ($original_category_id != $target_category_id) {
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_threads` set `catid`=%d where `tid`=%d;',
			$target_category_id,
			$thread_id
		)
	);
}

//Redirect to thread
$absolute_url = QhebunelUI::get_url_for_thread($thread_id);
wp_redirect($absolute_url);//Temporal redirect
?>