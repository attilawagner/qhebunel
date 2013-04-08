<?php
/**
 * Qhebunel
 * Date functions
 */
class QhebunelDate {
	
	/**
	 * Formats the given MySQL date string for
	 * displaying it in the thread/category lists.
	 * Dates less than a day ago are displayed as relative time strings (eg. '34 mins ago').
	 * Dates that belong to this year may use a different format according to localizations.
	 * @param string $datetime MySQL datetime field
	 * @return string Formatted string.
	 */
	public static function get_list_date($datetime) {
		//TODO: make it into an option
		$use_relative_time_for24_h = true; //Option from the general settings admin page
		$time = mysql2date('U', $datetime);
		$diff = abs($time - time());
		if ($diff < 60*60*24 && $use_relative_time_for24_h) {
			return self::get_human_time_diff($time);
		} else {
			//TODO: make it into an option
			$use_short_date_for_current_year = true; //Option from the general settings admin page
			
			$use_short_format = false; //flag for choosing the format string
			if ($use_short_date_for_current_year) {
				$year = substr($datetime, 0, 4);
				$this_year = date('Y');
				$use_short_format = ($this_year == $year);
			}
			
			if ($use_short_format) {
				/*translators: this date format is used when the date belongs to the current year */
				//TODO: option
				return mysql2date('j F', $datetime);
			} else {
				//TODO: option
				return mysql2date('j F Y', $datetime);
			}
		}
	}
	
	/**
	 * Returns the difference between two unix timestamps (epoch)
	 * as human readable time difference:
	 * '2 mins ago', '3 hours ago', etc.
	 * <p>
	 * Supported units are: min, hour, day.
	 * </p>
	 * @see human_time_diff($from,$to)
	 * @param integer $from
	 * @param integer $to
	 * @return string
	 */
	public static function get_human_time_diff($from, $to = '') {
		if (empty($to)) {
			$to = time();
		}
		$diff = (int) abs($to - $from);
		if ($diff <= 3600) {
			$mins = round($diff / 60);
			if ($mins <= 1) {
				$mins = 1;
			}
			/* translators: min=minute */
			$since = sprintf(_n('%s min ago', '%s mins ago', $mins), $mins);
		} else if (($diff <= 86400) && ($diff > 3600)) {
			$hours = round($diff / 3600);
			if ($hours <= 1) {
				$hours = 1;
			}
			$since = sprintf(_n('%s hour ago', '%s hours ago', $hours), $hours);
		} elseif ($diff >= 86400) {
			$days = round($diff / 86400);
			if ($days <= 1) {
				$days = 1;
			}
			$since = sprintf(_n('%s day ago', '%s days ago', $days), $days);
		}
		return $since;
	}
	
	/**
	 * Formats the given MySQL date string into
	 * human readable time difference relative to the actual time.
	 * @param string $datetime
	 * @return string Result of get_human_time_diff()
	 */
	public static function get_relative_date($datetime) {
		return self::get_human_time_diff(mysql2date('U', $datetime));
	}
	
	/**
	 * Formats the given MySQL date string for
	 * displaying it in the meta section of a single post.
	 * @param string $datetime
	 * @return string Formatted string.
	 */
	public static function get_post_date($datetime) {
		return mysql2date('j F, Y @ G:i', $datetime);
	}
	
	/**
	 * Formats the given MySQL date string for
	 * inclusion in &lt;time datetime=''&gt; attribute.
	 * @param string $datetime MySQL datetime field
	 * @return string Formatted string.
	 */
	public static function get_datetime_attribute($datetime) {
		return mysql2date('Y-m-d\TH:i', $datetime, false);
	}
	
	/**
	 * Formats the given MySQL date string into a local
	 * format that shows year, month, and day.
	 * @param unknown_type $datetime
	 */
	public static function get_short_date($datetime) {
		return mysql2date('j F Y', $datetime);
	}
}
?>