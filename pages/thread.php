<?php
/**
 * Qhebunel
 * Thread page
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

/*
 * Check permissions
 */
global $permission;
$permission = QhebunelUser::get_permissions_for_category($cat_id);

/**
 * Renders the buttons for various actions
 * (eg. posting a reply, closing the thread) according to
 * the permissions of the user.
 * @param integer $pagenum 0 based id of the page.
 */
function render_action_bar($pagenum) {
	global $permission, $thread_id, $thread;
	echo('<div class="thread-actions">');
	if ($permission >= QHEBUNEL_PERMISSION_WRITE && empty($thread['closedate'])) {
		echo('<a href="#send-reply">'.__('Reply', 'qhebunel').'</a> ');
	}
	if (QhebunelUser::is_moderator()) {
		
		//Close and reopen
		if (empty($thread['closedate'])) {
			echo('<a href="'.site_url('forum/close-thread/'.$thread_id).'">'.__('Close thread', 'qhebunel').'</a> ');
		} else {
			echo('<a href="'.site_url('forum/close-thread/'.$thread_id.'/reopen').'">'.__('Reopen thread', 'qhebunel').'</a> ');
		}
		
		//Pin and unpin
		if ($thread['pinned']) {
			echo('<a href="'.site_url('forum/pin-thread/'.$thread_id.'/unpin').'">'.__('Unpin thread', 'qhebunel').'</a> ');
		} else {
			echo('<a href="'.site_url('forum/pin-thread/'.$thread_id).'">'.__('Pin thread', 'qhebunel').'</a> ');
		}
		
		//Delete
		echo('<a href="'.site_url('forum/delete-thread/'.$thread_id).'">'.__('Delete thread', 'qhebunel').'</a> ');
		
		//Move
		echo('<a href="'.site_url('forum/move-thread/'.$thread_id).'">'.__('Move thread', 'qhebunel').'</a> ');
	}
	
	$post_per_page = QHEBUNEL_POSTS_PER_PAGE;
	if ($thread['postcount'] > $post_per_page) {
		echo('<nav class="thread-pagination">');
		
		$page_links = array();
		
		//First page
		$page_links[] = '<a href="'.QhebunelUI::get_url_for_thread($thread_id).'">1</a>';
		
		$page_total = ceil($thread['postcount'] / $post_per_page);
		for ($i=1; $i<$page_total; $i++) {
			$page_links[] = '<a href="'.QhebunelUI::get_url_for_thread($thread_id, $i).'">'.($i+1).'</a>';
		}
		
		$page_links = implode(' ', $page_links);
		
		//translators: The is the placeholder for the links to the pages in the thread.
		printf(__('Jump to page: %s'), $page_links);
		echo('</nav>');
	}
	
	echo('</div>');
}

/**
 * Renders the thread with the title, action bars and posts.
 */
