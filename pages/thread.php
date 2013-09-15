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
		printf(__('Jump to page: %s','qhebunel'), $page_links);
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
	
	QhebunelPost::render_posts($thread_id, $page_num);
	
	render_action_bar($page_num);
	
	render_reply_form();
	render_move_post_form();
	render_report_post_form();
	
	echo('</div>');
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
		echo('<label id="move-post-category-label">'.__('Select category:','qhebunel').' <select name="category" id="move-post-category" disabled="disabled"><option>'.__('Loading...', 'qhebunel').'</option></select></label> ');
		echo('<label id="move-post-thread-label">'.__('Select thread:','qhebunel').' <select name="thread" id="move-post-thread" disabled="disabled"><option>'.__('Loading...', 'qhebunel').'</option><option value="new">'.__('Create new thread', 'qhebunel').'</option></select></label> ');
		echo('<label id="move-post-thread-title-label">'.__('Title for the new thread:','qhebunel').' <input name="thread-title" id="move-post-thread-title" type="text" disabled="disabled" /></label> ');
		echo('<input id="move-post-submit" type="submit" name="move" value="'.__('Move post', 'qhebunel').'" disabled="disabled" />');
		echo('</form>');
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