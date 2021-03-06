<?php
if(!current_user_can('manage_options')) {
	die('Access Denied');
}

//Process user list actions
if (isset($_POST['qheb_ulist_modif']) && check_admin_referer('qheb_usermodif', 'qhebnonce') && isset($_POST['qheb_ulist_user'])) {
	$action = $_POST['qheb_ulist_action'];
	$uids = @$_POST['qheb_ulist_user'];
	$gid = (int)@$_POST['qheb-ulist-group'];
	
	if (count($uids) > 0) {
		switch ($action) {
			case 'addgroup':
				if ($gid > 10) {
					$q = 'insert into `qheb_user_group_links` values ';
					foreach ($uids as $uid) {
						$uid = (int)$uid;
						if ($uid > 0) {
							$q .= "(${gid}, ${uid}),";
						}
					}
					$q = substr($q, 0, -1).';';
					$wpdb->query($q);
				}
				break;
			
			
			case 'removegroup':
				if ($gid > 10) {
					$q = 'delete from `qheb_user_group_links` where ';
					foreach ($uids as $uid) {
						$uid = (int)$uid;
						if ($uid > 0) {
							$q .= "(`gid`=${gid} and `uid`=${uid}) or";
						}
					}
					$q = substr($q, 0, -3).';';
					$wpdb->query($q);
				}
				break;
			
			
			case 'addmod':
				//Add users to the mod group
				$q = 'insert ignore into `qheb_user_group_links` values ';
				foreach ($uids as $uid) {
					$uid = (int)$uid;
					if ($uid > 0) {
						$q .= "(3, ${uid}),";
					}
				}
				$q = substr($q, 0, -1).';';
				$wpdb->query($q);
				break;
			
			
			case 'removemod':
				//Remove from the mod group
				$q = 'delete from `qheb_user_group_links` where ';
				foreach ($uids as $uid) {
					$uid = (int)$uid;
					if ($uid > 0) {
						$q .= "(`gid`=3 and `uid`=${uid}) or";
					}
				}
				$q = substr($q, 0, -3).';';
				$wpdb->query($q);
				break;
			
			
			case 'ban':
				$uidss = array();
				foreach ($uids as $uid) {
					$uid = (int)$uid;
					if ($uid > 0) {
						$uidss[] = $uid;
					}
				}
				$uidss = implode(',',$uidss);
				$wpdb->query('update `qheb_user_ext` set `banned`=1 where `uid` in ('.$uidss.');');
				break;
			
			
			case 'unban':
				$uidss = array();
				foreach ($uids as $uid) {
					$uid = (int)$uid;
					if ($uid > 0) {
						$uidss[] = $uid;
					}
				}
				$uidss = implode(',',$uidss);
				$wpdb->query('update `qheb_user_ext` set `banned`=0 where `uid` in ('.$uidss.');');
				break;
		}
	}
}

//Load users
$sgq = '';
$showgroup = 0;
if (isset($_REQUEST['showgroup'])) {
	$showgroup_ = (int)$_REQUEST['showgroup'];
	if ($showgroup_ > 0) {
		$showgroup = $showgroup_;
		$sgq = ' and `gl`.`gid`='.$showgroup;
	}
}
$users = $wpdb->get_results('
	select `u`.`ID` as `uid`, `u`.`display_name`, `u`.`user_login`, `e`.`banned`, group_concat(`g`.`name`) as `glist`
	from `qheb_user_ext` as `e`
	  right join `qheb_wp_users` as `u`
	    on (`e`.`uid`=`u`.`ID`)
	  left join `qheb_user_group_links` as `gl`
	    on (`u`.`ID`=`gl`.`uid`)
	  left join `qheb_user_groups` as `g`
	    on (`gl`.`gid`=`g`.`gid`)
	where (`gl`.`gid`>10 or `gl`.`gid` is null or `gl`.`gid`=3)'.$sgq.'
	group by `u`.`ID` order by `u`.`display_name` asc;',
	ARRAY_A
);

//Load groups
$groups = $wpdb->get_results('
	select * from
	((
	  select `g`.`gid`, `g`.`name`, `g`.`prominent`, count(`gl`.`uid`) as `ucount`
	  from `qheb_user_groups` as `g`
	    left join `qheb_user_group_links` as `gl`
	      on (`g`.`gid`=`gl`.`gid`)
	  group by `g`.`gid`
	) union (
	  select 0, "All", 1, count(*)
	  from `qheb_wp_users`
	)) as `gg`
	order by `prominent` desc, `name` asc;',
	ARRAY_A
);

