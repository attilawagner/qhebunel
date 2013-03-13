/* User data extension table */
create table `qheb_user_ext` (
	`uid` bigint(20) unsigned,								/* UserID */
	`avatar` varchar(255),									/* Avatar image name */
	`postcount` int(10) unsigned not null default 0,		/* Post count */
	`signature` varchar(1000),								/* User signature */
	`banned` tinyint(1) unsigned not null default 0,		/* Flag for banning users from the site */
	primary key (`uid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Warns */
create table `qheb_user_warns` (
	`wid` int(10) unsigned auto_increment,					/* Warn ID */
	`uid` bigint(20) unsigned not null,						/* UserID */
	`issuer` bigint(20) unsigned not null,					/* Mod user ID */
	`reason` varchar(200),									/* Public message */
	`modreason` varchar(200),								/* Note for mods */
	`warndate` datetime not null,							/* Issue date */
	primary key (`wid`),
	index `uid` (`uid`),
	index `mod` (`issuer`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* User groups */
create table `qheb_user_groups` (
	`gid` int(10) unsigned auto_increment,					/* Group ID */
	`name` varchar(50) not null,	 						/* Display name of the group */
	`prominent` tinyint(1) not null default 0,				/* Show group as a tab on user list */
	primary key (`gid`),
	unique index `name` (`name`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* User and Group linking */
create table `qheb_user_group_links` (
	`gid` int(10) unsigned not null,						/* Group ID */
	`uid` bigint(20) unsigned not null,						/* UserID */
	unique index `ids` (`gid`, `uid`),
	index `uid` (`uid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Badges */
create table `qheb_badges` (
	`bid` int(10) unsigned auto_increment,					/* Badge ID */
	`bgid` int(10) unsigned not null,						/* Badge group ID */
	`name` varchar(50) not null,							/* Display name */
	`description` varchar(1000),							/* Longer description (eg: what can it be awarded for) */
	`largeimage` varchar(255),								/* Icon file name - displayed on profile page */
	`smallimage` varchar(255),								/* Icon file name - displayed next to posts */
	primary key (`bid`),
	unique index `name` (`name`),
	index `group` (`bgid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* User and Badge linking */
create table `qheb_user_badge_links` (
	`bid` int(10) unsigned not null,						/* Badge ID */
	`uid` bigint(20) unsigned not null,						/* UserID */
	`startdate` datetime not null,							/* When was the badge given to the user */
	`show` tinyint(1) unsigned not null default 0,			/* Show the badge next to posts - 0:no, 1:yes, 2:forced */
	unique index `ids` (`bid`, `uid`),
	index `uid` (`uid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Badge groups */
create table `qheb_badge_groups` (
	`bgid` int(10) unsigned auto_increment,					/* Badge group ID */
	`name` varchar(50) not null,							/* Group name */
	`climit` int(10) unsigned,								/* Claim limit - how many badges can the user claim from this group (if 0, only mods can award these badges) */
	`hidden` tinyint(1) unsigned not null default 1,		/* If the group is hidden, users won't be able to browse it's content. */
	unique index `name` (`name`),
	primary key (`bgid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Categories */
create table `qheb_categories` (
	`catid` int(10) unsigned auto_increment,				/* Category ID */
	`name` varchar(100) not null,							/* Display name */
	`description` varchar(500),								/* Description */
	`parent` int(10) unsigned not null,						/* Parent category ID */
	`orderid` int(10) unsigned not null,					/* Position when listing */
	`uri` varchar(100) not null,							/* URL component for the category */
	primary key (`catid`),
	index `ordering` (`parent`, `orderid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Category permissions */
create table `qheb_category_permissions` (
	`catid` int(10) unsigned not null,						/* Category ID */
	`gid` int(10) unsigned not null,						/* Group ID */
	`access` tinyint(1) not null default 0,					/* Access level - 0: none, 1: read, 2:write, 3:start */
	unique index `ids` (`catid`, `gid`),
	index `gid` (`gid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Threads */
create table `qheb_threads` (
	`tid` bigint(20) unsigned auto_increment,				/* Thread ID */
	`title` varchar(200) not null,							/* Title of the thread */
	`catid` int(10) unsigned not null,						/* Parent category */
	`startdate` datetime not null,							/* Start date */
	`starter` bigint(20) unsigned not null,					/* Starter user ID */
	`closedate` datetime,									/* Close date */
	`closer` bigint(20) unsigned,							/* Closer user ID */
	`postcount` bigint(20) unsigned not null default 1,		/* Post count */
	`lastpostid` bigint(20) unsigned,						/* Last post ID */
	`views` int(10) unsigned default 0,						/* View count */
	`uri` varchar(100) not null,							/* URL component for the thread */
	`pinned` tinyint(1) unsigned not null default 0,		/* Flag to mark pinned threads */
	primary key (`tid`),
	index `cat` (`catid`, `pinned` desc, `lastpostid` desc),
	index `user` (`starter`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Posts */
create table `qheb_posts` (
	`pid` bigint(20) unsigned auto_increment,				/* Post ID */
	`tid` bigint(20) unsigned not null,						/* Thread ID */
	`uid` bigint(20) unsigned not null,						/* UserID */
	`text` varchar(10000) not null,							/* Message body of the post */
	`postdate` datetime not null,							/* Post date */
	`editdate` datetime,									/* Last edit date */
	`editor` bigint(20) unsigned,							/* Last editor user ID (can be a mod or the original poster) */
	`editreason` varchar(200),								/* Last edit reason */
	`flag` tinyint(1) unsigned not null default 0,			/* Flag: 0-Nothing; 1-Deletion unconfirmed; 2-Reported */
	primary key (`pid`),
	index `thread` (`tid`, `postdate`),
	index `user` (`uid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Attachments */
create table `qheb_attachments` (
	`aid` bigint(20) unsigned auto_increment,				/* Attachment ID */
	`pid` bigint(20) unsigned not null,						/* Post ID */
	`uid` bigint(20) unsigned not null,						/* User ID */
	`name` varchar(255) not null,							/* Original filename */
	`safename` varchar(255) not null,						/* Base filename as saved in the user's directory (excluding id) */
	`size` bigint(20) unsigned not null,					/* File size */
	`upload` datetime not null,								/* Upload date */
	`dlcount` bigint(20) unsigned not null default 0,		/* Download count */
	primary key (`aid`),
	index `post` (`pid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Thread visits */
create table `qheb_visits` (
	`tid` bigint(20) unsigned not null,						/* Thread ID */
	`uid` bigint(20) unsigned not null,						/* User ID */
	`visitdate` datetime not null,							/* Date of last visit */
	`visitcount` bigint(20) unsigned,						/* Number of visits by the user */
	primary key (`tid`,`uid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Private messages */
create table `qheb_privmessages` (
	`mid` bigint(20) unsigned auto_increment,				/* Message ID */
	`from` bigint(20) unsigned not null,					/* User ID */
	`to` bigint(20) unsigned not null,						/* User ID */
	`text` varchar(10000) not null,							/* Message body */
	`sentdate` datetime not null,							/* Date the user sent the message */
	`readdate` datetime null,								/* Date the user read the message */
	primary key (`mid`),
	index `from` (`from` asc, `mid` desc),
	index `to` (`to` asc, `mid` desc),
	index `unread` (`to`, `readdate`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Stored procedure to log user visit in a thread */
delimiter ;;;
create procedure `qheb_log_user_visit` (in p_tid int, in p_uid int, in reference_time datetime, in expiration int)
begin
  declare is_expired bool default true;
  declare last_visit datetime;
  declare last_post datetime;
  set last_visit=(select `visitdate` from `qheb_visits` where `tid`=p_tid and `uid`=p_uid);
  set is_expired=IF(last_visit is null, true, timestampdiff(second,last_visit,reference_time)>expiration);
  if is_expired=false then
	set last_post=(select greatest(`postdate`,`editdate`) from `qheb_posts` where `tid`=p_tid order by `pid` desc limit 1);
    set is_expired=(last_post>reference_time);
  end if;
  if is_expired=true then
	insert into `qheb_visits` (`tid`,`uid`,`visitdate`,`visitcount`) values (p_tid,p_uid,reference_time,1)
      on duplicate key update `visitcount`=`visitcount`+1, `visitdate`=reference_time;
  end if;
end;
;;;
delimiter ;

/* View for the WP_Users table - used as an alias*/
create or replace view `qheb_wp_users` as select * from `wp_users`;

/* Special groups */
insert into `qheb_user_groups` values (1, 'Everyone', 0);
insert into `qheb_user_groups` values (2, 'Registered users', 0);
insert into `qheb_user_groups` values (3, 'Moderators', 1);
alter table `qheb_user_groups` auto_increment=11;

/* Insert extended data for all users */
insert into `qheb_user_ext` (`uid`) select `ID` from `qheb_wp_users`;

