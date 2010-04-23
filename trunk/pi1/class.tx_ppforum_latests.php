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

tx_pplib_div::dynClassLoad('tx_ppforum_forum');

/**
 * Class 'tx_ppforum_forum' for the 'pp_forum' extension.
 * Forum simulator
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_latests extends tx_ppforum_forum {

	/**
	 * Initialisation function : have to be called immediatly after instanciation
	 *
	 * @access public
	 * @return void 
	 */
	function initialize() {
		$this->forum = &$this->parent->getForumObj(0);
		$this->getMetaData();
	}

	/****************************************/
	/********** Erasing functions ***********/
	/****************************************/

	function load($id, $clearCache = false, $delaySubs = false) {
		return false;
	}

	function loadData($data, $delaySubs = false) {
		return false;
	}

	function displayHeader() {
		return '';
	}

	function displayChildList() {
		return '';
	}

	function displayForumTools() {
		return '';
	}

	/****************************************/
	/********** Overload functions **********/
	/****************************************/

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getPageTitle() {
		return $this->parent->pp_getLL('latest.pageTitle', null, false);
	}

	/**
	 * Generate a link to this forum
	 *
	 * @access public
	 * @return string 
	 */
	function getLink($title = false, $addParams=Array(), $parameter = null) {
		$addParams['mode'] = 'latest';
		
		if (!isset($addParams['lightMode'])) $addParams['lightMode'] = $this->parent->getVars['lightMode'];
		return $this->parent->pp_linkTP_piVars(
			$title,
			$addParams,
			TRUE,
			$parameter
		);
	}

	/**
	 * Initialize paginationInfo
	 *
	 * @param bool $clearCache = set to true to force new calc & clearing topic lists
	 * @access public
	 * @return void 
	 */
	function initPaginateInfos($clearCache = false) {
		if ($clearCache || !$this->_paginate) {
			$this->_topicList = array();
			$this->_topicList['_'] = $this->db_getTopicListQuery();

			$this->_paginate = $this->parent->pagination_calculateBase(
				$this->db_getTopicCount(),
				$this->parent->config['display']['maxTopics']
			);

			$this->_topicList = array_merge(
				array_chunk($this->_topicList['_'], $this->_paginate['itemPerPage']),
				array(
					'_' => $this->_topicList['_'],
					'_loaded' => $this->_topicList['_'],
				)
			);
		}
	}


	/**
	 * Counts forum's topics
	 *
	 * @access public
	 * @return int 
	 */
	function db_getTopicCount($options = array()) {
		return count($this->_topicList['_']);
	}

	/**
	 * Performs a query on forum's topics ids
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
	function db_getTopicList($params = array()) {
		/* Declare */
		$params += array(
			'page' => null,
		);
		$page = $params['page'];
	
		/* Begin */
		$this->initPaginateInfos();

		if (is_null($page)) {
			$page = '_';
		} else {
			$page = $this->parent->pagination_parsePointer($this->_paginate, $page);
		}

		$this->parent->internalLogs['querys']++;
		return $this->_topicList[$page];
	}


	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function db_getLastTopic($params = array()) {
		return end($this->_topicList['_']);
	}

	/**
	 * 
	 *
	 * @param 
	 * @access public
	 * @return mixed 
	 */
	function db_getTopicListQuery($limit = '', $options = array()) {
		/* Declare */
		$res = array();
		$latestVisitDate = $this->parent->currentUser->getUserPreference('latestVisitDate');
		$latestVisitDate = max($latestVisitDate, intval($this->parent->currentUser->data['crdate']));
		$preloadedTopicList = tx_pplib_div::forceArray($this->parent->currentUser->getUserPreference('preloadedTopicList'));

		/* Begin */
		$debug = $_GET['test'];

		if ($debug) {
			$latestVisitDate = 0;
		}

		$topicList = $this->db_getTopicListQuery_raw($latestVisitDate, $preloadedTopicList);
		$topicListIds = array_keys($topicList);
		$countTopics = count($topicListIds);
		$preloadedTopicList = array();

		if ($debug) t3lib_div::debug($countTopics, 'New topics count');

		$i = 0;
		$bufferLength = 0;
		$topicListPage = 0;
		$countResults = 0;

		while ($countResults < 40 && $i < $countTopics) {
			// Simulated buffer : Load topics by list to reduce effective query count
			if (!$bufferLength) {
				$this->parent->loadRecordObjectList(array_slice($topicListIds, $topicListPage * 20, 20), 'topic');
				$this->parent->flushDelayedObjects();
				$bufferLength = 20;
				$topicListPage++;
				if ($debug) t3lib_div::debug($topicListPage, 'Buffer call');
				if ($debug) t3lib_div::debug(t3lib_div::formatSize(memory_get_usage()), 'Memory usage');
			}

			// Load topic
			$currentTopicId = $topicListIds[$i];
			$topic = &$this->parent->getTopicObj($currentTopicId);

			// Check topic visibility
			if ($topic->isVisibleRecursive()) {
				// Topic is visible : add it to final topic list
				$preloadedTopicList[$currentTopicId] = $topicList[$currentTopicId];
				$countResults++;
				if ($debug) t3lib_div::debug('Visible -> added to list', 'Topic #'.$currentTopicId. ' : ' . $topic->data['title'] . ' / ' . $topic->forum->id);
			} else {
				if ($debug) t3lib_div::debug('Invisible -> free memory', 'Topic #'.$currentTopicId. ' : ' . $topic->data['title'] . ' / ' . $topic->forum->id);
				// Free memory : as long as objects are assigned with correct EXPLICIT references, it will work
				$topic = null;
			}

			$bufferLength--;
			$i++;
		}

		$this->parent->currentUser->setUserPreference('preloadedTopicList', $preloadedTopicList);
		$this->parent->currentUser->setUserPreference('latestVisitDate', $GLOBALS['SIM_EXEC_TIME']);


		return array_keys($preloadedTopicList);
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function db_getTopicListQuery_raw($latestVisitDate, $preloadedTopicList) {
		/* Declare */
		$topicList = $this->parent->getLatestsTopics($latestVisitDate);
		$messageList = $this->parent->getLatestsMessages($latestVisitDate);
	
		/* Begin */
		// Add previous unread topics
		$topicList += $preloadedTopicList;

		foreach ($messageList as $id => $infos) {
			if (isset($topicList[$infos['topic']])) {
				// Topic is already in list, so store the latest creation date (in case of multiple messages)
				$topicList[$infos['topic']] = max($topicList[$infos['topic']], $infos['crdate']);
			} else {
				$topicList[$infos['topic']] = $infos['crdate'];
			}
		}

		arsort($topicList);

		return $topicList;
	}

	/**
	 * Build the basic where statement to select forum's topic
	 *
	 * @access public
	 * @return string 
	 */
	function db_topicsWhere($nocheck = false) {
		return '1 = 0';
	}


	/**
	 * Access check : Check if current user can write in forum
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanWriteInForum() {
		return false;
	}

	/**
	 * Access check : Check if current user can create a new topic
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanPostInForum() {
		return false;
	}

	/**
	 *
	 *
	 * @param bool $dontCheckWriteAccess = TRUE if no need to check write access before (maybe it has already been done)
	 * @access public
	 * @return bool 
	 */
	function userCanReplyInForum($dontCheckWriteAccess = false) {
		return false;
	}
	/**
	 * Access check : Check if user is a "Guard"
	 *
	 * @access public
	 * @return boolean 
	 */
	function userIsGuard() {
		return false;
	}


	/**
	 * Access check : Check if user is an Admin
	 *
	 * @access public
	 * @return boolean 
	 */
	function userIsAdmin() {
		return false;
	}

}

tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_latests.php');
?>