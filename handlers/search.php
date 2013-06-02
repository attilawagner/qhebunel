<?php
/**
 * Qhebunel
 * Search handler
 * Processes the POST fields and builds a user friendly URL to the search page.
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$terms =		@$_POST['terms'];
$location =		@$_POST['location'];
$user =			@$_POST['user'];
$date_from =	@$_POST['date-from'];
$date_to =		@$_POST['date-to'];
$categories =	@$_POST['categories'];
$post_flags =	@$_POST['post-flags'];
$result_type =	@$_POST['list-mode'];

$url = site_url('/forum/search/');

$url .= rawurlencode(trim($terms)) . '/';

$valid_locations = array('post', 'title', 'both');
if (in_array($location, $valid_locations)) {
	$url_location = $location;
} else {
	$url_location = 'post';
}
$url .= 'l:' . $url_location . '/';

$user = trim($user);
if (!empty($user)) {
	$url .= 'u:' . rawurlencode($user) . '/';
}

if (preg_match('/(\d{4})[-.\s]*(\d{1,2})[-.\s]*(\d{1,2})/', $date_from, $regs)) {
	$url .= 'df:' . $regs[1].'-'.$regs[2].'-'.$regs[3] . '/';
}
if (preg_match('/(\d{4})[-.\s]*(\d{1,2})[-.\s]*(\d{1,2})/', $date_to, $regs)) {
	$url .= 'dt:' . $regs[1].'-'.$regs[2].'-'.$regs[3] . '/';
}

if (!empty($categories) && is_array($categories)) {
	$categories = array_unique(array_map('intval', $categories), SORT_NUMERIC);
	sort($categories);
	$url .= 'c:' . implode(';', $categories) . '/';
}

if (!empty($post_flags) && is_array($post_flags)) {
	$flags = array();
	$valid_flags = array('new', 'edited', 'reported');
	foreach ($valid_flags as $vf) {
		if (in_array($vf, $post_flags)) {
			$flags[] = $vf;
		}
	}
	if (!empty($flags)) {
		$flags = array_unique($flags);
		$url .= 'f:' . implode(';', $flags) . '/';
	}
}

$valid_result_types = array('posts', 'threads');
if (in_array($result_type, $valid_result_types)) {
	$url_result_type = $result_type;
} else {
	$url_result_type = 'posts';
}
$url .= 'r:' . $result_type . '/';

//Redirect to search result page
wp_redirect($url);//Temporal redirect
?>