//Displays a select with the user groups
function qheb_ulist_group_select() {
	global $groups;
	echo('<select name="qheb-ulist-group" id="qheb-ulist-group">');
	foreach ($groups as $group) {
		if ($group['gid'] > 10) {
			echo('<option value="'.$group['gid'].'"'.($group['prominent'] ? ' class="qheb-prom"' : '').'>'.$group['name'].'</option>');
		}
	}
	echo('</select>');
}

//Displays the prominent groups on top
function qheb_ulist_top_groups() {
	global $groups, $showgroup;
	$pgroups = array();
	foreach ($groups as $group) {
		if ($group['prominent'] && $group['gid'] > 0) {
			$pgroups[] = $group;
		}
	}
	$lastgid = $pgroups[count($pgroups)-1]['gid'];
	echo('<li><a href="'.admin_url('admin.php?page=qhebunel/admin/optusers.php').'"'.($showgroup == 0 ? ' class="current"' : '').'>'.__('All','qhebunel').' <span class="count">('.$group['ucount'].')</count></a> |</li>');
	foreach ($pgroups as $group) {
		echo('<li><a href="'.admin_url('admin.php?page=qhebunel/admin/optusers.php&amp;showgroup='.$group['gid']).'"'.($showgroup == $group['gid'] ? ' class="current"' : '').'>'.$group['name'].' <span class="count">('.$group['ucount'].')</count></a>'.($group['gid'] != $lastgid ? ' |' : '').'</li>');
	}
}
?>
<div class="wrap">
	<div class="icon32 qhebunelicon"></div>
	<h2><?php _e('Users','qhebunel'); ?></h2>
	<ul class="subsubsub qhebsubsubsub"><?php qheb_ulist_top_groups(); ?></ul>
	<form id="qheb_grouplistform" name="qheb_grouplistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optusers.php');?>" method="post">
		<?php wp_nonce_field('qheb_usermodif','qhebnonce'); ?>
		<table class="widefat fixed qheb_catlist">
			<thead>
				<tr>
					<th scope="col" class="qheb-username"><?php _e('Display name (Username)','qhebunel'); ?></th>
					<th scope="col" class="qheb-usergroups"><?php _e('Groups','qhebunel'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col"><?php _e('Display name (Username)','qhebunel'); ?></th>
					<th scope="col"><?php _e('Groups','qhebunel'); ?></th>
				</tr>
			</tfoot>
			<tbody>
				<?php
				foreach ($users as $user) {
					$label_class = ($user['banned'] == 1 ? ' class="banned"' : '');
					echo('<tr'.$label_class.'><td><label><input type="checkbox" name="qheb_ulist_user[]" value="'.$user['uid'].'" />'.$user['display_name'].($user['display_name'] != $user['user_login'] ? ' ('.$user['user_login'].')' : '').'</label></td>');
					echo('<td>'.$user['glist'].'</td></tr>');
				}
				?>
			</tbody>
		</table>
		<div class="tablenav bottom">
			<div class="alignleft actions">
				<select name="qheb_ulist_action" id="qheb_ulist_action" onchange="qhebUlistActchange();">
					<option value="none" selected="selected"><?php _e('Bulk Actions','qhebunel'); ?></option>
					<option value="addgroup"><?php _e('Add to group','qhebunel'); ?></option>
					<option value="removegroup"><?php _e('Remove from group','qhebunel'); ?></option>
					<option value="addmod"><?php _e('Promote to moderator','qhebunel'); ?></option>
					<option value="removemod"><?php _e('Revoke mod rights','qhebunel'); ?></option>
					<option value="ban"><?php _e('Ban','qhebunel'); ?></option>
					<option value="unban"><?php _e('Unban','qhebunel'); ?></option>
				</select>
				<?php qheb_ulist_group_select(); ?>
				<input class="action-secondary button" type="submit" name="qheb_ulist_modif" value="<?php _e('Save','qhebunel'); ?>"/>
			</div>
			<br class="clear" />
		</div>
	</form>
</div>