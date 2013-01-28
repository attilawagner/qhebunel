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
$permission = QhebunelUser::getPermissionsForCategory($catId);

/**
 * Renders the buttons for various actions
 * (eg. posting a reply, closing the thread) according to
 * the permissions of the user.
 * @param array $thread The db row for the current thread.
 * @param integer $pagenum 0 based id of the page.
 */
function renderActionBar($thread, $pagenum) {
	global $permission, $threadId;
	echo('<div class="thread_actions">');
	if ($permission >= QHEBUNEL_PERMISSION_WRITE) {
		//TODO: reply link is the last page
		echo('<a href="#">'.__('Reply', 'qhebunel').'</a>');
	}
	
	$postPerPage = QHEBUNEL_POSTS_PER_PAGE;
	if ($thread['postcount'] > $postPerPage) {
		echo('<nav class="thread_pagination">');
		
		$pageLinks = array();
		
		//First page
		$pageLinks[] = '<a href="'.QhebunelUI::getUrlForThread($threadId).'">1</a>';
		
		$pageTotal = ceil($thread['postcount'] / $postPerPage);
		for ($i=1; $i<$pageTotal; $i++) {
			$pageLinks[] = '<a href="'.QhebunelUI::getUrlForThread($threadId, $i).'">'.($i+1).'</a>';
		}
		
		$pageLinks = implode(' ', $pageLinks);
		
		//translators: The is the placeholder for the links to the pages in the thread.
		printf(__('Jump to page: %s'), $pageLinks);
		echo('</nav>');
	}
	
	echo('</div>');
}

/**
 * Renders the thread with the title, action bars and posts.
 */
function renderThread() {
	global $wpdb, $threadId,$threadId,$pageId;
	
	//Load thread info
	$thread = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_threads` where `tid`=%d limit 1;',
			$threadId
		),
		ARRAY_A
	);
	$thread = $thread[0];
	
	/*
	 * The $pageId parameter is 0 for the first page, and acts as a one based counter starting from the second page.
	 * (This means that the pageIds for the first few pages are: 0,2,3,4.)
	 * To use it, we must convert it to 0 based.
	 */
	$pageNum = $pageId;
	if ($pageNum > 0) {
		$pageNum--;
	}
	
	$postPerPage = QHEBUNEL_POSTS_PER_PAGE;
	$postOffset = $pageNum * $postPerPage;
	
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
			$threadId,
			$postOffset,
			$postPerPage
		),
		ARRAY_A
	);
	//A thread contains at least the opening post, so we do not need to check for empty result
	echo('<div class="qheb_thread">');
	
	//Use h2 tag only on the first page
	$titleTag = ($pageId == 0 ? 'h2' : 'div');
	echo('<'.$titleTag.' class="thread_title">'.QhebunelUI::formatTitle($thread['title']).'</'.$titleTag.'>');
	
	renderActionBar($thread, $pageNum);
	
	foreach ($posts as $post) {
		renderSinglePost($post);
	}
	
	renderActionBar($thread, $pageNum);
	
	renderReplyForm();
	
	echo('</div>');
}

/**
 * Renders a single post into the main container div.
 */
function renderSinglePost($post) {
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
	echo('<time class="post_date" datetime="'.QhebunelDate::getDatetimeAttribute($post['postdate']).'" title="'.QhebunelDate::getRelativeDate($post['postdate']).'">'.QhebunelDate::getPostDate($post['postdate']).'</time>');
	echo('</header>');
	
	//Post content
	echo('<div class="post_message">');
	echo(QhebunelUI::formatPost($post['text']));
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
			$url = get_site_url(null, "forum/attachments/${attachment['aid']}-${attachment['safename']}");
			echo('<li><a href="'.$url.'">'.$attachment['safename'].'</a></li>');
		}
		echo('</ul></div>');
	}
	
	//Signature
	echo('<div class="user_signature">');
	echo(QhebunelUI::formatPost($post['signature']));
	echo('</div>');
	
	//Post action buttons
	echo('<footer class="post_actions">');
	echo('Reply, Quote, etc.');
	echo('</footer>');
	
	echo('</div>');
	
	//Post holder div
	echo('</article>');
}

function renderReplyForm() {
	global $threadId;
	echo('<form id="replyForm" action="'.site_url('forum/').'" method="post" enctype="multipart/form-data">');
	echo('<input type="hidden" name="action" value="reply" />');
	echo('<input type="hidden" name="MAX_FILE_SIZE" value="' . QHEBUNEL_ATTACHMENT_MAX_SIZE . '" />');
	echo('<input type="hidden" name="reply_thread" value="'.$threadId.'" />');
	echo('<textarea name="reply_message"></textarea>');
	if (QhebunelUser::hasPersmissionToUpload()) {
		echo('<div class="attachments"><span class="attachmentlist">'.__('Attachments','qhebunel').'</span><div class="attachmentlist"><div class="file"><input type="file" name="attachments[]" class="attachment" /><input type="button" value="Remove" class="remove" /></div></div></div>');
	}
	echo('<input type="submit" name="new_thread" value="'.__('Post reply','qhebunel').'" />');
	echo('</form>');
}

function renderNoPermissionPage() {
	echo('<div class="qheb_error_message">'.__('You do not have sufficient permissions to view this thread.', 'qhebunel').'</div>');
}

/*
 * Render Page
 */
if ($permission == QHEBUNEL_PERMISSION_NONE) {
	renderNoPermissionPage();
} else {
	renderThread();
}
?>