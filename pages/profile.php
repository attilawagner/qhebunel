<?php
/**
 * Qhebunel
 * User profile (settings) page
 * This page should be used instead of the WP admin dashboard for readers
 * to change their settings.
 * 
 * This page is displayed when a handler runs into an error.
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Show message to users who aren't logged in
if ($current_user->ID == 0) {
	echo('<div class="qheb_error_message">'.__('You must log in to edit your profile.', 'qhebunel').'</div>');
	return;//stop page rendering, but create footer
}

//Load data to display
$userName = $current_user->data->user_login;
$firstName = get_user_meta($current_user->ID, 'first_name', true);
$lastName = get_user_meta($current_user->ID, 'last_name', true);
$nickName = $current_user->data->display_name;
$email = $current_user->data->user_email;

$extData = $wpdb->get_row(
	$wpdb->prepare(
		'select * from `qheb_user_ext` where `uid`=%d',
		$current_user->ID
	),
	ARRAY_A
);

?>
<form method="post" action="<?=site_url('forum/')?>" enctype="multipart/form-data" onsubmit="return validateProfileForm();" id="profileForm">
	<input type="hidden" name="action" value="profile" />
	<input type="hidden" name="MAX_FILE_SIZE" value="<?=QHEBUNEL_AVATAR_MAX_FILESIZE?>" />
	<h2><?php _e('Basic information', 'qhebunel'); ?></h2>
	<table class="profile_settings">
		<tfoot>
			<tr><td colspan="2"><input name="update" type="submit" value="<?php _e('Save', 'qhebunel'); ?>" /></td></tr>
		</tfoot>
		<tbody>
			<tr title="<?php _e('This is the name you use to log in. You cannot change it.', 'qhebunel'); ?>">
				<th><label for="username"><?php _e('Login name', 'qhebunel'); ?></label></th>
				<td><input name="username" id="username" type="text" readonly="readonly" value="<?=$current_user->data->user_login?>" /><span class="icon">🔒</span></td>
			</tr>
			<tr>
				<th><label for="firstname"><?php _e('First name', 'qhebunel'); ?></label></th>
				<td><input name="firstname" id="firstname" type="text" value="<?=$firstName?>" /></td>
			</tr>
			<tr>
				<th><label for="lastname"><?php _e('Last name', 'qhebunel'); ?></label></th>
				<td><input name="lastname" id="lastname" type="text" value="<?=$lastName?>" /></td>
			</tr>
			<tr title="<?php _e('This is the name visible next to your comments and forum posts.', 'qhebunel'); ?>">
				<th><label for="nickname"><?php _e('Nickname', 'qhebunel'); ?></label></th>
				<td><input name="nickname" id="nickname" type="text" required="required" value="<?=$nickName?>" /></td>
			</tr>
			<tr title="<?php _e('Your email address is used to send you notifications you request and it\'s used in case you forgot your password.', 'qhebunel'); ?>">
				<th><label for="email"><?php _e('Email', 'qhebunel'); ?></label></th>
				<td><input name="email" id="email" type="email" required="required" value="<?=$email?>" /><span class="icon">✓</span></td>
			</tr>
			<tr title="<?php _e('Leave the password fields blank if you don\'t want to change your current password.', 'qhebunel'); ?>">
				<th><label for="pass1"><?php _e('Password', 'qhebunel'); ?></label></th>
				<td><input name="pass1" id="pass1" type="password" value="" /><span class="icon"></span></td>
			</tr>
			<tr title="<?php _e('Leave the password fields blank if you don\'t want to change your current password.', 'qhebunel'); ?>">
				<th><label for="pass2"><?php _e('Password', 'qhebunel'); ?></label></th>
				<td><input name="pass2" id="pass2" type="password" value="" /><span class="icon"></span></td>
			</tr>
		</tbody>
	</table>
	
	
	<h2><?php _e('Avatar and signature', 'qhebunel'); ?></h2>
	<table class="profile_settings">
		<tfoot>
			<tr><td colspan="2"><input name="update" type="submit" value="<?php _e('Save', 'qhebunel'); ?>" /></td></tr>
		</tfoot>
		<tbody>
			<tr>
				<th><?php _e('Current avatar', 'qhebunel'); ?></th>
				<td>
					<?php
						if (!empty($extData['avatar'])) {
							echo('<img src="'.WP_CONTENT_URL.'/forum/avatars/'.$extData['avatar'].'" alt="" />');
							echo('<br/><input name="delete_avatar" type="submit" value="'.__('Delete avatar', 'qhebunel').'" />');
						} else {
							_e('You don\'t have an avatar.', 'qhebunel');
						}
					?>
				</td>
			</tr>
			<tr>
				<th><?php _e('Upload new avatar', 'qhebunel'); ?></th>
				<td>
					<input type="file" name="avatar" accept="image/jpeg,image/png,image/gif" />
				</td>
			</tr>
			<tr>
				<th><?php _e('Current signature', 'qhebunel'); ?></th>
				<td>
					<?php
						if (!empty($extData['signature'])) {
							echo('<div class="user_signature">'.QhebunelUI::formatPost($extData['signature']).'</div>');
						} else {
							_e('You don\'t have a signature.', 'qhebunel');
						}
					?>
				</td>
			</tr>
			<tr>
				<th><?php _e('Edit signature', 'qhebunel'); ?></th>
				<td>
					<textarea name="signature"><?=(empty($extData['signature']) ? '' : $extData['signature'])?></textarea>
				</td>
			</tr>
		</tbody>
	</table>
	
	
	<h2><?php _e('Badges', 'qhebunel'); ?></h2>

</form>