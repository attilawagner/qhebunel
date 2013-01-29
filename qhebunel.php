<?php
/*
Plugin Name: Qhebunel
Description: Adds a forum system to your site.
Version: 1.0
Author: Attila Wagner
*/

require_once('constants.php');

class Qhebunel {
	
	/**
	 * Called by WP after the user data has been processed in any request.
	 * Catches /forum/ URLs, and loads the localization.
	 */
	public static function init() {
		//Forum pages only
		$forumRoot = site_url('forum', 'relative');
		$forumRootLen = strlen($forumRoot);
		if (strncmp($forumRoot, $_SERVER['REQUEST_URI'], $forumRootLen) === 0) {
			//Localization
			//load_plugin_textdomain('qhebunel', 'locales');
			self::removeWpMagicQuotes();
			
			//SCEditor
			wp_register_style('sceditor', plugins_url('qhebunel/ui/sceditor/minified/jquery.sceditor.default.min.css'));
			wp_enqueue_style('sceditor');
			wp_register_style('sceditortheme', plugins_url('qhebunel/ui/sceditor/minified/themes/default.min.css'));
			wp_enqueue_style('sceditortheme');
			wp_register_script('sceditor', plugins_url('qhebunel/ui/sceditor/minified/jquery.sceditor.min.js'), array('jquery'));
			wp_enqueue_script('sceditor');
			/*wp_register_script('sceditor', plugins_url('qhebunel/ui/sceditor/jquery.sceditor.js'), array('jquery'));
			wp_enqueue_script('sceditor');
			wp_register_script('sceditorbb', plugins_url('qhebunel/ui/sceditor/jquery.sceditor.bbcode.js'), array('sceditor'));
			wp_enqueue_script('sceditorbb');*/
			
			//Own files
			wp_register_style('qhebunel', plugins_url('qhebunel/ui/qhebunel.css'));
			wp_enqueue_style('qhebunel');
			wp_register_script('qhebunel', plugins_url('qhebunel/ui/qhebunel.js'), array('jquery'));
			wp_enqueue_script('qhebunel');
			
			add_action('wp_loaded', array('Qhebunel','bootstrap'), 99, 2);
		}
		
		//Admin area only
		if (is_admin()) {			
			//Load CSS
			wp_register_style('qhebunel-admin', plugins_url('qhebunel/admin/optstyle.css'));
			wp_enqueue_style('qhebunel-admin');
			//Load JS
			wp_register_script('qhebunel-admin', plugins_url('qhebunel/admin/optscript.js'), array('jquery'));
			wp_enqueue_script('qhebunel-admin');
			
			if (strpos($_SERVER['REQUEST_URI'], 'wp-admin/admin.php?page=qhebunel') !== false) {
				self::removeWpMagicQuotes();
			}
		}
	}
	
	/**
	 * Called on /forum/ requests, this function includes the bootstrap,
	 * then terminates any further processing.
	 */
	public static function bootstrap() {
		global $wpdb, $current_user;
		define('QHEBUNEL_REQUEST', true);
		include('bootstrap.php');
		die();
	}


	/**
	 * Registers the admin menus.
	 */
	public static function registerAdminMenus() {
		add_menu_page('Qhebunel', 'Qhebunel', 'manage_options', 'qhebunel/admin/optindex.php');
		add_submenu_page('qhebunel/admin/optindex.php', __('General settings &lsaquo; Qhebunel', 'qhebunel'), __('General settings', 'qhebunel'), 'manage_options', 'qhebunel/admin/optindex.php');
		add_submenu_page('qhebunel/admin/optindex.php', __('Categories &lsaquo; Qhebunel', 'qhebunel'), __('Categories', 'qhebunel'), 'manage_options', 'qhebunel/admin/optcats.php');
		add_submenu_page('qhebunel/admin/optindex.php', __('Groups &lsaquo; Qhebunel', 'qhebunel'), __('Groups', 'qhebunel'), 'manage_options', 'qhebunel/admin/optgroups.php');
		add_submenu_page('qhebunel/admin/optindex.php', __('Users &lsaquo; Qhebunel', 'qhebunel'), __('Users', 'qhebunel'), 'manage_options', 'qhebunel/admin/optusers.php');
		add_submenu_page('qhebunel/admin/optindex.php', __('Badges &lsaquo; Qhebunel', 'qhebunel'), __('Badges', 'qhebunel'), 'manage_options', 'qhebunel/admin/optbadges.php');
	}
	
