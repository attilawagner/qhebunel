<?php
/**
 * Qhebunel
 * Thread reply handler
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Only logged in users can modify their profile
if ($current_user->ID <= 0) {
	Qhebunel::redirectToErrorPage();
}

/**
 * This function is called when the user clicks on the Save button at the bottom of the form.
 */
function qheb_user_profile_update() {
	global $wpdb, $current_user;
	
	$firstName = $_POST['firstname'];
	$lastName = $_POST['lastname'];
	$nickName = $_POST['nickname'];
	$email = $_POST['email'];
	$pass1 = $_POST['pass1'];
	$pass2 = $_POST['pass2'];
	$signature = $_POST['signature'];
	
	//Clean whitespace from both ends of the text fields
	$textFields = array('firstName', 'lastName', 'nickName', 'email', 'signature');
	foreach ($textFields as $fieldName) {
		$$fieldName = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $$fieldName);
	}
	
	//TODO: checks, JS checks
	$errorInPass = (!empty($pass1) || !empty($pass2)) && $pass1 != $pass2;
	if (empty($nickName) || $errorInPass|| empty($email)) {
		Qhebunel::redirectToErrorPage();	
	}
	
	//Update user table
	$userUpdateData = array(
		'ID' =>				$current_user->ID,
		'user_email' =>		$email,
		'display_name' =>	$nickName
	);
	if (!empty($pass1)) {
		$userUpdateData['user_pass'] = $pass1;
	}
	wp_update_user($userUpdateData);
	
	//Update user meta
	update_user_meta($current_user->ID, 'first_name', $firstName);
	update_user_meta($current_user->ID, 'last_name', $lastName);
	update_user_meta($current_user->ID, 'nickname', $nickName);
	
	//TODO: avatar
	if (!empty($_FILES['avatar'])) {
		if (($avatar = QhebunelFiles::saveAvatar($_FILES['avatar'])) !== false) {
			//The path is returned, save it into the DB
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_user_ext` set `avatar`=%s where `uid`=%d limit 1;',
					$avatar,
					$current_user->ID
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
			$current_user->ID
		)
	);
}

/**
 * This function is called when the user click on the Delete avatar button below the image.
 */
function qheb_user_profile_delete_avatar() {
	global $wpdb, $current_user;
	
	//Delete file
	QhebunelFiles::deleteAvatar();
	
	//Remove from DB
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_user_ext` set `avatar`=NULL where `uid`=%d limit 1;',
			$current_user->ID
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
$relativeUrl .= 'profile';
$absoluteUrl = get_site_url(null, 'forum/'.$relativeUrl);
wp_redirect($absoluteUrl);//Temporal redirect
?>