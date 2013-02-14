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
		$absoluteUrl = get_url('forum/');
		wp_redirect($absoluteUrl, 301);//Moved permanently
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
	$forumRootURI = site_url('forum/', 'relative');
	$forumRootLen = strlen($forumRootURI);
	$forumURI = substr($_SERVER['REQUEST_URI'], $forumRootLen);
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
			
			//Special sections
			'attachments' =>	array('download', true),
			'quote' =>			array('quote', true)
		),
		
		//Category level special pages
		'category' => array(
			//Special pages
			'new-thread' =>		array('newthread', false, __('Create new thread', 'qhebunel'))
		)
	);
	
	global $catId, $threadId, $pageId, $sectionParams, $titleElement;
	$catId = $threadId = $pageId = 0;
	$forumPage = $section = $sectionParams = '';
	
	if (empty($forumURI)) {
		/*
		 * Display the category list as the root page by default,
		 * and redirect /forum to /forum/
		 */
		if (substr($_SERVER['REQUEST_URI'],-1) != '/') {
			$absoluteUrl = site_url('forum/');
			wp_redirect($absoluteUrl, 301);//Moved permanently
			die();
		}
		
		$forumPage = 'catlist';
		
	} else {
		/*
		 * Try matching the global sections first
		 */
		$pattern = implode('|', array_keys($sections['global']));
		if (preg_match("%^(${pattern})(?:/(.*))?$%", $forumURI, $regs)) {
			$section = $regs[1];
			$sectionParams = (isset($regs[2]) ? $regs[2] : '');
			
			if ($sections['global'][$section][1] == true) {
				
				//Handler -> include and terminate the bootstrap
				$handler = dirname(__FILE__).'/handlers/'.$sections['global'][$section][0].'.php';
				require_once($handler);
				die();
				
			} else {
				
				//Special page
				$forumPage = $sections['global'][$section][0];
				if (count($sections['global'][$section]) >= 3) {
					$titleElement = $sections['global'][$section][2];
				}
			}
		}
		
		
		/*
		 * The requested URL does not belong to a global special page,
		 * so figure out what forum content should be displayed
		 */
		if (empty($forumPage)) {
			
			/*
			 * Check for post permalink, and redirect instantly
			 */
			if (preg_match('%(?<=^post-)(\d+)(?=/|$)%', $forumURI, $regs)) {
				$postId = $regs[0];
				$absoluteUrl = QhebunelUI::getUrlForPost($postId);
				wp_redirect($absoluteUrl, 302);//Temporary redirect, so the permalink does not get messed up in caches
				die();
			}
			
			/*
			 * Get content IDs
			 */
			if (preg_match('%(?<=-c|^c)(\d+)(?=/|$)%', $forumURI, $regs)) {
				$catId = $regs[0];
			}
			if (preg_match('%(?<=-t|^t)(\d+)(?=/|$)%', $forumURI, $regs)) {
				$threadId = $regs[0];
			}
			if (preg_match('%(?<=/p)(\d+)(?=/|$)%', $forumURI, $regs)) {
				$pageId = $regs[0];
			}
			if (preg_match('%(?<=/|^)[^/]+$%', $forumURI, $regs)) {
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
			$legalURI = '';
			if ($threadId > 0) {
				//Topic
				$threadData = $wpdb->get_row(
					"select `c`.`catid`, `c`.`uri` as `caturi`, `t`.`uri` as `threaduri`, `t`.`title`
					from `qheb_threads` as `t`
					  left join `qheb_categories` as `c`
					    on (`t`.`catid`=`c`.`catid`)
					where `t`.`tid`=$threadId limit 1;",
					ARRAY_N
				);
				if (!is_null($threadData)) {
					//If it's null, it means that the thread does not exist, so redirect to the index page.
					list($threadCatId, $threadCatURI, $threadThreadURI, $titleElement) = $threadData;
					$legalURI = "${threadCatURI}-c${threadCatId}/${threadThreadURI}-t${threadId}/".($pageId >= 2 ? 'p'.$pageId : '');
					$forumPage = 'thread'; //page file to insert
				}
				
			} elseif ($catId > 0) {
				//Category
				$catData = $wpdb->get_row(
					"select `uri`, `name` as `asd` from `qheb_categories` where `catid`=$catId limit 1;",
					ARRAY_N
				);
				if (!is_null($catData)) {
					list($catURI, $titleElement) = $catData;
					$legalURI = "${catURI}-c${catId}/";
					if (array_key_exists($section, $sections['category'])) {
						$legalURI .= $section;
						$forumPage = $sections['category'][$section][0];
						if (count($sections['category'][$section]) >= 3) {
							$titleElement = $sections['category'][$section][2];
						}
					} else {
						$forumPage = 'threadlist'; //page file to insert
					}
				}
			}
		
			//Compare and redirect if needed
			if ($forumURI != $legalURI) {
				preg_match('/^(.*?)(?:\?|$)/', $forumURI, $regs);
				$paramlessForumURI = $regs[1];
				if ($paramlessForumURI != $legalURI) { //Allow parameters
					$absoluteUrl = site_url('forum/'.$legalURI);
					wp_redirect($absoluteUrl, 301);//Moved permanently
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
		global $titleElement;
		$site_name = get_bloginfo('name' , 'display');
		$elements = array(
			__('Forum', 'qhebunel'),
			$site_name
		);
		if (!empty($titleElement)) {
			array_unshift($elements, $titleElement);
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
	include('pages/'.$forumPage.'.php');
	echo('</div></section>'."\n");
	get_sidebar();
	get_footer();
}
?>