	/**
	 * Activation
	 */
	public static function pluginActivation() {
		require_once plugin_dir_path(__FILE__).'/install.php';
		qhebunelInstall();
	}
	
	/**
	 * Deactivation
	 */
	public static function pluginDeactivation() {
		require_once plugin_dir_path(__FILE__).'/install.php';
		qhebunelUninstall();
	}
	
	/**
	 * Helper function to undo WP's magic quoting.
	 */
	private static function removeWpMagicQuotes() {
		//$_GET     = stripslashes_deep($_GET);
		$_POST    = stripslashes_deep($_POST);
		//$_COOKIE  = stripslashes_deep($_COOKIE);
		//$_REQUEST = stripslashes_deep($_REQUEST);
	}
	
	/**
	 * Converts the title of a category or topic into a
	 * string that could be included in a URL.
	 * 
	 * @param string $title Title for a category or topic
	 * @return string The URL safe title.
	 */
	public static function getUriComponentForTitle($title) {
		//Get first 6 words
		$title = preg_split('/\s+/', $title, 7);
		$title = implode(' ', array_slice($title, 0, 6));
		return sanitize_title_with_dashes(remove_accents($title));
	}
	
	/**
	 * Calls wp_redirect() to show the user the error page.
	 */
	public static function redirectToErrorPage() {
		$absoluteUrl = site_url('forum/error');
		wp_redirect($absoluteUrl);//Temporal redirect
		die();
	}
	
	/**
	 * Echoes code into the &lt;head&gt; tag.
	 */
	public static function buildHeadFragment() {
		$emoticons = QhebunelEmoticons::getList('grouped');
		
		$jsConfig = array(
			'forumRoot' => site_url('forum/'),
			'SCEditor' => array(
				'emoticonsRoot' => QHEBUNEL_URL.'ui/emoticons/',
				'emoticons' => array(
					'dropdown' => $emoticons['common'],
					'more' => $emoticons['other'],
					'hidden' => $emoticons['aliases']
				)
			)
		);
		echo '<script type="text/javascript">var qhebunelConfig='.json_encode($jsConfig).';</script>';
	}
}

//Define constants
define('QHEBUNEL_PATH', plugin_dir_path(__FILE__));
define('QHEBUNEL_URL', plugin_dir_url(__FILE__));

//Include libraries
require_once 'lib/qhebuneluser.php';
require_once 'lib/qhebuneldate.php';
require_once 'lib/qhebunelbb.php';
require_once 'lib/qhebunelui.php';;
require_once 'lib/qhebunelemoticons.php';
require_once 'lib/qhebunelfiles.php';

//Register hooks
add_action('init', array('Qhebunel','init'), 99);
add_action('admin_menu', array('Qhebunel','registerAdminMenus'));
add_action('wp_head', array('Qhebunel','buildHeadFragment'));
register_activation_hook(__FILE__, array('Qhebunel', 'pluginActivation'));
register_deactivation_hook(__FILE__, array('Qhebunel', 'pluginDeactivation'));
add_action('user_register', array('QhebunelUser', 'addDefaultData'));

//Register hooks to add emoticon parsing globally
add_filter('the_content', array('QhebunelEmoticons', 'replaceInText'), 5);
add_filter('the_excrept', array('QhebunelEmoticons', 'replaceInText'));
add_filter('comment_text', array('QhebunelEmoticons', 'replaceInText'));

add_action('activated_plugin','save_error');
function save_error(){
	file_put_contents('D:/asd.txt', ob_get_contents());
}
?>