<?php
if(!current_user_can('manage_options')) {
	die('Access Denied');
}

//Process new group or edit request
if ((isset($_POST['qheb-badgeg-new']) || isset($_POST['qheb-badgeg-edit']))&& check_admin_referer('qheb_badgeg_addnew', 'qhebnonce')) {
	$id = (int)$_POST['qheb_group_id'];
	$name = $_POST['qheb-badgeg-name'];
	$limit = (int)$_POST['qheb-badgeg-limit'];
	$hidden = (isset($_POST['qheb-badgeg-hidden']) && $_POST['qheb-badgeg-hidden'] == 'true' ? 1 : 0);
	$awarded = (isset($_POST['qheb-badgeg-awarded']) && $_POST['qheb-badgeg-awarded'] == 'true' ? 1 : 0);
	$priority = $_POST['qheb-badgeg-priority'];
	if (!empty($name) && $limit >= 0 && $priority >=0 && $priority <=9) {
		
		if (empty($id)) {
			//Create new badge group
			$wpdb->query(
				$wpdb->prepare(
					'insert into `qheb_badge_groups` (`name`,`climit`,`awarded`,`hidden`,`priority`) values (%s, %d, %d, %d, %d);',
					$name,
					$limit,
					$awarded,
					$hidden,
					$priority
				)
			);
		} else {
			//Modify existing group
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_badge_groups` set `name`=%s, `climit`=%d, `awarded`=%d, `hidden`=%d, `priority`=%d where `bgid`=%d limit 1;',
					$name,
					$limit,
					$awarded,
					$hidden,
					$priority,
					$id
				)
			);
		}
		
	}
}

//Preprocess edit request
if (isset($_GET['editgid']) && $_GET['editgid'] > 0) {
	$group = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_badge_groups` where `bgid`=%d limit 1;',
			$_GET['editgid']
		),
		ARRAY_A
	);
	if (isset($group[0])) {
		$qheb_edit_group = $group[0];
	}
}

//Process delete group request
if (isset($_POST['qheb-badgeg-del']) && check_admin_referer('qheb_badgegdel', 'qhebnonce') && isset($_POST['qheb_group_del_id'])) {
	$qheb_group_del_ids = $_POST['qheb_group_del_id'];
	$id_list = '';
	foreach ($qheb_group_del_ids as $id) {
		$id = (int)$id;
		if ($id > 0) {
			$id_list .= $id . ',';
			
			//Deleting group badges		
			$bids_to_del = $wpdb->get_results(
				$wpdb->prepare(
					'select `bid` from `qheb_badges` where `bgid`=%d;', $id
				),
				ARRAY_A
			);
			foreach ($bids_to_del as $bids){
				QhebunelBadges::delete_badge($bids['bid']);
			}
		}
	}
	if (strlen($id_list) > 1) {
		$id_list = substr($id_list, 0, -1);
		$wpdb->query('delete from `qheb_badge_groups` where `bgid` in ('.$id_list.');');
	}

}

//Process create/edit badge request
if (isset($_POST['qheb-badge-new']) && isset($_GET['listgid']) && check_admin_referer('qheb_badge_add', 'qhebnonce')) {
	$edit_badge_id = isset($_POST['qheb-badge-edit-id']) ? $_POST['qheb-badge-edit-id'] : null;
	
	$group_id = $_GET['listgid'];
	$name = $_POST['qheb-badge-name'];
	$description = $_POST['qheb-badge-desc'];
	$points = $_POST['qheb-badge-points'];
	QhebunelBadges::save_badge($edit_badge_id, $group_id, $name, $description, $points, $_FILES['qheb-badge-icon-large'], $_FILES['qheb-badge-icon-small']);
}

//Process delete badge request
if (isset($_POST['qheb-badge-del']) && !empty($_POST['qheb-badge-del-id']) && check_admin_referer('qheb_badgedel', 'qhebnonce')) {
	$badge_ids_to_del = $_POST['qheb-badge-del-id'];
	foreach ($badge_ids_to_del as $id) {
		QhebunelBadges::delete_badge($id);
	}
}

