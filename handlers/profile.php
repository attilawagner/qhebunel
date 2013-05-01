<?php
/**
 * Qhebunel
 * Thread reply handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Only logged in users can modify their profile
if (!is_user_logged_in()) {
	Qhebunel::redirect_to_error_page();
}

//Only mods can edit the profile of other users
global $user_id, $updating_own;
$user_id = $_POST['user-id'];
$updating_own = ($user_id == $current_user->ID);
if (empty($user_id) || (!$updating_own && !QhebunelUser::is_moderator())) {
	Qhebunel::redirect_to_error_page();
}

/**
 * This function is called when the user clicks on the Save button at the bottom of the form.
 */
function qheb_user_profile_update() {
	global $wpdb, $current_user, $user_id, $updating_own;
	
	$first_name = $_POST['firstname'];
	$last_name = $_POST['lastname'];
	$nick_name = $_POST['nickname'];
	$email = $_POST['email'];
	$pass1 = $_POST['pass1'];
	$pass2 = $_POST['pass2'];
	$old_pass = $_POST['old-pass'];
	$signature = $_POST['signature'];
	
	//Clean whitespace from both ends of the text fields
	$text_fields = array('first_name', 'last_name', 'nick_name', 'email', 'signature');
	foreach ($text_fields as $field_name) {
		$$field_name = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $$field_name);
	}
	
	//TODO: checks, JS checks
	//Password check is skipped when moderators update other users' profile
	$error_in_pass = (!empty($pass1) || !empty($pass2)) && ($pass1 != $pass2 || (empty($old_pass) && $updating_own));
	if (empty($nick_name) || $error_in_pass|| empty($email)) {
		Qhebunel::redirect_to_error_page();
	}
	if (!empty($pass1) && $updating_own && !wp_check_password($old_pass, $current_user->user_pass, $current_user->ID)) {
		Qhebunel::redirect_to_error_page();
	}
	
	//Update user table
	$user_update_data = array(
		'ID' =>				$user_id,
		'user_email' =>		$email,
		'display_name' =>	$nick_name
	);
	if (!empty($pass1)) {
		$user_update_data['user_pass'] = $pass1;
	}
	wp_update_user($user_update_data);
	
	//Update user meta
	update_user_meta($user_id, 'first_name', $first_name);
	update_user_meta($user_id, 'last_name', $last_name);
	update_user_meta($user_id, 'nickname', $nick_name);
	
	if (!empty($_FILES['avatar'])) {
		if (($avatar = QhebunelFiles::save_avatar($_FILES['avatar'], $user_id)) !== false) {
			//The path is returned, save it into the DB
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_user_ext` set `avatar`=%s where `uid`=%d limit 1;',
					$avatar,
					$user_id
				)
			);
		} else {
			//TODO
		}
	}
	
	//Update ext
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_user_ext` set `signature`=%s where `uid`=%d limit 1;',
			$signature,
			$user_id
		)
	);
}

/**
 * This function is called when the user click on the Delete avatar button below the image.
 */
function qheb_user_profile_delete_avatar() {
	global $wpdb, $current_user, $user_id;
	
	//Delete file
	QhebunelFiles::delete_avatar($user_id);
	
	//Remove from DB
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_user_ext` set `avatar`=NULL where `uid`=%d limit 1;',
			$user_id
		)
	);
}


//Call proper handler function
if (isset($_POST['update'])) {
	qheb_user_profile_update();
} else {
	qheb_user_profile_delete_avatar();
}

//Redirect to profile page
//TODO: common function
$relative_url = 'edit-profile' . ($updating_own ? '' : '/'.$user_id);
$absolute_url = get_site_url(null, 'forum/'.$relative_url);
wp_redirect($absolute_url);//Temporal redirect
?>