<?php
/**
 * Qhebunel
 * AJAX backend for quoting a post.
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $section_params;

/*
 * Prevent hotlinking:
 * Only allow request that are referred from this site, or the ones containing a range request.
 */
$site_host = parse_url(get_site_url(), PHP_URL_HOST);
$ref_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
//TODO: check only for example.com instead of subdomain.example.com
if ($site_host != $ref_host && !isset($_SERVER['HTTP_RANGE'])) {
	//Hotlink
	Qhebunel::redirect_to_error_page();
}

if (preg_match('%^(\d+)$%s', $section_params, $regs)) {
	$post_id = $regs[1];
	
	echo(QhebunelUI::get_quote_for_post($post_id));
	
} else {
	//Invalid URL (does not match regex)
	Qhebunel::redirect_to_error_page();
}
?>