function render_thread() {
	global $wpdb, $thread_id,$thread_id,$page_id, $thread,$current_user;
	
	//Load thread info
	$thread = $wpdb->get_row(
		$wpdb->prepare(
			'select `t`.*, `c`.`name` as `catname`
			from `qheb_threads` as `t`
			  left join `qheb_categories` as `c`
			    on (`c`.`catid`=`t`.`catid`)
			where `tid`=%d limit 1;',
			$thread_id
		),
		ARRAY_A
	);
	
	/*
	 * The $page_id parameter is 0 for the first page, and acts as a one based counter starting from the second page.
	 * (This means that the page_ids for the first few pages are: 0,2,3,4.)
	 * To use it, we must convert it to 0 based.
	 */
	$page_num = $page_id;
	if ($page_num > 0) {
		$page_num--;
	}
	
	$post_per_page = QHEBUNEL_POSTS_PER_PAGE;
	$post_offset = $page_num * $post_per_page;
	
	$posts = $wpdb->get_results(
		$wpdb->prepare(
			'select `p`.*, `u`.`display_name`, `u2`.`display_name` as `editorname`, `e`.`avatar`, `e`.`signature`, `a`.`acount`, !isnull(`pr`.`pid`) as `userreported`
			from `qheb_posts` as `p`
			left join `qheb_wp_users` as `u`
				on (`u`.`ID`=`p`.`uid`)
			left join `qheb_wp_users` as `u2`
				on (`u2`.`ID`=`p`.`editor`)
			left join `qheb_user_ext` as `e`
				on (`e`.`uid`=`p`.`uid`)
			left join
				(select `pid`, count(*) as `acount` from `qheb_attachments` group by `pid`) as `a`
				on (`a`.`pid`=`p`.`pid`)
			left join
				(select `pid` from `qheb_post_reports` as `r` where `uid`=%d) as `pr`
				on (`pr`.`pid`=`p`.`pid`)
			where `tid`=%d
			order by `tid` asc
			limit %d,%d;',
			$current_user->ID,
			$thread_id,
			$post_offset,
			$post_per_page
		),
		ARRAY_A
	);
	
	//Get users who has post on this page, and preload their badges
	$user_ids = array();
	foreach ($posts as $post) {
		$user_ids[] = $post['uid'];
	}
	QhebunelBadges::preload_displayed_badges(array_unique($user_ids));
	
	//A thread contains at least the opening post, so we do not need to check for empty result
	echo('<div class="qheb-thread">');
	
	$category_name = '<div class="thread-category"><a href="'.QhebunelUI::get_url_for_category($thread['catid']).'">'.QhebunelUI::format_title($thread['catname']).'</a></div>';
	//Use h2 tag only on the first page
	$title_tag = ($page_id == 0 ? 'h2' : 'div');
	$thread_name = '<'.$title_tag.' class="thread-name"><a href="'.QhebunelUI::get_url_for_thread($thread_id).'">'.QhebunelUI::format_title($thread['title']).'</'.$title_tag.'>';
	echo('<div class="thread-title">');
	/* translators: first parameter is the category name, second is the thread name */
	printf(_x('%1$s: %2$s', 'thread-title', 'qhebunel'), $category_name, $thread_name);
	echo('</div>');
	
	render_action_bar($page_num);
	
	foreach ($posts as $post) {
		render_single_post($post);
	}
	
	render_action_bar($page_num);
	
	render_reply_form();
	render_move_post_form();
	render_report_post_form();
	
	echo('</div>');
}

/**
 * Renders a single post into the main container div.
 */
function render_single_post($post) {
	global $wpdb;
	
	/*
	 * Add meta for anonymous users.
	 */
	if ($post['uid'] == 0) {
		$post['display_name'] = __('A guest', 'qhebunel');
	}
	
	//Post holder div
	$class = get_class_for_post($post);
	echo('<article class="qheb-post'.$class.'" id="post-'.$post['pid'].'">');
	
	//User info
	echo('<aside class="user-info">');
	$profile_url = QhebunelUI::get_url_for_user($post['uid']);
	echo('<div class="user-name"><a href="'.$profile_url.'">'.$post['display_name'].'</a></div>');
	$avatar = '';
	if (!empty($post['avatar'])) {
		$avatar = '<a href="'.$profile_url.'"><img src="'.WP_CONTENT_URL.'/forum/avatars/'.$post['avatar'].'" alt="" /></a>';
	}
	echo('<div class="user-avatar">'.$avatar.'</div>');
	echo('<div class="user_stats"></div>');
	$badges = '';
	foreach (QhebunelBadges::get_displayed_badges($post['uid']) as $badge) {
		$badges .= '<div><img src="'.WP_CONTENT_URL.'/'.$badge['smallimage'].'" alt="'.$badge['name'].'" title="'.$badge['name'].'" /></div>';
	}
	echo('<div class="user-badges">'.$badges.'</div>');
	echo('</aside>');
	
	echo('<div class="post-holder">');
	
	//Post meta
	echo('<header class="post-meta">');
	echo('<a href="'.QhebunelUI::get_url_for_post($post['pid'], true).'" title="'.__('Permalink', 'qhebunel').'">#</a> ');
	echo('<time class="post_date" datetime="'.QhebunelDate::get_datetime_attribute($post['postdate']).'" title="'.QhebunelDate::get_relative_date($post['postdate']).'">'.QhebunelDate::get_post_date($post['postdate']).'</time>');
	echo('</header>');
	
	//Post content
	echo('<div class="post-message">');
	echo(QhebunelUI::format_post($post['text']));
	echo('</div>');
	
	//Attachments
	if ($post['acount'] > 0) {
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				'select * from `qheb_attachments` where `pid`=%d',
				$post['pid']
			),
			ARRAY_A
		);
		
		echo('<div class="post-attachments">');
		_e('Attachments:', 'qhebunel');
		echo('<ul>');
		foreach ($attachments as $attachment) {
			$url = site_url("forum/attachments/${attachment['aid']}-${attachment['safename']}");
			echo('<li><a href="'.$url.'">'.$attachment['name'].'</a></li>');
		}
		echo('</ul></div>');
	}
	
	//Signature
	echo('<div class="user-signature">');
	echo(QhebunelUI::format_post($post['signature']));
	echo('</div>');
	
	//Post action buttons
	render_post_actions($post);
	
	echo('</div>');
	
	//Post holder div
	echo('</article>');
}

