<?php
if(!current_user_can('manage_options')) {
	die('Access Denied');
}

//Process new group request
if (isset($_POST['qheb_badgeg_new']) && check_admin_referer('qheb_badgeg_addnew', 'qhebnonce')) {
	$name = $_POST['qheb_badgeg_name'];
	$limit = (int)$_POST['qheb_badgeg_limit'];
	$hidden = (isset($_POST['qheb_badgeg_hidden']) && $_POST['qheb_badgeg_hidden'] == 'true' ? 1 : 0);
	if (!empty($name) && $limit >= 0) {
		$wpdb->query(
			$wpdb->prepare(
				'insert into `qheb_badge_groups` values (0, %s, %d, %d);',
				$name,
				$limit,
				$hidden
			)
		);
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

//Process edit badge group request
if (isset($_POST['qheb_badgeg_edit']) && check_admin_referer('qheb_badgeg_edit', 'qhebnonce')) {
	$name = $_POST['qheb_badgeg_name'];
	$limit = (int)$_POST['qheb_badgeg_limit'];
	$hidden = (isset($_POST['qheb_badgeg_hidden']) && $_POST['qheb_badgeg_hidden'] == 'true' ? 1 : 0);
	$id = (int)$_POST['qheb_group_id'];
	if (!empty($name) && $limit >= 0 && $id > 0) {
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_badge_groups` set `name`=%s, `climit`=%d, `hidden`=%d where `bgid`=%d limit 1;',
				$name,
				$limit,
				$hidden,
				$id
			)
		);
	}
}

//Process delete group request
if (isset($_POST['qheb_badgeg_del']) && check_admin_referer('qheb_badgegdel', 'qhebnonce') && isset($_POST['qheb_group_del_id'])) {
	$qheb_group_del_ids = $_POST['qheb_group_del_id'];
	$idList = '';
	foreach ($qheb_group_del_ids as $id) {
		$id = (int)$id;
		if ($id > 0) {
			$idList .= $id . ',';
			
			//Deleting group badges		
			$bids_to_del = $wpdb->get_results($wpdb->prepare('select `bid` from `qheb_badges` where `bgid`=%d;', $id), ARRAY_A);
			foreach ($bids_to_del as $bids){
					QhebunelBadges::deleteBadge($bids['bid']);
			}
		}
	}
	if (strlen($idList) > 1) {
		$idList = substr($idList, 0, -1);
		$wpdb->query('delete from `qheb_badge_groups` where `bgid` in ('.$idList.');');
	}

}

//Process create/edit badge request
if (isset($_POST['qheb_badge_new']) && isset($_GET['listgid']) && check_admin_referer('qheb_add_badge', 'qhebnonce')) {
	$editBadgeId = isset($_POST['qheb_badge_edit_id']) ? $_POST['qheb_badge_edit_id'] : null;
	
	$groupId = $_GET['listgid'];
	$name = $_POST['qheb_badge_name'];
	$description = $_POST['qheb_badge_desc'];
	QhebunelBadges::saveBadge($editBadgeId, $groupId, $name, $description, $_FILES['qheb_badge_icon_large'], $_FILES['qheb_badge_icon_small']);
}

//Process delete badge request
if (isset($_POST['qheb_badge_del']) && !empty($_POST['qheb_badge_del_id']) && check_admin_referer('qheb_badgedel', 'qhebnonce')) {
	$badgeIdsToDel = $_POST['qheb_badge_del_id'];
	foreach ($badgeIdsToDel as $id) {
		QhebunelBadges::deleteBadge($id);
	}
}

//List badges in a group
if (isset($_GET['listgid']) && $_GET['listgid'] > 0) {
	$badgeListId = (int)$_GET['listgid'];
	$badgeListName = $wpdb->get_var(
		$wpdb->prepare(
			'select `name` from `qheb_badge_groups` where `bgid`=%d limit 1;',
			$badgeListId
		)
	);
	$badgeList = $wpdb->get_results(
		$wpdb->prepare(
			'select `b`.*, count(`l`.`uid`) as `users`
			from `qheb_badges` as `b`
			  left join `qheb_user_badge_links` as `l`
			    on (`l`.`bid`=`b`.`bid`)
			where `b`.`bgid`=%d
			group by `b`.`bid`
			order by `b`.`name`;',
			$badgeListId
		),
		ARRAY_A
	);
}

//Set badge for editing
if (isset($_GET['editbid']) && $_GET['editbid'] > 0) {
	$editBid = (int)$_GET['editbid'];
	foreach ($badgeList as $badge) {
		if ($badge['bid'] == $editBid) {
			$editBadge = $badge;
			break;
		}
	}
}

//Load badge groups
$bgroups = $wpdb->get_results('
	select `g`.`bgid`, `g`.`name`, `g`.`climit`, `g`.`hidden`, count(`b`.`bid`) as `bcount`
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
	<h2><?php _e('Badges'); ?> <a href="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;newgroup');?>" class="add-new-h2"><?php _e('Add new group'); ?></a></h2>
	<form id="qheb_grouplistform" name="qheb_grouplistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php');?>" method="post">
		<?php wp_nonce_field('qheb_badgegdel','qhebnonce'); ?>
		<table class="widefat fixed qheb_catlist">
			<thead>
				<tr>
					<th scope="col" class="qheb_catname"><?php _e('Group name'); ?></th>
					<th scope="col" class="qheb_catdesc"><?php _e('Badges (Limit)'); ?></th>
					<th scope="col" class="qheb_catact"><?php _e('Actions'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" class="qheb_catname"><?php _e('Group name'); ?></th>
					<th scope="col" class="qheb_catdesc"><?php _e('Badges (Limit)'); ?></th>
					<th scope="col" class="qheb_catstart"><?php _e('Actions'); ?></th>
				</tr>
			</tfoot>
			<tbody>
				<?php
				if (empty($bgroups)) {
					echo('<tr><td colspan="3">'.__('There are no groups in the database.').'</td></tr>');
				} else {
					foreach ($bgroups as $group) {
						echo('<tr><td'.($group['hidden'] ? ' class="qheb_bgroup_hidden"' : '').'><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$group['bgid']).'">'.$group['name'].'</a></td>');
						echo('<td>'.$group['bcount'].($group['climit'] > 0 ? ' ('.$group['climit'].')' : '').'</td>');
						echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;editgid='.$group['bgid']).'">'.__('Edit').'</a> <label><input type="checkbox" class="qheb_catdelcb" name="qheb_group_del_id[]" value="'.$group['bgid'].'" />'.__('Delete').'</label></td></tr>');
					}
				}
				?>
			</tbody>
		</table>
		<div class="tablenav bottom">
			<div class="alignright actions">
				<input class="action-secondary button" type="submit" name="qheb_badgeg_del" value="<?php _e('Delete'); ?>"/>
			</div>
			<br class="clear" />
		</div>
	</form>
	
	<?php if (isset($_GET['newgroup'])) { ?>
		<h3 class="title"><?php _e('Create new'); ?></h3>
		<form id="qheb_addcatform" name="qheb_addcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php');?>" method="post">
			<?php wp_nonce_field('qheb_badgeg_addnew','qhebnonce'); ?>
			<div id="poststuff" class="metabox-holder qheb_metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Create new group'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb_badgeg_name"><?php _e('Group name'); ?></label></th>
								<td><input type="text" name="qheb_badgeg_name" id="qheb_badgeg_name" /></td>
							</tr>
							<tr title="<?php _e('How many badges can be given/claimed from this group?','qhebunel'); ?>">
								<th scope="row"><label for="qheb_badgeg_limit"><?php _e('Claim limit'); ?></label></th>
								<td><input type="text" name="qheb_badgeg_limit" id="qheb_badgeg_limit" /></td>
							</tr>
							<tr title="<?php _e('Check this to make the entire group hidden from users. Awarded badges will still be visible.','qhebunel'); ?>">
								<th scope="row"><label for="qheb_badgeg_hidden"><?php _e('Hidden'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb_badgeg_hidden" id="qheb_badgeg_hidden" /></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php _e('Create'); ?>" name="qheb_badgeg_new" />
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
	
	<?php if (isset($qheb_edit_group)) { ?>
		<form id="qheb_addcatform" name="qheb_addcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php');?>" method="post">
			<?php wp_nonce_field('qheb_badgeg_edit','qhebnonce'); ?>
			<input type="hidden" name="qheb_group_id" value="<?=$qheb_edit_group['bgid'];?>" />
			<div id="poststuff" class="metabox-holder qheb_metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Edit group'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb_badgeg_name"><?php _e('Group name'); ?></label></th>
								<td><input type="text" name="qheb_badgeg_name" id="qheb_badgeg_name" value="<?=$qheb_edit_group['name'];?>" /></td>
							</tr>
							<tr title="<?php _e('How many badges can be given/claimed from this group?','qhebunel'); ?>">
								<th scope="row"><label for="qheb_badgeg_limit"><?php _e('Claim limit'); ?></label></th>
								<td><input type="text" name="qheb_badgeg_limit" id="qheb_badgeg_limit" value="<?=$qheb_edit_group['climit'];?>" /></td>
							</tr>
							<tr title="<?php _e('Check this to make the entire group hidden from users. Awarded badges will still be visible.','qhebunel'); ?>">
								<th scope="row"><label for="qheb_badgeg_hidden"><?php _e('Hidden'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb_badgeg_hidden" id="qheb_badgeg_hidden" <?=($qheb_edit_group['hidden'] ? ' checked="checked"' : '');?> /></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php _e('Save'); ?>" name="qheb_badgeg_edit" />
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
	
	<?php if (isset($badgeList)) { ?>
		<h3><?=$badgeListName;?></h3>
		<form id="qheb_badgelistform" name="qheb_badgelistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$badgeListId);?>" method="post">
			<?php wp_nonce_field('qheb_badgedel','qhebnonce'); ?>
			<input type="hidden" name="qheb_group_id" value="<?=$badgeListId;?>" />
			<table class="widefat fixed qheb_catlist">
				<thead>
					<tr>
						<th scope="col" class="qheb_bicon_large"><?php _e('Normal image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bicon_small"><?php _e('Small image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bname"><?php _e('Name', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bdesc"><?php _e('Description', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_busers" title="<?php _e('Number of users who have the badge.', 'qhebunel'); ?>"><?php _e('Users', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bact"><?php _e('Actions', 'qhebunel'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="qheb_bicon_large"><?php _e('Normal image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bicon_small"><?php _e('Small image', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bname"><?php _e('Name', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bdesc"><?php _e('Description', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_busers" title="<?php _e('Number of users who have the badge.', 'qhebunel'); ?>"><?php _e('Users', 'qhebunel'); ?></th>
						<th scope="col" class="qheb_bact"><?php _e('Actions', 'qhebunel'); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php
					if (empty($badgeList)) {
						echo('<tr><td colspan="6">'.__('There are no badges in this group.','qhebunel').'</td></tr>');
					} else {
						foreach ($badgeList as $badge) {
							echo('<tr><td><img src="'.WP_CONTENT_URL.'/'.$badge['largeimage'].'" alt=""/></td>');
							echo('<td>'.(empty($badge['smallimage']) ? __('Missing image', 'qhebunel') : '<img src="'.WP_CONTENT_URL.'/'.$badge['smallimage'].'" alt=""/>').'</td>');
							echo('<td>'.$badge['name'].'</td>');
							echo('<td>'.$badge['description'].'</td>');
							echo('<td>'.$badge['users'].'</td>');
							echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$badgeListId.'&amp;editbid='.$badge['bid']).'">'.__('Edit').'</a> <label><input type="checkbox" class="qheb_catdelcb" name="qheb_badge_del_id[]" value="'.$badge['bid'].'" />'.__('Delete').'</label></td></tr>');
						}
					}
					?>
				</tbody>
			</table>
			<div class="tablenav bottom">
				<div class="alignright actions">
					<input class="action-secondary button" type="submit" name="qheb_badge_del" value="<?php _e('Delete'); ?>"/>
				</div>
				<br class="clear" />
			</div>
		</form>
		
		<form id="qheb_addcatform" name="qheb_addbadgeform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$badgeListId);?>" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field('qheb_add_badge','qhebnonce'); ?>
			<?php echo(isset($editBadge) ? '<input type="hidden" name="qheb_badge_edit_id" value="'.$editBadge['bid'].'" />' : '');?>
			<div id="poststuff" class="metabox-holder qheb_metabox">
				<div class="stuffbox">
					<h3><span><?php if (isset($editBadge)) { _e('Edit badge','qhebunel'); } else { _e('Add new badge','qhebunel'); } ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr title="<?php printf(__('The image shown on the user\'s profile page. Allowed formats: %s.','qhebunel'), QHEBUNEL_BADGE_FORMATS); ?>">
								<th scope="row"><label for="qheb_badge_icon_large"><?php _e('Normal icon','qhebunel'); ?></label></th>
								<td><input type="file" name="qheb_badge_icon_large" id="qheb_badge_icon_large" /></td>
							</tr>
							<tr title="<?php printf(__('The image shown under the avatar next to forum posts. If not provided, the badge can only be viewed on the user\'s profile page. Allowed formats: %s.','qhebunel'), QHEBUNEL_BADGE_FORMATS); ?>">
								<th scope="row"><label for="qheb_badge_icon_small"><?php _e('Small icon','qhebunel'); ?></label></th>
								<td><input type="file" name="qheb_badge_icon_small" id="qheb_badge_icon_small" /></td>
							</tr>
							<tr title="<?php _e('Name of the badge.','qhebunel'); ?>">
								<th scope="row"><label for="qheb_badge_name"><?php _e('Name','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb_badge_name" id="qheb_badge_name" <?php echo(isset($editBadge) ? 'value="'.htmlspecialchars($editBadge['name']).'"' : ''); ?>/></td>
							</tr>
							<tr title="<?php _e('Description about the achivement the badge is awarded for.','qhebunel'); ?>">
								<th scope="row"><label for="qheb_badge_desc"><?php _e('Description','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb_badge_desc" id="qheb_badge_desc" <?php echo(isset($editBadge) ? 'value="'.htmlspecialchars($editBadge['description']).'"' : ''); ?>/></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php if (isset($editBadge)) { _e('Save','qhebunel'); } else { _e('Create','qhebunel'); } ?>" name="qheb_badge_new" />
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