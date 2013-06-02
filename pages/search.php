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
		'page' =>			0
	);
	
	foreach ($param_segments as $segment) {
		if (!empty($segment)) {
			if (preg_match('/^(l|u|df|dt|c|f|r):(.+)$/', $segment, $regs)) {
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
	
	if ($search['result_type'] == 'posts') {
		$query = "select distinct `p`.`pid` \n";
	} else {
		$query = "select distinct `t`.`tid` \n";
	}
	
	$query .= "from `qheb_threads` as `t` \n";
	$query .= "  left join `qheb_posts` as `p` \n";
	$query .= "    on (`p`.`tid`=`t`.`tid`) \n";
	$query .= "  left join `qheb_category_permissions` as `cp` \n";
	$query .= "    on (`cp`.`catid`=`t`.`catid`) \n";
	$query .= "  left join (select `tid`, `visitdate` from `qheb_visits` where `uid`=".($current_user->ID).") as `v` \n";
	$query .= "    on (`v`.`tid`=`t`.`tid`) \n";
	$query .= "where \n";
	$conditions = array();
	
	if (!empty($search['terms'])) {
		$terms = $search['terms'];
		$wpdb->escape_by_ref($terms);
		if ($search['location'] == 'post') {
			$conditions[] = "`p`.`text` like '%${terms}%'";
		} elseif ($search['location'] == 'title') {
			$conditions[] = "`t`.`title` like '%${terms}%'";
		} else {
			$conditions[] = "`p`.`text` like '%${terms}%' or `t`.`title` like '%${terms}%'";
		}
	}
	
	if (!empty($search['user'])) {
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				'select `ID` from `qheb_wp_users` where `display_name`=%s',
				$search['user']
			)
		);
		if ($user_id > 0) {
			$conditions[] = "`p`.`uid`=${user_id}";
		}
	}
	
	if (!empty($search['date_from'])) {
		$conditions[] = "`p`.`postdate`>='${search['date_from']}'";
	}
	if (!empty($search['date_to'])) {
		$conditions[] = "`p`.`postdate`<='${search['date_to']}'";
	}
	
	//Restrict categories to the ones the user is allowed to read.
	$groups = QhebunelUser::get_groups();
	$categories = array();
	$cats = $wpdb->get_results(
		$wpdb->prepare(
			'select distinct `catid`
			from `qheb_category_permissions`
			where `gid` in ('.implode(',', $groups).')
			and `access`>=%d;',
			QHEBUNEL_PERMISSION_READ
		),
		ARRAY_N
	);
	if (empty($cats)) {
		//TODO: error: no read access to any of the categories
	} else {
		foreach ($cats as $cat) {
			$categories[] = $cat[0];
		}
	}
	if (!empty($search['categories'])) {
		$categories = array_intersect($categories, $search['categories']);
	}
	$conditions[] = "`t`.`catid` in (".implode(',', $categories).")";
	
	if (!empty($search['flags'])) {
		foreach ($flags as $flag) {
			if ($flag == 'new') {
				$conditions[] = "`p`.`postdate`>`v`.`visitdate`";
			} elseif ($flag == 'edited') {
				$conditions[] = "`p`.`editdate` is not null";
			} elseif ($flag == 'reported') {
				$conditions[] = "`p`.`flag`=2";
			}
		}
	}
	
	$query .= "  (" . implode(") and \n  (", $conditions) . ") \n";
	$limit = ($search['result_type'] == 'posts' ? QHEBUNEL_POSTS_PER_PAGE : QHEBUNEL_THREADS_PER_PAGE);
	$query .= 'limit ' . ($search['page']*$limit) . ','.$limit.';';
	
	$query_result = $wpdb->get_results(
		$query,
		ARRAY_N
	);
	$matching_ids = array();
	foreach ($query_result as $row) {
		$matching_ids[] = $row[0];
	}
	
	if ($search['result_type'] == 'posts') {
		QhebunelPost::render_posts(null, 0, $matching_ids, false);
	} else {
		QhebunelPost::render_thread_list(null, $matching_ids);
	}
}
?>