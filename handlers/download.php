<?php
/**
 * Qhebunel
 * Download handler
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

if (preg_match('%^(\d+)-([^/]+)$%s', $section_params, $regs)) {
	$attachment_id = $regs[1];
	$attachment_safe_name = $regs[2];
	
	global $wpdb;
	$attachment = $wpdb->get_row(
		$wpdb->prepare(
			'select * from `qheb_attachments` where `aid`=%d limit 1;',
			$attachment_id
		),
		ARRAY_A
	);
	
	if (is_array($attachment)) {
		/*
		 * Check the attachment name and die if there's a mismatch.
		 * (Redirect to the correct URL would be a security hole.)
		 */
		if ($attachment_safe_name != $attachment['safename']) {
			Qhebunel::redirect_to_error_page();
		}
		
		/*
		 * Check user permissions
		 */
		$has_permission = QhebunelUser::has_permission_for_attachment($attachment_id);
		if (!$has_permission) {
			Qhebunel::redirect_to_error_page();
		}
		
		/*
		 * Check whether the file exists, and send a 404 error if it doesn't.
		 */
		$path = WP_CONTENT_DIR.'/'.QhebunelFiles::get_attachment_path($attachment['uid'], $attachment['pid'], $attachment['aid'], $attachment['safename']);
		if (!is_file($path)) {
			$title = __('Missing attachment', 'qhebunel');
			$message = __('The requested file is missing from the server.', 'qhebunel');
			wp_die($message, $title, array('response'=>404));
		}
		
		/*
		 * Check the filesize, and report if the file is corrupted.
		 */
		if (filesize($path) != $attachment['size']) {
			$title = __('Corrupted attachment', 'qhebunel');
			$message = __('The requested file is missing from the server.', 'qhebunel');
			wp_die($message, $title, array('response'=>404));
		}
		
		/*
		 * Update download count only if it's a first request for the file,
		 * and not a range request.
		 */
		if (!isset($_SERVER['HTTP_RANGE'])) {
			$wpdb->query(
				$wpdb->prepare(
					'update `qheb_attachments` set `dlcount`=`dlcount`+1 where `aid`=%d',
					$attachment_id
				)
			);
		}
		
		/*
		 * Send file headers and stream the file.
		 */
		QhebunelFiles::stream_file($path, $attachment['name']);
		
		
		
	} else {
		//Attachment ID not found in the database
		Qhebunel::redirect_to_error_page();
	}
	
} else {
	//Invalid URL (does not match regex)
	Qhebunel::redirect_to_error_page();
}
?>