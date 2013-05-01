<?php
/**
 * Qhebunel
 * Badges
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

/*
 * Only logged in users can see this page.
 */
if (!is_user_logged_in()) {
	echo('<div class="qheb-error-message">'.__('Only logged in users can view the badges.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}

global $section_params;
if (preg_match('%^(\d+)%', $section_params, $regs)) {
	$badge_id = $regs[1];
}

if (empty($badge_id)) {
	/*
	 * Render list of badges
	 */
	$badge_groups = $wpdb->get_results(
		$wpdb->prepare(
			'select *
			from `qheb_badge_groups`
			where `hidden`<=%d
			order by `name`',
			(QhebunelUser::is_moderator() ? 1 : 0) //Moderators will see the hidden groups
		),
		ARRAY_A
	);
	
	$badges = $wpdb->get_results(
		$wpdb->prepare(
			'select `b`.*, `l`.`startdate`
			from `qheb_badges` as `b`
			  left join `qheb_badge_groups` as `g`
			    on (`g`.`bgid`=`b`.`bgid`)
			  left join (
			      select `bid`,`startdate`
			      from `qheb_user_badge_links`
			      where `uid`=%d
			    ) as `l`
			    on (`l`.`bid`=`b`.`bid`)
			where `g`.`hidden`<=%d
			order by `b`.`name`;',
			$current_user->ID,
			(QhebunelUser::is_moderator() ? 1 : 0)
		),
		ARRAY_A
	);
	
	foreach ($badge_groups as $group) {
		echo('<h3>'.$group['name'].'</h3>');
		echo('<ul class="badge-wall">');
		foreach ($badges as $badge) {
			if ($badge['bgid'] == $group['bgid']) {
				QhebunelBadges::render_badge($badge, true, $group);
			}
		}
		echo('</ul>');
	}
} else {
	
	/*
	 * Render a detail page for a single badge
	 */
	$badge = $wpdb->get_row(
		$wpdb->prepare(
			'select `b`.*, `l`.`startdate`, `g`.`awarded`
			from `qheb_badges` as `b`
			  left join `qheb_badge_groups` as `g`
			    on (`g`.`bgid`=`b`.`bgid`)
			  left join (
			      select `bid`,`startdate`
			      from `qheb_user_badge_links`
			      where `uid`=%d
			    ) as `l`
			    on (`l`.`bid`=`b`.`bid`)
			where `g`.`hidden`<=%d and `b`.`bid`=%d',
			$current_user->ID,
			(QhebunelUser::is_moderator() ? 1 : 0),
			$badge_id
		),
		ARRAY_A
	);
	
	if (empty($badge)) {
		//Invalid ID or the user does not have the necessary permissions to view it
		echo('<div class="qheb-error-message">'.__('The badge you requested cannot be displayed.', 'qhebunel').'</div>');
		return;
	}
	
	echo('<ul class="badge-wall badge-wall-single">');
	QhebunelBadges::render_badge($badge, true);
	echo('</ul>');
	
	echo('<div class="badge-action">');
	if (!$badge['awarded']) {
		if (empty($badge['startdate'])) {
			$url = site_url('forum/claim-badge/'.$badge['bid']);
			echo('<a href="'.$url.'">'._x('Claim','badge action','qhebunel').'</a> ');
		} else {
			$url = site_url('forum/claim-badge/'.$badge['bid'].'/remove');
			echo('<a href="'.$url.'">'._x('Remove','badge action','qhebunel').'</a> ');
		}
	}
	echo('</div>');
	
	if (QhebunelUser::is_moderator()) {
		echo('<h3>'.__('Award this badge','qhebunel').'</h3>');
		echo('<form id="award-badge-form" action="'.site_url('forum/').'" method="post">');
		echo('<input type="hidden" name="action" value="badgeaward" />');
		echo('<input type="hidden" name="badge-id" value="'.$badge_id.'" />');
		echo('<table class="profile_settings">');
		echo('<tfoot><tr><td colspan="2"><input name="award" type="submit" value="'._x('Award','badge action','qhebunel').'" /></td></tr></tfoot>');
		echo('<tbody><tr><th><label for="nickname">'.__('Nickname', 'qhebunel').'</label></th><td><input name="nickname" id="nickname" type="text" required="required" /></td></tbody>');
		echo('</table>');
		echo('</form>');
	}
	
	$users = $wpdb->get_results(
		$wpdb->prepare(
			'select `u`.`ID` as `uid`, `u`.`display_name`, `l`.`startdate`, `e`.`avatar`
			from `qheb_wp_users` as `u`
			  left join `qheb_user_badge_links` as `l`
			    on (`l`.`uid`=`u`.`ID`)
			  left join `qheb_user_ext` as `e`
			    on (`e`.`uid`=`u`.`ID`)
			where `l`.`bid`=%d order by `u`.`display_name`;',
			$badge_id
		),
		ARRAY_A
	);
	
	if (empty($users)) {
		echo('<div class="qheb-notice-message">'.__('Currently nobody has this badge.', 'qhebunel').'</div>');
	} else {
		echo('<h3>'.__('Users who have this badge','qhebunel').'</h3>');
		echo('<ul class="user-wall">');
		foreach ($users as $user) {
			echo('<li>');
			$avatar = ($user['avatar'] ? '<img src="'.WP_CONTENT_URL.'/forum/avatars/'.$user['avatar'].'" alt=""/>' : '');
			$profile_url = QhebunelUI::get_url_for_user($user['uid']);
			echo('<div class="avatar-holder"><a href="'.$profile_url.'">'.$avatar.'</a></div>');
			echo('<div class="name"><a href="'.$profile_url.'">'.$user['display_name'].'</a></div>');
			echo('<div class="date">'.QhebunelDate::get_short_date($user['startdate']).'</div>');
			if (QhebunelUser::is_moderator()) {
				$url = site_url('forum/claim-badge/'.$badge['bid'].'/remove/'.$user['uid']);
				echo('<div class="date"><a href="'.$url.'">'._x('Revoke','badge action','qhebunel').'</a></div>');
			}
			echo('</li>');
		}
		echo('</ul>');
	}
}
?>