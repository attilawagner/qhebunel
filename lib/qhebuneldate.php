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
	public static function getListDate($datetime) {
		//TODO: make it into an option
		$useRelativeTimeFor24H = true; //Option from the general settings admin page
		$time = mysql2date('U', $datetime);
		$diff = abs($time - time());
		if ($diff < 60*60*24 && $useRelativeTimeFor24H) {
			return self::getHumanTimeDiff($time);
		} else {
			//TODO: make it into an option
			$useShortDateForCurrentYear = false; //Option from the general settings admin page
			
			$useShortFormat = true; //flag for choosing the format string
			if ($useShortDateForCurrentYear) {
				$year = substr($datetime, 0, 4);
				$thisYear = date('Y');
				$useShortFormat = ($thisYear == $year);
			}
			
			if ($useShortFormat) {
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
	public static function getHumanTimeDiff($from, $to = '') {
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
	 * @return string Result of getHumanTimeDiff()
	 */
	public static function getRelativeDate($datetime) {
		return self::getHumanTimeDiff(mysql2date('U', $datetime));
	}
	
	/**
	 * Formats the given MySQL date string for
	 * displaying it in the meta section of a single post.
	 * @param string $datetime
	 * @return string Formatted string.
	 */
	public static function getPostDate($datetime) {
		return mysql2date('j F, Y @ G:i', $datetime);
	}
	
	/**
	 * Formats the given MySQL date string for
	 * inclusion in &lt;time datetime=''&gt; attribute.
	 * @param string $datetime MySQL datetime field
	 * @return string Formatted string.
	 */
	public static function getDatetimeAttribute($datetime) {
		return mysql2date('Y-m-d\TH:i', $datetime, false);
	}
}
?>