<?php
/**
 * Qhebunel
 * Edit post page
 * 
 * Post ID is in the global $section_param variable.
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params, $post, $post_id;
$post_id = $section_params;

/*
 * Load post
 */
$post = $wpdb->get_row(
	$wpdb->prepare(
		'select `p`.`uid`, `p`.`text`, `t`.`catid`, `t`.`closedate`
		from `qheb_posts` as `p`
		  left join `qheb_threads` as `t`
		    on (`t`.`tid`=`p`.`tid`)
		where `pid`=%d',
		$post_id
	),
	ARRAY_A
);
if (empty($post)) {
	echo('<div class="qheb-error-message">'.__('The post does not exists in the database.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}

/*
 * Check permissions
 *  - No post can be edited in a closed thread.
 *  - Everyone can edit only their own posts.
 *  - Moderators can edit other users' posts.
 */
if ($post['closedate'] != null) {
	echo('<div class="qheb-error-message">'.__('You cannot edit a post in a closed thread.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}
if ($post['uid'] != $current_user->ID && !QhebunelUser::is_moderator()) {
	echo('<div class="qheb-error-message">'.__('You can only edit your own posts.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}

function render_old_attachment_list() {
	global $wpdb, $post_id;
	/*
	 * Load attachments
	 */
	$attachments = $wpdb->get_results(
		$wpdb->prepare(
			'select * from `qheb_attachments` where `pid`=%d order by `name`;',
			$post_id
		),
		ARRAY_A
	);
	
	if (!empty($attachments)) {
		echo('<ul class="old-files">');
		foreach ($attachments as $att) {
			echo('<li><label title="'.__('Deselect to delete the file.', 'qhebunel').'"><input type="checkbox" name="keep-file['.$att['aid'].']" value="true" checked="checked" />'.$att['name'].'</label></li>');
		}
		echo('</ul>');
	}
}

?>
<form id="edit-post-form" onsubmit="return qheb_validateNewThreadForm();" action="<?=site_url('forum/');?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="editpost" />
<input type="hidden" name="post-id" value="<?=$post_id?>" />
<input type="hidden" name="MAX_FILE_SIZE" value="<?=QHEBUNEL_ATTACHMENT_MAX_SIZE?>" />
<table class="qheb-post-table">
<tfoot>
	<tr>
		<th colspan="2"><input type="submit" name="save" value="<?php _e('Save','qhebunel'); ?>" /></th>
	</tr>
</tfoot>
<tbody>
	<tr>
		<th><?php _e('Message','qhebunel'); ?></th>
		<td><textarea name="post-message"><?=htmlentities2($post['text'])?></textarea></td>
	</tr>
	<tr>
		<th><?php _e('Reason','qhebunel'); ?></th>
		<td><input type="text" name="edit-reason" /></td>
	</tr>
	<?php if (QhebunelUser::has_persmission_to_upload()) { ?>
	<tr>
		<th><?php _e('Attachments','qhebunel'); ?></th>
		<td>
			<?php render_old_attachment_list();?>
			<div class="file"><input type="file" name="attachments[]" class="attachment" /><input type="button" value="Remove" class="remove" /></div>
		</td>
	</tr>
	<?php } ?>
</tbody>
</table>
</form>
