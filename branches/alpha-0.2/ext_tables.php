<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::allowTableOnStandardPages('tx_ppforum_forums');

$TCA['tx_ppforum_forums'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums',		
		'label' => 'title',	
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'sortby' => 'sorting',	
		'delete' => 'deleted',
		'enablecolumns' => Array (		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tx_ppforum_forums.tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ppforum_forums.gif',
		'hideAtCopy' => TRUE,
		'prependAtCopy' => '(copy %s)',
		'shadowColumnsForNewPlaceholders' => 'sys_language_uid,l18n_parent',

		'useColumnsForDefaultValues'=>'parent, notopic, notoolbar',

		//l18n :
		'languageField' => 'sys_language_uid',
		'transOrigPointerField' => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
	),
	'feInterface' => Array (
		'fe_admin_fieldList' => 'hidden, title, parent',
	)
);


t3lib_extMgm::allowTableOnStandardPages('tx_ppforum_topics');

$TCA['tx_ppforum_topics'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics',		
		'label' => 'title',	
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY crdate',	
		'delete' => 'deleted',	
		'enablecolumns' => Array (		
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tx_ppforum_topics.tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ppforum_topics.gif',
	),
	'feInterface' => Array (
		'fe_admin_fieldList' => 'hidden, author, title, message, forum',
	)
);


t3lib_extMgm::allowTableOnStandardPages('tx_ppforum_messages');

$TCA['tx_ppforum_messages'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_messages',		
		'label' => 'uid',	
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY crdate',	
		'delete' => 'deleted',	
		'enablecolumns' => Array (
			//*** The hidden col still exists and work, but a hidden message is visible to a guad user
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tx_ppforum_messages.tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_ppforum_messages.gif',

		'adminOnly' => TRUE,
	),
	'feInterface' => Array (
		'fe_admin_fieldList' => 'hidden, author, message',
	)
);


t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1'] = 'layout,select_key,pages,recursive';
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1'] = 'pi_flexform,tx_ppsearchengine_isengine';


if (t3lib_extMgm::isLoaded('pp_rsslatestcontent')) {
	$TCA['tt_content']['types']['pp_rsslatestcontent_rssfeed']['subtypes_excludelist'][$_EXTKEY.'_pi1'] = $TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1'];
	$TCA['tt_content']['types']['pp_rsslatestcontent_rssfeed']['subtypes_addlist'][$_EXTKEY.'_pi1'] = $TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1'];
}

t3lib_extMgm::addPlugin(array('LLL:EXT:pp_forum/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');
t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:'.$_EXTKEY.'/flexform.xml');


//t3lib_extMgm::addStaticFile($_EXTKEY,'pi1/static/','Popy Forum');
?>