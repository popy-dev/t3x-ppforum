#
# Table structure for table 'tx_ppforum_forums'
#
CREATE TABLE tx_ppforum_forums (
	#System fields
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	sorting int(10) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,

	#Localization fields
	sys_language_uid int(11) DEFAULT '0' NOT NULL,
	l18n_parent int(11) DEFAULT '0' NOT NULL,
	l18n_diffsource mediumblob NOT NULL,

	title varchar(120) DEFAULT '' NOT NULL,
	description varchar(255) DEFAULT '' NOT NULL,
	parent int(11) DEFAULT '0' NOT NULL,
	notopic tinyint(4) DEFAULT '0' NOT NULL,
	notoolbar tinyint(4) DEFAULT '0' NOT NULL,

	hidetopic tinyint(4) DEFAULT '0' NOT NULL,
	hidemessage tinyint(4) DEFAULT '0' NOT NULL,

	#Access fields
	readaccess mediumblob NOT NULL,
	readaccess_mode varchar(10) DEFAULT '' NOT NULL,
	writeaccess mediumblob NOT NULL,
	writeaccess_mode varchar(10) DEFAULT '' NOT NULL,
	guardaccess mediumblob NOT NULL,
	guardaccess_mode varchar(10) DEFAULT '' NOT NULL,
	adminaccess mediumblob NOT NULL,
	adminaccess_mode varchar(10) DEFAULT '' NOT NULL,

	#Restrict fields
	newtopic_restrict varchar(10) DEFAULT '' NOT NULL,
	reply_restrict varchar(10) DEFAULT '' NOT NULL,
	edit_restrict varchar(10) DEFAULT '' NOT NULL,
	delete_restrict varchar(10) DEFAULT '' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_ppforum_topics'
#
CREATE TABLE tx_ppforum_topics (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	status tinyint(4) DEFAULT '0' NOT NULL,
	author int(11) DEFAULT '0' NOT NULL,
	title varchar(120) DEFAULT '' NOT NULL,
	message text NOT NULL,
	forum int(11) DEFAULT '0' NOT NULL,
	pinned tinyint(4) DEFAULT '0' NOT NULL,
	nosmileys tinyint(4) DEFAULT '0' NOT NULL,
	parser varchar(20) DEFAULT '' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_ppforum_messages'
#
CREATE TABLE tx_ppforum_messages (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	author int(11) DEFAULT '0' NOT NULL,
	message text NOT NULL,
	topic int(11) DEFAULT '0' NOT NULL,
	nosmileys tinyint(4) DEFAULT '0' NOT NULL,
	parser varchar(20) DEFAULT '' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);


#
# Table structure for table 'tx_ppforum_userpms'
#
CREATE TABLE `tx_ppforum_userpms` (
  `rel_id` int(11) NOT NULL default '0',
  `rel_table` varchar(10) NOT NULL default '',
  `rel_type` varchar(10) NOT NULL default '',
  `user_id` int(11) NOT NULL default '0',
  `parent` int(11) NOT NULL default '0',
  PRIMARY KEY  (`rel_id`,`rel_table`,`rel_type`),
  KEY `usertype` (`user_id`,`rel_type`)
);