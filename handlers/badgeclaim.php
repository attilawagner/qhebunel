<?php
/**
 * Qhebunel
 * Special section handler for claiming badges
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;
if (preg_match('%(\d+)(/remove)?(?:/(\d+))?%', $section_params, $regs)) {
	$badge_id = $regs[1];
	$remove = (isset($regs[2]) ? $regs[2] == '/remove' : false);
	$user_id = @$regs[3];
} else {
	Qhebunel::redirect_to_error_page();
}

if (!is_user_logged_in() || (!QhebunelUser::is_moderator() && !empty($user_id))) {
	Qhebunel::redirect_to_error_page();
}

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

if (empty($badge) || $badge['awarded']) {
	Qhebunel::redirect_to_error_page();
}

if (!$remove) {
	$wpdb->query(
		$wpdb->prepare(
			'insert ignore into `qheb_user_badge_links` (`bid`,`uid`,`startdate`) values (%d,%d,%s);',
			$badge_id,
			$current_user->ID,
			current_time('mysql')
		)
	);
} else {
	$wpdb->query(
		$wpdb->prepare(
			'delete ignore from `qheb_user_badge_links` where `bid`=%d and `uid`=%d;',
			$badge_id,
			(empty($user_id) ? $current_user->ID : $user_id) //revoke and remove
		)
	);
}

//Redirect to badge page
$absolute_url = QhebunelUI::get_url_for_badge($badge_id);
wp_redirect($absolute_url);//Temporal redirect
?>