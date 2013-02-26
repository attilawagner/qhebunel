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
	global $wpdb, $cat_id, $current_user;
	echo('<table class="qheb_threadlist"><thead><tr><th>'.__('Thread topic','qhebunel').'</th><th>'.__('Posts','qhebunel').'</th><th>'.__('Last post','qhebunel').'</th><th>'.__('Starter','qhebunel').'</th></tr></thead><tbody>');
	$threads = $wpdb->get_results(
		$wpdb->prepare(
			'select `t`.`tid`, `t`.`title`, `t`.`startdate`, `t`.`starter`, `t`.`uri`, `us`.`display_name` as `startname`, `t`.`postcount`, `t`.`lastpostid`, `p`.`uid` as `lastuid`, `p`.`postdate` as `lastdate`, `ul`.`display_name` as `lastname`, `n`.`new`, `t`.`pinned`,`t`.`closedate`
			from `qheb_threads` as `t`
			  left join `qheb_wp_users` as `us`
			    on (`us`.`id`=`t`.`starter`)
			  left join `qheb_posts` as `p`
			    on (`p`.`pid`=`t`.`lastpostid`)
			  left join `qheb_wp_users` as `ul`
			    on (`ul`.`id`=`p`.`uid`)
			  left join
			    (
			      select `p`.`tid`, count(*) as `new`
			      from `qheb_posts` as `p`
			        left join `qheb_visits` as `v`
			          on (`v`.`tid`=`p`.`tid`)
			      where `p`.`postdate`>`v`.`visitdate` and `v`.`uid`=%d
			      group by `p`.`tid`
			    ) as `n`
			    on (`n`.`tid`=`t`.`tid`)
			where `t`.`catid`=%d
			order by `t`.`pinned` desc, `t`.`lastpostid` desc;',
			@$current_user->ID,
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
			$new_posts = ($thread['new'] > 0 ? '<span class="new-posts">'.sprintf(_n('(%d new)', '(%d new)', $thread['new'], 'qhebunel'),$thread['new']).'</span>' : '');
			$icon = get_icon_for_thread($thread);
			echo('<tr><td>'.$icon.'<a href="'.$thread_link.'">'.QhebunelUI::format_title($thread['title']).'</a></td><td>'.$thread['postcount'].' '.$new_posts.'</td><td>'.$lastpost.'</td><td>'.$starter.'</td></tr>');
		}
	}
	echo('</tbody></table>');
	echo(get_date_template());
}

/**
 * Returns the &lt;span&gt; tag for the thread icon.
 * @param array $thread A thread row from the database.
 * @return string HTML fragment.
 */
function get_icon_for_thread($thread) {
	$icon = '<span class="thread-icon';
	if (!empty($thread['closedate'])) {
		$icon .= ' closed';
	}
	if ($thread['pinned']) {
		$icon .= ' pinned';
	}
	if ($thread['new'] > 0) {
		$icon .= ' unread';
	}
	$icon .= '"></span>';
	return $icon;
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