//List badges in a group
if (isset($_GET['listgid']) && $_GET['listgid'] > 0) {
	$badge_list_id = (int)$_GET['listgid'];
	$badge_list_name = $wpdb->get_var(
		$wpdb->prepare(
			'select `name` from `qheb_badge_groups` where `bgid`=%d limit 1;',
			$badge_list_id
		)
	);
	$badge_list = $wpdb->get_results(
		$wpdb->prepare(
			'select `b`.*, count(`l`.`uid`) as `users`
			from `qheb_badges` as `b`
			  left join `qheb_user_badge_links` as `l`
			    on (`l`.`bid`=`b`.`bid`)
			where `b`.`bgid`=%d
			group by `b`.`bid`
			order by `b`.`name`;',
			$badge_list_id
		),
		ARRAY_A
	);
}

function render_priority_select($name, $default_value = 0) {
	echo('<select name="'.$name.'" id="'.$name.'">');
	echo('<option value="0"'.($default_value == 0 ? ' default="default"' : '').'>'.__('Not forced', 'qhebunel').'</option>');
	for ($i=1; $i<10; $i++) {
		echo('<option value="'.$i.'"'.($default_value == $i ? ' default="default"' : '').'>'.$i.'</option>');
	}
	echo('</select>');
}

//Set badge for editing
if (isset($_GET['editbid']) && $_GET['editbid'] > 0) {
	$edit_bid = (int)$_GET['editbid'];
	foreach ($badge_list as $badge) {
		if ($badge['bid'] == $edit_bid) {
			$edit_badge = $badge;
			break;
		}
	}
}

//Load badge groups
$bgroups = $wpdb->get_results('
	select `g`.*, count(`b`.`bid`) as `bcount`
	from `qheb_badge_groups` as `g`
	  left join `qheb_badges` as `b`
	    on (`b`.`bgid`=`g`.`bgid`)
	group by `g`.`bgid`
	order by `g`.`name` asc;',
	ARRAY_A
);
?>

