<?php

########################################################################
# Extension Manager/Repository config file for ext: "pp_forum"
#
# Auto generated 27-04-2010 13:05
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Popy Forum',
	'description' => 'A forum plugin designed to be extensible, powerfull, and with this challenge : the plugin is a USER cObj, not a USER_INT ! This is only a ALPHA version, read the README.txt file for more informations.',
	'category' => 'plugin',
	'author' => 'Popy',
	'author_email' => 'popy.dev@gmail.com',
	'shy' => '',
	'dependencies' => 'cms,pp_lib',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.3.0',
	'constraints' => array(
		'depends' => array(
			'cms' => '',
			'pp_lib' => '1.5.1',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:86:{s:9:"ChangeLog";s:4:"94ab";s:10:"README.txt";s:4:"b1c4";s:12:"ext_icon.gif";s:4:"1bdc";s:17:"ext_localconf.php";s:4:"acb1";s:14:"ext_tables.php";s:4:"3292";s:14:"ext_tables.sql";s:4:"cad4";s:28:"ext_typoscript_constants.txt";s:4:"1074";s:24:"ext_typoscript_setup.txt";s:4:"a70d";s:12:"flexform.xml";s:4:"b094";s:26:"icon_tx_ppforum_forums.gif";s:4:"475a";s:28:"icon_tx_ppforum_messages.gif";s:4:"1ad2";s:31:"icon_tx_ppforum_messages__h.gif";s:4:"cdb4";s:26:"icon_tx_ppforum_topics.gif";s:4:"f370";s:10:"index.html";s:4:"e71f";s:16:"locallang_db.xml";s:4:"44e8";s:25:"tx_ppforum_forums.tca.php";s:4:"ab92";s:27:"tx_ppforum_messages.tca.php";s:4:"e399";s:25:"tx_ppforum_topics.tca.php";s:4:"e34c";s:24:"css_templates/index.html";s:4:"e71f";s:38:"css_templates/archetype/forums-old.css";s:4:"f4dd";s:34:"css_templates/archetype/forums.css";s:4:"0ddb";s:38:"css_templates/archetype/global-old.css";s:4:"f4ed";s:34:"css_templates/archetype/global.css";s:4:"cfd1";s:34:"css_templates/archetype/index.html";s:4:"e71f";s:37:"css_templates/archetype/lock-icon.gif";s:4:"c0df";s:40:"css_templates/archetype/messages-old.css";s:4:"0c3b";s:36:"css_templates/archetype/messages.css";s:4:"a594";s:41:"css_templates/archetype/square-update.gif";s:4:"c0fb";s:34:"css_templates/archetype/square.gif";s:4:"3434";s:34:"css_templates/archetype/sticky.gif";s:4:"b4d2";s:38:"css_templates/archetype/topics-old.css";s:4:"dfa9";s:34:"css_templates/archetype/topics.css";s:4:"f224";s:37:"css_templates/archetype/users-old.css";s:4:"5bf6";s:33:"css_templates/archetype/users.css";s:4:"12ba";s:26:"css_templates/green/bg.jpg";s:4:"c5ee";s:30:"css_templates/green/forums.css";s:4:"a149";s:30:"css_templates/green/global.css";s:4:"0ed5";s:32:"css_templates/green/messages.css";s:4:"f6bf";s:30:"css_templates/green/topics.css";s:4:"3642";s:32:"css_templates/macmade/forums.css";s:4:"590b";s:32:"css_templates/macmade/global.css";s:4:"4d95";s:32:"css_templates/macmade/index.html";s:4:"e71f";s:34:"css_templates/macmade/messages.css";s:4:"0bbf";s:32:"css_templates/macmade/topics.css";s:4:"ea37";s:31:"css_templates/macmade/users.css";s:4:"c43e";s:22:"doc/test de charge.php";s:4:"8580";s:19:"doc/wizard_form.dat";s:4:"be2f";s:20:"doc/wizard_form.html";s:4:"9a58";s:29:"pi1/class.tx_ppforum_base.php";s:4:"d22d";s:30:"pi1/class.tx_ppforum_forum.php";s:4:"07ec";s:33:"pi1/class.tx_ppforum_forumsim.php";s:4:"92a0";s:32:"pi1/class.tx_ppforum_latests.php";s:4:"fa6c";s:32:"pi1/class.tx_ppforum_message.php";s:4:"8b1c";s:28:"pi1/class.tx_ppforum_pi1.php";s:4:"7106";s:29:"pi1/class.tx_ppforum_rpi1.php";s:4:"a5a0";s:32:"pi1/class.tx_ppforum_smileys.php";s:4:"b75d";s:30:"pi1/class.tx_ppforum_topic.php";s:4:"99f9";s:29:"pi1/class.tx_ppforum_user.php";s:4:"0ad3";s:34:"pi1/class.tx_pplib_formtoolkit.php";s:4:"10fb";s:17:"pi1/locallang.php";s:4:"8fc7";s:23:"res/belink.resizable.js";s:4:"4751";s:14:"res/index.html";s:4:"e71f";s:15:"res/pp_forum.js";s:4:"662a";s:24:"res/resizable-corner.png";s:4:"d22c";s:22:"res/smilets/README.txt";s:4:"3e97";s:35:"res/smilets/aime-firefox-pas-ie.gif";s:4:"4cbe";s:21:"res/smilets/angry.gif";s:4:"6291";s:23:"res/smilets/biggrin.gif";s:4:"9138";s:21:"res/smilets/blink.gif";s:4:"2d18";s:24:"res/smilets/blushing.gif";s:4:"bf36";s:20:"res/smilets/cool.gif";s:4:"b3e7";s:22:"res/smilets/crying.gif";s:4:"7618";s:19:"res/smilets/dry.gif";s:4:"829e";s:20:"res/smilets/ermm.gif";s:4:"5de5";s:20:"res/smilets/fear.gif";s:4:"1fe8";s:21:"res/smilets/happy.gif";s:4:"3b84";s:19:"res/smilets/huh.gif";s:4:"34ee";s:22:"res/smilets/index.html";s:4:"e71f";s:21:"res/smilets/laugh.gif";s:4:"97f6";s:21:"res/smilets/pinch.gif";s:4:"aa89";s:24:"res/smilets/rolleyes.gif";s:4:"6edb";s:21:"res/smilets/sleep.gif";s:4:"8cd7";s:22:"res/smilets/tongue.gif";s:4:"8c39";s:21:"res/smilets/wacko.gif";s:4:"b06e";s:25:"res/smilets/whistling.gif";s:4:"e79d";s:20:"res/smilets/wink.gif";s:4:"a401";}',
	'suggests' => array(
	),
);

?>