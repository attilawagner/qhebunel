<?php
if(!current_user_can('manage_options')) {
	die('Access Denied');
}

//Process new category request
if (isset($_POST['qheb-cat-new']) && check_admin_referer('qheb_add_category', 'qhebnonce')) {
	$qheb_cat_name = $_POST['qheb-cat-name'];
	$qheb_cat_desc = $_POST['qheb-cat-desc'];
	$qheb_cat_parent = $_POST['qheb-cat-parent'];
	$qheb_cat_order = $_POST['qheb-cat-order'];
	
	if ($qheb_cat_parent >= 0 && !empty($qheb_cat_name)) {
		$qheb_cat_uri = Qhebunel::get_uri_component_for_title($qheb_cat_name);
		if ($qheb_cat_order == 'top') {
			$wpdb->query(
				$wpdb->prepare(
					"update `qheb_categories` set `orderid`=`orderid`+1 where `parent`=%d;",
					$qheb_cat_parent
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"insert into `qheb_categories` values (0, %s, %s, %d, 1, %s);",
					$qheb_cat_name,
					$qheb_cat_desc,
					$qheb_cat_parent,
					$qheb_cat_uri
				)
			);
		} else {
			$max = $wpdb->get_results(
				$wpdb->prepare(
					"select max(`orderid`) from `qheb_categories` where `parent`=%d;",
					$qheb_cat_parent
				),
				ARRAY_N
			);
			$wpdb->query(
				$wpdb->prepare(
					"insert into `qheb_categories` values (0, %s, %s, %d, %d, %s);",
					$qheb_cat_name,
					$qheb_cat_desc,
					$qheb_cat_parent,
					$max[0][0]+1,
					$qheb_cat_uri
				)
			);
		}
	}
}

//Process reordering request
if (isset($_POST['qheb-catlist-catid']) && check_admin_referer('qheb_catorder', 'qhebnonce')) {
	$qheb_catid = $_POST['qheb-catlist-catid'];
	$qheb_catdir = $_POST['qheb-catlist-direction'];
	if ($qheb_catid > 0) {
		$cat = $wpdb->get_results(
			$wpdb->prepare(
				'select `parent`,`orderid` from `qheb_categories` where `catid`=%d limit 1;',
				$qheb_catid
			),
			ARRAY_A
		);
		$cat = $cat[0];
		if ($qheb_catdir == 'up') {
			//Switch position with the one above
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_categories` set `orderid`=`orderid`+1 where `orderid`=%d and `parent`=%d limit 1;',
					$cat['orderid']-1,
					$cat['parent']
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_categories` set `orderid`=`orderid`-1 where `catid`=%d limit 1;',
					$qheb_catid
				)
			);
		} else {
			//Switch position with the one below
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_categories` set `orderid`=`orderid`-1 where `orderid`=%d and `parent`=%d limit 1;',
					$cat['orderid']+1,
					$cat['parent']
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_categories` set `orderid`=`orderid`+1 where `catid`=%d limit 1;',
					$qheb_catid
				)
			);
		}
	}
}

//Preprocess edit request
if (isset($_GET['editid'])) {
	$cat = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_categories` where `catid`=%d limit 1;',
			$_GET['editid']
		),
		ARRAY_A
	);
	if (isset($cat[0])) {
		$qheb_edit_cat = $cat[0];
	}
}

//Process edit request
if (isset($_POST['qheb-cat-edit']) && check_admin_referer('qheb_edit_category', 'qhebnonce')) {
	$qheb_cat_id = $_POST['qheb-cat-id'];
	$qheb_cat_name = $_POST['qheb-cat-name'];
	$qheb_cat_desc = $_POST['qheb-cat-desc'];
	$qheb_cat_parent = $_POST['qheb-cat-parent'];
	
	$cat = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_categories` where `catid`=%d limit 1;',
			$qheb_cat_id
		),
		ARRAY_A
	);
	if (isset($cat[0])) {
		$cat = $cat[0];
		$qheb_cat_uri = Qhebunel::get_uri_component_for_title($qheb_cat_name);
		
		if ($qheb_cat_parent != $cat['parent']) {
			$max = $wpdb->get_results(
				$wpdb->prepare(
					"select max(`orderid`) from `qheb_categories` where `parent`=%d;",
					$qheb_cat_parent
				),
				ARRAY_N
			);
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_categories` set `name`=%s, `uri`=%s, `description`=%s, `orderid`=%d, `parent`=%d where `catid`=%d limit 1;',
					$qheb_cat_name,
					$qheb_cat_uri,
					$qheb_cat_desc,
					$max[0][0]+1,
					$qheb_cat_parent,
					$qheb_cat_id
				)
			);
			
		} else {
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_categories` set `name`=%s, `uri`=%s, `description`=%s where `catid`=%d limit 1;',
					$qheb_cat_name,
					$qheb_cat_uri,
					$qheb_cat_desc,
					$qheb_cat_id
				)
			);
		}
	}
}

