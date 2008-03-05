<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_ppforum_topics'] = Array (
	'ctrl' => $TCA['tx_ppforum_topics']['ctrl'],
	'interface' => Array (
		'showRecordFieldList' => 'hidden,author,title,message,forum'
	),
	'feInterface' => $TCA['tx_ppforum_topics']['feInterface'],
	'columns' => Array (
		'author' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.author',		
			'config' => Array (
				'type' => 'group',	
				'internal_type' => 'db',	
				'allowed' => 'fe_users',	
				'size' => 1,	
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'status' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.status',		
			'config' => Array (
				'type' => 'select',
				'items' => Array (
					Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.status.normal', '0'),
					Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.status.hidden', '1'),
					Array('LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.status.closed', '2'),
				),
				'size' => 1,	
				'maxitems' => 1,
			)
		),
		'title' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.title',		
			'config' => Array (
				'type' => 'input',	
				'size' => '30',	
				'max' => '120',	
				'eval' => 'required,trim',
			)
		),
		'message' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.message',		
			'config' => Array (
				'type' => 'text',
				'cols' => '30',	
				'rows' => '5',
			)
		),
		'forum' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.forum',		
			'config' => Array (
				'type' => 'group',	
				'internal_type' => 'db',	
				'allowed' => 'tx_ppforum_forums',	
				'size' => 1,	
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'pinned' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:pp_forum/locallang_db.xml:tx_ppforum_topics.pinned',		
			'config' => Array (
				'type' => 'check',
				'default' => '0'
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
		'0' => Array('showitem' => 'status;;;;1-1-1, author, pinned, title;;;;2-2-2, forum, message;;;;3-3-3,nosmileys')
	),
	'palettes' => Array (
		'1' => Array('showitem' => '')
	)
);

tx_pplib_div::loadTcaAddition('tx_ppforum_topics');
?>