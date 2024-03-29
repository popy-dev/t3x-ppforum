<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Popy <popy.dev@gmail.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

tx_pplib_div::dynClassLoad('tx_pplib_pibase');

/**
 * Plugin 'Popy Forum' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_rpi1 extends tx_pplib_pibase {
	var $prefixId = 'tx_ppforum_pi1';
	var $scriptRelPath = 'pi1/class.tx_ppforum_pi1.php';
	var $extKey = 'pp_forum';
	public $piVars = array();
	public $getVars = array();
	public $config = array();


	/**
	 * "Current user" object (tx_ppforum_user instance)
	 * @access public
	 * @var object
	 */
	var $currentUser = null;
	/**
	 * tablename shortcut list
	 * @access public
	 * @var array
	 */
	var $tables = Array(
		'forum'   => 'tx_ppforum_forums',
		'topic'   => 'tx_ppforum_topics',
		'message' => 'tx_ppforum_messages',
		'user'    => 'fe_users',
	);
	/**
	 * List of callable vars wich will be called before plugin end
	 * @access public
	 * @var array
	 */
	var $callbackList = Array();
	/**
	 * No-cached parts
	 * @access public
	 * @var array
	 */
	var $intPartList = Array();
	/**
	 * The plugin's record uid
	 * @access public
	 * @var int
	 */
	var $pluginId = 0;

	/**
	 * 
	 * @access private
	 * @var array
	 */
	var $_delayedObjectList = Array(
		'message' => Array(),
		'topic' => Array(),
		'user' => Array(),	
	);

	/**
	 * Internal cache
	 * @access protected
	 * @var tx_pplib_picache
	 */
	var $cache = null;

	/****************************************/
	/************* Main funcs ***************/
	/****************************************/

	/**
	 * Class constructor
	 */
	function __construct() {
		parent::__construct();

		// Init plugin internal cache object
		$this->cache = &tx_pplib_div::makeInstance('tx_pplib_picache');
	}

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf)	{
		$content = '';

		if (isset($conf['isCallBack']) && $conf['isCallBack']) {
			return $this->mainCallback($conf);
		}

		//*** Basic init
		$this->init($conf);

		$printRest = true;

		//Hook list : if a hook return something and switch $printRest to true, the plugin will return this content instead of the normal content
		$hookRes = $this->pp_playHookObjList('main_alternateRendering', $printRest, $this);

		if (!$printRest) {
			//Looking for first available content
			foreach ($hookRes as $content) {
				if (strlen($content)) break;
			}
		} else {
			//Normal rendering
			$this->checkCssTemplate();
			$this->printJs();

			$this->getCurrent();

			$content .= '<div class="forum-head-part">';
			$content .= $this->printRootLine();
			$content .= $this->printUserBar();
			$content .= '</div>';

			$editProfile = isset($this->getVars['editProfile']) ? intval($this->getVars['editProfile']) : false;
			$viewProfile = isset($this->getVars['viewProfile']) ? intval($this->getVars['viewProfile']) : false;
			$userProfileId = $editProfile ? $editProfile : $viewProfile;
			$viewLatests = isset($this->getVars['mode']) ? ($this->getVars['mode'] == 'latest') : false;
			$viewLatests = $viewLatests && $this->currentUser->getId();

			if ($userProfileId) {
				$obj = &$this->getUserObj($userProfileId);
				$this->setPageTitle($obj);

				$lConf = Array(
					'cmd'  => 'callObj',
					'cmd.' => Array(
						'object' => 'user',
						'uid'    => $userProfileId,
						'method' => 'displayProfile',
						'mode'   => $editProfile ? 'edit' : 'view',
					)
				);

				$content .= $this->callINTpart($lConf);

			} elseif ($viewLatests) {
				$obj = &$this->getUnreadTopicsHandler();
				$this->setPageTitle($obj);

				$lConf = Array(
					'cmd'  => 'self',
					'cmd.' => Array(
						'method' => '_printUnreadTopics',
					)
				);


				$content .= $this->callINTpart($lConf);
			} elseif ($topic = $this->getCurrentTopic()) {
				$obj = &$this->getTopicObj(intval($topic));
				if ($obj->id) {
					tx_pplib_cachemgm::storeHash($obj->getCacheParam());
					$this->setPageTitle($obj);

					$content .= $obj->display();
				} else {
					$GLOBALS['TSFE']->set_no_cache();
					$content .= 'Topic inexistant ->@TODO message d\'erreur';
				}
			} elseif ($forum = $this->getCurrentForum()) {
				if ($forum < 0) {
					$lConf = Array(
						'cmd'  => 'callObj',
						'cmd.' => Array(
							'object' => 'forum',
							'uid'    => $forum,
							'method' => 'display',
						)
					);

					$content .= $this->callINTpart($lConf);
					
				} else {

					$obj = &$this->getForumObj($forum);
					if ($obj->id) {
						tx_pplib_cachemgm::storeHash($obj->getCacheParam());
						$this->setPageTitle($obj);

						$content .= $obj->display();
					} else {
						$content .= 'Forum inexistant ->@TODO message d\'erreur';
						$GLOBALS['TSFE']->set_no_cache();
					}
					
				}
			} else {
				$tempList = $this->getForumChilds(0, true);
				$this->flushDelayedObjects();
				foreach ($tempList as $key => $forum) {
					$obj[$key] = &$this->getForumObj($forum);
					if ($obj[$key]->id && $obj[$key]->isVisible()) {
						$content .= $obj[$key]->display();
					}
				}
				tx_pplib_cachemgm::storeHash(array());
			}

			$content .= '<div class="forum-bottom-part">';
			$content .= $this->printRootLine();
			$content .= '</div>';

			if ($this->config['display']['printStats']) {
				$content .= $this->printStats();
			}

			//$this->batch_updateUsersMessageCounter();
			//$this->batch_updateTopicMessageCounter();
		}
		
		$lConf = Array(
			'cmd' => 'self',
			'cmd.' => array(
				'method' => 'handleIntParts',
				'parts.' => $this->intPartList
			)
		);

		$content .= $this->callINTPlugin($lConf,TRUE);
		$this->intPartList = Array();

		$this->close();

		return $this->pp_wrapInBaseClass($content);
	}

	/**
	 * Function called by every USER_INT parts
	 *   It make a peace of loading (restoring env) and launch another method
	 *
	 * @param	array		$conf: The PlugIn configuration
	 * @access public
	 * @return string 
	 */
	function mainCallback($conf, $dontInit = false) {
		/* Declare */
		$this->_disableINTCallback = true;
		$this->internalLogs['userIntPlugins']++;
		$this->internalLogs['allUserIntPlugins']++;

		/* Begin */
		$this->init($conf, $dontInit);

		if (isset($this->conf['meta']['called'])) {
			$this->internalLogs['allUserIntPlugins'] += intval($this->conf['meta']['called']);
		}

		switch ($this->conf['cmd']){
		case 'callObj': //Asked to build an object
			$theObj = &$this->getRecordObject($this->conf['cmd.']['uid'], $this->conf['cmd.']['object']);

			//$theObj->parent = &$this;//Force backref to this
			if (method_exists($theObj,$method = $this->conf['cmd.']['method'])) {
				$content = $theObj->$method($this->conf['cmd.']); //Call the specified method
			}
			break;
		case 'self':
			if (method_exists($this,$method = $this->conf['cmd.']['method'])) {
				$content = $this->$method($this->conf['cmd.']);
			}
			break;
		case 'object':
			$theObj = &$this->pp_makeInstance($this->conf['cmd.']['classname']);
			$method = $this->conf['cmd.']['method'];
			$content = $theObj->$method($this->conf['cmd.']); //Call the specified method
			break;
		}

		if (!$dontInit) {
			$this->close();
		}

		if ($this->getVars['outlineUserInt']) { //If asked, user int will be outlined (on CSS2 compliant browsers)
			return '<div style="border: 1px blue dotted; padding: 0px; margin: 0px;">'.$content.'</div>';
		} else {
			return $content;
		}
	}



	/**
	 * Generate an item list for pp_rsslatestcontent integration
	 *
	 * @param array $conf = Typoscript configuration
	 * @param object &$ref = pp_rsslatestcontent plugin object
	 * @access public
	 * @return void 
	 */
	function rss_getList($conf,&$ref) {
		/* Declare */
		$this->conf = $conf;
		$this->conf['parsers.'] = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_ppforum_pi1.']['parsers.'];
		$mergedList = array();
		$finalList = array();

		/* Begin */
		$this->init();
		tx_pplib_cachemgm::storeHash(Array());

		//Force altdPageId when links will be build
		$this->_displayPage = reset(explode(',',$this->config['pidList']));
		//$page = $ref->getPage();

		//Get latest message list
		$messages = $this->db_query(
			'uid,crdate',
			$this->tables['message'],
			'1=1' . $this->pp_getEnableFields($this->tables['message']),
			'',
			$this->getOrdering('message', 'reverse')
		);

		//Get latest topic list
		$topics = $this->db_query(
			'uid,crdate',
			$this->tables['topic'],
			'forum > 0' . $this->pp_getEnableFields($this->tables['topic']),
			'',
			$this->getOrdering('topic','nopinned'),
			max(intval($ref->feed['select_key']),5)
		);

		//Merge them
		foreach ($messages as $val) {
			$mergedList['message:'.$val['uid']] = $val['crdate'];
		}
		foreach ($topics as $val) {
			$mergedList['topic:'.$val['uid']] = $val['crdate'];
		}

		//Sorting
		natsort($mergedList);

		//Reverse order and re-apply limitation
		$mergedList = array_reverse($mergedList);

		$counter = 0;
		
		$nbrows = max(intval($ref->feed['select_key']),5);

		//Then we build the result array
		foreach (array_keys($mergedList) as $key) {
			//Get back type and uid
			list($type,$uid) = explode(':',$key);
			//Init item
			$result = array();

			//Build objects
			if ($type == 'message') {
				$obj = &$this->getMessageObj($uid);
				if ($obj->topic->forum->id<0) {
					continue;
				}

				$result['title'] = $obj->topic->data['title'].' ('.htmlspecialchars(strip_tags($obj->author->displayLight())).')';
			} else {
				$obj = &$this->getTopicObj($uid);
				$result['title'] = $obj->data['title'];
			}

			if (!$obj->isVisibleRecursive()) {
				continue;
			}

			//Build item
			$result['pubDate'] = date('r',$obj->data['tstamp']);
			$result['guid'] = htmlspecialchars($ref->siteUrl.$obj->getLink());
			$result['link'] = htmlspecialchars($ref->siteUrl.$obj->getLink());
			$result['description'] = htmlspecialchars($obj->processMessage($obj->data['message']));
			
			//Push it to list
			$finalList[] = $result;

			$counter++;
			if ($counter >= $nbrows) {
				break;
			}
		}

		return $finalList;
	}

	/**
	 * Loads some parameters and builds the currentUser obj
	 *
	 * @access public
	 * @return void 
	 */
	function init($conf = array(), $keepMicrotime = false) {
		if (!$keepMicrotime || !$this->startTime) {
			$this->startTime = microtime();
		}

		//*** Load given conf
		if (!is_array($this->conf) || !count($this->conf)) {
			$this->conf = $conf;
		} else {
			//** Ensure that cmd & cmd. will be totally overriden
			unset($this->conf['cmd']);
			unset($this->conf['cmd.']);

			$this->conf = t3lib_div::array_merge_recursive_overrule($this->conf, $conf);
		}
		
		//** Check if object is not yet initialized
		if (!$this->pluginId) {
			$this->pluginId = intval($this->cObj->data['uid']);

			parent::init();

			$this->config['.lightMode'] = isset($this->getVars['lightMode'])?($this->getVars['lightMode']?TRUE:FALSE):FALSE;
			if ($this->config['display']['lightMode_def']) {
				$this->config['.lightMode'] = !$this->config['.lightMode'];
			}

			$this->currentUser = &$this->getUserObj($this->getCurrentUser());

			if ($this->currentUser->getId() && !$this->currentUser->getUserPreference('latestVisitDate')) {
				$this->currentUser->setUserPreference('latestVisitDate', $GLOBALS['SIM_EXEC_TIME']);
			}
			$this->autoDisableCache();
			$this->smileys = &$this->pp_makeInstance('tx_ppforum_smileys');
			$this->_displayPage = $GLOBALS['TSFE']->id;
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function close() {
		if (is_array($this->callbackList)) {
			$count = count($this->callbackList);
			for ($i = 0;$i<$count;$i++) {
				call_user_func($this->callbackList[$i]);
			}
		}
		$this->callbackList = array();

		//Cache stats
		$this->pushStats();
	}

	/**
	 * Caching stats
	 *
	 * @access public
	 * @return void 
	 */
	function pushStats() {
		/* Declare */
		list($startM,$startS) = explode(' ',$this->startTime);
		list($stopM,$stopS) = explode(' ',$this->startTime = microtime());
	
		/* Begin */
		//Calculating exec time
		$this->exec_time += (
			(intval($stopS) - intval($startS)) +
			(floatval($stopM) - floatval($startM))
		);
	}

	/**
	 * Turns the TSFE in "no_cache" mode if needed
	 *
	 * @access public
	 * @return void 
	 */
	function autoDisableCache() {
		$disable = FALSE;

		$disable = $disable || ($this->getVars['edittopic']);
		$disable = $disable || ($this->getVars['deletetopic']);
		$disable = $disable || ($this->getVars['editmessage']);
		$disable = $disable || ($this->getVars['deletemessage']);
		$disable = $disable || ($this->getVars['clearCache']);

		if ($disable) {
			$GLOBALS['TSFE']->set_no_cache();
		}
	}

	/**
	 * Loading of message parsers
	 *
	 * @access public
	 * @return void 
	 */
	function loadParsers() {
		if (!is_array($this->parsers) || !count($this->parsers)) {
			//Default parser
			$this->parsers['0'] = $this->pp_getLL('parsers.default','Default');

			foreach (array_keys($this->hookObjList) as $key) {
				if (method_exists($this->hookObjList[$key], 'parser_getKey')) {
					$realKey = $this->hookObjList[$key]->parser_getKey();
					$this->parsers[$realKey] = &$this->hookObjList[$key];
					$this->parsers[$realKey]->parser_init($this);
					unset($this->hookObjList[$key]);
				}
			}
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getCurrent($clearCache = false) {
		if (!$clearCache && $this->cache->isInCache('currentItems', 'DIV')) {
			$res = $this->cache->getFromCache('currentItems', 'DIV');
		} else {
			/* Declare */
			$forumId = intval($this->getVars['forum']);
			$topicId = intval($this->getVars['topic']);
			$topic = NULL;
			$forum = NULL;
		
			/* Begin */
			if ($topicId) {
				//Load topic
				$topic = &$this->getTopicObj($topicId);
				if (!$topic->checkUpdatesAndVisibility()) {
					$topicId = 0;
				}

				// keep its parent forum
				$forumId = $topic->forum->id;

				unset($topic);
			}

			//Now checking current forum
			if ($forumId) {
				$newForumId = $forumId;

				while ($newForumId) {
					//Load forum
					$tempForum = &$this->getForumObj($newForumId);
					$newForumId = 0;

					// Check visibility
					$forum = &$tempForum->getFirstVisibleParent();
					unset($tempForum);
					
					if (is_object($forum)) {
						//We have a valid parent
						$forumId = $forum->id;

						//Now checking for alternate mode
						$changes = $forum->alternateRendering($topicId);

						if ($changes['topicId']) {
							$topicId = $changes['topicId'];

							//Load topic
							$topic = &$this->getTopicObj($topicId);

							if (!$topic->checkUpdatesAndVisibility()) {
								$topicId = 0;
							}

							$forumId = $topic->forum->id;
						} elseif ($changes['forumId']) {
							$newForumId = $changes['forumId'];
						}
					} else {
						$forumId = 0;
					}

					unset($forum);
				}
			}

			//Check for new topic
			if ($forumId && $this->getVars['edittopic']) {
				//Load forum
				$forum = &$this->getForumObj($forumId);
				
				$topic = &$this->getTopicObj(0);
				$topic->forum = &$forum;

				$topic->checkTopicData();
				
				$topicId = $topic->id;

				if (intval($topic->id)) {
					// Clearing object cache (because now the topic has an id !)
					$this->getTopicObj(0, true);
				}
			}

			unset($topic);
			unset($forum);

			$res = Array(
				'topic' => $topicId,
				'forum' => $forumId
			);

			$this->cache->storeInCache($res, 'currentItems', 'DIV');

			$this->getVars['topic'] = $topicId;
			$this->getVars['forum'] = $forumId;
		}

		return $res;
	}

	/**
	 * pp_searchengine integration
	 *
	 * @param string $content
	 * @param array $conf
	 * @access public
	 * @return array 
	 */
	function doSearch($conf) {
		/* Declare */
		global $PP_SEARCHENGINE_PI1;
		$this->conf = $conf;
		$this->init();
		$this->smileys->disable = TRUE;
		$swords = $conf['searchParams.']['sword'];
		$tablesConf = Array(
			$this->tables['topic'] => Array(
				'fieldList' => 'title,message',
				//'addWhere' => ' AND forum>0'.$this->getEnableFields('topic'),
				'addWhere' => ' AND forum>0'.$this->pp_getEnableFields($this->tables['topic']),
				'noWordCount' => TRUE,
				),
			$this->tables['message'] => Array(
				'fieldList' => 'message',
				//'addWhere' => $this->getEnableFields('message'),
				'addWhere' => $this->pp_getEnableFields($this->tables['message']),
				'noWordCount' => TRUE,
				),
			);
		$finalResult = Array();
		$this->_displayPage = $this->cObj->data['pid'];
		$this->_addParams = t3lib_div::explodeUrl2Array($conf['searchParams.']['addParams'],TRUE);
	
		/* Begin */
		$result = $PP_SEARCHENGINE_PI1->searchInTables($tablesConf,$swords);

		foreach (array_keys($tablesConf) as $table) {
			foreach ($result[$table] as $singleRes) {
				$uid = $singleRes['uid'];
				$tRes = Array();

				switch ($table){
				case $this->tables['topic']:
					$obj = &$this->getTopicObj($uid);
					$title = $tRes['title'] = $obj->data['title'];
					$theResKey = 't'.$uid;
					break;
				case $this->tables['message']: 
					$obj = &$this->getMessageObj($uid);
					//Checking PM
					if ($obj->topic->forum->id<0) {
						continue;
					}
					$tRes['title'] = $obj->topic->data['title'];
					$title = '';

					if ($this->config['mergeSearchResults']) {
						$theResKey = 't'.$obj->topic->id;
					} else {
						$theResKey = 'm'.$uid;
					}
					break;
				}

				if (!$obj->isVisibleRecursive()) {
					continue;
				}

				$tRes['description'] = html_entity_decode(strip_tags($obj->processMessage($obj->data['message'])));
				$tRes['link'] = $obj->getLink();

				list($tRes['words'],$tRes['count']) = $PP_SEARCHENGINE_PI1->calculatePertinence(
					array($title,$tRes['description']),
					$swords
					);


				if (isset($finalResult[$theResKey])) {
					$finalResult[$theResKey]['words'] = array_merge($tRes['words'],$finalResult[$theResKey]['words']);
					$finalResult[$theResKey]['pertinence'] = $PP_SEARCHENGINE_PI1->countRealSwords(array_unique($finalResult[$theResKey]['words']));
					$finalResult[$theResKey]['count'] += $tRes['count'];
					$finalResult[$theResKey]['others']++;
				} else {
					$tRes['pertinence'] = $PP_SEARCHENGINE_PI1->countRealSwords(array_unique($tRes['words']));
					$finalResult[$theResKey] = $tRes;
				}
			}
		}

		foreach (array_keys($finalResult) as $key) {
			unset($finalResult[$key]['words']);

			if (isset($finalResult[$key]['others']) && intval($finalResult[$key]['others'])) {
				$finalResult[$key]['title'] .= ' ('.intval($finalResult[$key]['others']).')';
			}
		}

		return $finalResult;
	}

	/**
	 * Modifiys the page title (using a record object to determine the new title)
	 * 
	 * @param object $object =
	 * @access public
	 * @return void 
	 */
	function setPageTitle(&$object) {
		/* Declare */
		$currentPageTitle = $GLOBALS['TSFE']->page['title'];
	
		/* Begin */
		if (method_exists($object, 'getPageTitle')) {
			$GLOBALS['TSFE']->altPageTitle = $currentPageTitle . ' -> ' . $object->getPageTitle();
		} elseif (method_exists($object, 'getTitle')) {
			$GLOBALS['TSFE']->altPageTitle = $currentPageTitle . ' -> ' . $object->getTitle();
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function batch_updateUsersMessageCounter() {
		$res = array_keys($this->db_queryItems(array(
			'*',
			'user',
			'1=1',
			null,
			null,
			null,
			'uid'
		), array(
			'preload' => true,
		)));
		$this->flushDelayedObjects();

		foreach ($res as $id) {
			$temp = &$this->getUserObj($id);
			$temp->batch_updateMessageCounter();
			$temp = null;
		}
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function batch_updateTopicMessageCounter() {

		$res = array_keys($this->db_queryItems(array(
			null,
			'topic',
			'1=1',
			null,
			null,
			null,
			'uid'
		), array(
			'preload' => true,
			'extendedQuery' => true,
		)));
		$this->flushDelayedObjects();

		foreach ($res as $id) {
			$temp = &$this->getTopicObj($id);
			$temp->batch_updateMessageCounter();
		}
	}

	/****************************************/
	/************* Print funcs **************/
	/****************************************/


	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function printStats() {
		return $this->callINTpart(array('cmd' => 'self','cmd.' => array('method' => '_printStats')));
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function _printStats() {
		$this->pushStats();

		$content='<div class="stats" id="ppforum_stats">';
		$content.='Eclapsed time : '.intval($this->exec_time * 1000).'ms.<br />';

		$content.='Total querys : '.tx_pplib_div::strintval($this->internalLogs['querys']).'.<br />';
		$content.='Real querys : '.tx_pplib_div::strintval($this->internalLogs['realQuerys']).'.<br />';
		$content.='Query time : '.tx_pplib_div::strintval($this->internalLogs['queryTime']).'ms.<br />';
		$content.='<br />';
		$content.='Called USER_INT cObjects : '.tx_pplib_div::strintval($this->internalLogs['allUserIntPlugins']).'.<br />';
		$content.='Effective USER_INT cObjects : '.tx_pplib_div::strintval($this->internalLogs['userIntPlugins']).'.<br />';

		$content.='<br />';
		if ($this->getVars['outlineUserInt']) {
			$content.=$this->pp_linkTP_keepPiVars(
				'Back',
				array('outlineUserInt'=>'')
				);
		} else {
			$content .= $this->pp_linkTP_keepPiVars(
				'Outline USER_INT cObjects !',
				array('outlineUserInt'=>1)
			);
		}

		$content.='</div>';
/*
		$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['STATS']['EXEC_TIME']=0;
		$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['STATS']['QUERYS']=0;
		$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['STATS']['REALQUERYS']=0;
		$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['STATS']['USER_INT']=0;
*/
		return $content;
	}

	/**
	 * Prints the forum rootline
	 *
	 * @access public
	 * @return string 
	 */
	function printRootLine($id = null) {
		if (is_null($id)) {
			$id = $this->getCurrentForum();
		}

		if ($this->cache->isInCache('rootlineDraw:' . strval($id), 'DIV')) {
			$res = $this->cache->getFromCache('rootlineDraw:' . strval($id), 'DIV');
		} else {
			/* Declare */
			$rootLine = $this->getForumRootline($id);
			$obj      = &$this->getForumObj(0);
			$root     = Array(
				$obj->getLink($this->pp_getLL('forum.forumIndex','Forum index')),
			);
		
			/* Begin */
			foreach (array_keys($rootLine) as $key) {
				$root[] = $rootLine[$key]->getTitleLink();
			}
			if ($topic = $this->getCurrentTopic()) {
				$obj = &$this->getTopicObj(intval($topic));
				$root[] = $obj->getTitleLink();
			}

			$res = '<div class="rootline">' . implode(' &gt; ',$root) . '</div>';
			$this->cache->storeInCache($res, 'rootlineDraw:' . strval($id), 'DIV');
		}
		return $res;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function printUserBar() {
		/* Declare */
		$conf = Array();
	
		/* Begin */
		if ($this->getCurrentUser()) {
			$conf['cmd']='self';
			$conf['cmd.']['method']='_printUserBar';

			return $this->callINTpart($conf);
		} else {
			//If user isn't connected we can put the userBar into the Typo3 cache
			return $this->_printUserBar();
		}
	}

	/**
	 * Prints the user's toolbar
	 *
	 * @access public
	 * @return string 
	 */
	function _printUserBar() {
		/* Declare */
		$content = '<div class="user-bar">';
	
		/* Begin */
		$content .= '<div class="left-part">'.
			$this->pp_getLL('user.loguedas', 'Logued as', true).
			$this->currentUser->displayLight();

		if ($this->currentUser->getId()) {
			$content .= ' ('.
				$this->currentUser->displayLogout() . ' / ' .
				$this->currentUser->displayEditLink() . ' / ' .
				$this->currentUser->displayInboxLink() . ' / ' .
				$this->currentUser->displayUnreadMessagesLink() . 
			')';
		}

		$content .= '</div>';
	
		$content .= '</div>';

		return $content;
	}

	/**
	 * Displays unread topic list
	 *
	 * @param array $cmd = callback parameters
	 * @access public
	 * @return string 
	 */
	function _printUnreadTopics($cmd) {
		/* Declare */
		$handler = &$this->getUnreadTopicsHandler();
	
		/* Begin */
		return $handler->display();
	}

	/**
	 * This is the CSS selector : output the selected stylesheet into the head part
	 *   of the page (using pp_lib natives functions)
	 *
	 * @access public
	 * @return void 
	 */
	function checkCssTemplate() {
		/* Declare */
		$cssTemplate = $this->config['display']['csstemplate'];
	
		/* Begin */
		if (!$this->conf['csstemplates.'][$cssTemplate]) {
			//*** Switching to default
			$cssTemplate = 'macmade';
		}
		$this->cObj->cObjGetSingle(
			$this->conf['csstemplates.'][$cssTemplate],
			$this->conf['csstemplates.'][$cssTemplate.'.'],
			'pp_forum->csstemplate'
		);

		/*
		tx_pplib_headmgr::addCssContent(
			$this->cObj->cObjGetSingle(
				$this->conf['csstemplates.'][$cssTemplate],
				$this->conf['csstemplates.'][$cssTemplate.'.'],
				'pp_forum->csstemplate'
			)
		);
		*/
	}

	/**
	 * Output some javascript into the head part of the generated page
	 *   (using pp_lib natives functions)
	 *
	 * @access public
	 * @return void 
	 */
	function printJs() {
		foreach ($this->conf['javascript.'] as $path) {
			tx_pplib_headmgr::addJsFile($path);
		}
	}


	/****************************************/
	/************* Forum funcs **************/
	/****************************************/

	/**
	 * Return the uid of the current forum
	 * OBSOLETE
	 *
	 * @access public
	 * @return int 
	 */
	function getCurrentForum() {
		$temp = $this->getCurrent();
		return $temp['forum'];
	}

	/**
	 * Build the forum rootline
	 *
	 * @param int $id = uid of the forum where start the rootline (keep blank for current)
	 * @param boolean $clearCache = if set to TRUE, the cached rootline will be re-resolved
	 * @access public
	 * @return array
	 */
	function &getForumRootline($id = null, $clearCache = false) {
		/* Declare */
		$res = array();
		$forum = null;
	
		/* Begin */
		if (is_null($id)) {
			$id = $this->getCurrentForum();
		}

		if ($this->cache->isInCache('rootline:' . strval($id), 'DIV')) {
			$res = &$this->cache->getFromCache('rootline:' . strval($id), 'DIV');
		} elseif($id) {
			$forum = &$this->getForumObj($id);
			if ($forum->forum->id) {
				$parentRootline = &$this->getForumRootline($forum->forum->id, $clearCache);

				foreach (array_keys($parentRootline) as $key) {
					$res[] = &$parentRootline[$key];
				}
			}

			$res[] = &$forum;

			$this->cache->storeInCache($res, 'rootline:' . strval($id), 'DIV');
		} else {
			$res = Array();
		}

		$this->internalLogs['querys'] += count($res);
		return $res;
	}

	/**
	 * Return a uid array of forum's childs (if id=0 then giving the list of root forums)
	 *
	 * @param int $id = forum's uid
	 * @param boolean $preload = 
	 * @param boolean $clearCache = 
	 * @access public
	 * @return array 
	 */
	function getForumChilds($id = 0, $preload = false, $clearCache = false) {
		/* Declare */
		$res = null;
		$cacheKey = 'pi-getForumChilds;' . tx_pplib_div::strintval($id);

		/* Begin */
		if ($clearCache === 'clearCache') {
			$this->cache->storeInCache($res, $cacheKey, 'relations');
		} elseif (!$clearCache && $this->cache->isInCache($cacheKey, 'relations')) {
			$res = $this->cache->getFromCache($cacheKey, 'relations');
		} else {
			$res = $this->db_queryItems(array(
				'uid', // Will be switched to * if preload is true
				'forum',
				'parent = ' . tx_pplib_div::strintval($id),
				null,
				null,
				null,
				'uid'
			), array(
				'preload' => $preload,
			));

			$res = array_keys($res);

			$this->cache->storeInCache($res, $cacheKey, 'relations');
		}
		$this->internalLogs['querys']++;

		return $res;
	}

	/**
	 * Return a uid array of forum's childs and deep childs
	 *
	 * @param int $id = forum's uid
	 * @param boolean $clearCache = 
	 * @access public
	 * @return array 
	 */
	function getRecursiveForumChilds($id = 0, $clearCache = false) {
		/* Declare */
		$res = $this->getForumChilds($id, false, $clearCache);
	
		/* Begin */
		foreach ($res as $subId) {
			$res = array_merge($res, $this->getRecursiveForumChilds($subId , $clearCache));
		}

		return $res;
	}

	/**
	 * Return the uid-list of the user's PM list
	 *
	 * @param int $id = user's uid
	 * @param boolean $clearCache = @see pp_lib
	 * @access public
	 * @return array 
	 */
	function getUserTopics($id, $clearCache = false, $options = '') {
		$id = intval($id);
		$query = '';

		if ($id == $this->currentUser->getId()) {
			$query = '(forum = '.strval(-$id).' OR (forum < 0 AND author = '.strval($this->currentUser->getId()).'))';
		} else {
			$query = '(forum = '.strval(-$id).' AND author = '.strval($this->currentUser->getId()).')';
		}
		$res = $this->db_query(
			'uid',
			$this->tables['topic'],
			$query . $this->pp_getEnableFields($this->tables['topic']),
			'',
			$this->getOrdering('topic', $options),
			'',
			'uid'
		);
		$this->internalLogs['querys']++;

		if (is_array($res)) {
			return array_keys($res);
		} else {
			return array();
		}
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getAllForumCounters() {
		/* Declare */
		$cacheKey = 'getAllForumCounters';
	
		/* Begin */
		if ($this->cache->isInCache($cacheKey, 'relations')) {
			$res = $this->cache->getFromCache($cacheKey, 'relations');
		} else {
			$res = $this->db_queryItems(array(
				'forum, count(%t%.uid) as topics, SUM(%t%.message_counter) as posts',
				'topic',
				'%t%.forum > 0',
				'%t%.forum',
				null,
				'',
				'forum'
			), array(
				'sort' => false,
			));
			$this->cache->storeInCache($res, $cacheKey, 'relations');
		}

		return $res;
	}

	/**
	 * Return a tx_ppforum_forum object and cache it (a user should be requested
	 *   many times during the rendering of a topic)
	 *
	 * @param int $id = forum uid
	 * @param boolean $clearCache = if TRUE, cached object will be overrided
	 * @access public
	 * @return object 
	 */
	function &getForumObj($id,$clearCache = false, $delayed = false) {
		return $this->getRecordObject($id, 'forum', $clearCache, $delayed);
	}

	/****************************************/
	/************* Topic funcs **************/
	/****************************************/

	/**
	 * Return the uid of the current topic
	 * OBSOLETE
	 *
	 * @access public
	 * @return int 
	 */
	function getCurrentTopic($clearCache = false) {
		$temp = $this->getCurrent($clearCache);
		return $temp['topic'];
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getLatestsTopics($since, $preload = false, $maxResults = null) {
		/* Declare */
		$fields = $preload ? '*' : 'uid, crdate';
		$res = $this->db_queryItems(array(
			$fields,
			'topic',
			'crdate > ' . $since,
			null,
			'crdate DESC',
			$maxResults,
			'uid',
		), array(
			'preload' => $preload,
		));
	
		/* Begin */
		foreach ($res as $k => $val) {
			$res[$k] = intval($val['crdate']);
		}

		return $res;
	}

	/**
	 * Return a tx_ppforum_topic object and cache it (a user should be requested
	 *   many times during the rendering of a topic)
	 *
	 * @param int $id = topic uid
	 * @param boolean $clearCache = if TRUE, cached object will be overrided
	 * @access public
	 * @return object 
	 */
	function &getTopicObj($id, $clearCache = false, $delayed = false) {
		return $this->getRecordObject($id, 'topic', $clearCache, $delayed);
	}

	/****************************************/
	/************ Messages funcs ************/
	/****************************************/

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getLatestsMessages($since, $preload = false, $maxResults = null) {
		/* Declare */
		$finalRes = Array();
		$fields = $preload ? '*' : 'uid, crdate, topic';
		$res = $this->db_queryItems(array(
			$fields,
			'message',
			'crdate > ' . $since,
			null,
			'crdate DESC',
			$maxResults
		), array(
			'preload' => $preload,
		));
	
		/* Begin */
		foreach ($res as $val) {
			$finalRes[intval($val['uid'])] = array(
				'crdate' => intval($val['crdate']),
				'topic' => intval($val['topic'])
			);
		}

		return $finalRes;
	}

	/**
	 * Return a tx_ppforum_message object and cache it (a user should be requested
	 *   many times during the rendering of a topic)
	 *
	 * @param int $id = message uid
	 * @access public
	 * @return object 
	 */
	function &getMessageObj($id, $clearCache = false, $delayed = false) {
		return $this->getRecordObject($id, 'message', $clearCache, $delayed);
	}

	/****************************************/
	/************** User funcs **************/
	/****************************************/

	/**
	 * Returns current user's uid
	 *
	 * @access public
	 * @return int 
	 */
	function getCurrentUser() {
		return $GLOBALS['TSFE']->loginUser ? intval($GLOBALS['TSFE']->fe_user->user['uid']) : false;
	}

	/**
	 * Return a tx_ppforum_user object and cache it (a user should be requested
	 *   many times during the rendering of a topic)
	 *
	 * @param int $id = user_id
	 * @param boolean $clearCache = if TRUE, cached object will be overrided
	 * @access public
	 * @return void 
	 */
	function &getUserObj($id, $clearCache = false, $delayed = false) {
		return $this->getRecordObject($id, 'user', $clearCache, $delayed);
	}

	/****************************************/
	/*************** Div funcs **************/
	/****************************************/

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function &getUnreadTopicsHandler() {
		if ($this->cache->isInCache('tx_ppforum_latests', 'SINGLETON')) {
			$res = &$this->cache->getFromCache('tx_ppforum_latests', 'SINGLETON');
		} else {
			$res = &$this->pp_makeInstance('tx_ppforum_latests');
			$res->initialize();
			$this->cache->storeInCache($res, 'tx_ppforum_latests', 'SINGLETON');			
		}

		return $res;
	}

	/**
	 * Determine a record object classname based on the record type
	 *
	 * @param string $type = record type
	 * @access public
	 * @return string / null if not found 
	 */
	function recordObject_getClass($type) {
		/* Declare */
		$className = null;

		/* Begin */
		if (isset($this->conf['recordObjects.'][$type])) {
			$className = $this->conf['recordObjects.'][$type];
		}

		return $className;
	}

	/**
	 * Instanciate a record object classname based on the record type
	 *
	 * @param string $type = record type
	 * @access public
	 * @return object / null 
	 */
	function &recordObject_instanciate($type) {
		/* Declare */
		$className = $this->recordObject_getClass($type);
		$res = null;
	
		/* Begin */
		//** if a valid class is found, build object and init it
		if (trim($className)) {
			//* Instanciate object
			$res = &$this->pp_makeInstance($className);
			
			//* Force the type proprety value
			$res->type = $type;
		}

		return $res;
	}

	/**
	 * Get a record object
	 * Objects are cached : every record object is also unique during the whole page generation
	 * 
	 * @param int $id = the row uid
	 * @param string $type = the object type
	 * @param boolean $clearCache = if TRUE, cached object will be overrided
	 * @param boolean $delayed = if TRUE, the object data will not be loaded, but added to a stack, and will be loaded later
	 * @access public
	 * @return object / null if object cannot be build
	 */
	function &getRecordObject($id, $type, $clearCache = false, $delayed = false) {
		/* Declare */
		$cacheKey = $this->generateCacheKey($id, $type);
		$className = null;
		$res = null;

		/* Begin */
		//*** Special case : negative forum id means forumsim object
		if ($type == 'forum' && $id < 0) $type = 'forumsim';

		if ($clearCache || !$this->cache->isInCache($cacheKey)) {
			$res = &$this->recordObject_instanciate($type);

			// If object have been successfully built
			if (is_object($res)) {
				if ($delayed && isset($this->_delayedObjectList[$type])) {
					// Deleyed mode : The data will not be loaded now, it will be done later trought the method "flushDelayedObjects"
					$this->_delayedObjectList[$type][] = $id;
				} else {
					//* Load data
					if ($type == 'forum' && $id == 0) {
						$rData = $this->config['rootForum'];
						$rData['uid'] = 'root';
						$res->loadData($rData);
					} else {
						if ($id) {
							$res->load($id);
						} else {
							$res->loadData(array());
						}
					}
				}
			}

			$this->cache->storeInCache($res, $cacheKey);
		} else {
			$res = &$this->cache->getFromCache($cacheKey);
		}

		//*** Return the cached object
		return $res;
	}

	/**
	 * Flush the "Object wich have to be loaded" stack by loading them, recursively, type by type
	 * 
	 * @access public
	 * @return void 
	 */
	function flushDelayedObjects() {
		do { // This do/while handle internal delaying (each load level can re-load childs as delayed)
			$count = 0;
			foreach ($this->_delayedObjectList as $type => $idList) {
				$idList = array_unique($idList);
				$listCount = count($idList);

				if ($listCount) {
					$count += $listCount;
					$this->_delayedObjectList[$type] = Array();
					$this->loadRecordObjectList($idList, $type);
				}
			}
		} while ($count);
	}

	/**
	 * Add an item list to the preloadStack
	 * Those items will be loaded by the "flushDelayedObjects" method
	 * 
	 * @param array $idList = items ids
	 * @param string $type = item type
	 * @access public
	 * @return void 
	 */
	function addItemsToPreloadStack($idList, $type) {
		foreach ($idList as $id) {
			$this->getRecordObject($id, $type, false, true);
		}
	}

	/**
	 * Loads a list of records (query only non-loaded records)
	 * 
	 * @param array $idList = items ids
	 * @param string $type = item type (forumsim is skipped, as their is no sense to load a list of them FOR NOW)
	 * @access protected
	 * @return void 
	 */
	function loadRecordObjectList($idList, $type) {
		// Sanity check : Only for REAL data
		if (!in_array($type, array('message', 'topic', 'user', 'forum'))) {
			return ;
		}

		// Get items
		$tabRes = $this->db_queryItems(array(
			null,
			$type,
			'uid IN (' . implode(',', $idList) . ')',
			null,
			null,
			null,
		), array(
			'sort' => false,
			'preload' => true,
		));
		$this->internalLogs['querys']++;
	}

	/**
	 * Preload a recordset into data objects
	 *   Used internally when a query did return full rows instead of just ids, to limit query count
	 *   Loading has to be completed by flushDelayedObjects
	 *
	 * @param array $list = recordset (list of rows)
	 * @param string $type = items type
	 * @access protected
	 * @return void 
	 */
	function preloadDataIntoRecordObjects($list, $type) {
		/* Declare */
		$res = null;
	
		/* Begin */
		foreach ($list as $row) {
			$id = intval($row['uid']);
			$cacheKey = $this->generateCacheKey($id, $type);

			if (!$this->cache->isInCache($cacheKey)) {
				//* Instanciate object
				$res = &$this->recordObject_instanciate($type);

				//* Load data, with delayed sub-item loading
				$res->loadData($row, true);

				$this->cache->storeInCache($res, $cacheKey);
			} else {
				// Already loaded
				$res = &$this->cache->getFromCache($cacheKey);

				// Delayed object
				if (!$res->getId()) {
					$res->loadData($row, true);
				}
			}
		}
	}

	/**
	 * 
	 * 
	 * @param int $id =
	 * @param string $type = 
	 * @access public
	 * @return void 
	 */
	function isRecordLoaded($id, $type) {
		/* Declare */
		$cacheKey = $this->generateCacheKey($id, $type);
		$tmp = &$this->cache->getFromCache($cacheKey);
	
		/* Begin */
		return is_object($tmp) && $tmp->id;
	}

	/**
	 * Generate an item (record object) unique cache key
	 * Used for internal caching API
	 *
	 * @param int $id = item's id
	 * @param string $type = item's type
	 * @access public
	 * @return string 
	 */
	function generateCacheKey($id, $type) {
		return $type . ',' . strval($id);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function renderDate($tstamp) {
		return date('d/m/Y H:i:s',$tstamp);
	}

	/**
	 * pp_linkTP redefinition : used to force the $altPageId param and to add some GET vars
	 *
	 * @param string $str = Title of the link. if none, only the URL will be returned
	 * @param array $addParams = _GET vars to set in url
	 * @param boolean $cache = set to false to disable cache
	 * @param mixed $altPageId = alternative target page (could be an uid, an alias, an anchor @see typolink)
	 * @access public
	 * @return string 
	 */
	function pp_linkTP($str, $addParams = array(), $cache = 1, $altPageId = null) {
		
		if (is_null($parameter) && $this->_displayPage) {
			$parameter = $this->_displayPage;
		}
		
		//** Become obsolete
		/*
		if ($this->_displayPage && !intval($altPageId)) {
			$altPageId = $this->_displayPage.$altPageId;
		}
		*/

		if (is_array($this->_addParams) && count($this->_addParams)) {
			$addParams = t3lib_div::array_merge_recursive_overrule($this->_addParams,$addParams);
		}

		return parent::pp_linkTP($str,$addParams,$cache,$altPageId);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function pagination_calculateBase($nbItems, $resPerPage) {
		/* Declare */
		$nbItems = intval($nbItems);
		$resPerPage = max(1, $resPerPage);

		/* Begin */
		return array(
			'itemCount' => $nbItems,
			'itemPerPage' => $resPerPage,
			'pageCount' => max(1, ceil($nbItems / $resPerPage)),
		);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function pagination_parsePointer($pagination, $pointer) {
		if ($pointer === 'last') {
			$pointer = $pagination['pageCount'] - 1;
		}
		$pointer = min($pointer, $pagination['pageCount'] - 1);
		$pointer = max($pointer, 0);

		return $pointer;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function pagination_getRange($paginate, $pointer) {
		return array(
			$pointer * $paginate['itemPerPage'],
			$paginate['itemPerPage'],
		);
	}

	/**
	 * Build the message browser
	 *
	 * @access public
	 * @return string 
	 */
	function displayPagination($nbChilds, $resPerPage, &$ref, $addClasses=array()) {
		if ($nbChilds <= $resPerPage) return '';
		/* Declare */
		$nbPages = max(1, ceil($nbChilds / $resPerPage));
		$maxPageNum = $nbPages-1;
		$selectedPage = trim($this->getVars['pointer']);
		$links = array();
		$startPage = 0;
		$endPage = 0;
		$pageRange = max(1, intval(intval($this->config['display']['pageRange'])/2));
	
		/* Begin */
		if ($selectedPage === 'last') {
			$selectedPage = $maxPageNum; //Handling 'last' value
		} elseif (intval($selectedPage)<0) {		
			$selectedPage = max(0, $maxPageNum - intval($selectedPage));//Handling negative value (not use yet, but... one day... maybe !)
		} else {
			$selectedPage = min(intval($selectedPage) , $maxPageNum);
		}

		$startPage = max(0, $selectedPage - $pageRange);
		$endPage = min($maxPageNum,$selectedPage+$pageRange);

		if ($selectedPage>1) {
			$links[] = '<a href="'.htmlspecialchars($ref->getLink(false)).'" title="'.$this->pp_getLL('messages.pointer.goToFirst_title','Back to first page',TRUE).'">'.$this->pp_getLL('messages.pointer.goToFirst','<<',TRUE).'</a>';
		}
		if ($selectedPage>0) {
			$links[]='<a href="'.htmlspecialchars($ref->getLink(false,array('pointer'=>$selectedPage-1))).'" title="'.$this->pp_getLL('messages.pointer.goToPrev_title','Back to previous page',TRUE).'">'.$this->pp_getLL('messages.pointer.goToPrev','<',TRUE).'</a>';
		}

		if ($startPage) {
			$links[]='...';
		}

		for ($i=$startPage;$i<$endPage+1;$i++) {
			if ($i!=$selectedPage) {
				$addparams = $i ? array('pointer'=>$i) : array();
				$links[] = '<a href="'.htmlspecialchars($ref->getLink(false,$addparams)).'" title="'.str_replace('###pagenum###',$i+1,$this->pp_getLL('messages.pointer.goToPage_title','Jump to page ###pagenum###',TRUE)).'">'.str_replace('###pagenum###',$i+1,$this->pp_getLL('messages.pointer.goToPage','###pagenum###',TRUE)).'</a>';
			} else {
				$links[]=str_replace('###pagenum###',$i+1,$this->pp_getLL('messages.pointer.goToPage','###pagenum###',TRUE));
			}
		}

		if ($endPage!=$maxPageNum) {
			$links[]='...';
		}

		if ($selectedPage<$maxPageNum) {
			$links[]='<a href="'.htmlspecialchars($ref->getLink(false,array('pointer'=>$selectedPage+1))).'" title="'.$this->pp_getLL('messages.pointer.goToNext_title','Next page',TRUE).'">'.$this->pp_getLL('messages.pointer.goToNext','>',TRUE).'</a>';
		}
		if ($selectedPage<$maxPageNum-1) {
			$links[]='<a href="'.htmlspecialchars($ref->getLink(false,array('pointer'=>'last'))).'" title="'.$this->pp_getLL('messages.pointer.goToLast_title','Last page',TRUE).'">'.$this->pp_getLL('messages.pointer.goToLast','>>',TRUE).'</a>';
		}
		
		$addClasses[]='browser';
		return '<div class="'.htmlspecialchars(implode(' ',$addClasses)).'">'.implode(' ',$links).'</div>';
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function registerCloseFunction($callback) {
		$this->callbackList[] = $callback;
	}


	/****************************************/
	/******** Database related funcs ********/
	/****************************************/

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function db_query() {
		/* Declare */
		$params = func_get_args();
		static $l = array();
	
		/* Begin */
		if (count($params) == 1) {
			$params = reset($params);
		}

		$starttime = microtime(true);
		$res = call_user_func_array(array(&$this->db, 'exec_SELECTgetRows'), $params);
		$stoptime = microtime(true);
		$this->internalLogs['querys']++;
		$this->internalLogs['realQuerys']++;

		if (!is_array($res)) {
			$res = array();
		}

		//*** Performance watch !
		$query = call_user_func_array(array(&$this->db, 'SELECTquery'), $params);
		$queryId = md5($query);
		$exec_time = ($stoptime - $starttime) * 1000;
		$this->internalLogs['queryTime'] += $exec_time ;

		$debug = false;
		//$debug = true;

		if (0 && (isset($l[$queryId]) || $debug)) {
			t3lib_div::debug(array(
				'lastBuiltQuery' => $query,
				'queryId' => $queryId,
				'exec_time' => intval($exec_time) . 'ms',
				'resCount' => count($res),
				'trail' => t3lib_div::debug_trail(),
				'previous trail' => $l[$queryId],
			), 'Query');
		}
		$l[$queryId] = t3lib_div::debug_trail();

		return $res;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function db_queryItems($params, $options = array()) {
		/* Declare */
		$tableShortName = $params[1];
		$replacementArray = array(
			'%t1%' => '',
			'%t%' => $this->tables[$tableShortName],
		);
		$options += array(
			'enableFields' => true,
			'sort' => true,
			'preload' => false,
			'extendedQuery' => false,
			'extendedQuery_completeSelect' => true,
			'extendedQuery_addWhere' => '',
		);
	
		/* Begin */
		// Set default fields
		if (is_null($params[0]) || ! $params[0] || $options['preload']) {
			$params[0] = '%t%.*';
		}

		// Resolves table name
		$params[1] = '%t%';


		// Add enableFields
		if ($options['enableFields']) {
			$params[2] .= $this->pp_getEnableFields($tableShortName);
		}

		// Automatic sorting
		if ($options['sort'] !== false && !isset($params[4])) {
			$params[4] = $this->getOrdering($tableShortName, $options['sort']);
		}

		// Extended queries !!!
		if ($options['extendedQuery']) {
			switch ($tableShortName) {
			case 'topic':
				$replacementArray['%t1%'] = $this->tables['message'];

				if ($options['extendedQuery_completeSelect']) $params[0] .= ', count(%t1%.uid) as __count_messages';

				$params[1] .= ' LEFT JOIN %t1% ON (%t1%.topic = %t%.uid';
				$params[1] .= $options['extendedQuery_addWhere'] . $this->pp_getEnableFields('message');
				$params[1] .= ')';

				if (is_null($params[3])) $params[3] = '%t%.uid';
				break;
			}
		}

		$params = str_replace(array_keys($replacementArray), array_values($replacementArray), $params);

		$res = $this->db_query($params);

		if (!is_array($res)) {
			// Error handling ?
			return array();
		}

		if ($options['preload']) {
			$this->preloadDataIntoRecordObjects($res, $tableShortName);
		}

		return $res;
	}

	/**
	 * Build WHERE statement wich filter from deleted/hidden/not visible records
	 * Used by getRecord : feel free to redefine this function !!
	 * 
	 * @param string $table = table name
	 * @param bool $show_hidden = if true, will not filter out hidden records
	 * @access public
	 * @return string
	 */
	function pp_getEnableFields($table) {
		/* Declare */
		$addWhere='';
		$show_hidden = 0;
		$usePidList = true;
	
		/* Begin */
		if (isset($this->tables[$table])) {
			$table = $this->tables[$table];
		}
		if ($table == $this->tables['forum']) {
			$addWhere .= ' AND '.$table.'.sys_language_uid = 0';
		}

		if ($table == $this->tables['message']) {
			$show_hidden = 1;
			$usePidList = false;
		}

		if ($table == $this->tables['topic']) {
			$usePidList = false;
		}

		$addWhere .= parent::pp_getEnableFields($table, $show_hidden, $usePidList);

		return $addWhere;
	}



	/**
	 * Build the "ORDER BY" clause (without ORDER BY) for the given table
	 *
	 * @param string $tablename = table short name
	 * @param string $options = comma separated options list (allowed options : reverse, nopinned)
	 * @access public
	 * @return string 
	 */
	function getOrdering($tablename,$options='') {
		/* Declare */
		$res=array();
		$options=array_filter(explode(',',$options),'trim');
	
		/* Begin */
		switch ($tablename){
		case 'message': 
			$res[] = $this->tables[$tablename].(in_array('reverse',$options)?'.crdate DESC':'.crdate ASC');
			break;
		case 'topic':
			if (!in_array('nopinned',$options)) {
				$res[] = $this->tables[$tablename].'.pinned'.(in_array('reverse',$options)?' ASC':' DESC');
			}
			$res[]=$this->tables[$tablename].'.tstamp'.(in_array('reverse',$options)?' ASC':' DESC');
			break;
		case 'forum': 
			$res[] = $this->tables[$tablename].(in_array('reverse',$options)?'.sorting DESC':'.sorting ASC');
			break;
		}

		return implode(',',$res);
	}

	/****************************************/
	/******** No-cached parts (INT) *********/
	/****************************************/

	/**
	 * Launch a INT part (will not be cached) of the plugin. @see ->mainCallback
	 *
	 * @param array $conf = additionnal TS conf
	 * @access public
	 * @return string 
	 */
	function callINTPlugin($conf) {
		//Forcing userFunc propretie
		//$conf['userFunc']='tx_ppforum_pi1->mainCallback';
		$conf['isCallBack'] = true;
		//Ensure that a INT part can't call another INT part
		if ($this->_disableINTCallback) {
			$this->internalLogs['userIntPlugins']--; //Because this is not a real USER_INT and it will increase the counter
			return $this->mainCallback($conf, true);
		} else {
			$lConf=$this->conf;
			//Cleaning conf
			unset($lConf['cmd']);
			unset($lConf['cmd.']);

			//Merging conf
			$conf = tx_pplib_div::arrayMergeRecursive($lConf,$conf,TRUE);
			return $this->cObj->cObjGetSingle('USER_INT',$conf);
		}
	}

	/**
	 * Same usage than callINTPlugin. The difference is that the processing of parts called here will be calculated INTERNALLY,
	 *   and not by the tslib_fe class. This run also faster, and a single INT part will be calculated only once
	 *
	 * @param array $conf = configuration overlay
	 * @access public
	 * @return string (a HTML comment, wich is a marker)
	 */
	function callINTpart($conf) {
		/* Declare */
		$key = md5(serialize($conf));
	
		/* Begin */
		if ($this->_disableINTCallback) {
			//Everything is already done in callINTPlugin :)
			return $this->callINTPlugin($conf);
		} else {
			if (isset($this->intPartList[$key])) {
				//This part has already been recorded, it means that the part is needed more than one time on the page
				$this->intPartList[$key]['meta']['called']++;
			} else {
				//First call
				$conf['meta']['called'] = 0;
				$this->intPartList[$key] = $conf;
			}

			return '<!-- tx_ppforum_pi1:INTPART_'.$key.'-->';
		}
	}


	/**
	 * Here we render every INT parts wich has been called throught callINTPart
	 *   Expects that this function is called as an USER_INT cObject AFTER the plugin content !! (because only content wich is
	 *      BEFORE the USER_INT marker will be pushed in TSFE->content when the USER_INT is called !)
	 *
	 * @access public
	 * @return string 
	 */
	function handleIntParts() {
		/* Declare */
		global $TSFE;
	
		/* Begin */
		if (isset($this->conf['cmd.']['parts.']) && is_array($this->conf['cmd.']['parts.']) && count($this->conf['cmd.']['parts.'])) {
			$data = $this->conf['cmd.']['parts.'];

			// Preload all needed objects
			foreach ($data as $k => $v) {
				if ($v['cmd'] == 'callObj') {
					$this->getRecordObject($v['cmd.']['uid'], $v['cmd.']['object'], false, true);
				}
			}

			$this->flushDelayedObjects();

			$replace = array();
			foreach ($data as $key => $val) {
				$replace['<!-- tx_ppforum_pi1:INTPART_'.$key.'-->'] = $this->mainCallback($val);
			}

			$TSFE->content = $this->fastMarkerArray($replace, $TSFE->content);
		}

		return '';
	}

}

tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_pi1.php');
?>