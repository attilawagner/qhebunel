<?php
/**
 * Qhebunel
 * Post related functions and constants
 * 
 * @author Attila Wagner
 */
class QhebunelPost {
	const FLAG_NONE = 0;
	const FLAG_DELETION_UNCONFIRMED = 1;
	const FLAG_REPORTED = 2;
	
	/**
	 * Loads the post from the database and
	 * creates a BBCode quote from it.
	 *
	 * @param integer $post_id Post ID.
	 * @return string Post as a quote. Empty string if the post does not exist.
	 */
	public static function get_quote_for_post($post_id) {
		global $wpdb;
		$post_data = $wpdb->get_row(
			$wpdb->prepare(
				'select `p`.`text`, `u`.`display_name` as `name`
				from `qheb_posts` as `p`
				  left join `qheb_wp_users` as `u`
				    on (`u`.`ID`=`p`.`uid`)
				where `pid`=%d;',
				$post_id
			),
			ARRAY_A
		);
		if (empty($post_data)) {
			return '';
		}
	
		return '[quote="'.$post_data['name'].'" post="'.$post_id.'"]'.$post_data['text'].'[/quote]';
	}
	
	/**
	 * Deletes a post with all of its attachments from the database
	 * and the filesystem. Also removes thread if this was the only post in it.
	 * @param integer $post_id Post ID in the databse.
	 * @return boolean Marks whether the thread exists after the deletion.
	 * False if this was the last post, and the thread also got deleted.
	 */
	public static function delete_post($post_id) {
		global $wpdb;
		
		//Load thread data
		$thread = $wpdb->get_row(
			$wpdb->prepare(
				'select `t`.`tid`, `t`.`catid`, `t`.`postcount`
				from `qheb_posts` as `p`
				  left join `qheb_threads` as `t`
				    on (`t`.`tid`=`p`.`tid`)
				where `p`.`pid`=%d',
				$post_id
			),
			ARRAY_A
		);
		
		//Remove reports
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_post_reports` where `pid`=%d;'
			)
		);
		
		//Remove post and attachments
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				'select `aid` from `qheb_attachments` where `pid`=%d;',
				$post_id
			),
			ARRAY_A
		);
		foreach ($attachments as $att) {
			QhebunelFiles::delete_attachment($att['aid']);
		}
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_posts` where `pid`=%d;',
				$post_id
			)
		);
		
		//Handle thread update/deletion
		if ($thread['postcount'] > 1) {
			//Update postcount in thread
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_threads` set `postcount`=`postcount`-1 where `tid`=%d;',
					$thread['tid']
				)
			);
		
			return true; //The thread still exists
		
		} else {
			//This was the last post, delete thread
			$wpdb->query(
				$wpdb->prepare(
					'delete from `qheb_threads` where `tid`=%d;',
					$thread['tid']
				)
			);
		
			return false; //Thread got deleted too
		}
	}
	
	/**
	 * Deletes a thread and all attachments from the thread.
	 * Updates user statistics after deletion.
	 * 
	 * @param integer $thread_id
	 */
	public static function delete_thread($thread_id) {
		global $wpdb;
		
		//Remove attachments
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				'select `a`.`aid`
				from `qheb_attachments` as `a`
				  left join `qheb_posts` as `p`
				    on (`p`.`pid`=`a`.`aid`)
				where `p`.`tid`=%d;'
			),
			ARRAY_A
		);
		foreach ($attachments as $att) {
			QhebunelFiles::delete_attachment($att['aid']);
		}
		
		//Update users' post count
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_user_ext` as `u`
				set `u`.`postcount`=`u`.`postcount`-(
				  select count(`p`.`pid`)
				  from `qheb_posts` as `p`
				  where `p`.`uid`=`u`.`uid` and `p`.`tid`=%d
				);',
				$thread_id
			)
		);
		
		//Remove thread
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_threads` where `tid`=%d;',
				$thread_id
			)
		);
		
		//Remove posts
		$wpdb->query(
			$wpdb->prepare(
				'delete from `qheb_posts` where `tid`=%d;',
				$thread_id
			)
		);
	}
	
	/**
	 * Renders a &lt;select&gt; tag with the list of categories to the output.
	 * Only those categories will be listed where the current user has
	 * at least the specified permission. Admins get the entire list.
	 * @param string $name Name of the &lt;select&gt; tag.
	 * @param integer $selected_id The ID of the category that should be selected by default.
	 * @param integer $permission_level One of the QHEBUNEL_PERMISSION_* constants.
	 * @param mixed $user_id User ID whose permissions are tested. If left at its default value
	 * ('current'), the current user will be tested.
	 * @param boolean $multiple Make it a multiple choice select (true)
	 * or render it as a dropdown list (false).
	 */
	public static function render_category_select($name, $selected_id = null, $permission_level = QHEBUNEL_PERMISSION_START, $user_id = 'current', $multiple = false) {
		global $wpdb;
		if ($user_id == 'current' && QhebunelUser::is_admin()) {
			$categories = $wpdb->get_results(
				$wpdb->prepare(
					'select distinct `c`.`catid`, `c`.`parent`, `c`.`name`
					from `qheb_categories` as `c`
					order by `c`.`orderid` asc;',
					$permission_level
				),
				ARRAY_A
			);
		} else {
			$groups = QhebunelUser::get_groups($user_id);
			$categories = $wpdb->get_results(
				$wpdb->prepare(
					'select distinct `c`.`catid`, `c`.`parent`, `c`.`name`
					from `qheb_categories` as `c`
					  left join `qheb_category_permissions` as `cp`
					    on (`c`.`catid`=`cp`.`catid`)
					where `cp`.`gid` in ('.implode(',',$groups).')
					and `cp`.`access`>=%d
					order by `c`.`orderid` asc;',
					$permission_level
				),
				ARRAY_A
			);
		}
		if ($multiple) {
			$name .= '[]';
		}
		echo('<select name="'.$name.'"'.($multiple ? ' multiple' : '').'>');
		foreach ($categories as $cat1) {
			if ($cat1['parent'] == 0) {
				echo('<optgroup label="'.$cat1['name'].'">');
				foreach ($categories as $cat2) {
					if ($cat2['parent'] == $cat1['catid']) {
						$selected = $cat2['catid'] == $selected_id;
						echo('<option value="'.$cat2['catid'].'"'.($selected ? ' selected' : '').'>'.$cat2['name'].'</option>');
					}
				}
				echo('</optgroup>');
			}
		}
		echo('</select>');
	}
	
	/**
	 * Checks whether the current user has submitted a report for the post.
	 * @param integer $post_id Post ID to check.
	 * @return boolean True if the current user has submitted a report.
	 */
	public static function is_post_reported_by_user($post_id) {
		global $wpdb, $current_user;
		static $report_cache = array();
		
		if (isset($report_cache[$post_id])) {
			return $report_cache[$post_id];
		}
		
		$has_report = $wpdb->get_var(
			$wpdb->prepare(
				'select 1 as `reported` from `qheb_post_reports` where `pid`=%d and `uid`=%d;',
				$post_id,
				$current_user->ID
			)
		);
		return $report_cache[$post_id] = ($has_report == 1);
	}
	
	/**
	 * Renders a list of threads filtered by category ID
	 * or by a given list of thread IDs. At least one parameter must be specified.
	 * @param integer $category Optional.
	 * @param array $thread_ids Optional.
	 */
	public static function render_thread_list($category = null, $thread_ids = null) {
		global $wpdb, $cat_id, $current_user;
		
		$conditions = array();
		if (!empty($category)) {
			$category = (int)$category;
			$conditions[] = '`t`.`catid`='.$category;
		}
		if (is_array($thread_ids)) {
			$ids = array();
			foreach ($thread_ids as $id) {
				$id = (int)$id;
				if ($id > 0) {
					$ids[] = $id;
				}
			}
			$conditions[] = '`t`.`tid` in (' . implode(',', $ids) . ')';
		}
		
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
				where '.implode (' and ', $conditions).'
				order by `t`.`pinned` desc, `t`.`lastpostid` desc;',
				@$current_user->ID
			),
			ARRAY_A
		);
		if (empty($threads)) {
			echo('<tr><td colspan="4">'.__('There are no threads in this category.','qhebunel').'</td></tr>');
		} else {
			foreach ($threads as $thread) {
				$last_post_user = ($thread['lastuid'] > 0 ? $thread['lastname'] : 'A guest');
				$last_post_user_url = QhebunelUI::get_url_for_user($thread['lastuid']);
				$start_user = ($thread['starter'] > 0 ? $thread['startname'] : 'A guest');
				$start_user_url = QhebunelUI::get_url_for_user($thread['starter']);
				$lastpost = '<span class="name"><a href="'.$last_post_user_url.'">'.$last_post_user.'</a></span> <span class="date" title="'.mysql2date('j F, Y @ G:i', $thread['lastdate']).'">'.QhebunelDate::get_list_date($thread['lastdate']).'</span>';
				$starter = '<span class="name"><a href="'.$start_user_url.'">'.$start_user.'</a></span> <span class="date" title="'.mysql2date('j F, Y @ G:i', $thread['startdate']).'">'.QhebunelDate::get_list_date($thread['startdate']).'</span>';
				$thread_link = QhebunelUI::get_url_for_thread($thread['tid']);
				$new_posts = ($thread['new'] > 0 ? '<span class="new-posts">'.sprintf(_n('(%d new)', '(%d new)', $thread['new'], 'qhebunel'),$thread['new']).'</span>' : '');
				$icon = self::get_icon_for_thread($thread);
				echo('<tr><td>'.$icon.'<a href="'.$thread_link.'">'.QhebunelUI::format_title($thread['title']).'</a></td><td>'.$thread['postcount'].' '.$new_posts.'</td><td>'.$lastpost.'</td><td>'.$starter.'</td></tr>');
			}
		}
		echo('</tbody></table>');
	}
	
	/**
	 * Returns the &lt;span&gt; tag for the thread icon.
	 * @param array $thread A thread row from the database.
	 * @return string HTML fragment.
	 */
	private static function get_icon_for_thread($thread) {
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
	 * Renders a single post into the main container div.
	 * @param array $post A row from the database.
	 * @param boolean $show_actions If set to false, the post action links won't be displayed.
	 */
	private static function render_single_post($post, $show_actions) {
		global $wpdb;
	
		/*
		 * Add meta for anonymous users.
		 */
		if ($post['uid'] == 0) {
			$post['display_name'] = __('A guest', 'qhebunel');
		}
	
		//Post holder div
		$class = self::get_class_for_post($post);
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
			echo(__('Attachments:', 'qhebunel'));
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
		self::render_post_footer($post, $show_actions);
		
		echo('</div>');
		
		//Post holder div
		echo('</article>');
	}
	
	/**
	 * Returns a CSS class name for a single post.
	 * @param array $post A row from the DB.
	 * @return string Class attribute for the div holding the post.
	 */
	private static function get_class_for_post($post) {
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
	
	/**
	 * Generates and outputs the HTML for the action links in the footer of a single post. 
	 * @param array $post A row from the database.
	 * @param boolean $show_actions If set to false, the post action links won't be displayed.
	 */
	private static function render_post_footer($post, $show_actions) {
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
		
		if ($show_actions) {
			echo('<div>');
			if ($thread_open && $permission >= QHEBUNEL_PERMISSION_WRITE) {
				$quote_url = QhebunelUI::get_url_for_thread($thread_id, $page_id).'?quote='.$post['pid'].'#send-reply';
				echo('<a class="post-action reply-link" href="#send-reply">'.__('Reply', 'qhebunel').'</a> ');
				echo('<a class="post-action quote-link" href="'.$quote_url.'">'.__('Quote', 'qhebunel').'</a> ');
			}
			if ($thread_open && $post['uid'] == $current_user->ID || QhebunelUser::is_moderator()) {
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
		}
		
		if ($post['flag'] == QhebunelPost::FLAG_REPORTED && QhebunelUser::is_moderator()) {
			self::render_reports($post);
		}
		
		echo('</footer>');
	}
	
	/**
	 * Queries the database and renders posts in a thread or as search results.
	 * Either one of the $thread_id or $post_ids parameters must be specified, but not both at once.
	 * 
	 * @param integer $thread_id Thread ID, optional.
	 * @param integer $page_num Zero based ID of the page when rendering a thread. Ignored for $post_ids.
	 * @param array $post_ids Array of post IDs, optional. If specified, the thread won't be queried,
	 * but the posts with their IDs in this array will be rendered.
	 * @param boolean $show_actions If set to false, the post action links won't be displayed.
	 */
	public static function render_posts($thread_id = null, $page_num = 0, $post_ids = null, $show_actions = true) {
		global $wpdb, $current_user;
		
		if (is_array($post_ids)) {
			$ids = array();
			foreach ($post_ids as $id) {
				$id = (int)$id;
				if ($id > 0) {
					$ids[] = $id;
				}
			}
			$condition = '`p`.`pid` in (' . implode(',', $ids) . ')';
			$limit = '';
		} else {
			$condition = '`tid`='.(int)$thread_id;
			$post_per_page = QHEBUNEL_POSTS_PER_PAGE;
			$post_offset = $page_num * $post_per_page;
			$limit = 'limit '.$post_offset.','.$post_per_page;
		}
		
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
				where '.$condition.'
				order by `pid` asc
				'.$limit.';',
				@$current_user->ID
			),
			ARRAY_A
		);
		
		//Get users who has post on this page, and preload their badges
		$user_ids = array();
		foreach ($posts as $post) {
			$user_ids[] = $post['uid'];
		}
		QhebunelBadges::preload_displayed_badges(array_unique($user_ids));
		
		foreach ($posts as $post) {
			self::render_single_post($post, $show_actions);
		}
	}
	
	
	/**
	 * Runs a search query on the database and renders the results.
	 * @param array $parameters See pages/search.php for the population of the array.
	 */
	public static function show_search_results($parameters) {
		global $wpdb, $current_user;
		$error = false;
		
		if ($parameters['result_type'] == 'posts') {
			$query = "select distinct `p`.`pid` \n";
		} else {
			$query = "select distinct `t`.`tid` \n";
		}
		
		$query .= "from `qheb_threads` as `t` \n";
		$query .= "  left join `qheb_posts` as `p` \n";
		$query .= "    on (`p`.`tid`=`t`.`tid`) \n";
		$query .= "  left join `qheb_category_permissions` as `cp` \n";
		$query .= "    on (`cp`.`catid`=`t`.`catid`) \n";
		$query .= "  left join (select `tid`, `visitdate` from `qheb_visits` where `uid`=".($current_user->ID).") as `v` \n";
		$query .= "    on (`v`.`tid`=`t`.`tid`) \n";
		$query .= "where \n";
		$conditions = array();
		
		if (!empty($parameters['terms'])) {
			$terms = $parameters['terms'];
			$wpdb->escape_by_ref($terms);
			if ($parameters['location'] == 'post') {
				$conditions[] = "`p`.`text` like '%${terms}%'";
		} elseif ($parameters['location'] == 'title') {
			$conditions[] = "`t`.`title` like '%${terms}%'";
		} else {
			$conditions[] = "`p`.`text` like '%${terms}%' or `t`.`title` like '%${terms}%'";
		}
		}
		
		if (!empty($parameters['user'])) {
			$user_id = $wpdb->get_var(
				$wpdb->prepare(
					'select `ID` from `qheb_wp_users` where `display_name`=%s',
					$parameters['user']
				)
			);
			if ($user_id > 0) {
				$conditions[] = "`p`.`uid`=${user_id}";
		}
		}
		
		if (!empty($parameters['date_from'])) {
			$conditions[] = "`p`.`postdate`>='${search['date_from']}'";
		}
		if (!empty($parameters['date_to'])) {
			$conditions[] = "`p`.`postdate`<='${search['date_to']}'";
		}
		
		//Restrict categories to the ones the user is allowed to read.
		$groups = QhebunelUser::get_groups();
		$categories = array();
		$cats = $wpdb->get_results(
			$wpdb->prepare(
				'select distinct `catid`
			from `qheb_category_permissions`
			where `gid` in ('.implode(',', $groups).')
			and `access`>=%d;',
				QHEBUNEL_PERMISSION_READ
			),
			ARRAY_N
		);
		if (empty($cats)) {
			$error = true;
		} else {
			foreach ($cats as $cat) {
				$categories[] = $cat[0];
			}
		}
		if (!empty($parameters['categories'])) {
			$categories = array_intersect($categories, $parameters['categories']);
		}
		$conditions[] = "`t`.`catid` in (".implode(',', $categories).")";
		
		if (!empty($parameters['flags'])) {
			foreach ($parameters['flags'] as $flag) {
				if ($flag == 'new') {
					$conditions[] = "`p`.`postdate`>`v`.`visitdate`";
				} elseif ($flag == 'edited') {
					$conditions[] = "`p`.`editdate` is not null";
				} elseif ($flag == 'reported') {
					$conditions[] = "`p`.`flag`=2";
				}
			}
		}
		
		$query .= "  (" . implode(") and \n  (", $conditions) . ") \n";
		$limit = ($parameters['result_type'] == 'posts' ? QHEBUNEL_POSTS_PER_PAGE : QHEBUNEL_THREADS_PER_PAGE);
		$query .= 'limit ' . ($parameters['page']*$limit) . ','.$limit.';';
		$query_result = $wpdb->get_results(
			$query,
			ARRAY_N
		);
		$matching_ids = array();
		foreach ($query_result as $row) {
			$matching_ids[] = $row[0];
		}
		
		if (empty($matching_ids) || $error) {
			echo('<div class="qheb-error-message">'.$parameters['no_result_message'].'</div>');
			return;
		}
		
		
		$title_tag = $parameters['page'] ? 'div' : 'h2';
		echo('<div class="qheb-thread">');
		echo('<'.$title_tag.' class="thread-title">'.__('Search results','qhebunel').'</'.$title_tag.'>');
		self::render_search_page_numbers($parameters['page']);
		if ($parameters['result_type'] == 'posts') {
			QhebunelPost::render_posts(null, 0, $matching_ids, false);
		} else {
			QhebunelPost::render_thread_list(null, $matching_ids);
		}
		self::render_search_page_numbers($parameters['page']);
		echo('</div>');
	}
	
	private static function render_search_page_numbers($page_number) {
		global $section_params;
		echo('<div class="thread-actions"><nav class="thread-pagination">');
		$link_params = preg_replace('%p:\d+/?%', '', $section_params);
		$page_links = array();
		$page_links[] = '<a href="'.site_url('/forum/search/'.$link_params).'">1</a>';
		for ($i=1; $i<$page_number+2; $i++) {
			$page_links[] = '<a href="'.site_url('/forum/search/'.$link_params.'p:'.$i).'">'.($i+1).'</a>';
		}
		
		$page_links = implode(' ', $page_links);
		//translators: The is the placeholder for the links to the pages in the thread.
		printf(__('Jump to page: %s'), $page_links);
		
		echo('</nav></div>');
	}
	
	/**
	 * Renders the reports for a single post.
	 * @param array $post Database row.
	 */
	private static function render_reports($post) {
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
}
?>