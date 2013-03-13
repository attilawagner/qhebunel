<?php
/**
 * Qhebunel
 * Thread deletion confirmation page
 * 
 * Thread ID is in the global $section_param variable.
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;
$thread_id = $section_params;

/*
 * Check for moderator permissions
 */
if (QhebunelUser::is_moderator() == false) {
	echo('<div class="qheb-error-message">'.__('You must be a moderator to delete a thread.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}

/*
 * Load thread
 */
$thread = $wpdb->get_row(
	$wpdb->prepare(
		'select `t`.*, `u1`.`display_name` as `startername`, `u2`.`display_name` as `closername`
		from `qheb_threads` as `t`
		  left join `qheb_wp_users` as `u1`
		    on (`t`.`starter`=`u1`.`ID`)
		  left join `qheb_wp_users` as `u2`
		    on (`t`.`closer`=`u2`.`ID`)
		where `t`.`tid`=%d',
		$thread_id
	),
	ARRAY_A
);
if (empty($thread)) {
	echo('<div class="qheb-error-message">'.__('The thread does not exist in the database.', 'qhebunel').'</div>');
	return; //Stop processing this file, render footer
}

?>
<form id="delete-thread-form" action="<?=site_url('forum/');?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="threaddelete" />
<input type="hidden" name="thread-id" value="<?=$thread_id?>" />
<table class="qheb-post-table">
<tfoot>
	<tr>
		<th colspan="2"><input type="submit" name="save" value="<?php _e('Delete thread','qhebunel'); ?>" /></th>
	</tr>
</tfoot>
<tbody>
	<tr>
		<th><?php _e('Title','qhebunel'); ?></th>
		<td><?=htmlentities2($thread['title'])?></td>
	</tr>
	<tr>
		<th><?php _e('Starter','qhebunel'); ?></th>
		<td><?php /* translators: first parameter is username, second is the date */
			printf(__('%1$s on %2$s', 'qhebunel'), $thread['startername'], '<time class="post_date" datetime="'.QhebunelDate::get_datetime_attribute($thread['startdate']).'" title="'.QhebunelDate::get_relative_date($thread['startdate']).'">'.QhebunelDate::get_post_date($thread['startdate']).'</time>');?></td>
	</tr>
	<tr>
		<th><?php _e('Posts','qhebunel'); ?></th>
		<td><?=$thread['postcount']?></td>
	</tr>
	<tr>
		<th><?php _e('Closer','qhebunel'); ?></th>
		<td><?php
			if (empty($thread['closer'])) {
				_e('This is an open thread.','qhebunel');
			} else {
				/* translators: first parameter is username, second is the date */
				printf(__('%1$s on %2$s', 'qhebunel'), $thread['closername'], '<time class="post_date" datetime="'.QhebunelDate::get_datetime_attribute($thread['closedate']).'" title="'.QhebunelDate::get_relative_date($thread['closedate']).'">'.QhebunelDate::get_post_date($thread['closedate']).'</time>');
			}
			?></td>
	</tr>
	<tr>
		<th><?php _e('Pinned','qhebunel'); ?></th>
		<td><?php
			if ($thread['pinned']) {
				_e('This is a pinned thread.','qhebunel');
			} else {
				_e('This thread is not pinned.','qhebunel');
			}
			?></td>
	</tr>
</tbody>
</table>
</form>
