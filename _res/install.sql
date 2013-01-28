/* User data extension table */
create table `qheb_user_ext` (
	`uid` bigint(20) unsigned,								/* UserID */
	`avatar` varchar(255),									/* Avatar image name */
	`postcount` int(10) unsigned not null default 0,		/* Post count */
	`rank` tinyint(3) default 1,							/* Rank ID - 0: banned; 1:normal user; 2: mod; 3: admin; */
	`signature` varchar(1000),								/* User signature */
	`malname` varchar(50),									/* MAL username */
	`vndbid` varchar(50),									/* vndb user id */
	`anidbid` varchar(50),									/* AniDB user profile id */
	`twittername` varchar(50),								/* Twitter username */
	`daname` varchar(50),									/* deviantArt username */
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
	`name` varchar(50) not null,							/* Display name */
	`description` varchar(1000),							/* Longer description (eg: what can it be awarded for) */
	`icon` varchar(255) not null,							/* Icon file name */
	`display` tinyint(1) not null default 0,				/* Display icon next to every post by the user */
	`claimable` tinyint(1) not null default 0,				/* Can users claim the badge for themselves (1), or only a mod can award it (0) */
	`bgid` int(10) unsigned not null,						/* Badge group ID */
	primary key (`bid`),
	unique index `name` (`name`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* User and Badge linking */
create table `qheb_user_badge_links` (
	`bid` int(10) unsigned not null,						/* Badge ID */
	`uid` bigint(20) unsigned not null,						/* UserID */
	`startdate` datetime not null,							/* When was the badge given to the user */
	unique index `ids` (`bid`, `uid`),
	index `uid` (`uid`)
	) character set utf8 collate utf8_unicode_ci engine MyISAM;

/* Badge groups */
create table `qheb_badge_groups` (
	`bgid` int(10) unsigned auto_increment,					/* Badge group ID */
	`name` varchar(50) not null,							/* UserID */
	`climit` int(10) unsigned,								/* Claim limit - how many badges can the user claim from this group */
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
	primary key (`tid`),
	index `cat` (`catid`, `lastpostid`),
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


/* View for the WP_Users table - used as an alias*/
create or replace view `qheb_wp_users` as select * from `wp_users`;

/* Special groups */
insert into `qheb_user_groups` values (1, 'Everyone', 0);
insert into `qheb_user_groups` values (2, 'Registered users', 0);
insert into `qheb_user_groups` values (3, 'Moderators', 1);
alter table `qheb_user_groups` auto_increment=11;

/* Insert extended data for all users */
insert into `qheb_user_ext` (`uid`) select `ID` from `qheb_wp_users`;

