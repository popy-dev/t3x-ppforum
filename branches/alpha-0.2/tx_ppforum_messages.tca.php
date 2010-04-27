<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_ppforum_messages'] = Array (
	'ctrl' => $TCA['tx_ppforum_messages']['ctrl'],
	'interface' => Array (
		'showRecordFieldList' => 'hidden,author,message'
	),
	'feInterface' => $TCA['tx_ppforum_messages']['feInterface'],
	'columns' => Array (
		'hidden' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'author' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_messages.author',		
			'config' => Array (
				'type' => 'group',	
				'internal_type' => 'db',	
				'allowed' => 'fe_users',	
				'size' => 1,	
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'message' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_messages.message',		
			'config' => Array (
				'type' => 'text',
				'cols' => '30',	
				'rows' => '5',
			)
		),
		'topic' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_messages.topic',		
			'config' => Array (
				'type' => 'group',	
				'internal_type' => 'db',	
				'allowed' => 'tx_ppforum_topics',	
				'size' => 1,	
				'minitems' => 1,
				'maxitems' => 1,
			)
		),
		'nosmileys' => Array (		
			'exclude' => 1,
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_messages.nosmileys',		
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
	),
	'types' => Array (
		'0' => Array('showitem' => 'hidden;;1;;1-1-1, author, topic, message, nosmileys')
	),
	'palettes' => Array (
		'1' => Array('showitem' => '')
	)
);

tx_pplib_div::loadTcaAddition('tx_ppforum_messages');

?>