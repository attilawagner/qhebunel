<?php
/**
 * Qhebunel
 * User interface:
 *   - Post formatting
 *   - Links
 * 
 * @author Attila Wagner
 */

class QhebunelUI {
	
	/**
	 * Generates the URL for the category (thread list page) with the given ID.
	 *
	 * Generated URLs are cached, so repetitive queries to the same ID
	 * do not result in database queries.
	 *
	 * @param integer $catid Category ID.
	 * @return string The absolute URL for the category.
	 * Empty string, if the category does not exist in the database.
	 */
	public static function get_url_for_category($catid) {
		global $wpdb;
		static $cache;
		
		//Check the cache first
		if (isset($cache[$catid])) {
			return $cache[$catid];
		}
		
		//Build URL
		$cat_uri = $wpdb->get_var(
			$wpdb->prepare(
				'select `uri` from `qheb_categories` where `catid`=%d limit 1;',
				$catid
			),
			0,
			0
		);
		if (is_null($cat_uri)) {
			return '';
		}
		$url = site_url("forum/${cat_uri}-c${catid}/");
		
		//Save and return
		$cache[$catid] = $url;
		return $url;
	}
	
	/**
	 * Generates the URL for a single thread.
	 * 
	 * Generated URLs are cached, so repetitive queries to the same ID
	 * do not result in database queries.
	 * 
	 * @param integer $thread_id Thread ID.
	 * @param integer $page_id Optional, zero-based. If provided, the link will point to the given page.
	 * To get the link to the last page, use -1.
	 * @return string Absolute URL for the thread (or page in the thread),
	 * or an empty string if the thread does not exist.
	 */
	public static function get_url_for_thread($thread_id, $page_id = 0) {
		global $wpdb;
		static $cache; //Cache only holds the base URL, page ID is appended separately
		
		if (isset($cache[$thread_id]) && $page_id > -1) {
			$url = $cache[$thread_id];
		} else {
			$thread_data = $wpdb->get_row(
				$wpdb->prepare(
					'select `c`.`catid`, `c`.`uri` as `caturi`, `t`.`uri` as `threaduri`, `t`.`postcount`
					from `qheb_threads` as `t`
					  left join `qheb_categories` as `c`
					    on (`t`.`catid`=`c`.`catid`)
					where `t`.`tid`=%d limit 1;',
					$thread_id
				),
				ARRAY_N
			);
			if (is_null($thread_data)) {
				return '';
			}
			list($cat_id, $cat_uri, $thread_uri, $postcount) = $thread_data;
			$url = get_site_url(null, "forum/${cat_uri}-c${cat_id}/${thread_uri}-t${thread_id}/");
			$cache[$thread_id] = $url;
			
			//Get last page ID
			if ($page_id == -1) {
				$page_id = ceil($postcount / QHEBUNEL_POSTS_PER_PAGE);
				if ($page_id == 1) {
					$page_id = 0;
				}
			}
		}
		
		//Add page ID if needed
		if ($page_id == 0) {
			return $url;
		} else {
			return $url.'p'.($page_id+1);
		}
	}
	
	/**
	 * Generates an URL pointing to the given post.
	 * This URL can be a short permalink, or a full URL pointing to a specific page
	 * of the thread the post belongs to, with the post ID in the a # part. 
	 * 
	 * If a full URL is needed ($permalink = false), this function loads the
	 * thread ID from the database and calculates the page number if the post
	 * exists. For deleted or not yet created posts it gives back an empty string.
	 * 
	 * For permalinks no checks are done.
	 * 
	 * @param integer $post_id ID of the post.
	 * @param boolean $permalink True if a short permalink is needed.
	 * Defaults to false.
	 * @return string URL pointing to the post.
	 */
	public static function get_url_for_post($post_id, $permalink = false) {
		global $wpdb;
		static $full_cache; //Stores the calculated full URLs (thread->page->post); permalinks are not cached.
		
		//For permalinks no checks are needed
		if ($permalink) {
			return site_url('forum/post-'.$post_id);
		}
		
		//If the full URL is required and it's in the cache, return it from there
		if (isset($full_cache[$post_id])) {
			return $full_cache[$post_id];
		}
		
		//Generate full URL if it's not in the cache
		$post_data = $wpdb->get_row(
			$wpdb->prepare(
				'select `tid`, count(*) as `pnum` from `qheb_posts`
				where `tid`=(select`tid` from `qheb_posts` where `pid`=%d) and `pid`<=%d
				group by `tid`;',
				$post_id,
				$post_id
			),
			ARRAY_A
		);
		if (is_null($post_data)) {
			$url = '';
		} else {
			$page_num = floor($post_data['pnum'] / QHEBUNEL_POSTS_PER_PAGE);
			$url = self::get_url_for_thread($post_data['tid'], $page_num);
			$url .= '#post-'.$post_id;
		}
		
		//Add it to cache and return
		$full_cache[$post_id] = $url;
		return $url;
	}
	
	/**
	 * Formats a forum post or comment.
	 * Calls the BBCode parser, and removes HTML code entered by the user.
	 * @param string $text Post content.
	 * @return string Formatted HTML.
	 */
	public static function format_post($text) {
		return wptexturize(
			QhebunelEmoticons::replace_in_text(
				QhebunelBB::parse(
					str_replace(
						array('&','<','>',				"\n\r","\r\n","\n","\r"),
						array('&amp;','&lt;','&gt;',	'<br/>','<br/>','<br/>','<br/>'),
						$text
					)
				)
			)
		);
	}
	
	/**
	 * Formats the given string to be displayed as a category or topic title.
	 * @param string $text
	 * @return string Escaped and formatted title.
	 */
	public static function format_title($text) {
		return wptexturize(
			str_replace(
				array('&','<','>',				"\n\r","\r\n","\n","\r"),
				array('&amp;','&lt;','&gt;',	'','','',''),
				$text
			)
		);
	}
	
	/**
	 * Gives back the URL pointing to the private message
	 * conversation between the currently logged in user and the
	 * given user.
	 * @param integer $user_id User ID. Won't be validated.
	 * @param integer $page_num Page number in the history.
	 * Defaults to 0, the latest page. 
	 * @return string URL pointing to the private message conversation.
	 */
	public static function get_url_for_pm_user($user_id, $page_num=0) {
		$user_id = (int)$user_id;
		if ($user_id <= 0) {
			return '';
		}
		$url = 'forum/pm/'.$user_id;
		if ($page_num > 0) {
			$url .= '/page-'.$page_num;
		}
		return site_url($url);
	}
}
?>