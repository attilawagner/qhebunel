<?php
/**
 * Qhebunel
 * Private Messages
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

/*
 * Only logged in users can see PM pages,
 * so bail out if an anonymous user tries to visit the URL.
 */
if (!is_user_logged_in()) {
	echo('<div class="qheb-error-message">'.__('Only logged in users can exchange private messages.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}

global $section_params;
if (preg_match('%^(\d+)(?:/page-(\d+))?%', $section_params, $regs)) {
	$partner_user_id = $regs[1];
	$page_num = @$regs[2];
} else {
	$partner_user_id = 0;
	$page_num = 0;
}

/**
 * Renders the users in a list, showing those
 * whith whom the current user conversed recently.
 */
function render_user_list() {
	global $wpdb, $current_user;
	$user_id = $current_user->ID;
	
	//Load the last messages
	$messages = $wpdb->get_results(
		$wpdb->prepare(
			'select `pf`.`mid`, `pf`.`uid`, if(`p`.`from`=`pf`.`uid`, \'in\', \'out\') as `direction`, `u`.`display_name` as `name`, left(`p`.`text`,30) as `text`, `p`.`sentdate`, `p`.`readdate`
			from (
			    select max(`mid`) as `mid`, if(`from`=%d, `to`, `from`) as `uid`
			    from `qheb_privmessages`
			    where `from`=%d or `to`=%d
			    group by `uid`
			    order by `mid` desc
			  ) as `pf`
			  left join `qheb_privmessages` as `p`
			    on (`p`.`mid`=`pf`.`mid`)
			  left join `qheb_wp_users` as `u`
			    on (`u`.`ID`=`pf`.`uid`)',
			$user_id,
			$user_id,
			$user_id
		),
		ARRAY_A
	);
	
	//Load those users with whom the current user did not have any conversation
	$historyless_users = $wpdb->get_results(
		$wpdb->prepare(
		    'select `u`.`ID` as `uid`, `u`.`display_name` as `name`
			from `qheb_wp_users` as `u`
			  left join (
			    select distinct if(`from`=%d, `to`, `from`) as `uid`
			    from `qheb_privmessages`
			    where `from`=%d or `to`=%d
			  ) as `p`
			    on (`p`.`uid`=`u`.`ID`)
			where `p`.`uid` is null
			order by `name`',
			$user_id,
			$user_id,
			$user_id
		),
		ARRAY_A
	);
	
	echo('<div class="private-msg-list">');
	//Render messages
	foreach ($messages as $message) {
		$is_read = $message['readdate'] != null;
		$is_incoming = $message['direction'] == 'in';
		$url = QhebunelUI::get_url_for_pm_user($message['uid']);
		echo('<a href="'.$url.'" class="private-msg-excrept '.($is_read ? 'read' : 'unread').' '.($is_incoming ? 'inbox' : 'outbox').'">');
		echo('<span class="private-msg-icon"></span>');
		echo('<span class="private-msg-name">'.$message['name'].'</span> ');
		echo('<time datetime="'.QhebunelDate::get_datetime_attribute($message['sentdate']).'" title="'.QhebunelDate::get_relative_date($message['sentdate']).'">'.QhebunelDate::get_list_date($message['sentdate']).'</time> ');
		echo('<span class="private-msg-excrept">'.htmlentities2($message['text']).'</span>');
		echo('</a>');
	}
	
	//Render users without messages
	foreach ($historyless_users as $user) {
		if ($user['uid'] != $user_id) {
			$url = QhebunelUI::get_url_for_pm_user($user['uid']);
			echo('<a href="'.$url.'" class="private-msg-excrept empty">');
			echo('<span class="private-msg-icon"></span>');//icon placeholder
			echo('<span class="private-msg-name">'.$user['name'].'</span>');
			echo('</a>');
		}
	}
	echo('</div>');
}

/**
 * Renders the conversation with the selected user,
 * and displays a form at the bottom of the page to sent a new message.
 * @param integer $partner_user_id The user ID of the partner.
 * @param integer $page_num Number of page backwards in the history.
 */
function render_conversation($partner_user_id, $page_num) {
	global $current_user, $wpdb;
	$user_id = $current_user->ID;
	$messages_per_page = 20; 
	
	//Load meta information
	$partner_name = $wpdb->get_var(
		$wpdb->prepare(
			'select `display_name` from `qheb_wp_users` where `ID`=%d;',
			$partner_user_id
		)
	);
	$total_messages = $wpdb->get_var(
		$wpdb->prepare(
			'select count(*) from `qheb_privmessages`
			where (`from`=%d and `to`=%d) or (`from`=%d and `to`=%d);',
			$user_id,
			$partner_user_id,
			$partner_user_id,
			$user_id
		)
	);
	
	//Load messages
	$messages = $wpdb->get_results(
		$wpdb->prepare(
			'select `p`.*, `u`.`display_name` as `name`
			from `qheb_privmessages` as `p`
			  left join `qheb_wp_users` as `u`
			    on (`u`.`ID`=`p`.`from`)
			where (`from`=%d and `to`=%d) or (`from`=%d and `to`=%d)
			order by `mid` asc
			limit %d,%d;',
			$user_id,
			$partner_user_id,
			$partner_user_id,
			$user_id,
			$page_num,
			$messages_per_page
		),
		ARRAY_A
	);
	
	//Set unread messages as read
	$wpdb->query(
		$wpdb->prepare(
			'update `qheb_privmessages` set `readdate`=%s
			where `from`=%d and `to`=%d and `readdate` is null;',
			current_time('mysql'),
			$partner_user_id,
			$user_id
		)
	);
	
	//Render messages
	echo('<div class="private-msg-title">'.sprintf(__('Conversation with %s','qhebunel'), $partner_name).'</div>');
	echo('<div class="private-msg-conversation">');
	$previous_msg_count = $total_messages - ($page_num+1)*$messages_per_page;
	$next_msg_count = $page_num*$messages_per_page;
	if ($previous_msg_count > 0) {
		$url = QhebunelUI::get_url_for_pm_user($partner_user_id, $page_num+1);
		echo('<a href="'.$url.'">'.sprintf(__('Load previous %1$d messages (%2$d remaining)','qhebunel'),$messages_per_page,$previous_msg_count).'</a>');
	}
	foreach ($messages as $message) {
		$is_read = $message['readdate'] != null;
		$is_incoming = $message['to'] == $user_id;
		echo('<div class="private-msg-msg '.($is_read ? 'read' : 'unread').' '.($is_incoming ? 'inbox' : 'outbox').'">');
		echo('<span class="private-msg-name">'.$message['name'].':</span> ');
		echo('<time datetime="'.QhebunelDate::get_datetime_attribute($message['sentdate']).'" title="'.QhebunelDate::get_relative_date($message['sentdate']).'">'.QhebunelDate::get_post_date($message['sentdate']).'</time> ');
		echo('<span class="private-msg-text">'.QhebunelUI::format_post($message['text']).'</span>');
		echo('</div>');
	}
	if ($next_msg_count > 0) {
		$url = QhebunelUI::get_url_for_pm_user($partner_user_id, $page_num-1);
		echo('<a href="'.$url.'">'.sprintf(__('Load next %1$d messages (%2$d remaining)','qhebunel'),$messages_per_page,$next_msg_count).'</a>');
	}
	echo('</div>');
	
	//Render form
	echo('<form class="private-msg-form" action="'.site_url('forum/').'" method="post">');
	echo('<input type="hidden" name="action" value="pm" />');
	echo('<input type="hidden" name="partner" value="'.$partner_user_id.'" />');
	echo('<textarea name="message"></textarea>');
	echo('<input type="submit" name="send" value="'.__('Send message','qhebunel').'" />');
	echo('</form>');
}


/*
 * Render page
 */

//User list is always displayed
render_user_list();

if ($partner_user_id > 0) {
	//Render conversation with specified user
	render_conversation($partner_user_id, $page_num);
}
?>