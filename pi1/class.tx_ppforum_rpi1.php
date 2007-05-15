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

//require_once(t3lib_extMgm::extPath('pp_lib').'class.tx_pplib.php');
require_once(t3lib_extMgm::extPath('pp_lib').'class.tx_pplib2.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_forum.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_forumsim.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_message.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_topic.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_user.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_smileys.php');


/**
 * Plugin 'Popy Forum' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_rpi1 extends tx_pplib2 {
	/**
	 * backReference to the mother cObj object (set at call time by cObj itself)
	 * @access public
	 * @var &object
	 */
	var $cObj = null;
	/**
	 * Should be same as classname of the plugin, used for CSS classes, variables
	 * @access public
	 * @var string
	 */
	var $prefixId = 'tx_ppforum_pi1';
	/**
	 * Path to the plugin class script relative to extension directory, eg. 'pi1/class.tx_newfaq_pi1.php'
	 * @access public
	 * @var string
	 */
	var $scriptRelPath = 'pi1/class.tx_ppforum_pi1.php';
	/**
	 * The plugin's extension key
	 * @access public
	 * @var string
	 */
	var $extKey = 'pp_forum';
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


	/****************************************/
	/************* Main funcs ***************/
	/****************************************/

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($conf)	{
		$content = '';

		//*** Basic init
		$this->init($conf);
		$this->loadHashList(true);

		$printRest = true;

		//Hook list : if a hook reurn something and switch $printRest to true, the plugin will return this content instead of the normal content
		$hookRes = tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_pi1']['main:alternateRendering'],
			$printRest,
			$this
			);

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

			$content .= $this->printRootLine();
			$content .= $this->printUserBar();
			
			$editProfile = isset($this->getVars['editProfile']) ? intval($this->getVars['editProfile']) : false;
			$viewProfile = isset($this->getVars['viewProfile']) ? intval($this->getVars['viewProfile']) : false;

			if ($editProfile || $viewProfile) {
				$lConf = Array(
					'cmd'  => 'callObj',
					'cmd.' => Array(
						'object' => 'user',
						'uid'    => ($editProfile ? $editProfile : $viewProfile),
						'method' => 'displayProfile',
						'mode'   => $editProfile ? 'edit' : 'view',
					)
				);

				$content .= $this->callINTPlugin($lConf);

			} elseif ($topic = $this->getCurrentTopic()) {
				$obj = &$this->getTopicObj($topic);
				if ($obj->id) {
					$this->storeHash(array('topic' => $topic));
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

					$content .= $this->callINTPlugin($lConf);
					
				} else {

					$obj = &$this->getForumObj($forum);
					if ($obj->id) {
						$this->storeHash(array('forum' => $forum));
						$content .= $obj->display();
					} else {
						$content .= 'Forum inexistant ->@TODO message d\'erreur';
						$GLOBALS['TSFE']->set_no_cache();
					}
					
				}
			} else {
				foreach ($this->getForumChilds() as $key => $forum) {
					$obj[$key] = &$this->getForumObj($forum);
					if ($obj[$key]->id && $obj[$key]->isVisible()) {
						$content .= $obj[$key]->display();
					}
				}
				$this->storeHash(array());
			}

			$content .= $this->printRootLine();

			if ($this->config['display']['printStats']) {
				$content .= $this->printStats();
			}

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


		$this->saveHashList(TRUE);
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
			switch ($this->conf['cmd.']['object']){
			case 'forum':
				$theObj = &$this->getForumObj($this->conf['cmd.']['uid']);
				break;
			case 'user': 
				$theObj = &$this->getUserObj($this->conf['cmd.']['uid']);
				break;
			case 'topic': 
				$theObj = &$this->getTopicObj($this->conf['cmd.']['uid']);
				break;
			case 'message': 
				$theObj = &$this->getMessageObj($this->conf['cmd.']['uid']);
				break;
			}
			//$theObj->parent = &$this;//Force backref to this
			if (method_exists($theObj,$method = $this->conf['cmd.']['method'])) {
				$content .= $theObj->$method($this->conf['cmd.']); //Call the specified method
			}
			break;
		case 'self':
			if (method_exists($this,$method = $this->conf['cmd.']['method'])) {
				$content .= $this->$method($this->conf['cmd.']);
			}
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
		$this->loadHashList(TRUE);
		$this->storeHash(array());
		$this->saveHashList(TRUE);

		//Force altdPageId when links will be build
		$this->_displayPage = reset(explode(',',$this->config['pidList']));
		//$page = $ref->getPage();

		//Get latest message list
		$messages = $this->doCachedQuery(
			array(
				'select' => 'uid,crdate',
				'from'   => $this->tables['message'],
				//'where'  => '1'.$this->getEnableFields('message'),
				'where'  => '1'.$this->pp_getEnableFields($this->tables['message']),
				'orderby'=> $this->getOrdering('message','reverse'),
//				'limit'  => max(intval($ref->feed['select_key']),5)
				)
			);

		//Get latest topic list
		$topics = $this->doCachedQuery(
			array(
				'select' => 'uid,crdate',
				'from'   => $this->tables['topic'],
				//'where'  => 'forum>0'.$this->getEnableFields('topic'),
				'where'  => 'forum>0'.$this->pp_getEnableFields($this->tables['topic']),
				'orderby'=> $this->getOrdering('topic','nopinned'),
				'limit'  => max(intval($ref->feed['select_key']),5)
				)
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
				$obj->loadAuthor();
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
		//$this->pp_saveLL();

		//If user has submited data, some piVars keys should have changed
		//  So we need to report it in the GET/POST vars (because the USER_INT objects will reload piVars from them !)
		/*if ($GLOBALS['TSFE']->no_cache && !$this->_disableINTCallback && is_array($this->piVars)) {
			$piVars = $this->piVars;
			t3lib_div::addSlashesOnArray($piVars);
			//Just modify POST vars, because they will override GET vars
			$GLOBALS['HTTP_POST_VARS'][$this->prefixId] = $_POST[$this->prefixId] = $piVars;

			$getVars = $this->getVars;
			t3lib_div::addSlashesOnArray($getVars);
			$GLOBALS['HTTP_GET_VARS'][$this->prefixId] = $_GET[$this->prefixId] = $getVars;
		}*/

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
		//$disable = $disable || ($this->getVars['editProfile']);
		//$disable = $disable || ($this->getVars['viewProfile']);
		$disable = $disable || (intval($this->getVars['forum'])<0);


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
		if (!is_array($this->parsers) || !count($this->parsers)) { //Check if parsers are already loaded
			//Parsers may have been loaded by another instance !
			if (is_array($GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['OBJECTS']['PARSERS']) && count($GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['OBJECTS']['PARSERS'])) {
				$this->parsers = &$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['OBJECTS']['PARSERS'];

				//We just need to update parent pointer
				foreach (array_keys($this->parsers) as $key) {
					$this->parsers[$key]->parent = &$this;
				}
			} else {
				//We have to load the full list
				global $TYPO3_CONF_VARS; //-> XCLASS will not works if we don't do this
				if (!is_array($this->conf['parsers.'])) $this->conf['parsers.'] = array();

				//Default parser
				$this->parsers['0']['label'] = $this->pp_getLL('parsers.default','Default',TRUE);

				foreach ($this->conf['parsers.'] as $key => $val) {
					if (!strpos($key,'.')) {

						//Get parser label
						$this->parsers[$key]['label'] = $GLOBALS['TSFE']->sL($val);
						if (!trim($this->parsers[$key]['label'])) $this->parsers[$key]['label'] = $key;

						//Get parser label
						$this->parsers[$key]['conf'] = $this->conf['parsers.'][$key.'.'];

						//If needed, include a php file
						if (trim($this->parsers[$key]['conf']['includeLib'])) {
							include_once(t3lib_div::getFileAbsFileName($this->parsers[$key]['conf']['includeLib']));
						}

						//Builds the object
						$this->parsers[$key]['object'] = $this->pp_makeInstance($this->parsers[$key]['conf']['object']);

						//Checks parser validity
						if (!is_object($this->parsers[$key]['object']) || !method_exists($this->parsers[$key]['object'],$this->parsers[$key]['conf']['messageParser'])) {
							//Parser is invalid, also unset the key
							unset($this->parsers[$key]);
						} else {
							//Init parser conf (makes easy to configure parser via Typoscript !)
							$this->parsers[$key]['object']->conf = $this->parsers[$key]['conf'];
						}
					}

					//End of loop
				}
				
				//Here we have a clean parser list, so cache it
				$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['OBJECTS']['PARSERS'] = &$this->parsers;
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
	function getCurrent() {
		if (!isset($GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['CURRENT'])) {
			/* Declare */
			$forumId = intval($this->getVars['forum']);
			$topicId = intval($this->getVars['topic']);
			$topic = NULL;
			$forum = NULL;
			$dataChecked = FALSE;
		
			/* Begin */
			if ($topicId) {
				//Load topic
				$topic = &$this->getTopicObj($topicId);

				if ($topic->id && is_object($topic->forum) && $topic->forum->id) {
					//For now, we have a valid topic, so we keep its parent forum
					$forumId = $topic->forum->id;

					//Check visibility of the topic
					if ($topic->isVisibleRecursive()) {
						//Then we can check updates
						if ($this->getVars['edittopic'] || $this->getVars['deletetopic']) {
							$dataChecked = TRUE;
							$topic->checkTopicData();

							if (!$topic->isVisibleRecursive()) {
								$topicId = 0;
							}
						}
					} else {
						$topicId = 0;
					}


				} else {
					//Unable to load topic, we can do nothing there
					$topicId = 0;
				}
			}


			//Now checking current forum
			if ($forumId) {
				//Load forum
				$forum = &$this->getForumObj($forumId);

				if ($forum->id) {
					//Maybe this forum isn't visible, we need to fall back to the first visible parent
					while (is_object($forum) && !$forum->isVisible()) {
						$forum = &$forum->forum;
						//If we come hre, it means that the given "forum" GET var is NOT valid, but if user has try to post in this forum
						//We don't want this new topic to appear on first visible forum
						$dataChecked = TRUE;
					}

					if (is_object($forum)) {
						//We have a valid parent
						$forumId = $forum->id;
					} else {
						//No visible parent : fall back to root
						$forumId = 0;
					}
				} else {
					//Unable to load forum, we can do nothing there
				}
			}

			//Check for new topic
			if ($forumId && !$dataChecked && $this->getVars['edittopic']) {
				//Load forum
				if (!is_object($forum)) {
					$forum = &$this->getForumObj($forumId);
				}
				
				$topic = &$this->getTopicObj(0);
				$topic->forum = &$forum;

				$topic->checkTopicData();
				
				$topicId = $topic->id;

				if (intval($topic->id)) {
					// Clearing object cache (because now the topic has an id !)
					$this->getTopicObj(0,TRUE);
				}
			}

			unset($topic);
			unset($forum);

			$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['CURRENT'] = Array(
				'topic' => $topicId,
				'forum' => $forumId
				);
			$this->getVars['topic'] = $topicId;
			$this->getVars['forum'] = $forumId;
		}

		return $GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['CURRENT'];
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
	function printRootLine($id=0) {
		$id=intval($id)?intval($id):$this->getCurrentForum();

		if (!isset($GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['CONTENT']['ROOTLINE'][$id])) {
			/* Declare */
			$rootLine=$this->getForumRootline($id);
			$obj=&$this->getForumObj(0);
			$root=Array(
				$obj->getLink($this->pp_getLL('forum.forumIndex','Forum index')),
				);
		
			/* Begin */
			foreach (array_keys($rootLine) as $key) {
				$root[] = $rootLine[$key]->getTitleLink();
			}
			if ($topic = $this->getCurrentTopic()) {
				$obj = &$this->getTopicObj($topic);
				$root[] = $obj->getTitleLink();
			}
			$content.='<li>'.implode(' &gt; </li><li>',$root).'</li>';

			$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['CONTENT']['ROOTLINE'][$id]='<div class="rootline"><ul>'.$content.'</ul></div>';
		}
		return $GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['CONTENT']['ROOTLINE'][$id];
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
		$conf=array();
	
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
		$content='<div class="user-bar">';
	
		/* Begin */
		$content.='<div class="left part">'.
			$this->pp_getLL('user.loguedas','Logued as',TRUE).
			$this->currentUser->displayLight();
		if ($this->currentUser->id) {
			$content.=' ('.
				$this->currentUser->displayLogout().' / '.
				$this->currentUser->displayEditLink().' / '.
				$this->currentUser->displayInboxLink().')';
		}

		$content.='</div>';

		$content.='</div>';

		return $content;
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

		$this->st_addCss(
			$this->cObj->cObjGetSingle(
				$this->conf['csstemplates.'][$cssTemplate],
				$this->conf['csstemplates.'][$cssTemplate.'.'],
				'pp_forum->csstemplate'
				)
			);
	}

	/**
	 * Output some javascript into the head part of the generated page
	 *   (using pp_lib natives functions)
	 *
	 * @access public
	 * @return void 
	 */
	function printJs() {
		$this->st_addJs(
			$this->cObj->cObjGetSingle(
				$this->conf['javascript'],
				$this->conf['javascript.'],
				'pp_forum->addjs'
				)
			);
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
	function &getForumRootline($id = 0, $clearCache = false) {
		/* Declare */
		$id = intval($id) ? intval($id) : $this->getCurrentForum();
		$tid = $id;
		$forum = null;
	
		/* Begin */
		if (!$id) {
			return array();
		} elseif ($clearCache || !is_array($GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['DATA']['ROOTLINE'][$id])) {
			$forum = &$this->getForumObj($tid);
			if ($forum->forum->id) {
				$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['DATA']['ROOTLINE'][$id]=&$this->getForumRootline($forum->forum->id,$clearCache);
			} else {
				$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['DATA']['ROOTLINE'][$id]=Array();
			}
			$GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['DATA']['ROOTLINE'][$id][]=&$forum;
		}
		$this->internalLogs['querys']++;
		return $GLOBALS['CACHE']['PP_FORUM'][$this->cObj->data['uid']]['DATA']['ROOTLINE'][$id];
	}

	/**
	 * Return a uid array of forum's childs (if id=0 then giving the list of root forums)
	 *
	 * @param int $id = forum's uid
	 * @param boolean $clearCache = @see pp_lib
	 * @access public
	 * @return array 
	 */
	function getForumChilds($id = 0 , $clearCache = false) {
		$res = $this->doCachedQuery(
			Array(
				'select'     => 'uid',
				'from'       => $this->tables['forum'],
				'where'      => 'parent = '.tx_pplib_div::strintval($id) . $this->pp_getEnableFields($this->tables['forum']),
				'orderby'    => $this->getOrdering('forum'),
				'indexField' => 'uid'
			),
			$clearCache
		);
		if (is_array($res)) {
			return array_keys($res);
		} else {
			return array();
		}
	}

	/**
	 * Return the uid-list of a forum topics
	 *
	 * @param int $id = forum's uid
	 * @param boolean $clearCache = @see pp_lib
	 * @access public
	 * @return array 
	 */
	function getForumTopics($id, $clearCache = false, $options=  '') {
		$id = intval($id);
		if ($id < 0) {
			return $this->getUserTopics(-$id, $clearCache, $options);
		} else {
			$res = $this->doCachedQuery(
				Array(
					'select'  => 'uid',
					'from'    => $this->tables['topic'],
					'where'   => 'forum = ' . intval($id) . $this->pp_getEnableFields($this->tables['topic']),
					'orderby' => $this->getOrdering('topic', $options),
					'indexField' => 'uid',
				),
				$clearCache
			);
			if (is_array($res)) {
				return array_keys($res);
			} else {
				return array();
			}
		}
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

		if ($id == $this->currentUser->id) {
			$query = '(forum = '.strval(-$id).' OR (forum < 0 AND author = '.strval($this->currentUser->id).'))';
		} else {
			$query = '(forum = '.strval(-$id).' AND author = '.strval($this->currentUser->id).')';
		}
		$res = $this->doCachedQuery(
			Array(
				'select'     => 'uid',
				'from'       => $this->tables['topic'],
				'where'      => $query . $this->pp_getEnableFields($this->tables['topic']),
				'orderby'    => $this->getOrdering('topic', $options),
				'indexField' => 'uid',
			),
			$clearCache
		);
		if (is_array($res)) {
			return array_keys($res);
		} else {
			return array();
		}
	}

	/**
	 * Return the latest created/updated topic
	 *
	 * @param int $id = forum's uid
	 * @param boolean $clearCache = @see pp_lib
	 * @access public
	 * @return void 
	 */
	function getForumLastTopic($id,$clearCache=FALSE) {
		if (!intval($id)) return false;
		$topicList = $this->getForumTopics($id, $clearCache, 'nopinned');
		if (!is_array($topicList)) return FALSE;
		foreach ($topicList as $uid) {
			$topic = &$this->getTopicObj($uid);
			if ($topic->isVisible()) {
				return $topic->id;
			}
		}
		return 0;
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
	function &getForumObj($id,$clearCache = false) {
		return $this->getRecordObject($id, 'forum', $clearCache);
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
	function getCurrentTopic($clearCache=FALSE) {
		$temp = $this->getCurrent();
		return $temp['topic'];
	}
	
	/**
	 * Return the uid-list of a topic messages
	 *
	 * @param int $id = topic's uid
	 * @param boolean $clearCache = @see pp_lib
	 * @access public
	 * @return array 
	 */
	function getTopicMessages($id , $clearCache = false) {
		$res = array();
		if (intval($id)) {
			$res = $this->doCachedQuery(
				Array(
					'select'     => 'uid',
					'from'       => $this->tables['message'],
					'where'      => 'topic = ' . tx_pplib_div::strintval($id) . $this->pp_getEnableFields($this->tables['message']),
					'orderby'    => $this->getOrdering('message'),
					'indexField' => 'uid'
				),
				$clearCache
			);
			
			$res = is_array($res) ? array_keys($res) : array();
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
	function &getTopicObj($id, $clearCache = false) {
		return $this->getRecordObject($id, 'topic', $clearCache);
	}

	/****************************************/
	/************ Messages funcs ************/
	/****************************************/

	/**
	 * Return a tx_ppforum_message object and cache it (a user should be requested
	 *   many times during the rendering of a topic)
	 *
	 * @param int $id = message uid
	 * @access public
	 * @return object 
	 */
	function &getMessageObj($id, $clearCache = false) {
		return $this->getRecordObject($id, 'message', $clearCache);
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
	function &getUserObj($id, $clearCache = false) {
		return $this->getRecordObject($id, 'user', $clearCache);
	}

	/****************************************/
	/*************** Div funcs **************/
	/****************************************/

	/**
	 * Get a record from db.
	 * 
	 * @param int $id = record's uid
	 * @param string $table = table name
	 * @access public
	 * @return mixed : array if success / false if fails
	 */
	function pp_getRecord($id, $table) {
		$data = parent::pp_getRecord($id, $table);

		if ($data && ($table == $this->tables['forum'])) {
			$data = $GLOBALS['TSFE']->sys_page->getRecordOverlay(
				$this->tables['forum'],
				$data,
				$GLOBALS['TSFE']->sys_language_content
			);
		}

		return $data;
	}

	/**
	 * Get a record object
	 * Objects are cached : every record object is also unique during the whole page generation
	 * 
	 * @param int $id = the row uid
	 * @param string $type = the object type
	 * @param boolean $clearCache = if TRUE, cached object will be overrided
	 * @access public
	 * @return object / null if object cannot be build
	 */
	function &getRecordObject($id, $type, $clearCache = false) {
		/* Declare */
		$cacheKey = $this->pluginId.':recordObjects_'.$type.':'.strval($id);
		$classKey = $type;
		$className = false;

		/* Begin */
		//*** Special case : negative forum id means forumsim object
		if ($type == 'forum' && $id < 0) $classKey = 'forumsim';

		//*** Determine classname
		if (isset($this->conf['recordObjects.'][$classKey])) $className = $this->conf['recordObjects.'][$classKey];

		if ($clearCache || !isset($GLOBALS['T3_VAR']['CACHE'][$this->extKey][$cacheKey])) {
			$GLOBALS['T3_VAR']['CACHE'][$this->extKey][$cacheKey] = null;

			//** if a valid class is found, build object and init it
			if (trim($className)) {
				//* Instanciate object
				$GLOBALS['T3_VAR']['CACHE'][$this->extKey][$cacheKey] = &$this->pp_makeInstance($className);
				
				//* Force the type proprety value
				$GLOBALS['T3_VAR']['CACHE'][$this->extKey][$cacheKey]->type = $type;

				//* Load data
				$GLOBALS['T3_VAR']['CACHE'][$this->extKey][$cacheKey]->load($id);
			}
		}

		//*** Increment query counter
		if ($id > 0) {
			$this->internalLogs['querys']++;
		}

		//*** Return the cached object
		return $GLOBALS['T3_VAR']['CACHE'][$this->extKey][$cacheKey];
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
	 * Build WHERE statement wich filter from deleted/hidden/not visible records
	 * Used by getRecord : feel free to redefine this function !!
	 * 
	 * @param string $table = table name
	 * @param bool $show_hidden = if true, will not filter out hidden records
	 * @access public
	 * @return string
	 */
	function pp_getEnableFields($table, $unused = false) {
		/* Declare */
		$addWhere='';
		$show_hidden = 0;
	
		/* Begin */
		if ($table == $this->tables['forum']) {
			$addWhere .= ' AND '.$table.'.sys_language_uid = 0';
		}

		if ($table == $this->tables['message']) {
			$show_hidden = 1;
		}

		$addWhere .= parent::pp_getEnableFields($table, $show_hidden);

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

	/**
	 * Do nothing for now :p
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function log($type) {
		/* Declare */
	
		/* Begin */
		switch ($type){
		case 'UPDATE': 
		case 'INSERT': 
			$this->internalLogs['querys']++;
			break;
		default:
			break;
		}
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
	 * Build the message browser and set the recordRange var (used for display)
	 *
	 * @access public
	 * @return string 
	 */
	function displayPagination($nbChilds,$resPerPage,&$ref,$addClasses=array()) {
		/* Declare */
		$nbChilds=intval($nbChilds);
		$resPerPage=max(1,$resPerPage);
		$nbPages=intval(($nbChilds-1)/$resPerPage)+1;
		$maxPageNum=$nbPages-1;
		$selectedPage=trim($this->getVars['pointer']);
		$links=array();
		$startPage=0;
		$endPage=0;
		$pageRange=max(1,intval(intval($this->config['display']['pageRange'])/2));
	
		/* Begin */
		if (!strcmp($selectedPage,'last')) {
			$selectedPage=$maxPageNum; //Handling 'last' value
		} elseif (intval($selectedPage)<0) {		
			$selectedPage=max(0,$maxPageNum-intval($selectedPage));//Handling negative value (not use yet, but... one day... maybe !)
		} else {
			$selectedPage=min(intval($selectedPage),$maxPageNum);
		}

		$startPage=max(0,$selectedPage-$pageRange);
		$endPage=min($maxPageNum,$selectedPage+$pageRange);

		//Setting recordrange from calculated values
		$ref->recordRange=($selectedPage*$resPerPage).':'.$resPerPage;

		//If we have only one page (or 0)
		if ($nbPages<2) {
			return '';
		}

		if ($selectedPage>1) {
			$links[]='<a href="'.htmlspecialchars($ref->getLink(false)).'" title="'.$this->pp_getLL('messages.pointer.goToFirst_title','Back to first page',TRUE).'">'.$this->pp_getLL('messages.pointer.goToFirst','<<',TRUE).'</a>';
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
	function registerCloseFunction($method,&$obj) {
		$this->callbackList[]=Array(&$obj,$method);
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
		$conf['userFunc']='tx_ppforum_pi1->mainCallback';
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
			$conf = $this->arrayMergeRecursive($lConf,$conf,TRUE);
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

			$replace = array();
			foreach ($data as $key => $val) {
				$replace['<!-- tx_ppforum_pi1:INTPART_'.$key.'-->'] = $this->mainCallback($val);
			}

			$TSFE->content = $this->fastMarkerArray($replace, $TSFE->content);
		}

		return '';
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_pi1.php']);
}

?>