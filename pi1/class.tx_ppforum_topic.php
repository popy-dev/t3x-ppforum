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

tx_pplib_div::dynClassLoad('tx_ppforum_message');

/**
 * Class 'tx_ppforum_topic' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_topic extends tx_ppforum_message {
	/**
	 * Used to build forms and to get data in piVars
	 * @access public
	 * @var string
	 */
	var $datakey = 'edit_topic';
	
	/**
	 * Error message storage
	 * @access public
	 * @var array
	 */
	var $processMessage = Array();

	/**
	 * Pointer on parent forum
	 * @access public
	 * @var object
	 */
	var $forum = null;

	/**
	 * Topic's mesage uid list
	 * @access public
	 * @var array
	 */
	var $messageList = null;

	/**
	 * List of allowed incomming fields from forms(Other fields will be ignored)
	 * @access public
	 * @var array
	 */
	var $allowedFields=Array(
		'title'      => '',
		'message'    => '',
		'nosmileys'  => '',
		'parser'     => '',
		'pinned'     => 'guard',
		'status'     => 'guard',
		'move-topic' => 'guard',
	);

	/**
	 * 
	 * @access public
	 * @var string
	 */
	var $mode = '';

	/**
	 * 
	 * @access protected
	 * @var array
	 */
	var $counters = null;

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function __construct() {
		$this->cache['paginate'] = false;
		$this->cache['messageList'] = array();
	}

	/**
	 * Loads the topic
	 * 
	 * @param array $data = the record row
	 * @access public
	 * @return int = the loaded id 
	 */
	function loadData($data, $delaySubs = false) {
		if ($res = parent::loadData($data)) {
			$this->forum = &$this->parent->getRecordObject(intval($this->data['forum']), 'forum', false,$delaySubs);

			if (isset($this->data['__count_messages'])) {
				unset($this->mergedData['__count_messages']);
				$this->initPaginateInfos();
			}
		}

		return $res;
	}


	/**
	 * Saves the message to the DB (and call event function)
	 *
	 * @access public
	 * @return int/boolean = the message uid or false when an error occurs 
	 */
	function save($forceReload = true, $noTstamp = false) {
		/* Declare */
		$result = false;
		$mode = 'update';

		/* Begin */
		if (!$this->id) {
			$mode = 'create';
		} elseif ($this->mergedData['deleted'] && !$this->data['deleted']) {
			$mode = 'delete';
		}

		// Special case for topics : only status is changed, don't refresh it
		if ($tstampField && $this->id) {
			$diffData = array_diff_assoc(
				$this->mergedData,
				$this->data
			);

			if (count($diffData) == 1 && isset($diffData['status'])) {
				$noTstamp = true;
			}
		}

		$this->mergedData['author'] = $this->author->getId();
		$this->mergedData['forum'] = $this->forum->id;

		// Plays hook list : Allow to change some field before saving
		$this->parent->pp_playHookObjList('topic_save', $mode, $this);

		$result = $this->basic_save($noTstamp);

		if ($forceReload) {
			$this->forceReload['forum'] = true;
		}
		//Launch forum event handler
		$this->event_onUpdateInTopic($mode);

		return $result;
	}

	/**
	 * Deletes the message
	 *
	 * @param boolean $forceReload = if TRUE, will clear forum's topic list
	 * @access public
	 * @return int/boolean @see tx_ppforum_topic::save 
	 */
	function delete($forceReload = true) {
		if ($this->id) {
			if ($this->forum->deleteTopic($this->id)) {
				return true;
			} else {
				$this->mergedData['deleted'] = 1;

				$fullMessageList = $this->db_getMessageList(array(
					'nocheck' => true,
					'clearCache' => true,
				));

				foreach ($fullMessageList as $messageId) {
					$temp = &$this->parent->getMessageObj($messageId);
					$temp->delete(false);
					unset($temp);
				}

				if ($forceReload) {
					$this->forceReload['forum'] = true;
				}
				return $this->save($forceReload);
			}
		} else {
			return false;
		}
	}

	/**
	 * 
	 * 
	 * @access public
	 * @return void 
	 */
	function batch_updateMessageCounter() {
		$this->mergedData['message_counter'] = $this->db_getMessageCount();

		$this->save(false, true);
	}

	/****************************************/
	/********** Events functions ************/
	/****************************************/

	/**
	 * Launched when a topic (or a message in this topic) is modified
	 *
	 * @param bool $fromMessage = Set to true to ask the function to update the topic's tstamp field (typically, when updating a message)
	 * @access public
	 * @return void 
	 */
	function event_onUpdateInTopic($mode = '') {
		$null = null;
		//Playing hook list
		$this->parent->pp_playHookObjList('topic_event_onUpdateInTopic', $null, $this);

		//Clear cached page (but only where this topic is displayed !)
		tx_pplib_cachemgm::clearItemCaches(Array('topic' => intval($this->id)), false);

		//Forcing data and object reload. Could be used to ensure that data is "as fresh as possible"
		//Useless if oject wasn't builded with 'getMessageObj' function
		//In this case, you should use $this->parent->getSingleMessage($this->id,'clearCache');
		if ($this->forceReload['data']) $this->load($this->id, true);

		// Cached message list has to be reloaded
		if ($this->forceReload['list']) $this->initPaginateInfos(true);

		if ($this->forceReload['forum']) {
			switch ($mode){
			case 'create':
				$this->author->incrementMessageCounter();
				$this->forum->event_onNewTopic($this->id);
				break;
			case 'update': 
				$this->forum->event_onUpdateInForum();
				break;
			case 'delete': 
				$this->forum->event_onUpdateInForum();
				$this->forum->initPaginateInfos(true);
				break;
			default:
				$this->forum->event_onUpdateInForum();
				break;
			}
		}

		//Resets directives
		$this->forceReload = array();
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageModify($messageId) {
		$param = Array(
			'messageId' => $messageId,
		);

		$this->event_onUpdateInTopic();

		//Playing hook list
		$this->parent->pp_playHookObjList('topic_event_onMessageModify', $param, $this);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageCreate($messageId) {
		/* Declare */
		$param = Array(
			'messageId' => $messageId,
		);
		$this->forceReload['list'] = true;
	
		/* Begin */
		$this->mergedData['message_counter']++;
		$this->save(); // Touch topic (just update its tstamp date !)
		$this->forum->event_onMessageCreate($this->id, $messageId);

		//Playing hook list
		$this->parent->pp_playHookObjList('topic_event_onMessageCreate', $param, $this);
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageDelete($messageId) {
		/* Declare */
		$param = Array(
			'messageId' => $messageId,
		);
		$this->forceReload['list'] = true;
	
		/* Begin */
		$this->save(); // Touch topic (just update its tstamp date !)

		//Playing hook list
		$this->parent->pp_playHookObjList('topic_event_onMessageDelete', $param, $this);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageDisplay($messageId) {
		$param = Array(
			'messageId' => $messageId,
		);

		$this->forum->event_onMessageDisplay($this->id, $messageId);

		//Playing hook list
		$this->parent->pp_playHookObjList('topic_event_onMessageDisplay', $param, $this);
	}


	/****************************************/
	/************ Div functions *************/
	/****************************************/

	/**
	 * Load every "cachable" information and return the cache array
	 * This cache will be given throught Typoscript config to _INT part(s)
	 * 
	 * @access public
	 * @return array
	 */
	function cache_getCachableData() {
		if ($this->id) $this->initPaginateInfos();

		return $this->cache;
	}
	
	/****************************************/
	/*********** Links functions ************/
	/****************************************/

	/**
	 * Builds a link to a topic (including anchor)
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @param array $addParams = additional url parameters.
	 * @access public
	 * @return string 
	 */
	function getLink($title = false, $addParams=array(), $parameter = null) {
		//** Anchor
		if (is_null($parameter) && $this->id) {
			//$parameter = $this->parent->_displayPage . '#ppforum_topic_'.$this->id;
		}

		if (intval($this->id)) {
			$addParams['topic'] = $this->id;
		} else {
			$addParams['forum'] = $this->forum->id;
			$addParams['edittopic'] = 1;
		}

		return $this->forum->getTopicLink(
			$title,
			$addParams, //overrule piVars
			$parameter // Message anchor
		);
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getLinkKeepPage($title = false, $addParams = array(), $parameter = null) {
		$addParams['pointer'] = $this->parent->getVars['pointer'];
		return $this->getLink($title, $addParams, $parameter);
	}

	/**
	 * Prints the topic title (as a link to the topic detail)
	 *
	 * @access public
	 * @return string 
	 */
	function getTitleLink($withIcons = false) {
		if ($withIcons) {	
			$addText = '<img src="clear.gif" class="topic-icon" alt="" title="" />';
			if ($this->data['pinned']) {
				$addText .= '<img src="clear.gif" class="pinned-topic" alt="pinned" title="pinned" /> ';
			}
			if ($this->data['status']==1) {
				$addText .= '<img src="clear.gif" class="hidden-topic" alt="hidden" title="hidden" /> ';
			} elseif ($this->data['status']==2) {
				$addText .= '<img src="clear.gif" class="closed-topic" alt="closed" title="closed" /> ';
			}
		}
		return $addText . $this->getLink(tx_pplib_div::htmlspecialchars($this->mergedData['title']));
	}

	/**
	 * Returns the topic's title
	 * 
	 * @param bool $hsc = if true, title will be passed throught htmlspecialchars
	 * @access public
	 * @return string 
	 */
	function getTitle($hsc = true) {
		return $hsc ? tx_pplib_div::htmlspecialchars($this->mergedData['title']) : $this->mergedData['title'];
	}

	/**
	 * Returns the topic's title especially for the page's title
	 * 
	 * @access public
	 * @return string 
	 */
	function getPageTitle() {
		return $this->forum->getPageTitle() . ' / ' . $this->getTitle(false);
	}

	/**
	 * Builds a link to the Topic edit form
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @access public
	 * @return string 
	 */
	function getEditLink($title = false) {
		$addParams=array('edittopic'=>1);
		if (!$this->id) {
			$addParams['forum']=$this->forum->id;
		}
		return $this->getLink($title,$addParams);
	}

	/**
	 * Builds a link to the Topic delete form
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @access public
	 * @return string 
	 */
	function getDeleteLink($title = false) {
		if ($this->id) {
			$addParams=array('deletetopic'=>1,'pointer'=>$this->parent->getVars['pointer']);
			return $this->getLink($title,$addParams);
		} else {
			return '';
		}
	}

	/**
	 * Count messages in the specified topic
	 *
	 * @param int $topicId = uid of the topic
	 * @param bool $clearCache = @see tx_pplib::do_cachedQuery
	 * @access public
	 * @return array()
	 */
	function getCounters($clearCache = false) {

		if ($clearCache || is_null($this->counters)) {
			$this->counters = array(
				'posts' => $this->data['message_counter'],
			);
			
			$this->parent->pp_playHookObjList('topic_getCounters', $this->counters, $this);
		}

		return $this->counters;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function hasToCheckMessageInputData() {
		/* Declare */
		$res = false;
		$getVars = $this->parent->getVars;
	
		/* Begin */
		if (isset($getVars['editmessage']) && intval($getVars['editmessage'])) {
			$res = true;
		}

		if (isset($getVars['deletemessage']) && intval($getVars['deletemessage'])) {
			$res = true;
		}

		return $res;
	}

	/**
	 * Check if a message is modified/posted/deleted
	 *
	 * @access public
	 * @return void 
	 */
	function checkMessageInputData() {
		/* Declare */
		$data = Array(
			'currentTopic' => $this->data,
			'errors'  => Array(),
			'message' => null,
			'mode'    => 'new',
			'shouldContinue' => true
		);
		$postData = Array();
	
		/* Begin */
		$data['message'] = &$this->parent->getMessageObj(0);

		if (isset($this->parent->piVars[$data['message']->datakey])) {
			$postData = &$this->parent->piVars[$data['message']->datakey];
		}

		//If we have nothing in piVars, nothing to do
		if (!count($postData)) return false;

		//Current topic isn't valid, so we can't append (or modify) a child to it !
		if (!$this->id) return false;

		//Checking mode (don't check permissions, it wouldbe check later)
		if (intval($this->parent->getVars['editmessage']) > 0) {
			$data['mode'] = 'edit';
			$data['message'] = &$this->parent->getMessageObj($this->parent->getVars['editmessage']);
			$data['message']->mergeData($postData);
		} elseif (intval($this->parent->getVars['deletemessage'])) {
			$data['mode'] = 'delete';
			$data['message'] = &$this->parent->getMessageObj($this->parent->getVars['deletemessage']);
		} else {
			//New message : the message object already exists, also it just need a parent topic (current topic)
			$data['message']->topic = &$this;
			$data['message']->author = &$this->parent->currentUser;
			$data['message']->mergeData($postData);
		}

		$data['errors'] = &$data['message']->validErrors;

		//Checking data validity
		if (strcmp($data['mode'], 'delete') && ($GLOBALS['TSFE']->fe_user->getKey('ses','ppforum/justPosted') == $postData)) {
			//*** Incomming data is the same as last time -> user has probably refreshed the page
			//Cleaning incomming vars
			$postData = Array();
			unset($this->parent->piVars['editmessage']);
			unset($this->parent->piVars['deletemessage']);

			$this->parent->getMessageObj(0, true);
			if ($data['mode'] == 'new') return false;
		}

		switch ($data['mode']){
		case 'edit':
			if (!$data['message']->id || !$data['message']->userCanEdit()) {
				$data['errors']['global']['access-denied.message-edit']='Access denied : You can\'t edit this message';
			}
			break;
		case 'new':
			if (!$this->userCanWriteInTopic()) {
				$data['errors']['global']['access-denied.topic-write']='Access denied : You can\'t write in this topic';
			}
			break;
		case 'delete':
			if (!$data['message']->id || !$data['message']->userCanDelete()) {
				$data['errors']['global']['access-denied.message-delete']='Access denied : You can\'t delete this message';
			}
			break;
		default:
			//Nothing ! Everything else can be done in next hook
			break;
		}

		//Playing hook list : Allows to make additional validity/access check
		$this->parent->pp_playHookObjList('topic_checkMessageInputData_checkValidityAndAccess', $data, $this);

		//Allows a hook ordering to exit
		if (!$data['shouldContinue']) {
			return FALSE;
		}

		//If we have no errors :
		if (!count($data['errors'])) {
			if ($data['mode'] != 'delete') {
				$data['errors'] = $data['message']->checkData();
			}

			if ($data['mode'] == 'new') {
				$data['message']->mergedData['crdate'] = $GLOBALS['SIM_EXEC_TIME'];
			}

			//Playing hook list : Allows to fill other fields
			$this->parent->pp_playHookObjList('topic_checkMessageInputData_checkAndFetch', $data, $this);

			if (!count($data['errors'])) {
				if ($data['mode'] == 'delete') {
					$data['message']->delete();
				} else {
					if (!isset($postData['preview'])) {
						$data['message']->save();
						//Saving data for validity check (protection against multi-post)
						$GLOBALS['TSFE']->fe_user->setKey('ses','ppforum/justPosted', $this->parent->piVars['editpost']);

						if ($data['mode'] == 'new') {
							// Clearing object cache (because now the message has an id !)
							$this->parent->getMessageObj(0, true);
						}
					} elseif (!$data['message']->id) {
						//Preview mode for new topics
						//$this->loadMessages();
						//$this->messageList[] = 0;
						$data['message']->id = 'preview';
					}
				}

				// Cleaning incomming vars
				$postData = Array();
				unset($this->parent->getVars['editmessage']);
				unset($this->parent->getVars['deletemessage']);
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
	function checkUpdatesAndVisibility() {
		// Test if topic object is valid
		if (!$this->id || !$this->forum->id) {
			return false;
		}

		// Check if current user has access to this topic
		if (!$this->isVisibleRecursive()) {
			return false;
		}

		// Process updates if needed
		if ($this->haveToCheckData()) {
			$this->checkTopicData();

			// Topic is not visible after updates
			if (!$this->isVisibleRecursive()) {
				return false;
			}
		}

		if ($this->hasToCheckMessageInputData()) {
			$this->checkMessageInputData();
		}


		if ($this->parent->getVars['clearCache'] && $this->forum->userIsAdmin()) {
			$this->forceReload['forum'] = true;
			$this->event_onUpdateInTopic();
			unset($this->parent->getVars['clearCache']);
		}


		// Topic is visible
		return true;
	}

	/**
	 * Check if we have to process incomming POST data
	 * 
	 * @access public
	 * @return bool 
	 */
	function haveToCheckData() {
		if ($this->parent->getVars['edittopic']) {
			$this->mode = 'edit';
			unset($this->parent->getVars['edittopic']);
		} elseif ($this->parent->getVars['deletetopic']) {
			$this->mode = 'delete';
			unset($this->parent->getVars['deletetopic']);
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Check if topic is modified/created/deleted and update it (if needed)
	 *
	 * @access public
	 * @return void 
	 */
	function checkTopicData() {
		/* Declare */
		$data = Array(
			'errors' => '',
			'mode'   => 'new',
			'shouldContinue' => true,
		);
		$postData = Array();
	
		/* Begin */
		if (isset($this->parent->piVars[$this->datakey])) {
			$postData = &$this->parent->piVars[$this->datakey];
			unset($this->parent->piVars[$this->datakey]);
		}

		//If we have no data it's useless to continue / or if forum is not valid
		if (!count($postData) || !$this->forum->id) {
			return false;
		}

		//Store errors in forum object
		$data['errors'] = &$this->validErrors;

		//Checking mode (permissions will be checked later)
		if (!$this->id) {
			$this->author = &$this->parent->currentUser;
		} elseif ($this->mode == 'edit') {
			$data['mode'] = 'edit';
		} elseif ($this->mode == 'delete') {
			$data['mode'] = 'delete';
		}

		//Checking data validity
		if ($data['mode'] != 'delete') {
			$lastPostedData = $GLOBALS['TSFE']->fe_user->getKey('ses', 'ppforum/lastTopic');

			if (is_array($lastPostedData) && !count(array_diff_assoc($lastPostedData, $postData))) {
				$this->mode = '';
				
				// Clear object cache
				if ($data['mode'] == 'new') {
					$this->getTopicObj(0, true);
				}

				return false;
			}
		}

		$this->mergeData($postData);

		//Checking permissions
		switch ($data['mode']){
		case 'edit':
			if (!$this->id || !$this->userCanEdit()) {
				$data['errors']['global']['access-denied:topic-edit']='Access denied : You can\'t edit this topic';
			}
			break;
		case 'new': 
			if (!$this->forum->userCanPostInForum()) {
				$data['errors']['global']['access-denied:forum-write']='Access denied : You can\'t write in this forum';
			}
			break;
		case 'delete': 
			if (!$this->id || !$this->userCanDelete()) {
				$data['errors']['global']['access-denied:topic-delete']='Access denied : You can\'t delete this topic';
			}
			break;
		default:
			//Nothing :)
			break;
		}
		
		//Playing hook list : Allows to make additional validity/access check
		$this->parent->pp_playHookObjList('topic_checkTopicData_checkValidityAndAccess', $data, $this);

		//Allows a hook ordering to exit
		if (!$data['shouldContinue']) {
			return FALSE;
		}

		if (!count($data['errors'])) {

			if ($data['mode'] != 'delete') {
				$data['errors'] = $this->checkData();
			}

			if ($data['mode'] == 'edit' && isset($this->mergedData['move-topic']) && intval($this->mergedData['move-topic'])) {
				$destinationForum = &$this->parent->getForumObj(intval($this->mergedData['move-topic']));

				if (
					$destinationForum->id &&
					$destinationForum->id != $this->forum->id &&
					$destinationForum->userIsGuard() &&
					$destinationForum->userCanPostInForum()
					) {
					// Clearing caches of old forum
					$this->forum->initPaginateInfos(true);
					$this->forum->event_onUpdateInForum();

					unset($this->forum);
					$this->forum = &$destinationForum;

					// Clearing caches of the new forum
					$this->forum->initPaginateInfos(true);
					$this->forum->event_onNewTopic($this->id);
				}
			}

			if (isset($this->mergedData['move-topic'])) {
				unset($this->mergedData['move-topic']);
			}

			//Playing hook list : Allows to fill other fields
			$this->parent->pp_playHookObjList('topic_checkTopicData_checkAndFetch', $data, $this);

			if (!count($data['errors'])) {
				if ($data['mode']=='delete') {
					$this->delete();
				} else {
					//Preview support
					if (!isset($postData['preview'])) {
						$this->save();
					
						//Saving data for validity check (protection against multi-post)
						$GLOBALS['TSFE']->fe_user->setKey('ses','ppforum/lastTopic',$this->parent->piVars[$this->datakey]);
					} elseif (!$this->id) {
						$this->id = 'preview';
					}
				}

				//Cleaning incomming data
				$this->parent->piVars[$this->datakey]=array();
				$this->mode = '';
			}
		}
	}

	/**
	 * Display topic
	 *
	 * @access public
	 * @return string 
	 */
	function display() {
		/* Declare */
		$content='
	<div class="topic-details">';
		$data=array(
			'data' => array(),
			'mode' => 'view'
			);
	
		/* Begin */
		if ($this->forum->id < 0) {
			$GLOBALS['TSFE']->set_no_cache();
		}
		if (!$this->id) {
			$data['mode'] = 'new';
		} elseif (!intval($this->id)) {
			$data['mode'] = 'preview';
			$this->id=0;
		} elseif ($this->mode == 'edit' && $this->userCanEdit()) {
			$data['mode'] = 'edit';
		} elseif ($this->mode == 'delete' && $this->userCanDelete()) {
			$data['mode'] = 'delete';
		} elseif (count(array_diff_assoc($this->data,$this->mergedData))) {
			$data['mode'] = 'preview';
		}

		// Preloading paginate infos and message list
		if ($this->id) {
			$this->initPaginateInfos();
			if ($this->cache['paginate']['itemCount']) {
				$this->getMessageList();
			}
		}

		$content .= $this->displaySingle($data);
		if ($data['mode'] == 'preview') {
			$data['mode'] = 'edit';
			$content .= $this->displaySingle($data);
		}

		if ($this->id) {
			//Generate message-browser
			$tempStr = $this->parent->displayPagination(
				$this->cache['paginate']['itemCount'],
				$this->cache['paginate']['itemPerPage'],
				$this,
				array('message-browser')
			);
			//Print it
			$content .= $tempStr;
			//Print message list
			$content .= $this->displayMessages();
			//Print browser again
			$content .= $tempStr;
			unset($tempStr);
			//Print footer tools
			$content.=$this->displayTopicTools();
		}

		$content.='
	</div>';//Wrapper tag

		unset($data);//Memory optimisation

		$this->forum->event_onTopicDisplay($this->id);

		return $content;
	}


	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displaySingle($data) {
		/* Declare */
		$content='';
		$addClasses=Array('single-message','single-topic');

		/* Begin */
		if (in_array($data['mode'],array('view','preview'))) {
			if (intval($this->mergedData['status'])==1) {
				$addClasses[]='hidden-message';
			}
		}
		
		//Prints topic anchor
		if ($data['mode']=='preview') {
			$content.='
	<div class="'.htmlspecialchars(implode(' ',$addClasses)).'" id="ppforum_topic_preview_'.$this->id.'">';
		} else {
			$content.='
	<div class="'.htmlspecialchars(implode(' ',$addClasses)).'" id="ppforum_topic_'.$this->id.'">';
		}

		if (0 && $data['mode']!='new') {
			$this->checkMessageInputData();

			if ($this->parent->getVars['clearCache'] && $this->forum->userIsAdmin()) {
				$this->forceReload['forum'] = true;
				$this->event_onUpdateInTopic();
				unset($this->parent->getVars['clearCache']);
			}
		}

		//Openning form tag
		if (in_array($data['mode'],array('new','edit'))) {
			$content.='
	<form method="post" action="'.htmlspecialchars($this->getEditLink(false)).'" class="topic-edit">';
		} elseif ($data['mode']=='delete') {
			$content.='
	<form method="post" action="'.htmlspecialchars($this->getDeleteLink(false)).'" class="topic-delete">';
		}

		if (in_array($data['mode'] ,array('new','edit','delete'))) {
			//** Adding a no_cache hidden field : prevents the page to be pre-cached
			$content .= '
		<div style="display: none;"><input type="hidden" name="no_cache" value="1" /></div>';
		}

		//Building standard parts
		$data['data']['title-row'] = $this->display_titleRow($data['mode']);
		$data['data']['head-row'] = $this->display_headRow($data['mode']);
		$data['data']['parser-row'] = $this->display_parserRow($data['mode']);
		$data['data']['main-row'] = $this->display_mainRow($data['mode']);
		$data['data']['options-row'] = $this->display_optionsRow($data['mode']);
		$data['data']['tools-row'] = $this->display_toolsRow($data['mode']);

		//Playing hooks : Allows to manipulate parts (add, sort, etc)
		$this->parent->pp_playHookObjList('topic_display', $data, $this);

		//Printing parts
		foreach ($data['data'] as $key=>$val) {
			if (trim($val)) {
				$content.='
		<div class="row '.htmlspecialchars($key).'">'.$val.'
		</div>';
			}
		}

		//Closing tags
		if (in_array($data['mode'] ,array('new','edit','delete'))) $content.='
	</form>';
		$content.='
	</div>'; //Anchor tag

		return $content;
	}

	/**
	 * Displays title part
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return string 
	 */
	function display_titleRow($mode) {
		if (in_array($mode,array('view','delete','preview'))) {
			return $this->getTitleLink();
		} else {
			return $this->parent->pp_getLL('topic.title','Title : ').' <input value="'.tx_pplib_div::htmlspecialchars($this->mergedData['title']).'" type="text" size="30" maxlength="200" name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[title]" />' . strval($this->error_getFieldError('title'));
		}
	}

	/**
	 * Display message list
	 *
	 * @access public
	 * @return string 
	 */
	function displayMessages() {
		/* Declare */
		$content = '<div class="message-list">';
		$counter = 0;
		$obj = null;
	
		/* Begin */
		if ($this->cache['paginate']['itemCount']) {
			$messageList = $this->getMessageList();

			foreach ($messageList as $message) {
				$data = array(
					'message' => null,
					'classes' => array(),
					'counter' => $counter,
				);
				$data['message'] = &$this->parent->getMessageObj($message);

				//Add some classes to the child tag (may be used in CSS)
				if ($counter%2) {
					$data['classes'][] = 'row';
				}	else {
					$data['classes'][] = 'row-alt';
				}

				if (!$counter) {
					$data['classes'][] = 'row-first';
				} elseif ($counter == $length-1) {
					$data['classes'][] = 'row-last';
				}

				//Play a hook list : allows to add more classes to the child row
				$this->parent->pp_playHookObjList('topic_displayMessages', $data, $this);

				$content .= $data['message']->display($data['classes']);

				unset($data['message']);
				$counter++;
			}

			$content .= '</div>';
			return $content;
		} else {
			return '';
		}
	}

	/**
	 * Displays topic footer tools (new post, etc)
	 *  Call  USER_INT cObj
	 *
	 * @access public
	 * @return string 
	 */
	function displayTopicTools() {
		$conf=Array(
			'cmd'=>'callObj',
			'cmd.'=>Array(
					'object'=>'topic',
					'uid'=>$this->id,
					'method'=>'_displayTopicTools',
				),
			);
		return $this->parent->callINTpart($conf);
	}

	/**
	 * Callback for displayTopicTools
	 *
	 * @access public
	 * @return string 
	 */
	function _displayTopicTools($conf) {
		/* Declare */
		$tarr=array();
		$content='';
		$param=array('topic'=>intval($this->id));
		$data=Array(
			'toolbar'=>Array(),
			'hiddentools'=>Array()
			);

		/* Begin */
		if ($this->userCanReplyInTopic()) {
			$data['toolbar']['reply-link']     = '<a class="button" href="#" onclick="return tx_ppforum.showhideTool(this,\'reply-form\');">'.$this->parent->pp_getLL('topic.newpost','Reply',TRUE).'</a>';
			$data['hiddentools']['reply-form'] = $this->displayReplyForm();
		}
		$data['toolbar']['refresh-link'] = '<a class="button" href="'.htmlspecialchars($this->getLinkKeepPage()).'">'.$this->parent->pp_getLL('topic.refresh','Refresh').'</a>';

		if ($this->parent->config['.lightMode']) {
			$url=$this->getLink(FALSE,Array('lightMode'=>!$this->parent->getVars['lightMode'],'pointer'=>$this->parent->getVars['pointer']));
			$data['toolbar']['lightmode-link']='<a class="button" href="'.htmlspecialchars($url).'">'.$this->parent->pp_getLL('topic.stdMode','Normal Mode').'</a>';
		} else {
			$url=$this->getLink(FALSE,Array('lightMode'=>!$this->parent->getVars['lightMode'],'pointer'=>$this->parent->getVars['pointer']));
			$data['toolbar']['lightmode-link']='<a class="button" href="'.htmlspecialchars($url).'">'.$this->parent->pp_getLL('topic.lightMode','Light Mode').'</a>';
		}

		if ($this->forum->userIsAdmin()) {
			$nbVersions = count(tx_pplib_cachemgm::getHashList($param, false));

			$url=$this->getLink(
				FALSE,
				array('clearCache'=>1,'pointer'=>$this->parent->getVars['pointer'])
				);
			$data['toolbar']['clearcache-link']='<a class="button" href="'.htmlspecialchars($url).'">'.
				str_replace(
					'###NBPAGES###',
					$nbVersions,
					$this->parent->pp_getLL('topic.clearCache','Refresh Topic\'s cache (###NBPAGES### versions)',TRUE)
					).
				'</a>';
		}

		$this->parent->pp_playHookObjList('topic_displayTopicTools', $data, $this);

		$content.='<div class="topic-toolbar toolbar">';
		if (count($data['toolbar'])) {
			$content.=implode(' ',$data['toolbar']);
		} else {
			$content.='&nbsp;';
		}
		$content.='</div>';

		if (count($data['hiddentools'])) {
			$content.='<div class="hiddentools">';
			foreach ($data['hiddentools'] as $key=>$val) {
				$content.='<div class="single-tool '.htmlspecialchars($key).'"'.($val['display']?'':' style="display: none;"').'>'.$val['content'].'</div>';
			}	
			$content.='</div>';
		}

		if ($this->id && intval($this->id) && $this->parent->currentUser->getId()) {
			
			// Step 1 : recalculate read / unread messages if needed
			if ($this->parent->currentUser->getUserPreference('latestVisitDate') <= intval($this->data['tstamp'])) {
				$handler = &$this->parent->getUnreadTopicsHandler();
				$handler->initPaginateInfos();
			}

			// Step 2 : Get unread topic list and remove current from them
			$forumKey = t3lib_div::shortMD5($this->parent->config['pidFullList']);
			$forumInfos = $this->parent->currentUser->getUserPreference('latest_' . $forumKey);
			if (isset($forumInfos['preloadedTopicList'][$this->id])) {
				unset($forumInfos['preloadedTopicList'][$this->id]);
			}
			$this->parent->currentUser->setUserPreference('latest_' . $forumKey, $forumInfos);
		}

		return $content;
	}

	/**
	 * Builds the display array of the reply form (for use in _displayTopicTools)
	 *
	 * @access public
	 * @return array
	 */
	function displayReplyForm() {
		//Build a message object
		$obj = &$this->parent->getMessageObj(0);

		//Force the parent topic to this (message functions must it to be set !)
		if (!is_object($obj->topic) || $obj->topic->id != $this->id) {
			$obj->topic = &$this;
		}
		return array(
			'content'=>'<div class="tool-title">'.$this->parent->pp_getLL('topic.newpost.title','Reply',TRUE).'</div>'.$obj->display(), //The form
			'display'=>(is_array($obj->validErrors) && count($obj->validErrors)) //If true, the form will not be hidden
		);
	}

	/****************************************/
	/******* Access check functions *********/
	/****************************************/

	/**
	 * Access check : Checks if current user can write (post edit, delete, etc...) in this topic
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanWriteInTopic() {
		$res = false;

		//Checking write access in parent forum
		if ($this->forum->userCanWriteInForum()) {
			//Then, checking topic status
			if ($this->data['status'] == 0 || $this->forum->userIsGuard()) {
				$res = true;
			}
		}

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_userCanWriteInTopic', $res, $this);

		return $res;
	}

	/**
	 * Access check : Check if current user can post a new message
	 * For now it's a simple alias of userCanWriteInTopic, but it can change :)
	 *
	 * @access public
	 * @return boolean 
	 */
	function userCanReplyInTopic() {
		$res=FALSE;

		if ($this->userCanWriteInTopic()) {
			if ($this->forum->userCanReplyInForum(TRUE)) {
				$res=TRUE;
			}
		}

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_userCanReplyInTopic', $res, $this);

		return $res;
	}

	/**
	 * Return TRUE if user has basic write access on this topic : he is the author, or he is a guard
	 * /!\ You should NEVER use this function if the current page will be cached ! /!\
	 *
	 * @access public
	 * @return boolean 
	 */
	function getBasicWriteAccess() {
		$res=FALSE;
		//Checking topic status
		if ($this->userCanWriteInTopic()) {
			if (!$this->id) {
				//Mode "new topic"
				$res=TRUE;
			} elseif ($this->parent->currentUser->getId() && ($this->data['author']==$this->parent->currentUser->getId())) {
				//User is author
				# /!\ User based check /!\
				$res=TRUE;
			} elseif ($this->forum->userIsGuard()) {
				//User is guard
				$res=TRUE;
			}
		}

		return $res;
	}

	/**
	 * Access check : check if current user can edit this topic
	 * /!\ You should NEVER use this function if the current page will be cached ! /!\
	 *
	 * @access public
	 * @return boolean 
	 */
	function userCanEdit() {
		# /!\ User based check /!\
		$res = $this->getBasicWriteAccess();
		$res = $res && $this->forum->userCanEditTopic($this->id);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_userCanReplyInTopic', $res, $this);

		return $res;
	}


	/**
	 * Access check : Check if current user can delete this topic
	 * /!\ You should NEVER use this function if the current page will be cached ! /!\
	 *
	 * @access public
	 * @return bool
	 */
	function userCanDelete() {
		# /!\ User based check /!\
		$res = $this->getBasicWriteAccess();
		$res = $res && $this->forum->userCanDeleteTopic($this->id,$res);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_userCanDelete', $res, $this);

		return $res;
	}


	/**
	 * Additional access check
	 *
	 * @param int $messageId = the message uid
	 * @param boolean $res = the original access
	 * @access public
	 * @return boolean 
	 */
	function userCanEditMessage($messageId) {
		$res = $this->forum->userCanEditMessage($messageId);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_userCanEditMessage', $res, $this);

		return $res;
	}

	/**
	 * Additional access check
	 *
	 * @param int $messageId = the message uid
	 * @param boolean $res = the original access
	 * @access public
	 * @return boolean 
	 */
	function userCanDeleteMessage($messageId) {
		$res = $this->forum->userCanDeleteMessage($messageId);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_userCanDeleteMessage', $res, $this);

		return $res;
	}

	/****************************************/
	/******* Messages related funcs *********/
	/****************************************/

	/**
	 * Initialize paginationInfo
	 *
	 * @param bool $clearCache = set to true to force new calc & clearing message lists
	 * @access public
	 * @return void 
	 */
	function initPaginateInfos($clearCache = false) {
		if ($clearCache || !$this->cache['paginate']) {
			$this->cache['paginate'] = $this->parent->pagination_calculateBase(
				$this->db_getMessageCount(),
				$this->parent->config['display']['maxMessages']
			);

			if ($this->cache['messageList']['_last'] && !$clearCache) {
				// Special case : last message may have been loaded before first init, so keep it
				$last = $this->cache['messageList']['_last'];
			}

			$this->cache['messageList'] = array();

			$this->cache['messageList']['_'] = false;
			$this->cache['messageList']['_loaded'] =	array();

			if (isset($last)) {
				$this->cache['messageList']['_loaded'][] = $last;
			}

			for ($i=0; $i<$this->cache['paginate']['pageCount']; $i++) {
				$this->cache['messageList'][$i] = false;
			}
		}
	}

	/**
	 * Counts topic's messages
	 *
	 * @access public
	 * @return int 
	 */
	function db_getMessageCount($options = array()) {
		/* Declare */
		$res = 0;
		$options += array(
			'nocheck' => false,
		);

		/* Begin */
		if (isset($this->data['__count_messages'])) {
			$res = $this->data['__count_messages'];
			unset($this->data['__count_messages']);
		} else {
			$res = $this->parent->db_queryItems(array(
				'count(uid) as count_messages',
				'message',
				$this->db_messagesWhere($options['nocheck']),
				null,
			), array(
				'sort' => false,
			));

			if (isset($res[0]['count_messages'])) {
				$res = intval($res[0]['count_messages']);
			}
		}

		return $res;
	}

	/**
	 * Performs a query on topic's messages ids
	 *
	 * @param array $params = query parameters. Keys are :
	 *                  - int page = "page" to load (null mean everything)
	 *                  - bool preload = query will fetch full rows and they will be loaded into record objects
	 *                  - bool nockech = disable access check
	 *                  - bool clearCache = clear current query cache
	 *                  - mixed sort = bool to enable / disable sorting, string for sorting options
	 * @access public
	 * @return array 
	 */
	function db_getMessageList($params = array()) {
		/* Declare */
		$params += array(
			'page' => null,
			'preload' => true,
			'nocheck' => false,
			'clearCache' => false,
			'sort' => true,
		);
		$page = $params['page'];
		$limit = '';
	
		/* Begin */
		$this->initPaginateInfos();
		if (is_null($page)) {
			// set special key
			$page = '_';
		} else {
			// Resolve pointer and calculate LIMIT
			$page = $this->parent->pagination_parsePointer($this->cache['paginate'], $page);
			$limit = implode(
				',',
				$this->parent->pagination_getRange($this->cache['paginate'], $page)
			);
		}

		if (!$params['clearCache'] && is_array($this->cache['messageList'][$page])) {
			$idList = $this->cache['messageList'][$page];
		} else {
			if ($this->cache['paginate']['itemCount']) {
				$idList = $this->db_getMessageListQuery($limit, $params);
			} else {
				$idList = array();
			}

			$this->cache['messageList'][$page] = $idList;

			if ($page == '_') {
				// As the full list has been loaded, we can determine each page id list
				$this->cache['messageList'] = array_merge(
					array_chunk($idList, $this->cache['paginate']['itemPerPage']),
					array(
						'_' => $idList,
						'_loaded' => $idList,
					)
				);
			} elseif (!$this->cache['messageList']['_']) {
				$this->cache['messageList']['_loaded'] = array_merge($this->cache['messageList']['_loaded'], $idList);
			}
		}

		$this->parent->internalLogs['querys']++;

		return $idList;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function db_getLastMessage($params = array()) {
		/* Declare */
		$params += array(
			'preload' => true,
			'nocheck' => false,
			'clearCache' => false,
		);
		$id = 0;
	
		/* Begin */
		if (!$params['clearCache'] && isset($this->cache['messageList']['_last'])) {
			$id = $this->cache['messageList']['_last'];
		} else {
			$id = reset($this->db_getMessageListQuery(
				'1',
				array(
					'preload' => $params['preload'],
					'nocheck' => $params['nocheck'],
					'sort' => 'reverse',
				)
			));

			$this->cache['messageList']['_last'] = $id;
			$this->cache['messageList']['_loaded'][] = $id;
		}

		$this->parent->internalLogs['querys']++;

		return $id;
	}

	/**
	 * 
	 *
	 * @param 
	 * @access public
	 * @return mixed 
	 */
	function db_getMessageListQuery($limit = '', $options = array()) {
		/* Declare */
		$res = array();
		$options += array(
			'preload' => false,
			'nocheck' => false,
			'sort' => true,
		);

		/* Begin */
		$res = $this->parent->db_queryItems(array(
			'uid',
			'message',
			$this->db_messagesWhere($options['nocheck']),
			null,
			null,
			$limit,
			'uid'
		), array(
			'preload' => $options['preload'],
			'sort' => $options['sort'],
		));

		$res = array_keys($res);

		return $res;
	}

	/**
	 * Build the basic where statement to select topic's message
	 *
	 * @access public
	 * @return string 
	 */
	function db_messagesWhere($nocheck = false) {
		$where = 'topic = ' . $this->id;
		$where .= $this->forum->db_messagesAddWhere($nocheck);

		return $where;
	}

	/**
	 * Returns the 'pointer' GET var value
	 * 
	 * @access public
	 * @return mixed 
	 */
	function getCurrentPointer() {
		return isset($this->parent->getVars['pointer']) ? $this->parent->getVars['pointer'] : 0;
	}

	/**
	 * Gets the messages id list to be displayed on given page. Message objects will be preloaded
	 * 
	 * @param mixed $page = page to display ('pointer' GET var by default, can be 'last' or any integer)
	 * @access public
	 * @return array 
	 */
	function getMessageList($page = null) {
		if (is_null($page)) {
			$page = $this->getCurrentPointer();
		}
		$messageList = $this->db_getMessageList(array(
			'page' => $page,
		));
	
		$this->parent->addItemsToPreloadStack($messageList, 'message');
		$this->parent->flushDelayedObjects();

		return $messageList;
	}

	/**
	 * Return the last posted message's uid (in this topics)
	 *
	 * @access public
	 * @return int 
	 */
	function getLastMessage() {
		$res = $this->db_getLastMessage();
		$this->parent->flushDelayedObjects();
		return $res;
	}

	/**
	 * Returns the page number where the message is displayed
	 *
	 * @param int $messageId = message's uid
	 * @access public
	 * @return int/string 
	 */
	function getMessagePageNum($messageId=0) {
		/* Declare */
		$res = $this->cache['paginate'] ? ($this->cache['paginate']['pageCount'] - 1) : 'last'; //Default value

		/* Begin */
		if (!isset($this->cache['messageList']['_loaded']) || !in_array($messageId, $this->cache['messageList']['_loaded'])) {
			$this->db_getMessageList(array(
				'preload' => false,
			));

			t3lib_div::debug($this->cache, 'db_getMessageList called');
		}


		for ($i=0;$i<$this->cache['paginate']['pageCount'];$i++) {
			if ($this->cache['messageList'][$i] && in_array($messageId, $this->cache['messageList'][$i])) {
				$res = $i;
			}
		}

		return $res;
	}

	/**
	 * Additional message visibility check
	 *
	 * @param int $messageId = the message uid
	 * @access public
	 * @return boolean = TRUE if visible 
	 */
	function messageIsVisible($messageId) {
		$res=$this->forum->messageIsVisible($messageId);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('topic_messageIsVisible', $res, $this);

		return $res;
	}

	/**
	 * Return TRUE if the message has been deleted here/in forum (and also don't need to be delete in tx_ppforum_message->delete)
	 *
	 * @param int $messageId = the message uid
	 * @access public
	 * @return boolean 
	 */
	function deleteMessage($messageId) {
		$data=array(
			'res'=>FALSE,
			'messageId'=>$messageId
			);

		//Plays hook list : don't forget to set the $res value to TRUE is your hook deletes the message, otherwise it ill done a second time !
		$this->parent->pp_playHookObjList('topic_deleteMessage', $data, $this);
		
		if (!$data['res'] && is_object($this->forum) && $this->forum->deleteMessage($messageId)) {
			$data['res']=TRUE;
		}
		return $data['res'];
	}

	/**
	 * Builds a link to a message
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @param array $addParams = additional url parameters.
	 * @param string $parameter = typolink parameter value.
	 * @access public
	 * @return string 
	 */
	function getMessageLink($title='',$addParams=array(),$parameter='') {
		return $this->getLink(
			$title,
			$addParams, //overrule piVars
			$parameter
		);
	}

}

tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_topic.php');
?>