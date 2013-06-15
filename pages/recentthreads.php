<?php
/**
 * Qhebunel
 * Recent thread listing using the search engine.
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Show message to users who aren't logged in
if (!is_user_logged_in()) {
	echo('<div class="qheb-error-message">'.__('You must log in to use this function.', 'qhebunel').'</div>');
	return;//stop page rendering, but create footer
}

$search = array(
	'terms' =>			'',
	'location' =>		'both',
	'user' =>			'',
	'date_from' =>		'',
	'date_to' =>		'',
	'categories' =>		array(),
	'flags' =>			array('new'),
	'result_type' =>	'threads',
	'page' =>			0,
	
	'no_result_message' => __('There\'re no unread posts in the forum.')
);
QhebunelPost::show_search_results($search);

?>