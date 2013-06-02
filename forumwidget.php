<?php
/**
 * Qhebunel
 * Widget that holds the important links to the forum.
 */

class QhebunelForumWidget extends WP_Widget {
	
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'qhebunel_forum_widget',
			'Forum',
			array(
				'description' => __('Displays links to different forum functions.', 'qhebunel')
			)
		);
	}
	
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget($args, $instance) {
		global $current_user;
		extract($args);
		
		$title = apply_filters('widget_title', $instance['title']);
	
		echo($before_widget);
		if (!empty($title)) {
			echo($before_title . $title . $after_title);
		}
		
		echo('<ul>');
		echo('<li><a href="'.site_url('forum/').'">'.__('Categories','qhebunel').'</a> </li>');
		if (is_user_logged_in()) {
			echo('<li><a href="'.site_url('forum/search').'">'.__('Search','qhebunel').'</a> </li>');
			echo('<li><a href="'.site_url('forum/badges').'">'.__('Badges','qhebunel').'</a> </li>');
			echo('<li><a href="'.QhebunelUI::get_url_for_user($current_user->ID).'">'.__('View profile','qhebunel').'</a> </li>');
			echo('<li><a href="'.site_url('forum/pm/').'">'.__('Private messages','qhebunel').'</a> </li>');
		}
		echo('</ul>');
		
		echo($after_widget);
	}
	
	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
	
		return $instance;
	}
	
	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		if (isset($instance['title'])) {
			$title = $instance['title'];
		} else {
			$title = __('Forum', 'qhebunel');
		}
		
		echo('<p>');
		echo('<label for="'.$this->get_field_id('title').'">'.__('Title:').'</label> ');
		echo('<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($title).'" />');
		echo('</p>');
	}
}


?>