<?php
/**
 * Qhebunel
 * BBcode and emoticon parser
 * 
 * Tag rendering is based on the BBCode plugin by Viper007Bond.
 */
class QhebunelBB {
	
	/**
	 * Holds the tag definitons as an associative array.
	 * Each tag repesented by an associative array of the following structure:
	 * <ul>
	 * <li>
	 * <b>parse_children</b><br/>
	 * Parses BBCode tags nested inside this tag.
	 * </li>
	 * <li>
	 * <b>allow_selfnest</b><br/>
	 * Will parse the same tag in the content (eg. quote).
	 * </li>
	 * <li>
	 * <b>allowed_children</b><br/>
	 * If defiend, the parser will only look for tags listed in this array.
	 * </li>
	 * <li>
	 * <b>allowed_parent</b><br/>
	 * The parser will only look for this tag inside the specified parent tag.
	 * If null, no checks will be run.
	 * </li>
	 * </ul>
	 */
	private static $tags = array(
		//Inline formatters
		'b' => array(true, false, null, null),
		'i' => array(true, false, null, null),
		'u' => array(true, false, null, null),
		's' => array(true, false, null, null),
		'sub' => array(true, false, array('b','i','u','s','color','size','link','url','email'), null),
		'sup' => array(true, false, array('b','i','u','s','color','size','link','url','email'), null),
		'color' => array(true, true, null, null),
		'size' => array(true, true, null, null),
		
		//Image and links
		'img' => array(false, false, null, null),
		'url' => array(true, false, array('b','i','u','s','sub','sup','color','size','img'), null),
		'link' => array(true, false, array('b','i','u','s','sub','sup','color','size','img'), null),
		'email' => array(true, false, array('b','i','u','s','sub','sup','color','size','img'), null),
		
		//Blocks
		'left' => array(true, false, null, null),
		'right' => array(true, false, null, null),
		'center' => array(true, false, null, null),
		'justify' => array(true, false, null, null),
		'quote' => array(true, true, null, null),
		'code' => array(false, false, array(), null),
		'spoiler' => array(true, true, null, null),
		
		//Misc.
		'list' => array(true, false, null, null),
		'ul' => array(true, false, null, null),
		'ol' => array(true, false, null, null),
		'table' => array(true, false, null, null),
		'hr' => array(false, false, array(), null),
	);
	
	/**
	 * Replaces the BBCode tags with HTML tags.
	 * @param string $text String to parse.
	 * @return string HTML code.
	 */
	public static function parse($text) {
		$tokens = self::tokenize_text(self::remove_multiple_line_breaks($text));
		if (empty($tokens)) {
			return '';
		}
		$tokens = self::validate_tokens($tokens);
		
		$stack = array();
		foreach ($tokens as $token) {
			if (is_string($token)) {
				//Add string tokens to output stack
				self::add_text_token($stack, $token);
			} else {
				
				if ($token['closing']) {
					//Closing tag -> walk back to its opening and process it
					/*
					 * The opening tag can be either the previous one, or the one before that,
					 * because during the parsing every closed tag gets replaced by their HTML source code,
					 * and string tokens are concatenated.
					 */
					if (is_string(end($stack))) {
						$content = array_pop($stack);
						$opening_tag = array_pop($stack);
					} else {
						$content = null;
						$opening_tag = array_pop($stack);
					}
					
					$method_name = 'parseTag_'.$token['name'];
					$converted_tag = call_user_func(array('QhebunelBB', $method_name), $opening_tag['attr'], $content);
					self::add_text_token($stack, $converted_tag);
					
				} else {
					//Opening tag -> add it to the stack
					$stack[] = $token;
				}
			}
		}
		
		return $stack[0];
	}
	
	/**
	 * Called by the parse() method, this function
	 * adds the text token to the end of the stack.
	 * if the last token in the stack is a string, it merges them.
	 * 
	 * @param array $stack Stack used in the parse() method
	 * @param string $text Text token to add
	 */
	private static function add_text_token(&$stack, $text) {
		if (empty($text)) {
			//If the text token is empty, do nothing
			return;
		}
		
		if (is_string(end($stack))) {
			$last = array_pop($stack);
			$stack[] = $last.$text;
		} else {
			$stack[] = $text;
		}
	}
	
