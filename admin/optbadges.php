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
	$idlist = '';
	foreach ($qheb_group_del_ids as $id) {
		$id = (int)$id;
		if ($id > 0) {
			$idlist .= $id . ',';
		}
	}
	if (strlen($idlist) > 1) {
		$idlist = substr($idlist, 0, -1);
		$wpdb->query('delete from `qheb_badge_groups` where `bgid` in ('.$idlist.');');
	}

}

//List badges in group
if (isset($_GET['listgid']) && $_GET['listgid'] > 0) {
	$badge_list_id = (int)$_GET['listgid'];
	$badge_list_name = $badge_list = $wpdb->get_results(
		$wpdb->prepare(
			'select `name` from `qheb_badge_groups` where `bgid`=%d limit 1;',
			$badge_list_id
		),
		ARRAY_N
	);
	$badge_list_name = $badge_list_name[0][0];
	$badge_list = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_badges` where `bgid`=%d order by `name`;',
			$badge_list_id
		),
		ARRAY_A
	);
}

//Load badge groups
$bgroups = $wpdb->get_results('select `g`.`bgid`, `g`.`name`, `g`.`climit`, `g`.`hidden`, count(`b`.`bid`) as `bcount` from `qheb_badge_groups` as `g` left join `qheb_badges` as `b` on (`b`.`bgid`=`g`.`bgid`) group by `g`.`bgid` order by `g`.`name` asc;', ARRAY_A);
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
							<tr>
								<th scope="row"><label for="qheb_badgeg_limit"><?php _e('Claim limit'); ?></label></th>
								<td><input type="text" name="qheb_badgeg_limit" id="qheb_badgeg_limit" /></td>
							</tr>
							<tr>
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
							<tr>
								<th scope="row"><label for="qheb_badgeg_limit"><?php _e('Claim limit'); ?></label></th>
								<td><input type="text" name="qheb_badgeg_limit" id="qheb_badgeg_limit" value="<?=$qheb_edit_group['climit'];?>" /></td>
							</tr>
							<tr>
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
	
	<?php if (isset($badge_list)) { ?>
		<h3><?=$badge_list_name;?></h3>
		<form id="qheb_badgelistform" name="qheb_badgelistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optbadges.php');?>" method="post">
			<?php wp_nonce_field('qheb_badgegdel','qhebnonce'); ?>
			<input type="hidden" name="qheb_group_id" value="<?=$badge_list_id;?>" />
			<table class="widefat fixed qheb_catlist">
				<thead>
					<tr>
						<th scope="col" class="qheb_bicon"><?php _e('Image'); ?></th>
						<th scope="col" class="qheb_bname"><?php _e('Name'); ?></th>
						<th scope="col" class="qheb_bdesc"><?php _e('Description'); ?></th>
						<th scope="col" class="qheb_bclaim"><?php _e('Claimable'); ?></th>
						<th scope="col" class="qheb_bshow"><?php _e('Show in forum'); ?></th>
						<th scope="col" class="qheb_bact"><?php _e('Actions'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="qheb_bicon"><?php _e('Image'); ?></th>
						<th scope="col" class="qheb_bname"><?php _e('Name'); ?></th>
						<th scope="col" class="qheb_bdesc"><?php _e('Description'); ?></th>
						<th scope="col" class="qheb_bclaim"><?php _e('Claimable'); ?></th>
						<th scope="col" class="qheb_bshow"><?php _e('Show in forum'); ?></th>
						<th scope="col" class="qheb_bact"><?php _e('Actions'); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php
					/*if (empty($bgroups)) {
						echo('<tr><td colspan="3">'.__('There are no groups in the database.').'</td></tr>');
					} else {
						foreach ($bgroups as $group) {
							echo('<tr><td'.($group['hidden'] ? ' class="qheb_bgroup_hidden"' : '').'><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;listgid='.$group['bgid']).'">'.$group['name'].'</a></td>');
							echo('<td>'.$group['bcount'].($group['climit'] > 0 ? ' ('.$group['climit'].')' : '').'</td>');
							echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optbadges.php&amp;editgid='.$group['bgid']).'">'.__('Edit').'</a> <label><input type="checkbox" class="qheb_catdelcb" name="qheb_group_del_id[]" value="'.$group['bgid'].'" />'.__('Delete').'</label></td></tr>');
						}
					}*/
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
		
		<form id="qheb_addcatform" name="qheb_addcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optcats.php');?>" method="post">
			<?php wp_nonce_field('qheb_add_category','qhebnonce'); ?>
			<div id="poststuff" class="metabox-holder qheb_metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Add new badge'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb_cat_name"><?php _e('Icon'); ?></label></th>
								<td>
									<label for="upload_image">
										<input id="upload_image" type="text" size="36" name="upload_image" value="" />
										<input id="upload_image_button" type="button" value="Upload Image" />
										<br />Enter an URL or upload an image for the banner.
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb_cat_name"><?php _e('Name'); ?></label></th>
								<td><input type="text" name="qheb_cat_name" id="qheb_cat_name" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb_cat_desc"><?php _e('Description'); ?></label></th>
								<td><input type="text" name="qheb_cat_desc" id="qheb_cat_desc" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb_cat_parent"><?php _e('Display'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb_group_prom" id="qheb_group_prom" <?=($qheb_edit_group['prominent'] ? ' checked="checked"' : '');?>/></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb_cat_order"><?php _e('Claimable'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb_group_prom" id="qheb_group_prom" <?=($qheb_edit_group['prominent'] ? ' checked="checked"' : '');?>/></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php _e('Create'); ?>" name="qheb_cat_new" />
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