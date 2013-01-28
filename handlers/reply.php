<?php
/**
 * Qhebunel
 * Thread reply handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$replyMessage = $_POST['reply_message'];
$replyThreadId = (int)$_POST['reply_thread'];

//Clean whitespace from both ends of the message
$replyMessage = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $replyMessage);

if (empty($replyMessage) || $replyThreadId <= 0) {
	Qhebunel::redirectToErrorPage();	
}

//Get category id
$replyCatId = $wpdb->get_var(
	$wpdb->prepare(
		'select `catid` from `qheb_threads` where `tid`=%d limit 1;',
		$replyThreadId
	),
	0,
	0
);
if (empty($replyCatId) || $replyCatId <= 0) {
	Qhebunel::redirectToErrorPage();
}

//Check permissions
$permission = QhebunelUser::getPermissionsForCategory($replyCatId);
if ($permission < QHEBUNEL_PERMISSION_WRITE) {
	Qhebunel::redirectToErrorPage();
}

//Insert into the database
global $current_user;
$wpdb->flush();
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_posts` (`tid`, `uid`, `text`, `postdate`) values (%d, %d, %s, %s);',
		$replyThreadId,
		$current_user->ID,
		$replyMessage,
		current_time('mysql')
	)
);

$postId = $wpdb->insert_id;
if ($postId == 0) {
	Qhebunel::redirectToErrorPage();
}

$wpdb->query(
	$wpdb->prepare(
		'update `qheb_threads` set `postcount`=`postcount`+1, `lastpostid`=%d where `tid`=%d limit 1;',
		$postId,
		$replyThreadId
	)
);

$wpdb->query(
	$wpdb->prepare(
		'update `qheb_user_ext` set `postcount`=`postcount`+1 where `uid`=%d limit 1;',
		$current_user->ID
	)
);

/*
 * Process attachments
* Anonymous users are forbidden to post attachments.
*/
if (QhebunelUser::hasPersmissionToUpload()) {
	QhebunelFiles::saveAttachmentArray($_FILES['attachments'], $postId);
}

//Redirect to post
$absoluteUrl = QhebunelUI::getUrlForPost($postId);
wp_redirect($absoluteUrl);//Temporal redirect
?>