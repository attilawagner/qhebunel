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
 * Renders the table containing the list of threads
 * and statistics.
 */
function render_thread_list() {
	global $wpdb, $cat_id;
	echo('<table class="qheb_threadlist"><thead><tr><th>'.__('Thread topic','qhebunel').'</th><th>'.__('Posts','qhebunel').'</th><th>'.__('Last post','qhebunel').'</th><th>'.__('Starter','qhebunel').'</th></tr></thead><tbody>');
	$threads = $wpdb->get_results(
		$wpdb->prepare(
			'select `t`.`tid`, `t`.`title`, `t`.`startdate`, `t`.`starter`, `t`.`uri`, `us`.`display_name` as `startname`, `t`.`postcount`, `t`.`lastpostid`, `p`.`uid` as `lastuid`, `p`.`postdate` as `lastdate`, `ul`.`display_name` as `lastname`
			from `qheb_threads` as `t`
			left join `qheb_wp_users` as `us` on (`us`.`id`=`t`.`starter`)
			left join `qheb_posts` as `p` on (`p`.`pid`=`t`.`lastpostid`)
			left join `qheb_wp_users` as `ul` on (`ul`.`id`=`p`.`uid`)
			where `t`.`catid`=%d
			order by `lastpostid` desc;',
			$cat_id
		),
		ARRAY_A
	);
	if (empty($threads)) {
		echo('<tr><td colspan="4">'.__('There are no threads in this category.','qhebunel').'</td></tr>');
	} else {
		foreach ($threads as $thread) {
			$last_post_user = ($thread['lastuid'] > 0 ? $thread['lastname'] : 'A guest');
			$start_user = ($thread['starter'] > 0 ? $thread['startname'] : 'A guest');
			$lastpost = '<span class="name">'.$last_post_user.'</span> <span class="date" title="'.mysql2date('j F, Y @ G:i', $thread['lastdate']).'">'.QhebunelDate::get_list_date($thread['lastdate']).'</span>';
			$starter = '<span class="name">'.$start_user.'</span> <span class="date" title="'.mysql2date('j F, Y @ G:i', $thread['startdate']).'">'.QhebunelDate::get_list_date($thread['startdate']).'</span>';
			$thread_link = QhebunelUI::get_url_for_thread($thread['tid']);
			echo('<tr><td><a href="'.$thread_link.'">'.QhebunelUI::format_title($thread['title']).'</a></td><td>'.$thread['postcount'].'</td><td>'.$lastpost.'</td><td>'.$starter.'</td></tr>');
		}
	}
	echo('</tbody></table>');
	echo(get_date_template());
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
	render_thread_list();
	render_action_bar();
}
?>