<?php
/**
 * Qhebunel
 * AJAX backend for quoting a post.
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

global $sectionParams;

/*
 * Prevent hotlinking:
 * Only allow request that are referred from this site, or the ones containing a range request.
 */
$siteHost = parse_url(get_site_url(), PHP_URL_HOST);
$refHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
//TODO: check only for example.com instead of subdomain.example.com
if ($siteHost != $refHost && !isset($_SERVER['HTTP_RANGE'])) {
	//Hotlink
	Qhebunel::redirectToErrorPage();
}

if (preg_match('%^(\d+)$%s', $sectionParams, $regs)) {
	$postId = $regs[1];
	
	echo(QhebunelUI::getQuoteForPost($postId));
	
} else {
	//Invalid URL (does not match regex)
	Qhebunel::redirectToErrorPage();
}
?>