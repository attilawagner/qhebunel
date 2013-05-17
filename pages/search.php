<?php
/**
 * Qhebunel
 * Search form and result listing page
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Show message to users who aren't logged in
if ($current_user->ID == 0) {
	echo('<div class="qheb-error-message">'.__('You must log in to use the search function.', 'qhebunel').'</div>');
	return;//stop page rendering, but create footer
}

global $section_params;

?>
<form action="<?=site_url('forum/');?>" method="post">
<input type="hidden" name="action" value="search" />
<table>
	<tr>
		<th><?php _e('Search term:','qhebunel'); ?></th>
		<td><input type="text" name="terms" /></td>
	</tr>
	<tr>
		<th><?php _e('Search in:','qhebunel'); ?></th>
		<td>
			<select name="search-location">
				<option value="post"><?php _e('Posts','qhebunel'); ?></option>
				<option value="topic"><?php _e('Thread titles','qhebunel'); ?></option>
				<option value="both"><?php _e('Both','qhebunel'); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th><?php _e('Username:','qhebunel'); ?></th>
		<td><input type="text" name="user" /></td>
	</tr>
	<tr>
		<th><?php _e('Date:','qhebunel'); ?></th>
		<td><input type="text" name="date-from" placeholder="YYYY-MM-DD" /> - <input type="text" name="date-to" placeholder="YYYY-MM-DD" /></td>
	</tr>
	<tr>
		<th><?php _e('Categories:','qhebunel'); ?></th>
		<td><?php QhebunelPost::render_category_select('categories', null, QHEBUNEL_PERMISSION_READ, 'current', true); ?></td>
	</tr>
	<tr>
		<th><?php _e('List only:','qhebunel'); ?></th>
		<td>
			<input type="checkbox" name="list-only[]" id="list-only-new" value="new"/><label for="list-only-new"><?php _e('New posts','qhebunel'); ?></label><br/>
			<input type="checkbox" name="list-only[]" id="list-only-edited" value="edited"/><label for="list-only-edited"><?php _e('Edited posts','qhebunel'); ?></label><br/>
			<input type="checkbox" name="list-only[]" id="list-only-reported" value="reported"/><label for="list-only-reported"><?php _e('Reported posts','qhebunel'); ?></label>
		</td>
	</tr>
	<tr>
		<th><?php _e('Show:','qhebunel'); ?></th>
		<td>
			<select name="list-mode">
				<option value="posts"><?php _e('Posts','qhebunel'); ?></option>
				<option value="threads"><?php _e('Threads','qhebunel'); ?></option>
			</select>
		</td>
	</tr>
</table>
</form>