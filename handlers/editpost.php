<?php
/**
 * Qhebunel
 * Edit post handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$message = @$_POST['post-message'];
$post_id = (int)@$_POST['post-id'];
$edit_reason = @$_POST['edit-reason'];
$keep_files = @$_POST['keep-file'];
if (empty($message) || $post_id <= 0) {
	Qhebunel::redirect_to_error_page();
}

/*
 * Load post
 */
$post = $wpdb->get_row(
	$wpdb->prepare(
		'select `p`.`uid`, `p`.`text`, `t`.`catid`, `t`.`closedate`
		from `qheb_posts` as `p`
		  left join `qheb_threads` as `t`
		    on (`t`.`tid`=`p`.`tid`)
		where `pid`=%d',
		$post_id
	),
	ARRAY_A
);
if (empty($post)) {
	Qhebunel::redirect_to_error_page();
}

/*
 * Check permissions
 *  - No post can be edited in a closed thread.
 *  - Everyone can edit only their own posts.
 *  - Moderators can edit other users' posts.
 */
if ($post['closedate'] != null) {
	Qhebunel::redirect_to_error_page();
}
if ($post['uid'] != $current_user->ID && !QhebunelUser::is_moderator()) {
	Qhebunel::redirect_to_error_page();
}

/*
 * Load attachments
 */
$attachments = $wpdb->get_results(
	$wpdb->prepare(
		'select `aid` from `qheb_attachments` where `pid`=%d;',
		$post_id
	),
	ARRAY_A
);


/*
 * Save post message
 */
if (!empty($edit_reason)) {
	$edit_reason = trim($edit_reason);
}
$wpdb->query(
	$wpdb->prepare(
		'update `qheb_posts` set `text`=%s, `editdate`=%s, `editor`=%d, `editreason`=%s where `pid`=%d;',
		$message,
		current_time('mysql'),
		$current_user->ID,
		$edit_reason,
		$post_id
	)
);

/*
 * Remove old attachments if the user requests
 */
if (is_array($keep_files) && is_array($attachments)) {
	foreach ($attachments as $att) {
		if (!isset($keep_files[$att['aid']])) {
			QhebunelFiles::delete_attachment($att['aid']);
		}
	}
}

/*
 * Save new attachments
 */
if (QhebunelUser::has_persmission_to_upload()) {
	QhebunelFiles::save_attachment_array($_FILES['attachments'], $post_id);
}

//Redirect to post
$absolute_url = QhebunelUI::get_url_for_post($post_id);
wp_redirect($absolute_url);//Temporal redirect
?>