	/**
	 * Replaces multiple occurences of &lt;br/&gt; tags with a single one.
	 * BBCode opening and closing tags and whitespaces are ignored between the &lt;br/&gt; tags.
	 * @param string $text Text to clean up.
	 * @return string The text without unnecessary line breaks.
	 */
	private static function remove_multiple_line_breaks($text) {
		return preg_replace('%(<br */?>\p{Z}*){2,}%iu', '<br/><br/>', $text);
	}
	
	/**
	 * Called by the parse() method, this function
	 * validates the tokens and closes every unclosed tag
	 * when their parents get closed.
	 * 
	 * @param array $tokens Tokens created from the input text.
	 * @return array Corrected token list.
	 */
	private static function validate_tokens($tokens) {
		$stack = array();
		$parents = array();
		
		foreach ($tokens as $token) {
			if (is_string($token)) {
				//Add string tokens to output stack
				self::add_text_token($stack, $token);
			} else {
				
				if (self::is_valid_tag_token($parents, $token)) {
					if ($token['closing']) {
						$parents_max_index = count($parents) - 1;
						
						//Find opening tag by walking backwards in the parent list, and close any unclosed child tags
						for ($i=$parents_max_index; $i >=0; $i--) {
							if ($parents[$i] == $token['name']) {
								//The opening tag
								break;
							} else {
								
								//An unclosed child tag -> add closing tag token to the stack and pop from parent list
								$stack[] = array(
									'text' => '[/'.$parents[$i].']',
									'name' => $parents[$i],
									'closing' => true,
									'attr' => array()
								);
								array_pop($parents);
							}
						}
						
						//Add tag to stack and pop from parent list
						$stack[] = $token;
						array_pop($parents);
						
					} else {
						//Add to open tags
						$parents[] = $token['name'];
						$stack[] = $token;
					}
						
				} else {
						
					//Treat invalid tokens as texts
					self::add_text_token($stack, $token['text']);
				}
			}
		}
		
		return $stack;
	}
	
	/**
	 * Called by the parse() method, this function
	 * checks whether the given token is valid
	 * considering the actual tag hierarchy.
	 * 
	 * @param array $parents Array of strings, containing the open tag names.
	 * @param array $token The token to validate.
	 * @return boolean True, if the token is valid and can be processed.
	 */
	private static function is_valid_tag_token($parents, $token) {
		if ($token['closing']) {
			//A closing tag is valid if there's an opening tag
			foreach ($parents as $parent) {
				if ($parent == $token['name']) {
					return true;
				}
			}
			
			return false; //No opening tag found
		} else {
			
			//An opening tag is valid if it's allowed inside its parents
			$legal_tags = self::build_tag_list($parents);
			return in_array($token['name'], $legal_tags);
		}
		
	}
	
	/**
	 * Parses the text into BBCode tag and plain text tokens.
	 * In the returned array every parsed tag will be represented
	 * as an associative array:
	 * $tag = array(
	 *   'text' => "[quote='User Name' post='21']",
	 *   'name' => 'quote',
	 *   'closing' => false,
	 *   'attr' => array(
	 *     0 => 'User Name',
	 *     'post' => '21'
	 *   )
	 * )
	 * Text segments will be given back as strings.
	 * 
	 * @param string $text Text to parse.
	 * @return array Array of tokens.
	 */
	private static function tokenize_text($text) {
		if (empty($text)) {
			return '';
		}
		
		$tokens = array();
		$pattern = self::get_regex(array_keys(self::$tags));
		$offset = 0;
		while (preg_match("/$pattern/s", $text, $regs, PREG_OFFSET_CAPTURE, $offset)) {
			//Add text token and modify offset for the next iteration
			$tokens[] = substr($text, $offset, $regs[0][1]-$offset);
			$offset = $regs[0][1] + strlen($regs[0][0]);
			
			if (!empty($regs[1][0])) {
				//Escaped tag, remove escaping brackets and add as text
				$tokens[] = substr($regs[0][0], 1, -1);
			} else {
				
				//Parse tag attributes and add tag to output
				$attributes = self::parse_attributes($regs[4][0]);
				$tokens[] = array(
					'text' => $regs[0][0],
					'name' => $regs[3][0],
					'closing' => !empty($regs[2][0]),
					'attr' => $attributes
				);
			}
		}
		
		//Add text after the last tag to the output
		$last_text_token = substr($text, $offset);
		if ($last_text_token) {
			$tokens[] = substr($text, $offset);
		}
		
		return $tokens;
	}
	
