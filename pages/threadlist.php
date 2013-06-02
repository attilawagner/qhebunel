<?php
/**
 * Qhebunel
 * Category list page
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

/*
 * Load user permissions for this category
 */
global $permission;
$permission = QhebunelUser::get_permissions_for_category($cat_id);

/**
 * Renders the buttons for various actions
 * (eg. starting a new thread) according to
 * the permissions of the user.
 */
function render_action_bar() {
	global $permission, $cat_id;
	echo('<div class="qheb_actionbar">');
	if ($permission >= QHEBUNEL_PERMISSION_START) {
		echo('<a href="'.QhebunelUI::get_url_for_category($cat_id).'new-thread" />'.__('Start thread','qhebunel').'</a>');
	}
	echo('</div>');
}

/**
 * Displays an error message.
 */
function render_no_permission_page() {
	echo('<div class="qheb-error-message">'.__('You do not have sufficient permissions to view this category.', 'qhebunel').'</div>');
}

/*
 * Render Page
 */
if ($permission == QHEBUNEL_PERMISSION_NONE) {
	render_no_permission_page();
} else {
	render_action_bar();
	QhebunelPost::render_thread_list($cat_id);
	render_action_bar();
}
?>