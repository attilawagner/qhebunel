<?php
/**
 * Qhebunel
 * Bootstrap file
 * 
 * This file is called when a HTTP request is made to the forum.
 * It calls the specific handler for user input actions (such as posting)
 * and calls the page that should be displayed. It also builds up the WP
 * frame, so header, footer and sidebar should not be called within the
 * forum pages. 
 * 
 * A page is rendered only if it's not a modifying user action (eg. posting).
 * If a handler is finished, it sends a redirect in the end, so a doublepost cannot happen.
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

/*
 * Check whether a handler is needed, and include it,
 * or display a page that belong to a normal GET request.
 */
if (isset($_POST['action']) && !empty($_POST['action'])) {
	/*
	 * Handlers
	 */
	$action = $_POST['action'];
	$handler = dirname(__FILE__)."/handlers/${action}.php";

	if (is_file($handler)) {
		//Include handler for supported action
		//A redirect will be issued at the end of it.
		require_once($handler);
	} else {
		//Redirec to to main page if action is unsupported
		$absolute_url = site_url('forum/');
		wp_redirect($absolute_url, 301);//Moved permanently
	}
	die();
} else {
	
	/*
	 * Determine which page should be displayed
	 * An URI can belong to:
	 * - A category or thread
	 * - A special section (/forum/delete-thread/*)
	 * 
	 * Sections may have parameters (/delete-thread/[thread-id]),
	 * or could be single special pages (/profile, /error)
	 */
	$forum_root_uri = site_url('forum/', 'relative');
	$forum_root_len = strlen($forum_root_uri);
	$forum_uri = substr($_SERVER['REQUEST_URI'], $forum_root_len);
	/*
	 * The section names are the keys in the array.
	 * For each section, an array defines the file name that should be included,
	 * and a boolean flag that indicates whether it's a handler or a page.
	 * If the optional third string element is defined, it will be displayed in the title.
	 * Format:
	 * 'uri_for_special_page' =>	array('filename', false, 'Page title'),
	 * 'uri_for_command' =>			array('filename', true)
	 */
	$sections = array(
		//Global sections are special pages outside the forum hierarchy
		'global' => array(
			//Special pages
			'error' =>			array('error', false, __('Error', 'qhebunel')),
			'profile' =>		array('profile', false, __('Profile settings', 'qhebunel')),
			'edit-post' =>		array('postedit', false, __('Edit post', 'qhebunel')),
			'delete-thread' =>	array('threaddelete', false, __('Delete thread', 'qhebunel')),
			'move-thread' =>	array('threadmove', false, __('Move thread', 'qhebunel')),
			'pm' =>				array('pm', false, __('Private messages', 'qhebunel')),
			
			//Special sections
			'attachments' =>	array('download', true),
			'quote' =>			array('quote', true),
			'delete-post' =>	array('postdelete', true),
			'close-thread' =>	array('threadclose', true),
			'pin-thread' =>		array('threadpin', true),
		),
		
		//Category level special pages
		'category' => array(
			//Special pages
			'new-thread' =>		array('threadnew', false, __('Create new thread', 'qhebunel'))
		)
	);
	
	global $cat_id, $thread_id, $page_id, $section_params, $title_element;
	$cat_id = $thread_id = $page_id = 0;
	$forum_page = $section = $section_params = '';
	
	if (empty($forum_uri)) {
		/*
		 * Display the category list as the root page by default,
		 * and redirect /forum to /forum/
		 */
		if (substr($_SERVER['REQUEST_URI'],-1) != '/') {
			$absolute_url = site_url('forum/');
			wp_redirect($absolute_url, 301);//Moved permanently
			die();
		}
		
		$forum_page = 'catlist';
		
	} else {
		/*
		 * Try matching the global sections first
		 */
		$pattern = implode('|', array_keys($sections['global']));
		if (preg_match("%^(${pattern})(?:/(.*))?$%", $forum_uri, $regs)) {
			$section = $regs[1];
			$section_params = (isset($regs[2]) ? $regs[2] : '');
			
			if ($sections['global'][$section][1] == true) {
				
				//Handler -> include and terminate the bootstrap
				$handler = dirname(__FILE__).'/handlers/'.$sections['global'][$section][0].'.php';
				require_once($handler);
				die();
				
			} else {
				
				//Special page
				$forum_page = $sections['global'][$section][0];
				if (count($sections['global'][$section]) >= 3) {
					$title_element = $sections['global'][$section][2];
				}
			}
		}
		
		
		/*
		 * The requested URL does not belong to a global special page,
		 * so figure out what forum content should be displayed
		 */
		if (empty($forum_page)) {
			
			/*
			 * Check for post permalink, and redirect instantly
			 */
			if (preg_match('%(?<=^post-)(\d+)(?=/|$)%', $forum_uri, $regs)) {
				$post_id = $regs[0];
				$absolute_url = QhebunelUI::get_url_for_post($post_id);
				wp_redirect($absolute_url, 302);//Temporary redirect, so the permalink does not get messed up in caches
				die();
			}
			
			/*
			 * Get content IDs
			 */
			if (preg_match('%(?<=-c|^c)(\d+)(?=/|$)%', $forum_uri, $regs)) {
				$cat_id = $regs[0];
			}
			if (preg_match('%(?<=-t|^t)(\d+)(?=/|$)%', $forum_uri, $regs)) {
				$thread_id = $regs[0];
			}
			if (preg_match('%(?<=/p)(\d+)(?=/|$)%', $forum_uri, $regs)) {
				$page_id = $regs[0];
			}
			if (preg_match('%(?<=/|^)[^/]+$%', $forum_uri, $regs)) {
				$section = $regs[0];
			}
			
			/*
			 * Do a cleanup on the URL
			 * Valid formats are:
			 *   Category (thread list):
			 *     category-uri-c123/
			 *   Thread:
			 *     category-uri-c123/thread-uri-t456/
			 *   Thread with page number (only for >= 2):
			 *     category-uri-c123/thread-uri-t456/p2
			 *   A post permalink
			 *     post-987
			 */
			$legal_uri = '';
			if ($thread_id > 0) {
				//Topic
				$thread_data = $wpdb->get_row(
					"select `c`.`catid`, `c`.`uri` as `caturi`, `t`.`uri` as `threaduri`, `t`.`title`
					from `qheb_threads` as `t`
					  left join `qheb_categories` as `c`
					    on (`t`.`catid`=`c`.`catid`)
					where `t`.`tid`=$thread_id limit 1;",
					ARRAY_N
				);
				if (!is_null($thread_data)) {
					//If it's null, it means that the thread does not exist, so redirect to the index page.
					list($thread_cat_id, $thread_cat_uri, $thread_thread_uri, $title_element) = $thread_data;
					$legal_uri = "${thread_cat_uri}-c${thread_cat_id}/${thread_thread_uri}-t${thread_id}/".($page_id >= 2 ? 'p'.$page_id : '');
					$forum_page = 'thread'; //page file to insert
				}
				
			} elseif ($cat_id > 0) {
				//Category
				$cat_data = $wpdb->get_row(
					"select `uri`, `name` as `asd` from `qheb_categories` where `catid`=$cat_id limit 1;",
					ARRAY_N
				);
				if (!is_null($cat_data)) {
					list($cat_uri, $title_element) = $cat_data;
					$legal_uri = "${cat_uri}-c${cat_id}/";
					if (array_key_exists($section, $sections['category'])) {
						$legal_uri .= $section;
						$forum_page = $sections['category'][$section][0];
						if (count($sections['category'][$section]) >= 3) {
							$title_element = $sections['category'][$section][2];
						}
					} else {
						$forum_page = 'threadlist'; //page file to insert
					}
				}
			}
		
			//Compare and redirect if needed
			if ($forum_uri != $legal_uri) {
				preg_match('/^(.*?)(?:\?|$)/', $forum_uri, $regs);
				$paramless_forum_uri = $regs[1];
				if ($paramless_forum_uri != $legal_uri) { //Allow parameters
					$absolute_url = site_url('forum/'.$legal_uri);
					wp_redirect($absolute_url, 301);//Moved permanently
					die();
				}
			}
		}
	}
	
	
	
	/*
	 * Set title
	 * The filter is registered before the texturizer.
	 */
	//TODO
	function qhebunel_title($title, $sep, $seplocation) {
		global $title_element;
		$site_name = get_bloginfo('name' , 'display');
		$elements = array(
			__('Forum', 'qhebunel'),
			$site_name
		);
		if (!empty($title_element)) {
			array_unshift($elements, $title_element);
		}
		if ($seplocation != 'right') {
			$elements = array_reverse($elements);
		}
		return implode(' '.$sep.' ', $elements);
	}
	add_filter('wp_title', 'qhebunel_title', 5, 3);
	
	
	/*
	 * Display the page
	 * This section should be modified to be in accordance with the other pages of the theme
	 */
	get_header();
	echo('<section class="site-content" id="primary"><div role="main" id="content">'."\n");
	include('pages/'.$forum_page.'.php');
	echo('</div></section>'."\n");
	get_sidebar();
	get_footer();
}
?>