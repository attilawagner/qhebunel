<?php
/**
 * Qhebunel
 * Move post ajax backend (handler)
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;

if (preg_match('%(categories|threads)(/(\d+))?%', $section_params, $regs)) {
	$type = $regs[1];
	$id = $regs[3];
} else {
	Qhebunel::redirect_to_error_page();
}

//Check permissions
if (!QhebunelUser::is_moderator() && !QhebunelUser::is_admin()) {
	Qhebunel::redirect_to_error_page();
}

if ($type == 'categories') {
	/*
	 * For the categories list, the parameter is the post ID.
	 * In the list only those categories will be shown, that the user who
	 * wrote the post can post.
	 */
	$post_id = $id;
	
	$post = $wpdb->get_row(
		$wpdb->prepare(
			'select `p`.*, `t`.`catid`
			from `qheb_posts` as `p`
			  left join `qheb_threads` as `t`
			    on (`t`.`tid`=`p`.`tid`)
			where `pid`=%d;',
			$post_id
		),
		ARRAY_A
	);
	
	QhebunelPost::render_category_dropdown('', $post['catid'], QHEBUNEL_PERMISSION_WRITE, $post['uid']);
	
} else if ($type == 'threads') {
	/*
	 * For the thread list, the parameter is the category ID.
	 * Every thread will be displayed in the list that belongs to this category.
	 */
	$category_id = $id;
	
	$threads = $wpdb->get_results(
		$wpdb->prepare(
			'select `tid`,`title`,`pinned`
			from `qheb_threads`
			where `catid`=%d
			order by `pinned` desc, `title`;',
			$category_id
		),
		ARRAY_A
	);
	
	if (!empty($threads)) {
		foreach ($threads as $thread) {
			echo('<option '.($thread['pinned'] ? 'class="pinned" ' : '').'value="'.$thread['tid'].'">'.$thread['title'].'</option>');
		}
	}
}
?>