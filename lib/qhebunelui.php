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
	public static function getUrlForCategory($catid) {
		global $wpdb;
		static $cache;
		
		//Check the cache first
		if (isset($cache[$catid])) {
			return $cache[$catid];
		}
		
		//Build URL
		$catUri = $wpdb->get_var(
			$wpdb->prepare(
				'select `uri` from `qheb_categories` where `catid`=%d limit 1;',
				$catid
			),
			0,
			0
		);
		if (is_null($catUri)) {
			return '';
		}
		$url = site_url("forum/${catUri}-c${catid}/");
		
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
	 * @param integer $threadId Thread ID.
	 * @param integer $pageId Optional, zero-based. If provided, the link will point to the given page.
	 * To get the link to the last page, use -1.
	 * @return string Absolute URL for the thread (or page in the thread),
	 * or an empty string if the thread does not exists.
	 */
	public static function getUrlForThread($threadId, $pageId = 0) {
		global $wpdb;
		static $cache; //Cache only holds the base URL, page ID is appended separately
		
		if (isset($cache[$threadId]) && $pageId > -1) {
			$url = $cache[$threadId];
		} else {
			$threadData = $wpdb->get_row(
				$wpdb->prepare(
					'select `c`.`catid`, `c`.`uri` as `caturi`, `t`.`uri` as `threaduri`, `t`.`postcount`
					from `qheb_threads` as `t`
					  left join `qheb_categories` as `c`
					    on (`t`.`catid`=`c`.`catid`)
					where `t`.`tid`=%d limit 1;',
					$threadId
				),
				ARRAY_N
			);
			if (is_null($threadData)) {
				return '';
			}
			list($catId, $catURI, $threadURI, $postcount) = $threadData;
			$url = get_site_url(null, "forum/${catURI}-c${catId}/${threadURI}-t${threadId}/");
			$cache[$threadId] = $url;
			
			//Get last page ID
			if ($pageId == -1) {
				$pageId = ceil($postcount / QHEBUNEL_POSTS_PER_PAGE);
				if ($pageId == 1) {
					$pageId = 0;
				}
			}
		}
		
		//Add page ID if needed
		if ($pageId == 0) {
			return $url;
		} else {
			return $url.'p'.($pageId+1);
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
	 * @param integer $postId ID of the post.
	 * @param boolean $permalink True if a short permalink is needed.
	 * @return string URL pointing to the post.
	 */
	public static function getUrlForPost($postId, $permalink = false) {
		global $wpdb;
		static $fullCache; //Stores the calculated full URLs (thread->page->post); permalinks are not cached.
		
		//For permalinks no checks are needed
		if ($permalink) {
			return site_url('forum/post-'.$postId);
		}
		
		//If the full URL is required and it's in the cache, return it from there
		if (isset($fullCache[$postId])) {
			return $fullCache[$postId];
		}
		
		//Generate full URL if it's not in the cache
		$postData = $wpdb->get_row(
			$wpdb->prepare(
				'select `tid`, count(*) as `pnum` from `qheb_posts`
				where `tid`=(select`tid` from `qheb_posts` where `pid`=%d) and `pid`<=%d
				group by `tid`;',
				$postId,
				$postId
			),
			ARRAY_A
		);
		if (is_null($postData)) {
			$url = '';
		} else {
			$pageNum = floor($postData['pnum'] / QHEBUNEL_POSTS_PER_PAGE);
			$url = self::getUrlForThread($postData['tid'], $pageNum);
			$url .= '#post-'.$postId;
		}
		
		//Add it to cache and return
		$fullCache[$postId] = $url;
		return $url;
	}
	
	/**
	 * Formats a forum post or comment.
	 * Calls the BBCode parser, and removes HTML code entered by the user.
	 * @param string $text Post content.
	 * @return string Formatted HTML.
	 */
	public static function formatPost($text) {
		return wptexturize(
			QhebunelEmoticons::replaceInText(
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
	public static function formatTitle($text) {
		return wptexturize(
			str_replace(
				array('&','<','>',				"\n\r","\r\n","\n","\r"),
				array('&amp;','&lt;','&gt;',	'','','',''),
				$text
			)
		);
	}
	
	/**
	 * Loads the post from the database and
	 * creates a BBCode quote from it.
	 * 
	 * @param integer $postId Post ID.
	 * @return string Post as a quote. Empty string if the post does not exists.
	 */
	public static function getQuoteForPost($postId) {
		global $wpdb;
		$postData = $wpdb->get_row(
			$wpdb->prepare(
				'select `p`.`text`, `u`.`display_name` as `name`
				from `qheb_posts` as `p`
				  left join `qheb_wp_users` as `u`
				    on (`u`.`ID`=`p`.`uid`)
				where `pid`=%d;',
				$postId
			),
			ARRAY_A
		);
		if (empty($postData)) {
			return '';
		}
		
		return '[quote="'.$postData['name'].'" post="'.$postId.'"]'.$postData['text'].'[/quote]';
	}
}
?>