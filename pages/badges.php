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
			  left join `qheb_user_badge_links` as `l`
			    on (`l`.`bid`=`b`.`bid`)
			where `g`.`hidden`<=%d and (`l`.`uid`=%d or `l`.`uid` is null)
			order by `b`.`name`',
			(QhebunelUser::is_moderator() ? 1 : 0),
			$current_user->ID
		),
		ARRAY_A
	);
	
	foreach ($badge_groups as $group) {
		echo('<h3>'.$group['name'].'</h3>');
		echo('<ul class="badge-wall">');
		foreach ($badges as $badge) {
			if ($badge['bgid'] == $group['bgid']) {
				if (empty($badge['startdate'])) {
					$status = __('You do not have this badge.','qhebunel');
				} else {
					if ($group['awarded']) {
						$status = sprintf(__('This badge was awarded to you on %s.','qhebunel'), QhebunelDate::get_short_date($badge['startdate']));
					} else {
						$status = sprintf(__('You\'ve claimed this badge on %s.','qhebunel'), QhebunelDate::get_short_date($badge['startdate']));
					}
				}
				
				$url = site_url('forum/badges/'.$badge['bid']);
				echo('<li class="badge-frame">');
				echo('<div class="img"><a href="'.$url.'"><img src="'.WP_CONTENT_URL.'/'.$badge['largeimage'].'" alt="'.$badge['name'].'" /></a></div>');
				echo('<div class="name"><a href="'.$url.'">'.$badge['name'].'</a></div>');
				echo('<div class="status">'.$status.'</div>');
				/*echo('<div class="actions">');
				if ($group['awarded'] == false) {
					if (empty($badge['startdate'])) {
						echo('<a href="">'.__('Claim','qhebunel').'</a> ');
					} else {
						echo('<a href="">'.__('Remove','qhebunel').'</a> ');
					}
				}
				echo('</div>');*/
				echo('</li>');
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
			'select `b`.*, `l`.`startdate`
			from `qheb_badges` as `b`
			  left join `qheb_badge_groups` as `g`
			    on (`g`.`bgid`=`b`.`bgid`)
			  left join `qheb_user_badge_links` as `l`
			    on (`l`.`bid`=`b`.`bid`)
			where `g`.`hidden`<=%d and (`l`.`uid`=%d or `l`.`uid` is null)
			order by `b`.`name`',
			(QhebunelUser::is_moderator() ? 1 : 0),
			$current_user->ID
		),
		ARRAY_A
	);
}
?>