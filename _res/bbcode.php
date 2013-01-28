<?php /*

**************************************************************************

Plugin Name:  BBCode
Plugin URI:   http://www.viper007bond.com/wordpress-plugins/bbcode/
Description:  Implements <a href="http://en.wikipedia.org/wiki/BBCode">BBCode</a> in posts. Requires WordPress 2.5+ or WPMU 1.5+.
Version:      1.0.1
Author:       Viper007Bond
Author URI:   http://www.viper007bond.com/

**************************************************************************

Copyright (C) 2008 Viper007Bond

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

**************************************************************************/

class BBCode {

	// Plugin initialization
	function BBCode() {
		// This version only supports WP 2.5+ (learn to upgrade please!)
		if ( !function_exists('add_shortcode') ) return;

		// Register the shortcodes
		add_shortcode( 'b' , array(&$this, 'shortcode_bold') );
		add_shortcode( 'i' , array(&$this, 'shortcode_italics') );
		add_shortcode( 'u' , array(&$this, 'shortcode_underline') );
		add_shortcode( 'url' , array(&$this, 'shortcode_url') );
		add_shortcode( 'link' , array(&$this, 'shortcode_url') );
		add_shortcode( 'img' , array(&$this, 'shortcode_image') );
		add_shortcode( 'quote' , array(&$this, 'shortcode_quote') );
		add_shortcode( 'blockquote' , array(&$this, 'shortcode_quote') );
		add_shortcode( 'left' , array(&$this, 'shortcode_left') );
		add_shortcode( 'right' , array(&$this, 'shortcode_right') );
		add_shortcode( 'center' , array(&$this, 'shortcode_center') );
		add_shortcode( 'justify' , array(&$this, 'shortcode_justify') );
		add_shortcode( 'code' , array(&$this, 'shortcode_code') );
		add_shortcode( 'color' , array(&$this, 'shortcode_color') );
		add_shortcode( 'email' , array(&$this, 'shortcode_email') );
		add_shortcode( 'html' , array(&$this, 'shortcode_html') );
		add_shortcode( 'list' , array(&$this, 'shortcode_list') );
		add_shortcode( 'size' , array(&$this, 'shortcode_size') );
	}


	// No-name attribute fixing
	function attributefix( $atts = array() ) {
		if ( empty($atts[0]) ) return $atts;

		if ( 0 !== preg_match( '#=("|\')(.*?)("|\')#', $atts[0], $match ) )
			$atts[0] = $match[2];

		return $atts;
	}


	// Bold shortcode
	function shortcode_bold( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<strong>' . do_shortcode( $content ) . '</strong>';
	}

	// Italics shortcode
	function shortcode_italics( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<em>' . do_shortcode( $content ) . '</em>';
	}

	// Underline shortcode
	function shortcode_underline( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<span style="text-decoration:underline">' . do_shortcode( $content ) . '</span>';
	}

	// URL/Link shortcode
	function shortcode_url( $atts = array(), $content = NULL ) {
		$atts = $this->attributefix( $atts );
		if ( isset($atts[0]) ) { // [url="http://www.google.com/"]Google[/url]
			$url = $atts[0];
			$text = $content;
		} else { // [url]http://www.google.com/[/url]
			$url = $text = $content;
		}
		if ( empty($url) ) return '';
		if ( empty($text) ) $text = $url;
		return '<a href="' . $url . '">' . do_shortcode( $text ) . '</a>';
	}

	// Img shortcode
	function shortcode_image( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<img src="' . $content . '" alt="" />';
	}

	// Quote/Blockquote shortcode
	function shortcode_quote( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<blockquote>' . do_shortcode( $content ) . '</blockquote>';
	}
	
	// Left shortcode
	function shortcode_left( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<div style="text-align:left;">'.do_shortcode($content).'</div>';
	}
	// Right shortcode
	function shortcode_right( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<div style="text-align:right;">'.do_shortcode($content).'</div>';
	}
	// Center shortcode
	function shortcode_center( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<div style="text-align:center">'.do_shortcode($content).'</div>';
	}
	// Justify shortcode
	function shortcode_justify( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<div style="text-align:justify">'.do_shortcode($content).'</div>';
	}
	
	// Code //TODO: syntax hl
	function shortcode_code( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		return '<div style="margin:10px;font-family:monospace;text-align:left;">'.$content.'</div>';
	}
	
	// Color
	function shortcode_color( $atts = array(), $content = NULL ) {
		static $aColors = array(
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
		if ( NULL === $content ) return '';
		if(array_key_exists($atts[0], $aColors)) {
			return '<span style="color:{$aColors[$parm]}">'.do_shortcode($content).'</span>';
		} elseif(preg_match("/(#[a-fA-F0-9]{3,6})/", $atts[0], $matches)) {
			return '<span style="color:'.$matches[1].'">'.do_shortcode($content).'</span>';
		} else {
			return do_shortcode($content);
		}
	}
	
	// Email shortcode
	function shortcode_email( $atts = array(), $content = NULL ) {
		$atts = $this->attributefix( $atts );
		if ( isset($atts[0]) ) { // [email=asd@asd.as]Asd Asd[/email]
			$e = $atts[0];
			$text = $content;
		} else { // [email]asd@asd.as[/email]
			$e = $text = $content;
		}
		if ( empty($e) ) return '';
		if ( empty($text) ) $text = $url;
		return '<a href="mailto:'.$e.'">'.$text.'</a>';
	}
	
	// HTML
	function shortcode_html( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
		$content = str_replace("\r\n", " ", $content);
		$content = html_entity_decode($content, ENT_QUOTES);
		return $content;
	}
	
	// List
	function shortcode_list( $atts = array(), $content = NULL ) {
		if ( NULL === $content ) return '';
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
			$listitems = explode("[*]", $content);
		} else {
			$listitems = explode("*", $content);
		}
		if ($parm == '') {	/* unordered list */
			$listtext = '<ul class="bbcode">';
			$trailer = '</ul>';
		} else {
			$listtext = '<ol class="bbcode" style="list-style-type:'.$atts[0].';">';
			$trailer = '</ol>';
		}
		foreach($listitems as $item) {
			$t = preg_replace('/[ \t\n\r\t\0\x0B]/', "", $item);
			if ($t && $t != "<br/>" && $t != "<br>") {
				$listtext .= '<li class="bbcode">'.do_shortcode($item).'</li>';
			}
		}
		return $listtext.$trailer;
	}
	
	// Size
	function shortcode_size( $atts = array(), $content = NULL ) {
		if(is_numeric($parm) && $parm > 0 && $parm < 38) {
			return '<span style="font-size:'.$parm.'px">'.$content.'</span>';
		} else {
			return $content;
		}
	}
}

// Start this plugin once all other plugins are fully loaded
add_action( 'plugins_loaded', create_function( '', 'global $BBCode; $BBCode = new BBCode();' ) );

?>