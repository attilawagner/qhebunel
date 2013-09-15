<?php
if(!current_user_can('manage_options')) {
	die('Access Denied');
}

//Process new group request
if (isset($_POST['qheb_group_new']) && check_admin_referer('qheb_add_group', 'qhebnonce')) {
	$qheb_group_name = $_POST['qheb_group_name'];
	$qheb_group_prom = (isset($_POST['qheb_group_prom']) && $_POST['qheb_group_prom']=='true' ? 1 : 0);
	
	if (!empty($qheb_group_name)) {
		$wpdb->query(
			$wpdb->prepare(
				"insert into `qheb_user_groups` values (0, %s, %d);",
				$qheb_group_name,
				$qheb_group_prom
			)
		);
	}
}


//Preprocess edit request
if (isset($_GET['editid']) && $_GET['editid'] > 10) {
	$group = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_user_groups` where `gid`=%d limit 1;',
			$_GET['editid']
		),
		ARRAY_A
	);
	if (isset($group[0])) {
		$qheb_edit_group = $group[0];
	}
}

//Process edit request
if (isset($_POST['qheb_group_edit']) && check_admin_referer('qheb_edit_group', 'qhebnonce')) {
	$qheb_group_id = $_POST['qheb_group_id'];
	$qheb_group_name = $_POST['qheb_group_name'];
	$qheb_group_prom = (isset($_POST['qheb_group_prom']) && $_POST['qheb_group_prom']=='true' ? 1 : 0);
	
	if ($qheb_group_id > 10 && !empty($qheb_group_name)) {
		$wpdb->query(
			$wpdb->prepare(
				'update `qheb_user_groups` set `name`=%s, `prominent`=%d where `gid`=%d limit 1;',
				$qheb_group_name,
				$qheb_group_prom,
				$qheb_group_id
			)
		);
	}
}


//Process group deletion
if (isset($_POST['qheb_group_del']) && check_admin_referer('qheb_groupdel', 'qhebnonce') && isset($_POST['qheb_group_del_id'])) {
	$qheb_group_del_ids = $_POST['qheb_group_del_id'];
	$idlist = '';
	foreach ($qheb_group_del_ids as $id) {
		$id = (int)$id;
		if ($id > 10) {
			$idlist .= $id . ',';
		}
	}
	if (strlen($idlist) > 1) {
		$idlist = substr($idlist, 0, -1);
		$wpdb->query('delete from `qheb_user_groups` where `gid` in ('.$idlist.');');
		$wpdb->query('delete from `qheb_user_group_links` where `gid` in ('.$idlist.');');
		$wpdb->query('delete from `qheb_category_permissions` where `gid` in ('.$idlist.');');
	}
}


//Load groups
$groups = $wpdb->get_results('
	select `g`.`gid`, IF(`g`.`gid`<11, `g`.`gid`, 11) as `builtinorder`, `g`.`name`, `g`.`prominent`, count(`l`.`uid`) as `membercount`
	from `qheb_user_groups` as `g`
	  left join `qheb_user_group_links` as `l`
	    on (`l`.`gid`=`g`.`gid`)
	group by `g`.`gid`
	order by `builtinorder` asc, `g`.`name` asc;',
	ARRAY_A
);
?>
<div class="wrap">
	<div class="icon32 qhebunelicon"></div>
	<h2><?php _e('Groups','qhebunel'); ?></h2>
	<form id="qheb_grouplistform" name="qheb_grouplistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optgroups.php');?>" method="post">
		<?php wp_nonce_field('qheb_groupdel','qhebnonce'); ?>
		<table class="widefat fixed qheb_catlist">
			<thead>
				<tr>
					<th scope="col" class="qheb-catname"><?php _e('Group name','qhebunel'); ?></th>
					<th scope="col" class="qheb-catdesc"><?php _e('Members','qhebunel'); ?></th>
					<th scope="col" class="qheb_catact"><?php _e('Actions','qhebunel'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" class="qheb-catname"><?php _e('Group name','qhebunel'); ?></th>
					<th scope="col" class="qheb-catdesc"><?php _e('Members','qhebunel'); ?></th>
					<th scope="col" class="qheb_catstart"><?php _e('Actions','qhebunel'); ?></th>
				</tr>
			</tfoot>
			<tbody>
				<?php
				if (empty($groups)) {
					echo('<tr><td colspan="3">'.__('There are no groups in the database.','qhebunel').'</td></tr>');
				} else {
					foreach ($groups as $group) {
						echo('<tr><td'.($group['prominent'] ? ' class="prominent"' : '').'>'.$group['name'].'</td><td>'.$group['membercount'].'</td>');
						if ($group['gid'] > 10) {
							echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optgroups.php&amp;editid='.$group['gid']).'">'.__('Edit','qhebunel').'</a> <label><input type="checkbox" class="qheb-catdelcb" name="qheb_group_del_id[]" value="'.$group['gid'].'" />'.__('Delete','qhebunel').'</label></td></tr>');
						} else {
							//Built in special groups
							echo('<td>'.__('You cannot edit or delete this group.','qhebunel').'</td></tr>');
						}
					}
				}
				?>
			</tbody>
		</table>
		<div class="tablenav bottom">
			<div class="alignright actions">
				<input class="action-secondary button" type="submit" name="qheb_group_del" value="<?php _e('Delete','qhebunel'); ?>"/>
			</div>
			<br class="clear" />
		</div>
	</form>
	
	<?php if (isset($qheb_edit_group)) { ?>
		<form id="qheb_editgroupform" name="qheb_editgroupform" action="<?=admin_url('admin.php?page=qhebunel/admin/optgroups.php');?>" method="post">
			<?php wp_nonce_field('qheb_edit_group','qhebnonce'); ?>
			<input type="hidden" name="qheb_group_id" value="<?=$qheb_edit_group['gid'];?>" />
			<div id="poststuff" class="metabox-holder qheb-metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Edit group','qhebunel'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb_group_name"><?php _e('Group name','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb_group_name" id="qheb_group_name" value="<?=$qheb_edit_group['name'];?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb_group_prom"><?php _e('Prominent','qhebunel'); ?></label></th>
								<td><input type="checkbox" value="true" name="qheb_group_prom" id="qheb_group_prom" <?=($qheb_edit_group['prominent'] ? ' checked="checked"' : '');?>/></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php _e('Save','qhebunel'); ?>" name="qheb_group_edit" />
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
	
	
	<h3 class="title"><?php _e('Create new','qhebunel'); ?></h3>
	<form id="qheb_addcatform" name="qheb_addcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optgroups.php');?>" method="post">
		<?php wp_nonce_field('qheb_add_group','qhebnonce'); ?>
		<div id="poststuff" class="metabox-holder qheb-metabox">
			<div class="stuffbox">
				<h3><span><?php _e('Create new group','qhebunel'); ?></span></h3>
				<div class="inside">
					<table class="editform">
						<tr>
							<th scope="row"><label for="qheb_group_name"><?php _e('Group name','qhebunel'); ?></label></th>
							<td><input type="text" name="qheb_group_name" id="qheb_group_name" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="qheb_group_prom"><?php _e('Prominent','qhebunel'); ?></label></th>
							<td><input type="checkbox" value="true" name="qheb_group_prom" id="qheb_group_prom" /></td>
						</tr>
					</table>
					<div id="submitlink" class="submitbox">
						<div id="major-publishing-actions">
							<div id="publishing-action">
								<input type="submit" id="publish" class="button-primary" value="<?php _e('Create','qhebunel'); ?>" name="qheb_group_new" />
							</div>
							<div class="clear"></div>
						</div>
						<div class="clear"></div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>