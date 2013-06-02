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
}
?>