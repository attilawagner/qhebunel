<?php
/**
 * Qhebunel
 * User profile page
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Show message to users who aren't logged in
if ($current_user->ID == 0) {
	echo('<div class="qheb-error-message">'.__('You must log in to gain access to user profiles.', 'qhebunel').'</div>');
	return;//stop page rendering, but create footer
}

global $section_params;
$user_id = (int)$section_params;

$user = $wpdb->get_row(
	$wpdb->prepare(
		'select `e`.*, `u`.`display_name`, `u`.`user_registered`
		from `qheb_user_ext` as `e`
		left join `qheb_wp_users` as `u`
		on (`u`.`ID`=`e`.`uid`)
		where `e`.`uid`=%d',
		$user_id
	),
	ARRAY_A
);

if (empty($user)) {
	echo('<div class="qheb-error-message">'.__('User cannot be found.', 'qhebunel').'</div>');
	return;
}

$user_meta = get_user_meta($user_id);

$badges = $wpdb->get_results(
	$wpdb->prepare(
		'select `b`.*, `l`.`startdate`, `g`.`awarded`
		from `qheb_badges` as `b`
		  right join (
		      select `bid`,`startdate`
		      from `qheb_user_badge_links`
		      where `uid`=%d
		    ) as `l`
		    on (`l`.`bid`=`b`.`bid`)
		  left join `qheb_badge_groups` as `g`
		    on (`g`.`bgid`=`b`.`bgid`)
		order by `b`.`name`;',
		$user_id
	),
	ARRAY_A
);

$avatar = ($user['avatar'] ? '<img src="'.WP_CONTENT_URL.'/forum/avatars/'.$user['avatar'].'" alt=""/>' : '');
/* translators: First name, last name */
$full_name = sprintf(_x('%1$s %2$s', 'full name', 'qhebunel'), $user_meta['first_name'][0], $user_meta['last_name'][0]);
$reg_date = QhebunelDate::get_short_date($user['user_registered']);
?>
<table class="user-basic-data">
	<thead>
		<tr>
			<td colspan="2">
				<div class="avatar-holder"><?=$avatar?> </div>
				<div class="name"><?=$user['display_name']?> </div>
			</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th><?=__('Full name:', 'qhebunel')?></th>
			<td><?=$full_name?></td>
		</tr>
		<tr>
			<th><?=__('Member since:', 'qhebunel')?></th>
			<td><?=$reg_date?></td>
		</tr>
		<tr>
			<th><?=__('Posts:', 'qhebunel')?></th>
			<td><?=$user['postcount']?></td>
		</tr>
		<tr>
			<td colspan="2"><div class="user-signature"><?=QhebunelUI::format_post($user['signature'])?></div></td>
		</tr>
		<tr>
			<td colspan="2">
				<ul class="badge-wall">
					<?php
					foreach ($badges as $badge) {
						QhebunelBadges::render_badge($badge, false);
					}
					?>
				</ul>
			</td>
		</tr>
	</tbody>
</table>