//Process category deletion
if (isset($_POST['qheb-cat-del']) && check_admin_referer('qheb_catdel', 'qhebnonce') && isset($_POST['qheb-cat-del-id'])) {
	$qheb_cat_del_ids = $_POST['qheb-cat-del-id'];
	$idlist = '';
	foreach ($qheb_cat_del_ids as $id) {
		$id = (int)$id;
		if ($id > 0) {
			$idlist .= $id . ',';
		}
	}
	if (strlen($idlist) > 1) {
		$idlist = substr($idlist, 0, -1);
		$wpdb->query('delete from `qheb_categories` where `catid` in ('.$idlist.');');
		$wpdb->query('delete from `qheb_category_permissions` where `catid` in ('.$idlist.');');
	}
}


//Preprocess set permissions request
if (isset($_GET['setperm'])) {
	$cat_perms_catid = $_GET['setperm'];
	
	$cat_perms_catname_ = $wpdb->get_results(
		$wpdb->prepare(
			'select `name` from `qheb_categories` where `catid`=%d limit 1;',
			$cat_perms_catid
		),
		ARRAY_N
	);
	if (isset($cat_perms_catname_[0])) {
		$cat_perms_catname = $cat_perms_catname_[0][0];
		$cat_perms = $wpdb->get_results(
			$wpdb->prepare('
				select `g`.`name` as `gname`, `g`.`prominent`, `g`.`gid`, `p`.`access`, IF(`g`.`gid`<11, `g`.`gid`, 11) as `builtinorder`
				from `qheb_user_groups` as `g`
				  left join (
				    select `gid`, `access`
				    from `qheb_category_permissions`
				    where `catid`=%d
				  ) as `p`
				    on (`g`.`gid`=`p`.`gid`)
				order by `builtinorder` asc, `g`.`name` asc;',
				$cat_perms_catid
			),
			ARRAY_A
		);
	}
}


//Process set permissions request
if (isset($_POST['qheb-cat-perms']) && check_admin_referer('qheb_set_perms', 'qhebnonce')) {
	$qheb_catperm = $_POST['qheb-catperm'];
	$catid = (int)$_POST['qheb-cat-id'];
	if ($catid > 0 && is_array($qheb_catperm)) {
		foreach ($qheb_catperm as $gid => $acc) {
			$gid = (int)$gid;
			if ($gid <= 0) {
				continue;
			}
			$acc = (int)$acc;
			if ($acc < 0 || $acc > 3) {
				$acc = 0;
			}
			$wpdb->query("insert into `qheb_category_permissions` values ($catid, $gid, $acc) on duplicate key update `access`=$acc;");
		}
	}
}


//Load categories
$categories = $wpdb->get_results('select * from `qheb_categories` order by `parent` asc, `orderid` asc;', ARRAY_A);