<div class="wrap">
	<div class="icon32 qhebunelicon"></div>
	<h2><?php _e('Badges','qhebunel'); ?> <a href="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;newgroup');?>" class="add-new-h2"><?php _e('Add new group','qhebunel'); ?></a></h2>
	<form id="qheb_grouplistform" name="qheb_grouplistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php');?>" method="post">
		<?php wp_nonce_field('qheb_badgegdel','qhebnonce'); ?>
		<table class="widefat fixed qheb-catlist">
			<thead>
				<tr>
					<th scope="col" class="qheb-catname"><?php _e('Group name','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Badges (Limit)','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Hidden','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Award only','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Display priority','qhebunel'); ?></th>
					<th scope="col" class="qheb_catact"><?php _e('Actions','qhebunel'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" class="qheb-catname"><?php _e('Group name','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Badges (Limit)','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Hidden','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Award only','qhebunel'); ?></th>
					<th scope="col" class="qheb-catprop"><?php _e('Display priority','qhebunel'); ?></th>
					<th scope="col" class="qheb_catact"><?php _e('Actions','qhebunel'); ?></th>
				</tr>
			</tfoot>
			<tbody>
				<?php
				if (empty($bgroups)) {
					echo('<tr><td colspan="3">'.__('There are no groups in the database.','qhebunel').'</td></tr>');
				} else {
					foreach ($bgroups as $group) {
						echo('<tr><td><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$group['bgid']).'">'.$group['name'].'</a></td>');
						echo('<td>'.$group['bcount'].($group['climit'] > 0 ? ' ('.$group['climit'].')' : '').'</td>');
						echo('<td>'.($group['hidden'] ? __('Yes','qhebunel') : __('No','qhebunel')).'</td>');
						echo('<td>'.($group['awarded'] ? __('Yes','qhebunel') : __('No','qhebunel')).'</td>');
						echo('<td>'.($group['priority'] > 0 ? $group['priority'] : '').'</td>');
						echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;editgid='.$group['bgid']).'">'.__('Edit','qhebunel').'</a> <label><input type="checkbox" class="qheb-catdelcb" name="qheb_group_del_id[]" value="'.$group['bgid'].'" />'.__('Delete','qhebunel').'</label></td></tr>');
					}
				}
				?>
			</tbody>
		</table>
		<div class="tablenav bottom">
			<div class="alignright actions">
				<input class="action-secondary button" type="submit" name="qheb-badgeg-del" value="<?php _e('Delete','qhebunel'); ?>"/>
			</div>
			<br class="clear" />
		</div>
	</form>
	
	<?php if (isset($_GET['newgroup']) || isset($qheb_edit_group)) { ?>
		<h3 class="title"><?php if (isset($qheb_edit_group)) { _e('Edit group', 'qhebunel'); } else { _e('Create new', 'qhebunel'); } ?></h3>
		<form id="qheb_addcatform" name="qheb_addcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php');?>" method="post">
			<?php wp_nonce_field('qheb_badgeg_addnew','qhebnonce'); ?>
			<input type="hidden" name="qheb_group_id" value="<?=@$qheb_edit_group['bgid'];?>" />
			<div id="poststuff" class="metabox-holder qheb-metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Create new group','qhebunel'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb-badgeg-name"><?php _e('Group name', 'qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-badgeg-name" id="qheb-badgeg-name" value="<?=@$qheb_edit_group['name']?>" /></td>
							</tr>
							<tr title="<?php _e('How many badges can be given/claimed from this group?','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badgeg-limit"><?php _e('Claim limit', 'qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-badgeg-limit" id="qheb-badgeg-limit" value="<?=@$qheb_edit_group['climit']?>" /></td>
							</tr>
							<tr title="<?php _e('Check this to make the entire group hidden from users. Awarded badges will still be visible.','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badgeg-hidden"><?php _e('Hidden', 'qhebunel'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb-badgeg-hidden" id="qheb-badgeg-hidden" <?=(@$qheb_edit_group['hidden'] ? 'checked="checked"' : '');?> /></td>
							</tr>
							<tr title="<?php _e('If checked, only moderators can give these badges to users.','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badgeg-awarded"><?php _e('Award only', 'qhebunel'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb-badgeg-awarded" id="qheb-badgeg-awarded" <?=(@$qheb_edit_group['awarded'] ? 'checked="checked"' : '');?> /></td>
							</tr>
							<tr title="<?php _e('If you set a display priority, badges in this group will be forced to be displayed below user avatars.','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badgeg-priority"><?php _e('Display priority', 'qhebunel'); ?></label></th>
								<td><?php render_priority_select('qheb-badgeg-priority', @$qheb_edit_group['priority']); ?></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php if (isset($qheb_edit_group)) { _e('Save','qhebunel'); } else { _e('Create','qhebunel'); } ?>" name="<?=(isset($qheb_edit_group) ? 'qheb-badgeg-edit' : 'qheb-badgeg-new')?>" />
								</div>
								<div class="clear"></div>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
			</div>
		</form>
	<?php } ?>
	
	
	<?php if (isset($badge_list)) { ?>
		<h3><?=$badge_list_name;?></h3>
		<form id="qheb_badgelistform" name="qheb_badgelistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$badge_list_id);?>" method="post">
			<?php wp_nonce_field('qheb_badgedel','qhebnonce'); ?>
			<input type="hidden" name="qheb_group_id" value="<?=$badge_list_id;?>" />
			<table class="widefat fixed qheb-catlist">
				<thead>
					<tr>
						<th scope="col" class="qheb-bicon-large"><?php _e('Normal image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bicon-small"><?php _e('Small image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bname"><?php _e('Name', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bdesc"><?php _e('Description', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bpoints"><?php _e('Point value', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-busers" title="<?php _e('Number of users who have the badge.', 'qhebunel'); ?>"><?php _e('Users', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bact"><?php _e('Actions', 'qhebunel'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="qheb-bicon-large"><?php _e('Normal image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bicon-small"><?php _e('Small image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bname"><?php _e('Name', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bdesc"><?php _e('Description', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bpoints"><?php _e('Point value', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-busers" title="<?php _e('Number of users who have the badge.', 'qhebunel'); ?>"><?php _e('Users', 'qhebunel'); ?></th>
						<th scope="col" class="qheb-bact"><?php _e('Actions', 'qhebunel'); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php
					if (empty($badge_list)) {
						echo('<tr><td colspan="6">'.__('There are no badges in this group.','qhebunel').'</td></tr>');
					} else {
						foreach ($badge_list as $badge) {
							echo('<tr><td><img src="'.WP_CONTENT_URL.'/'.$badge['largeimage'].'" alt=""/></td>');
							echo('<td>'.(empty($badge['smallimage']) ? __('Missing image', 'qhebunel') : '<img src="'.WP_CONTENT_URL.'/'.$badge['smallimage'].'" alt=""/>').'</td>');
							echo('<td>'.$badge['name'].'</td>');
							echo('<td>'.$badge['description'].'</td>');
							echo('<td>'.$badge['points'].'</td>');
							echo('<td>'.$badge['users'].'</td>');
							echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$badge_list_id.'&amp;editbid='.$badge['bid']).'">'.__('Edit','qhebunel').'</a> <label><input type="checkbox" class="qheb-catdelcb" name="qheb-badge-del-id[]" value="'.$badge['bid'].'" />'.__('Delete','qhebunel').'</label></td></tr>');
						}
					}
					?>
				</tbody>
			</table>
			<div class="tablenav bottom">
				<div class="alignright actions">
					<input class="action-secondary button" type="submit" name="qheb-badge-del" value="<?php _e('Delete','qhebunel'); ?>"/>
				</div>
				<br class="clear" />
			</div>
		</form>
		
		<form id="qheb_addcatform" name="qheb_addbadgeform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$badge_list_id);?>" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field('qheb_badge_add','qhebnonce'); ?>
			<?php echo(isset($edit_badge) ? '<input type="hidden" name="qheb-badge-edit-id" value="'.$edit_badge['bid'].'" />' : '');?>
			<div id="poststuff" class="metabox-holder qheb-metabox">
				<div class="stuffbox">
					<h3><span><?php if (isset($edit_badge)) { _e('Edit badge','qhebunel'); } else { _e('Add new badge','qhebunel'); } ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr title="<?php printf(__('The image shown on the user\'s profile page. Allowed formats: %s.','qhebunel'), QHEBUNEL_BADGE_FORMATS); ?>">
								<th scope="row"><label for="qheb-badge-icon-large"><?php _e('Normal icon','qhebunel'); ?></label></th>
								<td><input type="file" name="qheb-badge-icon-large" id="qheb-badge-icon-large" /></td>
							</tr>
							<tr title="<?php printf(__('The image shown under the avatar next to forum posts. If not provided, it will be generated from the normal image. Allowed formats: %s.','qhebunel'), QHEBUNEL_BADGE_FORMATS); ?>">
								<th scope="row"><label for="qheb-badge-icon-small"><?php _e('Small icon','qhebunel'); ?></label></th>
								<td><input type="file" name="qheb-badge-icon-small" id="qheb-badge-icon-small" /></td>
							</tr>
							<tr title="<?php _e('Name of the badge.','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badge-name"><?php _e('Name','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-badge-name" id="qheb-badge-name" <?php echo(isset($edit_badge) ? 'value="'.htmlspecialchars($edit_badge['name']).'"' : ''); ?>/></td>
							</tr>
							<tr title="<?php _e('Description about the achivement the badge is awarded for.','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badge-desc"><?php _e('Description','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-badge-desc" id="qheb-badge-desc" <?php echo(isset($edit_badge) ? 'value="'.htmlspecialchars($edit_badge['description']).'"' : ''); ?>/></td>
							</tr>
							<tr title="<?php _e('Value of the badge in points.','qhebunel'); ?>">
								<th scope="row"><label for="qheb-badge-points"><?php _e('Point value','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-badge-points" id="qheb-badge-points" <?php echo(isset($edit_badge) ? 'value="'.htmlspecialchars($edit_badge['points']).'"' : ''); ?>/></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php if (isset($edit_badge)) { _e('Save','qhebunel'); } else { _e('Create','qhebunel'); } ?>" name="qheb-badge-new" />
								</div>
								<div class="clear"></div>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
			</div>
		</form>
	<?php } ?>

</div>