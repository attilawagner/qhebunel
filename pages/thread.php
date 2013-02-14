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
 * @param array $thread The db row for the current thread.
 * @param integer $pagenum 0 based id of the page.
 */
function render_action_bar($thread, $pagenum) {
	global $permission, $thread_id;
	echo('<div class="thread_actions">');
	if ($permission >= QHEBUNEL_PERMISSION_WRITE) {
		echo('<a href="#send-reply">'.__('Reply', 'qhebunel').'</a>');
	}
	
	$post_per_page = QHEBUNEL_POSTS_PER_PAGE;
	if ($thread['postcount'] > $post_per_page) {
		echo('<nav class="thread_pagination">');
		
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
	global $wpdb, $thread_id,$thread_id,$page_id;
	
	//Load thread info
	$thread = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_threads` where `tid`=%d limit 1;',
			$thread_id
		),
		ARRAY_A
	);
	$thread = $thread[0];
	
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
			'select `p`.*, `u`.`display_name`, `e`.`avatar`, `e`.`signature`, `a`.`acount`
			from `qheb_posts` as `p`
			left join `qheb_wp_users` as `u`
				on (`u`.`ID`=`p`.`uid`)
			left join `qheb_user_ext` as `e`
				on (`e`.`uid`=`p`.`uid`)
			left join
				(select `pid`, count(*) as `acount` from `qheb_attachments` group by `pid`) as `a`
				on (`a`.`pid`=`p`.`pid`)
			where `tid`=%d
			order by `tid` asc
			limit %d,%d;',
			$thread_id,
			$post_offset,
			$post_per_page
		),
		ARRAY_A
	);
	//A thread contains at least the opening post, so we do not need to check for empty result
	echo('<div class="qheb_thread">');
	
	//Use h2 tag only on the first page
	$title_tag = ($page_id == 0 ? 'h2' : 'div');
	echo('<'.$title_tag.' class="thread_title">'.QhebunelUI::format_title($thread['title']).'</'.$title_tag.'>');
	
	render_action_bar($thread, $page_num);
	
	foreach ($posts as $post) {
		render_single_post($post);
	}
	
	render_action_bar($thread, $page_num);
	
	render_reply_form();
	
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
	echo('<article class="qheb_post" id="post-'.$post['pid'].'">');
	
	//User info
	echo('<aside class="user_info">');
	echo('<div class="user_name">'.$post['display_name'].'</div>');
	$avatar = '';
	if (!empty($post['avatar'])) {
		$avatar = '<img src="'.WP_CONTENT_URL.'/forum/avatars/'.$post['avatar'].'" alt="" />';
	}
	echo('<div class="user_avatar">'.$avatar.'</div>');
	echo('<div class="user_stats"></div>');
	echo('<div class="user_badges"></div>');
	echo('</aside>');
	
	echo('<div class="post_holder">');
	
	//Post meta
	echo('<header class="post_meta">');
	echo('<time class="post_date" datetime="'.QhebunelDate::get_datetime_attribute($post['postdate']).'" title="'.QhebunelDate::get_relative_date($post['postdate']).'">'.QhebunelDate::get_post_date($post['postdate']).'</time>');
	echo('</header>');
	
	//Post content
	echo('<div class="post_message">');
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
		
		echo('<div class="post_attachments">');
		_e('Attachments:', 'qhebunel');
		echo('<ul>');
		foreach ($attachments as $attachment) {
			$url = site_url("forum/attachments/${attachment['aid']}-${attachment['safename']}");
			echo('<li><a href="'.$url.'">'.$attachment['safename'].'</a></li>');
		}
		echo('</ul></div>');
	}
	
	//Signature
	echo('<div class="user_signature">');
	echo(QhebunelUI::format_post($post['signature']));
	echo('</div>');
	
	//Post action buttons
	render_post_actions($post);
	
	echo('</div>');
	
	//Post holder div
	echo('</article>');
}

function render_post_actions($post) {
	global $permission, $thread_id, $page_id;
	
	echo('<footer class="post_actions">');
	if ($permission >= QHEBUNEL_PERMISSION_WRITE) {
		$quote_url = QhebunelUI::get_url_for_thread($thread_id, $page_id).'?quote='.$post['pid'].'#send-reply';
		echo('<a class="post_action reply_link" href="#send-reply">'.__('Reply', 'qhebunel').'</a> ');
		echo('<a class="post_action quote_link" href="'.$quote_url.'">'.__('Quote', 'qhebunel').'</a> ');
	}
	echo('</footer>');
}

function render_reply_form() {
	global $thread_id;
	
	//Get quoted post
	if (isset($_GET['quote'])) {
		$default_text = htmlentities2(QhebunelUI::get_quote_for_post($_GET['quote']));
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

function render_no_permission_page() {
	echo('<div class="qheb_error_message">'.__('You do not have sufficient permissions to view this thread.', 'qhebunel').'</div>');
}

/*
 * Render Page
 */
if ($permission == QHEBUNEL_PERMISSION_NONE) {
	render_no_permission_page();
} else {
	render_thread();
}
?>