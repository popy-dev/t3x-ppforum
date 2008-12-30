<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_ppforum_forums'] = Array (
	'ctrl' => $TCA['tx_ppforum_forums']['ctrl'],
	'interface' => Array (
		'showRecordFieldList' => 'hidden,title,parent'
	),
	'feInterface' => $TCA['tx_ppforum_forums']['feInterface'],
	'columns' => Array (
		'hidden' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'sys_language_uid' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.language',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => Array(
					//Array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages',-1),
					Array('LLL:EXT:lang/locallang_general.php:LGL.default_value',0)
				)
			)
		),
		'l18n_parent' => Array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.l18n_parent',
			'config' => Array (
				'type' => 'select',
				'items' => Array (
					Array('', 0),
				),
				'foreign_table' => 'tx_ppforum_forums',
				'foreign_table_where' => 'AND tx_ppforum_forums.pid=###CURRENT_PID### AND tx_ppforum_forums.sys_language_uid IN (-1,0)',
			)
		),
		'title' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.title',		
			'config' => Array (
				'type' => 'input',	
				'size' => '30',	
				'max' => '120',	
				'eval' => 'required,trim',
			)
		),
		'description' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.description',		
			'config' => Array (
				'type' => 'input',	
				'size' => '50',	
				'max' => '255',	
				'eval' => '',
			)
		),
		'parent' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.parent',		
			'l10n_mode' => 'exclude',		
			'config' => Array (
				'type' => 'group',	
				'internal_type' => 'db',	
				'allowed' => 'tx_ppforum_forums',	
				'size' => 1,	
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'ftype' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.ftype',		
			'l10n_mode' => 'exclude',
			'config' => Array (
				'type' => 'select',
				'size' => 1,
				'items' => array(
					array(),
					array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.ftype.topic_shortcut', 'topic_shortcut'),
				),
			)
		),
		'notopic' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.notopic',		
			'l10n_mode' => 'exclude',		
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'notoolbar' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.notoolbar',		
			'l10n_mode' => 'exclude',		
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'hidetopic' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.hidetopic',		
			'l10n_mode' => 'exclude',		
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'hidemessage' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.hidemessage',		
			'l10n_mode' => 'exclude',		
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'force_language' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.force_language',		
			'l10n_mode' => 'exclude',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',	
				'max' => '10',	
			)
		),
	),
	'types' => Array (
		'0' => Array('showitem' => 'hidden;;;;1-1-1, sys_language_uid, l18n_parent, title;;;;2-2-2,description, parent, ftype, notopic, notoolbar, hidetopic, hidemessage, force_language')
	),
	'palettes' => Array (
		'1' => Array('showitem' => '')
	)
);

//*** Generic fields definitions
// Access fields
$tmpAddColumns='';
foreach (array('read','write','guard','admin') as $access) {
	//Fields to add in type 0
	$tmpAddColumns.=',--div--;;;;3-3-3,'.$access.'access_mode,'.$access.'access';

	//Fields configuration
	$TCA['tx_ppforum_forums']['columns'][$access.'access_mode']=Array (		
		'exclude' => 1,		
		'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.'.$access.'access_mode',		
		'l10n_mode' => 'exclude',		
		'config' => Array (
			'type' => 'select',
			'items' => Array (
				Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.basic.mode.inherit', 'inherit'),
				Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.basic.mode.erase', 'erase'),
			),
			'size' => 1,	
			'maxitems' => 1,
		)
	);
	$TCA['tx_ppforum_forums']['columns'][$access.'access']=Array (		
		'exclude' => 1,		
		'displayCond' => 'FIELD:'.$access.'access_mode:!=:inherit,',
		'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.'.$access.'access',		
		'l10n_mode' => 'exclude',		
		'config' => Array (
			'type' => 'group',	
			'internal_type' => 'db',	
			'allowed' => 'fe_groups',	
			'size' => 5,	
			'minitems' => 0,
			'maxitems' => 10000,
		)
	);
}

//Restriction fields
$tmpAddColumns .= ',--div--;;;;4-4-4';
foreach (array('newtopic','reply','edit','delete') as $name) {
	$tmpAddColumns .= ','.$name.'_restrict';
	$TCA['tx_ppforum_forums']['columns'][$name.'_restrict'] = Array (		
		'exclude' => 1,		
		'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.'.$name.'_restrict',		
		'l10n_mode' => 'exclude',		
		'config' => Array (
			'type' => 'select',
			'items' => Array (
				Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.basic.restrict.inherit', 'inherit'),
				Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.basic.restrict.everybody', 'everybody'),
				Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.basic.restrict.guard', 'guard'),
				Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_forums.basic.restrict.admin', 'admin'),
			),
			'size' => 1,	
			'maxitems' => 1,
		)
	);
}
//Adding columns
$TCA['tx_ppforum_forums']['types']['0']['showitem'] .= $tmpAddColumns;
//Clearing vars (GLOBALS array is aleady big !)
unset($access);
unset($name);
unset($tmpAddColumns);

tx_pplib_div::loadTcaAddition('tx_ppforum_forums');
?>