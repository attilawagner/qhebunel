<?php
/**
 * Qhebunel
 * New thread form handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$topicTitle = $_POST['topic_title'];
$topicCatId = $_POST['topic_category'];
$topicBody = $_POST['topic_message'];

if (empty($topicBody) || empty($topicTitle) || empty($topicCatId)) {
	Qhebunel::redirectToErrorPage();
}

//Check permissions
$permission = QhebunelUser::getPermissionsForCategory($topicCatId);
if ($permission < QHEBUNEL_PERMISSION_START) {
	Qhebunel::redirectToErrorPage();
}

//TODO check for topic with the same name (in JS too)
//Save thread to db
global $current_user;
$threadUri = Qhebunel::getUriComponentForTitle($topicTitle);
$wpdb->flush();
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_threads` (`title`, `catid`, `startdate`, `starter`, `uri`) values (%s, %d, %s, %d, %s);',
		$topicTitle,
		$topicCatId,
		current_time('mysql'),
		$current_user->ID,
		$threadUri
	)
);
$threadId = $wpdb->insert_id;
if ($threadId == 0) {
	//failed to save
	Qhebunel::redirectToErrorPage();
}

//Save opening post into db
$wpdb->flush();
$wpdb->query(
	$wpdb->prepare(
		'insert into `qheb_posts` (`tid`, `uid`, `text`, `postdate`) values (%d, %d, %s, %s);',
		$threadId,
		$current_user->ID,
		$topicBody,
		current_time('mysql')
	)
);
$postId = $wpdb->insert_id;
if ($postId == 0) {
	//Remove thread if op post cannot be saved
	$wpdb->query(
		$wpdb->prepare(
			'delete from `qheb_threads` where `tid`=%d limit 1;',
			$threadId
		)
	);
	Qhebunel::redirectToErrorPage();
}

//Set last post id for the thread
$wpdb->query(
	$wpdb->prepare(
		'update `qheb_threads` set `lastpostid`=%d where `tid`=%d limit 1;',
		$postId,
		$threadId
	)
);

//Increment postcount for user
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

//Redirect to topic
$absoluteUrl = QhebunelUI::getUrlForThread($threadId);
wp_redirect($absoluteUrl);//Temporal redirect
?>