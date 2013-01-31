<?php
/*
 * Permissions.
 */
define('QHEBUNEL_PERMISSION_NONE', 0);
define('QHEBUNEL_PERMISSION_READ', 1);
define('QHEBUNEL_PERMISSION_WRITE', 2);
define('QHEBUNEL_PERMISSION_START', 3);

/*
 * Avatar sizes
 */
define('QHEBUNEL_AVATAR_MAX_WIDTH', 100);
define('QHEBUNEL_AVATAR_MAX_HEIGHT', 100);
define('QHEBUNEL_AVATAR_MAX_FILESIZE', 307200); //300k

/*
 * Date formats
 */
//TODO: make these into options on the GENERAL page
define('QHEBUNEL_DATEFORMAT_LIST', 'F j, Y g:i a'); //Displayed in list
define('QHEBUNEL_DATEFORMAT_LISTTIP', 'F j, Y g:i a'); //Displayed in list as tooltip

//TODO make this into an option
define('QHEBUNEL_POSTS_PER_PAGE', 10);
define('QHEBUNEL_ATTACHMENT_MAX_SIZE', 5242880); //5M
define('QHEBUNEL_ATTACHMENT_LIMIT_PER_POST', 5);
define('QHEBUNEL_ATTACHMENT_ALLOWED_TYPES', 'jpg,jpeg,jps,gif,png,bmp,psd,tga,tiff,tif,rar,zip,txt,doc,docx,xls,xlsx,ppt,pptx');
?>