function qheb_cat_parent_select($forcat = -1, $selected = -1) {
	global $categories;
	echo('<select name="qheb-cat-parent" id="qheb-cat-parent">');
	echo('<option value="0">'.__('Top level','qhebunel').'</option>');
	foreach ($categories as $cat) {
		if ($cat['parent'] == 0 && $cat['catid'] != $forcat) {
			echo('<option value="'.$cat['catid'].'"'.($cat['catid'] == $selected ? ' selected="selected"' : '').'>'.$cat['name'].'</option>');
		}
	}
	echo('</select>');
}
?>
<div class="wrap">
	<div class="icon32 qhebunelicon"></div>
	<h2><?php _e('Categories','qhebunel'); ?></h2>
	<form id="qheb-catorderform" name="qheb-catorderform" action="<?=admin_url('admin.php?page=qhebunel/admin/optcats.php');?>" method="post">
		<?php wp_nonce_field('qheb_catorder','qhebnonce'); ?>
		<input type="hidden" name="qheb-catlist-catid" id="qheb-catlist-catid" />
		<input type="hidden" name="qheb-catlist-direction" id="qheb-catlist-direction" />
	</form>
	<form id="qheb_catlistform" name="qheb_catlistform" action="<?=admin_url('admin.php?page=qhebunel/admin/optcats.php');?>" method="post">
		<?php wp_nonce_field('qheb_catdel','qhebnonce'); ?>
		<table class="widefat fixed qheb_catlist">
			<thead>
				<tr>
					<th scope="col" class="qheb-catname"><?php _e('Category name','qhebunel'); ?></th>
					<th scope="col" class="qheb-catdesc"><?php _e('Description','qhebunel'); ?></th>
					<th scope="col" class="qheb-catpos"><?php _e('Position','qhebunel'); ?></th>
					<th scope="col" class="qheb_catact"><?php _e('Actions','qhebunel'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col"><?php _e('Category name','qhebunel'); ?></th>
					<th scope="col"><?php _e('Description','qhebunel'); ?></th>
					<th scope="col"><?php _e('Position','qhebunel'); ?></th>
					<th scope="col"><?php _e('Actions','qhebunel'); ?></th>
				</tr>
			</tfoot>
			<tbody>
				<?php
				if (empty($categories)) {
					echo('<tr><td colspan="4">'.__('The category list is empty','qhebunel').'</td></tr>');
				} else {
					//Count subcategories
					$catsubcount = array();
					$catsubtotal = array();
					foreach ($categories as $cat) {
						if (array_key_exists($cat['parent'], $catsubtotal)) {
							$catsubtotal[$cat['parent']]++;
						} else {
							$catsubtotal[$cat['parent']] = 1;
						}
					}
					
					function qheb_display_catlistitem($cat) {
						global $catsubcount, $catsubtotal;
						//count already echoed subcategories
						if (array_key_exists($cat['parent'], $catsubcount)) {
							$catsubcount[$cat['parent']]++;
						} else {
							$catsubcount[$cat['parent']] = 1;
						}
						
						echo('<tr><td><div class="'.($cat['parent']==0 ? 'qheb_catlist_topitem' : 'qheb-catlist-subitem').'">'.$cat['name'].'</div></td>');
						echo('<td>'.$cat['description'].'</td>');
						
						if ($catsubtotal[$cat['parent']] > 1) {//at least 2 categories
							if ($catsubcount[$cat['parent']] > 1) {//this is not the first
								$updown = '<span class="qheb-catlist-subitempos-up"><a href="#" onclick="qhebCatlistUp('.$cat['catid'].')">↑</a></span>';
							} else {
								$updown = '<span class="qheb-catlist-subitempos-up">&nbsp;</span>';//placeholder
							}
							if ($catsubcount[$cat['parent']] < $catsubtotal[$cat['parent']]) {//this is not the last
								$updown .= '<span class="qheb-catlist-subitempos-down"><a href="#" onclick="qhebCatlistDown('.$cat['catid'].')">↓</a></span>';
							} else {
								$updown .= '<span class="qheb-catlist-subitempos-down">&nbsp;</span>';//placeholder
							}
						} else {
							$updown = '';
						}
						
						echo('<td'.($cat['parent']==0 ? '' : ' class="qheb-catlist-subitempos"').'>'.$cat['orderid'].$updown.'</td>');
						echo('<td><a href="'.admin_url('admin.php?page=qhebunel/admin/optcats.php&amp;editid='.$cat['catid']).'">'.__('Edit','qhebunel').'</a> <a href="'.admin_url('admin.php?page=qhebunel/admin/optcats.php&amp;setperm='.$cat['catid']).'">'.__('Set permissions','qhebunel').'</a> <label><input type="checkbox" class="qheb-catdelcb" name="qheb-cat-del-id[]" value="'.$cat['catid'].'" />'.__('Delete','qhebunel').'</label></td></tr>');
					}
					
					foreach ($categories as $cat) {
						if ($cat['parent'] == 0) {
							qheb_display_catlistitem($cat);
							foreach ($categories as $cat2) {
								if ($cat2['parent'] == $cat['catid']) {
									qheb_display_catlistitem($cat2);
								}
							}
						}
					}
				}
				?>
			</tbody>
		</table>
		<div class="tablenav bottom">
			<div class="alignright actions">
				<input class="action-secondary button" type="submit" name="qheb-cat-del" value="<?php _e('Delete','qhebunel'); ?>"/>
			</div>
			<br class="clear" />
		</div>
	</form>
	
	<?php if (isset($qheb_edit_cat)) { ?>
		<form id="qheb_editcatform" name="qheb_editcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optcats.php');?>" method="post">
			<?php wp_nonce_field('qheb_edit_category','qhebnonce'); ?>
			<input type="hidden" name="qheb-cat-id" value="<?=$qheb_edit_cat['catid'];?>" />
			<div id="poststuff" class="metabox-holder qheb-metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Edit category','qhebunel'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb-cat-name"><?php _e('Category name','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-cat-name" id="qheb-cat-name" value="<?=$qheb_edit_cat['name'];?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb-cat-desc"><?php _e('Description','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-cat-desc" id="qheb-cat-desc" value="<?=$qheb_edit_cat['description'];?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb-cat-parent"><?php _e('Parent','qhebunel'); ?></label></th>
								<td><?php qheb_cat_parent_select($qheb_edit_cat['catid'], $qheb_edit_cat['parent']); ?></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php _e('Save','qhebunel'); ?>" name="qheb-cat-edit" />
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
	
	<?php if (!isset($qheb_edit_cat) && !isset($cat_perms)) { ?>
		<h3 class="title"><?php _e('Create new','qhebunel'); ?></h3>
		<form id="qheb_addcatform" name="qheb_addcatform" action="<?=admin_url('admin.php?page=qhebunel/admin/optcats.php');?>" method="post">
			<?php wp_nonce_field('qheb_add_category','qhebnonce'); ?>
			<div id="poststuff" class="metabox-holder qheb-metabox">
				<div class="stuffbox">
					<h3><span><?php _e('Create new category','qhebunel'); ?></span></h3>
					<div class="inside">
						<table class="editform">
							<tr>
								<th scope="row"><label for="qheb-cat-name"><?php _e('Category name','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-cat-name" id="qheb-cat-name" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb-cat-desc"><?php _e('Description','qhebunel'); ?></label></th>
								<td><input type="text" name="qheb-cat-desc" id="qheb-cat-desc" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb-cat-parent"><?php _e('Parent','qhebunel'); ?></label></th>
								<td><?php qheb_cat_parent_select(); ?></td>
							</tr>
							<tr>
								<th scope="row"><label for="qheb-cat-order"><?php _e('Position','qhebunel'); ?></label></th>
								<td><select name="qheb-cat-order" id="qheb-cat-order"><option value="top"><?php _e('Top','qhebunel'); ?></option><option value="bottom" selected="selected"><?php _e('Bottom','qhebunel'); ?></option></select></td>
							</tr>
						</table>
						<div id="submitlink" class="submitbox">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<input type="submit" id="publish" class="button-primary" value="<?php _e('Create','qhebunel'); ?>" name="qheb-cat-new" />
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
	
	<?php if (isset($cat_perms)) {?>
		<h3 class="title"><?php _e('Set category permissions','qhebunel'); ?></h3>
		<form id="qheb-catpermsform" name="qheb-catpermsform" action="<?=admin_url('admin.php?page=qhebunel/admin/optcats.php');?>" method="post">
			<?php wp_nonce_field('qheb_set_perms','qhebnonce'); ?>
			<input type="hidden" name="qheb-cat-id" value="<?=$cat_perms_catid;?>" />
			<table class="widefat fixed qheb_catpermslist">
				<thead>
					<tr>
						<th scope="col" class="qheb-cpgroup"><?php _e('Group','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpnone"><?php _e('None','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpread"><?php _e('Read','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpwrite"><?php _e('Write','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpstart"><?php _e('Start','qhebunel'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th scope="col" class="qheb-cpgroup"><?php _e('Group','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpnone"><?php _e('None','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpread"><?php _e('Read','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpwrite"><?php _e('Write','qhebunel'); ?></th>
						<th scope="col" class="qheb-cpstart"><?php _e('Start','qhebunel'); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php
					if (empty($cat_perms)) {
						echo('<tr><td colspan="4">'.__('There are no groups in the database.','qhebunel').'</td></tr>');
					} else {
						foreach ($cat_perms as $catp) {
							echo('<tr><td'.($catp['prominent'] ? ' class="prominent"' : '').'>'.$catp['gname'].'</td>');
							echo('<td><input type="radio" id="qheb-catperm-'.$catp['gid'].'-0" name="qheb-catperm['.$catp['gid'].']" value="0"'.($catp['access'] == 0 ? ' checked="checked"' : '').' /></td>');
							echo('<td><input type="radio" id="qheb-catperm-'.$catp['gid'].'-1" name="qheb-catperm['.$catp['gid'].']" value="1"'.($catp['access'] == 1 ? ' checked="checked"' : '').' /></td>');
							echo('<td><input type="radio" id="qheb-catperm-'.$catp['gid'].'-2" name="qheb-catperm['.$catp['gid'].']" value="2"'.($catp['access'] == 2 ? ' checked="checked"' : '').' /></td>');
							echo('<td><input type="radio" id="qheb-catperm-'.$catp['gid'].'-3" name="qheb-catperm['.$catp['gid'].']" value="3"'.($catp['access'] == 3 ? ' checked="checked"' : '').' /></td>');
							echo('</tr>');
						}
					}
					?>
				</tbody>
			</table>
			<div id="submitlink" class="submitbox">
				<div id="major-publishing-actions">
					<div id="publishing-action">
						<input type="submit" id="publish" class="button-primary" value="<?php _e('Save','qhebunel'); ?>" name="qheb-cat-perms" />
					</div>
					<div class="clear"></div>
				</div>
				<div class="clear"></div>
			</div>
		</form>
	<?php } ?>
</div>