	/**
	 * Builds a list of tags that should be parsed inside the current tag hierarchy.
	 * @return mixed Returns the parsable tags for the current context as an array.
	 */
	private static function build_tag_list($parents) {
		$tag_list = array();
		
		//Get immediate parent
		if (!empty($parents)) {
			$last_parent = end($parents);
			$has_parent = true;
		} else {
			$has_parent = false;
		}
		
		//Get tags that are allowed by the parent hierarchy #1: loop through tag definitions
		foreach (self::$tags as $tag => $params) {
			//Check allowed_parent
			if ($params[3] == null || !$has_parent || $params[3] == $last_parent) {
				//Self nesting
				if ($params[1] || !$has_parent || !in_array($tag, $parents)) {
					$tag_list[] = $tag;
				}
			}
		}
		
		//Get tags that are allowed by the parent hierarchy #2: parent exclusions
		foreach ($parents as $parent) {
			//If allowed_children is defined, use only the intersection of the currently allowed tags
			//and the tags allowed by this parent tag.
			if (is_array(self::$tags[$parent][2])) {
				$tag_list = array_intersect($tag_list, self::$tags[$parent][2]);
			}
		}
		
		return $tag_list;
	}

	/**
	 * Retrieve all attributes from a bbcode tag.
	 * The attributes list has the attribute name as the key and the value of the
	 * attribute as the value in the key/value pair. This allows for easier
	 * retrieval of the attributes, since all attributes have to be known.
	 * @param string $text
	 * @return array List of attributes and their value.
	 */
	private static function parse_attributes($text) {
		$atts = array();
		$start = 0;
		
		//Get default attribute
		if (preg_match('/^=(\'|"|)([^\'"]+)\1/', $text, $regs)) {
			$atts[0] = $regs[2];
			$start = strlen($regs[0]);
		}
		
		//Build the pattern for matching named parameters
		$attribute_pattern = 
		  '([a-zA-Z]+)'						//1: Parameter name
		. '='								//=
		. '(\'|")?'							//2: single or double quote
		.    '(?(2)'						//   IF there was a single or double qoute
		.      '([^\'"]+)'					//3: THEN save the parameter value into group 3
		.      '\2'							//     and match the terminating quote character
		.    '|'							//   OR ELSE
		.      '([^ ]+)'					//4:   match the parameter value (only a single word)
		.    ')';							//   END IF
		
		//Iterate over other parameters
		preg_match_all('/([a-zA-Z]+)=(\'|")?(?(2)([^\'"]+)\2|([^ ]+))/', $text, $param_regs, PREG_SET_ORDER, $start);
		foreach ($param_regs as $param) {
			$atts[$param[1]] = (isset($param[3]) ? $param[3] : $param[4]);
		}
		
		return $atts;
	}
	
	/**
	 * Returns the regex pattern for the given tags.
	 * @param array $tags An array of tag names (strings).
	 * @return string Regex pattern.
	 */
	private static function get_regex($tags) {
		$tagregexp = join('|', $tags);
		
		// WARNING! Numbered groups are used in tokenize_text function.
		return
		  '(\[)?'							//1: Opening escaping bracket - [[tag]]
		. '\['								//Opening bracket
		.    '(\/?)'						//2: Closing tag
		.    '('.$tagregexp.')\b'			//3: Tag name
		.    '([^\]]*)'						//4: Parameters
		. '\]'								//Closing bracket
		. '(?(1)'							//   IF it's an escaped tag
		.   '\]'							//5: THEN match the closing escaping bracket - [[tag]]
		. ')';								//   END IF
	}
	
	
	
	/*
	 * 
	 * Single tag parser functions
	 * 
	 */
	
	
	/*
	 * Inline
	 */
	
	private static function parseTag_b($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<strong>'.$content.'</strong>';
	}
	
	private static function parseTag_i($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<em>'.$content.'</em>';
	}
	
	private static function parseTag_u($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<span style="text-decoration:underline">'.$content.'</span>';
	}
	
	private static function parseTag_s($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<span style="text-decoration:line-through">'.$content.'</span>';
	}
	
	private static function parseTag_sub($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<sub>'.$content.'</sub>';
	}
	
	private static function parseTag_sup($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<sup>'.$content.'</sup>';
	}
	