function get_class_for_post($post) {
	global $wpdb, $current_user;
	
	switch($post['flag']) {
		case QhebunelPost::FLAG_DELETION_UNCONFIRMED:
			return ' deleted';
			break;
		
		case QhebunelPost::FLAG_REPORTED:
			if ($post['userreported'] || QhebunelUser::is_moderator()) {
				//Only those users see the reported status who have submitted a report for it.
				//Moderators always see the reported status.
				return ' reported';
			}
			break;
	}
	
	return '';
}

function render_post_actions($post) {
	global $permission, $thread_id, $page_id, $current_user, $thread;
	$thread_open = $thread['closedate'] == null;
	
	echo('<footer class="post-actions">');
	if ($post['editor'] != null) {
		echo('<div class="edit-info">');
		$edit_date = '<time class="edit-date" datetime="'.QhebunelDate::get_datetime_attribute($post['editdate']).'" title="'.QhebunelDate::get_relative_date($post['editdate']).'">'.QhebunelDate::get_post_date($post['editdate']).'</time>';
		echo('<span class="edit-user">'.sprintf(__('Last edited by: %1$s on %2$s.', 'qhebunel'), $post['editorname'], $edit_date).'</span> ');
		if (!empty($post['editreason'])) {
			echo('<span class="edit-reason">'.sprintf(__('Reason: %s', 'qhebunel'), htmlentities2($post['editreason'])).'</span> ');
		}
		echo('</div>');
	}
	
	echo('<div>');
	if ($thread_open && $permission >= QHEBUNEL_PERMISSION_WRITE) {
		$quote_url = QhebunelUI::get_url_for_thread($thread_id, $page_id).'?quote='.$post['pid'].'#send-reply';
		echo('<a class="post-action reply-link" href="#send-reply">'.__('Reply', 'qhebunel').'</a> ');
		echo('<a class="post-action quote-link" href="'.$quote_url.'">'.__('Quote', 'qhebunel').'</a> ');
	}
	if ($thread_open && ($post['uid'] == $current_user->ID || QhebunelUser::is_moderator())) {
		$edit_url = site_url('forum/edit-post/'.$post['pid']);
		echo('<a class="post-action edit-link" href="'.$edit_url.'">'.__('Edit', 'qhebunel').'</a> ');
		if ($post['flag'] == QhebunelPost::FLAG_DELETION_UNCONFIRMED) {
			$del_url = site_url('forum/delete-post/'.$post['pid'].'/confirm');
			echo('<a class="post-action delete-link" href="'.$del_url.'">'.__('Confirm deletion', 'qhebunel').'</a> ');
			$del_url = site_url('forum/delete-post/'.$post['pid'].'/cancel');
			echo('<a class="post-action delete-link" href="'.$del_url.'">'.__('Cancel deletion', 'qhebunel').'</a> ');
		} else {
			$del_url = site_url('forum/delete-post/'.$post['pid']);
			echo('<a class="post-action delete-link" href="'.$del_url.'">'.__('Delete', 'qhebunel').'</a> ');
		}
	}
	if ($thread_open && QhebunelUser::is_moderator()) {
		echo('<a class="post-action move-link" href="#">'.__('Move', 'qhebunel').'</a> ');
	}
	if (!$post['userreported'] && QhebunelUser::has_permission_to_report()) {
		echo('<a class="post-action report-link" href="#">'.__('Report', 'qhebunel').'</a> ');
	}
	if ($post['flag'] == QhebunelPost::FLAG_REPORTED && QhebunelUser::is_moderator()) {
		$clear_url = site_url('forum/clear-reports/'.$post['pid']);
		echo('<a class="post-action clear-reports-link" href="'.$clear_url.'">'.__('Clear reports', 'qhebunel').'</a> ');
	}
	echo('</div>');
	
	if ($post['flag'] == QhebunelPost::FLAG_REPORTED && QhebunelUser::is_moderator()) {
		render_reports($post);
	}
	
	echo('</footer>');
}

