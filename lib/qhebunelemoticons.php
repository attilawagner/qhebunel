<?php
/**
 * Qhebunel
 * Emoticon related functions
 * 
 * @author Attila Wagner
 */

class QhebunelEmoticons {
	
	/**
	 * Returns an associative array containing the emoticon shortcodes
	 * as keys and the image file names as values.
	 *
	 * Example return value for $mode='flat':
	 * array(
	 *   ':)' => 'smile.png',
	 *   ':-)' => 'smile.png',
	 *   ':(' => 'sad.png',
	 *   ':yes:' => 'yes.png'
	 * )
	 *
	 * Example return value for $mode='grouped':
	 * array(
	 *   'common' => array(
	 *     ':)' => 'smile.png',
	 *     ':(' => 'sad.png'
	 *   ),
	 *   'other' => array(
	 *     ':yes:' => 'yes.png'
	 *   )
	 * )
	 *
	 * Note that the grouped version only includes the first code for every emoticon.
	 * @param string $mode Can be set to 'flat' or 'grouped'.
	 * @return array Contains the codes and image file names.
	 */
	public static function getList($mode = 'flat') {
		static $emoticons = array(
			'common' => array(
				':)' =>			'smile.png',
				';)' =>			'wink.png',
				':D' =>			'grin.png',
				':P' =>			'tongue.png',
				':S' =>			'dizzy.png',
				':(' =>			'sad.png',
				':\'(' =>		'cwy.png',
				'B)' =>			'cool.png',
				':O' =>			'shocked.png',
				':@' =>			'angry.png',
			),
			'other' => array(
				':cheer:' =>	'cheerful.png',
				':huh:' =>		'wassat.png',
				':ermm:' =>		'ermm.png',
				':blush:' =>	'blush.png',
				':blink:' =>	'blink.png',
				':happy:' =>	'happy.png',
				':getlost:' =>	'getlost.png',
				':*' =>			'kissing.png',
				':pinch:' =>	'pinch.png',
				':|' =>			'pouty.png',
				':unsure:' =>	'unsure.png',
				':sick:' =>		'sick.png',
				':side:' =>		'sideways.png',
				':silly:' =>	'silly.png',
				':sleep:' =>	'sleeping.png',
				':woohoo:' =>	'w00t.png',
				':whistle:' =>	'whistling.png',
				':love:' =>		'wub.png',
				':angel:' =>	'angel.png',
				':devil:' =>	'devil.png',
				':alien:' =>	'alien.png',
				':ninja:' =>	'ninja.png',
				'<3' =>			'heart.png',
			),
			'aliases' => array(
				':lol:' =>		'grin.png',
				':dizzy' =>		'dizzy.png',
				':ohmy:' =>		'shocked.png',
				':angry:' =>	'angry.png',
				':dry:' =>		'ermm.png',
				':oops:' =>		'blush.png',
				':kiss:' =>		'kissing.png',
				':pouty:' =>	'pouty.png',
				':woot:' =>		'w00t.png',
				'(A)' =>		'angel.png',
				':evil:' =>		'devil.png',
				'(6)' =>		'devil.png',
			)
		);
	
		if ($mode == 'flat') {
			static $flatArray = array();
			if (empty($flatArray)) {
				//Merge groups into one array
				foreach ($emoticons as $group) {
					$flatArray = array_merge($flatArray, $group);
				}
			}
			return $flatArray;
		} else {
			return $emoticons;
		}
	}
	
	/**
	 * Replaces emoticon codes with &lt;img&gt; tags.
	 * @param string $text Text to process.
	 * @return string Text containing images instead of emoticon codes.
	 */
	public static function replaceInText($text) {
		//Get flat list of emoticons
		$emoticons = self::getList('flat');
	
		//Build regex expression and cache it
		static $emoticonRegex;
		static $emoticonRoot;
		if (empty($emoticonRegex)) {
			$escapedEmoticons = preg_replace('/([\\\\:|*(){}[\]])/', '\\\\\1', array_keys($emoticons));//escape regex characters
			$emoticonRegex = '/(' . implode('|', $escapedEmoticons) . ')/e';
			$emoticonRoot = QHEBUNEL_URL.'ui/emoticons/';
		}
	
		//Split text into HTML tags and text fragments
		$fragments = preg_split('/(<[^>]+>)/', $text, 0, PREG_SPLIT_DELIM_CAPTURE);
		$ret = '';
		foreach ($fragments as $fragment) {
			if (substr($fragment, 0, 1) == '<') {
				//Add tags without modification
				$ret .= $fragment;
	
			} else {
				//Replace emoticons with img tags
				$ret .= preg_replace($emoticonRegex, '"<img src=\"".$emoticonRoot.$emoticons[stripslashes("\1")]."\" alt=\"".stripslashes("\1")."\" title=\"".stripslashes("\1")."\"/>"', $fragment);
			}
		}
		return $ret;
	}
}