	private static function parseTag_color($atts=array(), $content=null) {
		static $named_colors = array(
			"black" => "#000000",
			"blue" => "#0000FF",
			"brown" => "#A52A2A",
			"cyan" => "#00FFFF",
			"darkblue" => "#00008B",
			"darkred" => "#8B0000",
			"green" => "#008000",
			"indigo" => "#4B0082",
			"olive" => "#808000",
			"orange" => "#FFA500",
			"red" => "#FF0000",
			"violet" => "#EE82EE",
			"white" => "#FFFFFF",
			"yellow" => "#FFFF00",
			"aqua" => "#00FFFF",
			"fuchsia" => "#FF00FF",
			"gray" => "#808080",
			"lime" => "#00FF00",
			"maroon" => "#800000",
			"navy" => "#000080",
			"purple" => "#800080",
			"silver" => "#C0C0C0",
			"teal" => "#008080"
		);
		
		if (null === $content) return '';
		
		if (array_key_exists($atts[0], $named_colors)) {
			$color = $named_colors[$atts[0]];
		} elseif (preg_match("/(#[a-fA-F0-9]{3,6})/", $atts[0], $matches)) {
			$color = $matches[1];
		} else {
			//Invalid color name, or parameter not supplied
			return $content;
		}
		
		return '<span style="color:'.$color.'">'.$content.'</span>';
	}
	
	private static function parseTag_size($atts=array(), $content=null) {
		static $sizes = array(
			1 => '0.63',
			'0.82',
			'1.0',
			'1.13',
			'1.5',
			'2.0',
			'3.0',
		);
		
		if (null === $content) return '';
		
		if(preg_match("/\d/", $atts[0], $matches) && $matches[0] >= 1 && $matches[0] <= 7) {
			return '<span style="font-size:'.$sizes[$matches[0]].'em">'.$content.'</span>';
		} else {
			//Invalid size, or parameter not supplied
			return $content;
		}
	}
	
	
	/*
	 * Image and links
	 */
	
	private static function parseTag_img($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<img src="'.$content.'" alt="" />';
	}
	
	private static function parseTag_url($atts=array(), $content=null) {
		if (isset($atts[0])) { // [url=http://www.google.com/]Google[/url]
			$url = $atts[0];
			$text = (empty($content) ? $url : $content);
		} else { // [url]http://www.google.com/[/url]
			if (empty($content)) return '';
			$url = $text = $content;
		}
		
		return '<a href="'.$url .'">'.$text.'</a>';
	}
	
	private static function parseTag_link($atts=array(), $content=null) {
		return self::parseTag_url($atts, $content);
	}
	
	private static function parseTag_email($atts=array(), $content=null) {
		if (isset($atts[0])) { // [email=asd@example.com]Asad asd[/url]
			$url = $atts[0];
			$text = (empty($content) ? $url : $content);
		} else { // [email]asd@example.com[/email]
			if (empty($content)) return '';
			$url = $text = $content;
		}
		
		return '<a href="mailto:'.$url .'">'.$text.'</a>';
	}
	
	
	/*
	 * Blocks
	 */
	
	private static function parseTag_left($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<div style="text-align:left">'.$content.'</div>';
	}
	
	private static function parseTag_right($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<div style="text-align:right">'.$content.'</div>';
	}
	
	private static function parseTag_center($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<div style="text-align:center">'.$content.'</div>';
	}
	
	private static function parseTag_justify($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<div style="text-align:justify">'.$content.'</div>';
	}
	
	private static function parseTag_quote($atts=array(), $content=null) {
		if (null === $content) return '';
		$title = '';
		if (!empty($atts[0])) {
			$name = $atts[0];
			//Make the name a link pointing to the permalink of the post
			if (!empty($atts['post'])) {
				$name = '<a href="'.QhebunelUI::get_url_for_post($atts['post'], true).'">'.$name.'</a>';
			}
			$title = '<div class="qheb_quote_info">'.sprintf(__('%s wrote:', 'qhebunel'), $name).'</div>';
		}
		return '<div class="qheb_quote">'.$title.$content.'</div>';
	}
	
	private static function parseTag_code($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<div class="code" style="margin:10px auto;font-family:monospace;text-align:left;overflow:auto;width:90%;word-wrap: normal;">'.$content.'</div>';
	}
	
	
	/*
	 * Misc.
	 */
	
