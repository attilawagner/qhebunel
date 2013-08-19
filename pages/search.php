<?php
/**
 * Qhebunel
 * Search form and result listing page
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

//Show message to users who aren't logged in
if (!is_user_logged_in()) {
	echo('<div class="qheb-error-message">'.__('You must log in to use the search function.', 'qhebunel').'</div>');
	return;//stop page rendering, but create footer
}

global $section_params;
$param_segments = explode('/', $section_params);

$params_are_valid = false;
if (is_array($param_segments) && !empty($param_segments)) {
	$search = array(
		'terms' =>			'',
		'location' =>		'both',
		'user' =>			'',
		'date_from' =>		'',
		'date_to' =>		'',
		'categories' =>		array(),
		'flags' =>			array(),
		'result_type' =>	'posts',
		'page' =>			0,
		
		'no_result_message' => __('There\'s no post matching your criteria.')
	);
	
	foreach ($param_segments as $segment) {
		if (!empty($segment)) {
			if (preg_match('/^(l|u|df|dt|c|f|r|p):(.+)$/', $segment, $regs)) {
				$key = $regs[1];
				$value = $regs[2];
				switch ($key) {
					case 'l':
						$valid_locations = array('post', 'title', 'both');
						if (in_array($value, $valid_locations)) {
							$search['location'] = $value;
						}
						break;
					
					case 'u':
						$search['user'] = rawurldecode($value);
						break;
					
					case 'df':
					case 'dt':
						if (preg_match('/(\d{4})[-.\s]*(\d{1,2})[-.\s]*(\d{1,2})/', $value, $d)) {
							$value = $d[1].'-'.$d[2].'-'.$d[3];
							$search[ ($key == 'df' ? 'date_from' : 'date_to') ] = $value;
						}
						break;
					
					case 'c':
						$cats = explode(';', $value);
						foreach ($cats as $cat) {
							$cat = (int)$cat;
							if ($cat > 0) {
								$search['categories'][] = $cat;
							}
						}
						$search['categories'] = array_unique($search['categories']);
						break;
					
					case 'f':
						$flags = explode(';', $value);
						$valid_flags = array('new', 'edited', 'reported');
						foreach ($valid_flags as $vf) {
							if (in_array($vf, $flags)) {
								$search['flags'][] = $vf;
							}
						}
						$search['flags'] = array_unique($search['flags']);
						break;
					
					case 'r':
						$valid_result_types = array('posts', 'threads');
						if (in_array($value, $valid_result_types)) {
							$search['result_type'] = $value;
						}
						break;
					
					case 'p':
						$search['page'] = max(0, (int)$value);
						break;
				}
			} elseif (empty($search['terms'])) {
				$search['terms'] = rawurldecode($segment);
			}
		}
	}
	
	if (!empty($search['terms']) || !empty($search['user']) || !empty($search['date_from']) || !empty($search['date_to']) || !empty($search['flags'])) {
		$params_are_valid = true;
	}
}

if (!$params_are_valid) {
	/*
	 * Not a valid search query, display the search form.
	 */
?>
<form action="<?=site_url('forum/');?>" method="post">
<input type="hidden" name="action" value="search" />
<table>
	<tfoot>
		<tr>
			<td colspan="2"><input type="submit" name="search" value="<?php _e('Search','qhebunel'); ?>" /></td>
		</tr>
	</tfoot>
	<tbody>
		<tr>
			<th><?php _e('Search term:','qhebunel'); ?></th>
			<td><input type="text" name="terms" /></td>
		</tr>
		<tr>
			<th><?php _e('Search in:','qhebunel'); ?></th>
			<td>
				<select name="location">
					<option value="post"><?php _e('Posts','qhebunel'); ?></option>
					<option value="topic"><?php _e('Thread titles','qhebunel'); ?></option>
					<option value="both" selected><?php _e('Both','qhebunel'); ?></option>
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
				<input type="checkbox" name="post-flags[]" id="list-only-new" value="new"/><label for="list-only-new"><?php _e('New posts','qhebunel'); ?></label><br/>
				<input type="checkbox" name="post-flags[]" id="list-only-edited" value="edited"/><label for="list-only-edited"><?php _e('Edited posts','qhebunel'); ?></label><br/>
				<input type="checkbox" name="post-flags[]" id="list-only-reported" value="reported"/><label for="list-only-reported"><?php _e('Reported posts','qhebunel'); ?></label>
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
	</tbody>
</table>
</form>
<?php
} else {
	/*
	 * Run the query and display the results.
	 */
	QhebunelPost::show_search_results($search);
}
?>