<?php
/**
 * Qhebunel
 * Category list page
 */

if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

$cats = $wpdb->get_results(
	'select `c`.*, `t`.`threadcount`, `lp`.`postcount`, `po`.`postdate`, `po`.`tid`, `po`.`uid`, `u`.`display_name`
	from `qheb_categories` as `c`
	right join (
			select `catid`
			from `qheb_category_permissions`
			where `gid` in ('.implode(',', QhebunelUser::get_groups()).')
			group by `catid`
			having max(`access`)>0
		) as `p`
		on (`p`.`catid`=`c`.`catid`)
	left join (
			select `catid`, count(`tid`) as `threadcount`
			from `qheb_threads`
			group by `catid`
		) as `t`
		on (`t`.`catid`=`c`.`catid`)
	left join (
			select max(`pp`.`pid`) as `maxpid`, count(*) as `postcount`, `tt`.`catid`
			from `qheb_posts` as `pp`
			left join `qheb_threads` as `tt`
				on (`tt`.`tid`=`pp`.`tid`)
			group by `tt`.`catid`
		) as `lp`
		on (`lp`.`catid`=`c`.`catid`)
	left join `qheb_posts` as `po`
		on (`po`.`pid`=`lp`.`maxpid`)
	left join `qheb_wp_users` as `u`
		on (`u`.`ID`=`po`.`uid`)',
	ARRAY_A
);

echo('<table id="qheb_catlist">');
foreach ($cats as $cat1) {
	if ($cat1['parent'] == 0) {
		echo('<tbody><tr><th colspan="4"><div class="qheb_cat1_title">'.wptexturize($cat1['name']).'</div><div class="qheb_cat1_desc">'.wptexturize($cat1['description']).'</div></th></tr>');
		foreach ($cats as $cat2) {
			if ($cat2['parent'] == $cat1['catid']) {
				$threads = (int)$cat2['threadcount'];
				$thread_count = sprintf(_n('1 Thread', '%d Threads', $threads, 'qhebunel'), $threads);
				$posts = (int)$cat2['postcount'];
				$post_count = sprintf(_n('1 Post', '%d Posts', $posts, 'qhebunel'), $posts);
				$last_post = '';
				if ($post_count > 0) {
					//TODO: profile link using $cat2['uid']
					$profile_link = '#';
					//TODO: thread link using $cat2['tid']
					if ($cat2['uid'] > 0) {
						//Normal user
						$last_post = '<span class="last_post">'.sprintf(__('Last post by <a href="%2$s">%1$s</a>', 'qhebunel'), $cat2['display_name'], $profile_link).'</span> ';
					} else {
						//Guest post
						$last_post = '<span class="last_post">'.__('Last post by a guest', 'qhebunel').'</span> ';
					}					
					$last_post .= '<span class="date" title="'.mysql2date('j F, Y @ G:i', $cat2['postdate']).'">'.QhebunelDate::get_list_date($cat2['postdate']).'</span>';
				}
				echo('<tr><td><div class="qheb_cat2_title"><a href="'.QhebunelUI::get_url_for_category($cat2['catid']).'">'.QhebunelUI::format_title($cat2['name']).'</a></div><div class="qheb_cat2_desc">'.wptexturize($cat2['description']).'</div></td><td><span class="thread_count">'.$thread_count.'</span> <span class="post_count">'.$post_count.'</span></td><td></td><td>'.$last_post.'</td></tr>');
			}
		}
		echo('<tr><td colspan="4"></td></tbody>');
	}
}
echo('</table>');
?>