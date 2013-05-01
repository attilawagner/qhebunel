<?php
/**
 * Qhebunel
 * Private message notification widget
 */

class QhebunelPMWidget extends WP_Widget {
	
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'qhebunel_pm_widget',
			'PM Notification',
			array(
				'description' => __('Displays a notification if the user has unread private messages.', 'qhebunel')
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
		global $wpdb, $current_user;
		extract($args);
		
		//The widget is invisible for unauthenticated user
		if (!is_user_logged_in()) {
			return;
		}
		
		/*
		 * Check for unread messages.
		 */
		$unread_count = $wpdb->get_var(
			$wpdb->prepare(
				'select count(*) from `qheb_privmessages`
				where `to`=%d and `readdate` is null;',
				$current_user->ID
			)
		);
		
		//Display the widget only if there're unread messages or when it's set to show the 'no new' message.
		if ($unread_count > 0 || $instance['only_unread'] == false) {
			
			$title = apply_filters('widget_title', $instance['title']);
		
			echo($before_widget);
			if (!empty($title)) {
				echo($before_title . $title . $after_title);
			}
			
			if ($unread_count == 0) {
				_e('No new messages', 'qhebunel');
			} else {
				printf(_n('One unread message', '%s unread messages', $unread_count, 'qhebunel'), $unread_count);
			}
			
			echo($after_widget);
		}
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
		$instance['only_unread'] = (@$new_instance['only_unread'] == 'true');
	
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
			$title = __('Private messages', 'qhebunel');
		}
		if (isset($instance['only_unread'])) {
			$only_unread = $instance['only_unread'];
		} else {
			$only_unread = false;
		}
		
		echo('<p>');
		echo('<label for="'.$this->get_field_id('title').'">'.__('Title:').'</label> ');
		echo('<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($title).'" />');
		echo('</p>');
		echo('<p>');
		echo('<label for="'.$this->get_field_id('only_unread').'">'.__('Hide when there\'re no unread messages:').'</label> ');
		echo('<input class="widefat" id="'.$this->get_field_id('only_unread').'" name="'.$this->get_field_name('only_unread').'" type="checkbox" value="true"'.($only_unread ? ' checked="checked"' : '').' />');
		echo('</p>');
	}
}


?>