function render_reply_form() {
	global $thread_id, $thread;
	
	if ($thread['closedate'] == null) {
	
		//Get quoted post
		if (isset($_GET['quote'])) {
			$default_text = htmlentities2(QhebunelPost::get_quote_for_post($_GET['quote']));
		} else {
			$default_text = '';
		}
		
		echo('<div id="send-reply">');
		echo('<form id="reply-form" action="'.site_url('forum/').'" method="post" enctype="multipart/form-data">');
		echo('<input type="hidden" name="action" value="reply" />');
		echo('<input type="hidden" name="MAX_FILE_SIZE" value="' . QHEBUNEL_ATTACHMENT_MAX_SIZE . '" />');
		echo('<input type="hidden" name="reply_thread" value="'.$thread_id.'" />');
		echo('<textarea name="reply_message">'.$default_text.'</textarea>');
		if (QhebunelUser::has_persmission_to_upload()) {
			echo('<div class="attachments"><span class="attachmentlist">'.__('Attachments','qhebunel').'</span><div class="attachmentlist"><div class="file"><input type="file" name="attachments[]" class="attachment" /><input type="button" value="Remove" class="remove" /></div></div></div>');
		}
		echo('<input type="submit" name="new_thread" value="'.__('Post reply','qhebunel').'" />');
		echo('</form>');
		echo('</div>');
	
	}
}

function render_move_post_form() {
	if (QhebunelUser::is_moderator()) {
		echo('<div id="move-post">');
		echo('<form id="move-post-form" action="'.site_url('forum/').'" method="post">');
		echo('<input type="hidden" name="action" value="postmove" />');
		echo('<input type="hidden" name="post" id="move-post-id" value="" />');
		echo('<label id="move-post-category-label">'.__('Select category:').' <select name="category" id="move-post-category" disabled="disabled"><option>'.__('Loading...', 'qhebunel').'</option></select></label> ');
		echo('<label id="move-post-thread-label">'.__('Select thread:').' <select name="thread" id="move-post-thread" disabled="disabled"><option>'.__('Loading...', 'qhebunel').'</option><option value="new">'.__('Create new thread', 'qhebunel').'</option></select></label> ');
		echo('<label id="move-post-thread-title-label">'.__('Title for the new thread:').' <input name="thread-title" id="move-post-thread-title" type="text" disabled="disabled" /></label> ');
		echo('<input id="move-post-submit" type="submit" name="move" value="'.__('Move post', 'qhebunel').'" disabled="disabled" />');
		echo('</form>');
		echo('</div>');
	}
}

function render_reports($post) {
	global $wpdb;
	
	$reports = $wpdb->get_results(
		$wpdb->prepare(
			'select `r`.*, `u`.`display_name` as `username`
			from `qheb_post_reports` as `r`
			  left join `qheb_wp_users` as `u`
				on (`u`.`ID`=`r`.`uid`)
			where `pid`=%d
			order by `reportdate`;',
			$post['pid']
		),
		ARRAY_A
	);
	
	if (!empty($reports)) {
		echo('<div class="post-reports">');
		foreach ($reports as $report) {
			echo('<div class="post-report-message">');
			echo('<p class="report-meta">');
			$time = '<time class="post_date" datetime="'.QhebunelDate::get_datetime_attribute($report['reportdate']).'" title="'.QhebunelDate::get_relative_date($report['reportdate']).'">'.QhebunelDate::get_post_date($report['reportdate']).'</time>';
			/* translators: First parameter is the username, second is the date of the report submission */
			printf(__('Reported by %1$s on %2$s:', 'qhebunel'), $report['username'], $time);
			echo('</p>');
			echo('<p class="report-reason">');
			echo(htmlspecialchars($report['reason']));
			echo('</p>');
			echo('</div>');
		}
		echo('</div>');
	}
}

function render_report_post_form() {
	if (QhebunelUser::has_permission_to_report()) {
		echo('<div id="report-post">');
		echo('<form id="report-post-form" action="'.site_url('forum/').'" method="post">');
		echo('<input type="hidden" name="action" value="postreport" />');
		echo('<input type="hidden" name="post" id="report-post-id" value="" />');
		echo('<label>'.__('Please describe why do you think this post should be removed:','qhebunel').'<textarea name="reason" id="report-post-reason"></textarea></label>');
		echo('<input id="report-post-submit" type="submit" name="move" value="'.__('Submit report', 'qhebunel').'" disabled="disabled" />');
		echo('</form>');
		echo('</div>');
	}
}

function render_no_permission_page() {
	echo('<div class="qheb-error-message">'.__('You do not have sufficient permissions to view this thread.', 'qhebunel').'</div>');
}

/*
 * Render Page
 */
if ($permission == QHEBUNEL_PERMISSION_NONE) {
	render_no_permission_page();
} else {
	render_thread();
	
	//Save stats
	QhebunelStats::log_visit($thread_id);
}
?>