	private static function parseTag_list($atts=array(), $content=null) {
		if (null === $content) return '';
	
		/* Tag: unordered list [list][*]line 1[*]line2[*]line 3[*]line 4[*]line5 etc[/list]  - preferred */
		/* Tag: ordered list [list=<list type>][*]line 1[*]line2[*]line 3[*]line 4[*]line5 etc[/list]  - preferred */
		/* Tag: unordered list [list]*line 1*line2*line 3*line 4*line5 etc[/list]  - legacy*/
		/* Tag: ordered list [list=<list type>]*line 1*line2*line 3*line 4*line5 etc[/list] - legacy */
		/* valid list types:
			disc
			circle
			square
			decimal		1, 2, 3	(default)
			lower-roman	i, ii, iii
			upper-roman	I, II, III
			lower-alpha	a, b, c
			upper-alpha	A, B, C
		*/
		if (strpos($content,"[*]") !== FALSE) {
			$list_items = explode("[*]", $content);
		} else {
			$list_items = explode("*", $content);
		}
		if (empty($atts)) { /* unordered list */
			$start = '<ul>';
			$trailer = '</ul>';
		} else {
			$start = '<ol style="list-style-type:'.$atts[0].';">';
			$trailer = '</ol>';
		}
		$listtext = '';
		foreach($list_items as $item) {
			$t = preg_replace('/[ \t\n\r\t\0\x0B]/', "", $item);
			if ($t && $t != "<br/>" && $t != "<br>") {
				$listtext .= '<li>'.$item.'</li>';
			}
		}
	
		//List items are in the content (contains <li>)
		return $start.$listtext.$trailer;
	}
	
	private static function parseTag_ul($atts=array(), $content=null) {
		if (null === $content) return '';
		
		//Replace [li] and [/li] with HTML tags
		$content = preg_replace('%(?<!\[)\[li\](?!\])(.*?)(?<!\[)\[/li\](?!\])%s', '<li>${1}</li>', $content);
		
		//Remove characters from the beginning
		$content = preg_replace('%^.*?<li>%s', '<li>', $content);
		//Clear anything between two list items
		$content = preg_replace('%</li>.*?<li>%s', '</li><li>', $content);
		//Remove characters after the last </li>
		$content = preg_replace('%(.*</li>).*?$%s', '${1}', $content);
		
		//List items are in the content (contains <li>)
		return '<ul>'.$content.'</ul>';
	}
	
	private static function parseTag_ol($atts=array(), $content=null) {
		if (null === $content) return '';
		
		//Replace [li] and [/li] with HTML tags
		$content = preg_replace('%(?<!\[)\[li\](?!\])(.*?)(?<!\[)\[/li\](?!\])%s', '<li>${1}</li>', $content);
		
		//Remove characters from the beginning
		$content = preg_replace('%^.*?<li>%s', '<li>', $content);
		//Clear anything between two list items
		$content = preg_replace('%</li>.*?<li>%s', '</li><li>', $content);
		//Remove characters after the last </li>
		$content = preg_replace('%(.*</li>).*?$%s', '${1}', $content);
	
		//List items are in the content (contains <li>)
		return '<ol>'.$content.'</ol>';
	}
	
	private static function parseTag_table($atts=array(), $content=null) {
		if (null === $content) return '';
		
		//Replace [tr],[td],[th] and their closing tags with HTML tags
		$content = preg_replace('%(?<!\[)\[tr\](?!\])(.*?)(?<!\[)\[/tr\](?!\])%s', '<tr>${1}</tr>', $content);
		$content = preg_replace('%(?<!\[)\[(td|th)\](?!\])(.*?)(?<!\[)\[/\1\](?!\])%s', '<${1}>${2}</${1}>', $content);
		
		/*
		 * Cleanup
		 */
		//Between </tr> and <tr>
		$content = preg_replace('%</tr>.*?<tr>%s', '</tr><tr>', $content);
		//Before first <tr>
		$content = preg_replace('%^.*?<tr>%s', '<tr>', $content);
		//After last </tr>
		$content = preg_replace('%(.*</tr>).*?$%s', '${1}', $content);
		//Between <tr> and <td> or <th>
		//$content = preg_replace('%(?<=<tr>).*?(?=<(?:td|th)>)%s', '', $content);
		$content = preg_replace('%<tr>.*?<(td|th)>%s', '<tr><${1}>', $content);
		//After </td> or </th>
		$content = preg_replace('%(</td>|</th>).*?(</tr>|<td>|<th>)%s', '${1}${2}', $content);
		
		//The content contains the HTML <tr>,<td>,<th> tags
		return '<table>'.$content.'</table>';
	}
	
	private static function parseTag_hr($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<hr />';
	}
	
	private static function parseTag_spoiler($atts=array(), $content=null) {
		if (null === $content) return '';
		return '<div class="spoiler spoiler-closed"><input type="button" value="'.__('Show spoiler','qhebunel').'" class="spoiler_show"/><div>'.$content.'</div></div>';
	}
}
?>