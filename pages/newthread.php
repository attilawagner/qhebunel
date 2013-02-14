<?php
/**
 * Qhebunel
 * New thread page
 * 
 * Called from a category page (thread list),
 * parent category ID is provided in the
 * global $cat_id variable.
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

/**
 * Checks whether the current user can start
 * a new thread in the current category.
 * @return boolean True if the form can be displayed.
 */
function has_permissions() {
	global $cat_id;
	return QhebunelUser::get_permissions_for_category($cat_id) >= QHEBUNEL_PERMISSION_START;
}

/**
 * Renders a &lt;select&gt; tag with the list
 * of categories.
 */
function category_list() {
	global $wpdb, $cat_id;
	$groups = QhebunelUser::get_groups();
	$categories = $wpdb->get_results(
		'select distinct `c`.`catid`, `c`.`parent`, `c`.`name`
		from `qheb_categories` as `c` left join `qheb_category_permissions` as `cp`
		on (`c`.`catid`=`cp`.`catid`)
		where `cp`.`gid` in ('.implode(',',$groups).')
		and `cp`.`access`>=3
		order by `c`.`orderid` asc;',
		ARRAY_A
	);
	echo('<select name="topic_category">');
	foreach ($categories as $cat1) {
		if ($cat1['parent'] == 0) {
			echo('<optgroup label="'.$cat1['name'].'">');
			foreach ($categories as $cat2) {
				if ($cat2['parent'] == $cat1['catid']) {
					echo('<option value="'.$cat2['catid'].'"'.($cat2['catid'] == $cat_id ? ' selected="selected"' : '').'>'.$cat2['name'].'</option>');
				}
			}
			echo('</optgroup>');
		}
	}
	echo('</select>');
}

if (!has_permissions()) {
	echo('<div class="qheb_error_message">'.__('You do not have permissions to start a new thread in this category.', 'qhebunel').'</div>');
} else {
?>

<form id="new_thread_form" onsubmit="return qheb_validateNewThreadForm();" action="<?=site_url('forum/');?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="newthread" />
<input type="hidden" name="MAX_FILE_SIZE" value="<?=QHEBUNEL_ATTACHMENT_MAX_SIZE?>" />
<table class="qheb_post_table qheb_new_thread">
<tfoot>
	<tr>
		<th colspan="2"><input type="submit" name="new_thread" value="<?php _e('Start thread','qhebunel'); ?>" /></th>
	</tr>
</tfoot>
<tbody>
	<tr>
		<th><?php _e('Category','qhebunel'); ?></th>
		<td><?php category_list(); ?></td>
	</tr>
	<tr>
		<th><?php _e('Topic title','qhebunel'); ?></th>
		<td><input type="text" name="topic_title" /></td>
	</tr>
	<tr>
		<th><?php _e('Message','qhebunel'); ?></th>
		<td><textarea name="topic_message"></textarea></td>
	</tr>
	<?php if (QhebunelUser::has_persmission_to_upload()) { ?>
	<tr>
		<th><?php _e('Attachments','qhebunel'); ?></th>
		<td><div class="file"><input type="file" name="attachments[]" class="attachment" /><input type="button" value="Remove" class="remove" /></div></td>
	</tr>
	<?php } ?>
</tbody>
</table>
</form>

<?php } ?>