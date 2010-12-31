<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_ppforum_forums = 1
');

tx_pplib_div::addWrappedPluginToSetup($_EXTKEY, '_pi1', 'list_type', true, array(
	'classname' => 'tx_ppforum_rpi1',
));

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY] = Array(
	'hookObjList' => Array(),
);

tx_pplib_conf::registerClasses(array(
	'tx_ppforum_rpi1'     => 'EXT:pp_forum/pi1/class.tx_ppforum_rpi1.php',
	'tx_ppforum_base'     => 'EXT:pp_forum/pi1/class.tx_ppforum_base.php',
	'tx_ppforum_forum'    => 'EXT:pp_forum/pi1/class.tx_ppforum_forum.php',
	'tx_ppforum_forumsim' => 'EXT:pp_forum/pi1/class.tx_ppforum_forumsim.php',
	'tx_ppforum_topic'    => 'EXT:pp_forum/pi1/class.tx_ppforum_topic.php',
	'tx_ppforum_message'  => 'EXT:pp_forum/pi1/class.tx_ppforum_message.php',
	'tx_ppforum_user'     => 'EXT:pp_forum/pi1/class.tx_ppforum_user.php',
	'tx_ppforum_smileys'  => 'EXT:pp_forum/pi1/class.tx_ppforum_smileys.php',
	'tx_ppforum_latests'  => 'EXT:pp_forum/pi1/class.tx_ppforum_latests.php',
));

tx_pplib_div::addExtensionDefaultTyposcript($_EXTKEY);

?>