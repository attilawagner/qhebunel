<?php
/**
 * Qhebunel
 * Error page
 * 
 * This page is displayed when a handler runs into an error.
 */
if (!defined('QHEBUNEL_REQUEST') || QHEBUNEL_REQUEST !== true) die;

echo('<div class="qheb-error-message">'.__('There was an error processing your request. Please go back to the previous page and try again.', 'qhebunel').'</div>');
?>
