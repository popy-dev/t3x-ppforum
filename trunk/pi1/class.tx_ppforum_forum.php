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

require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_base.php');

/**
 * Class 'tx_ppforum_forum' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_forum extends tx_ppforum_base {

	/**
	 * Pointer to parent forum object
	 * @access public
	 * @var object
	 */
	var $forum = null;

	/**
	 * Misc options
	 * @access public
	 * @var array
	 */
	var $options = Array(
		'unsetForumId' => true,
	);

	/**
	 * userIsGuard() cache
	 * @access private
	 * @var boolean
	 */
	var $userIsGuard = null;

	/**
	 * userIsAdmin() cache
	 * @access private
	 * @var boolean
	 */
	var $userIsAdmin = null;

	/**
	 * 
	 * @access public
	 * @var string
	 */
	var $metaData = array();

	/**
	 * Access list
	 * @access public
	 * @var array
	 */
	var $access = array();

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
	function loadData($data, $delaySubs = false) {
		if (parent::loadData($data)) {
			$this->forum = &$this->parent->getForumObj($this->data['parent'], $delaySubs);
			$this->getMetaData();
		}

		return $this->id;
	}

	/**
	 *
	 *
	 * @param bool $clearCache = 
	 * @access public
	 * @return void 
	 */
	function loadTopicList($clearCache = false) {
		if (!is_array($this->topicList) || $clearCache) {
			$this->topicList = array();
			$idList = $this->parent->getForumTopics($this->id, $clearCache);

			$this->parent->loadRecordObjectList($idList, 'topic');
			$this->parent->flushDelayedObjects();

			foreach ($idList as $topicId) {
				$topic = &$this->parent->getTopicObj($topicId);
				if ($topic->isVisible() && $this->topicIsVisible($topic->id)) {
					$this->topicList[$topicId] = &$topic;
				}
			}
		}
	}

	/**
	 *
	 *
	 * @access public
	 * @return int 
	 */
	function getLastTopic() {
		$listIds=$this->parent->getForumTopics($this->id,FALSE,'nopinned');
		$ok=FALSE;
		$current=0;

		while (!$ok && $current<count($listIds)) {
			$topic=&$this->parent->getTopicObj($listIds[$current]);
			if ($topic->isVisible() && $this->topicIsVisible($listIds[$current])) {
				$ok=TRUE;
			} else {
				$current++;
			}
		}

		return $listIds[$current];
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getMetaData() {
		if (trim($this->data['force_language'])) {
			$this->metaData['force_language'] = $this->data['force_language'];
		}

		if (is_object($this->forum)) {
			$this->metaData = array_merge($this->forum->metaData, $this->metaData);
		}
	}

	/**
	 * Init user's access
	 * 
	 * @access public
	 * @return void 
	 */
	function initAccesses() {
		if (empty($this->access)) {
			$this->parent->currentUser->loadUserGroups();
			$this->access = $this->readAccess($this->parent->currentUser);
		}
	}

	/**
	 * Determine given user's role & permissions
	 *
	 * @param tx_ppforum_user $user = 
	 * @access public
	 * @return array 
	 */
	function readAccess(&$user) {
		$result = array();
		$parentAccess = array();

		// Load parent access to be able to inherit from him
		if ($this->id) {
			$parentAccess = $this->forum->readAccess($user);
		}

		$result['admin'] = $this->readSingleAccess($user, $parentAccess, 'admin', false);
		$result['guard'] = $result['admin'] || $this->readSingleAccess($user, $parentAccess, 'guard', false);
		$result['write'] = $result['guard'] || $this->readSingleAccess($user, $parentAccess, 'write', true);
		$result['read']  = $result['guard'] || $this->readSingleAccess($user, $parentAccess, 'read', true);

		$result['restrict'] = array();
		foreach (array('newtopic','reply','edit','delete') as $name) {
			$result['restrict'][$name] = $this->readSingleRestrict($result, $name);
		}

		return $result;
	}

	/**
	 * Check an access right for an user in the current forum
	 *
	 * @param tx_ppforum_user $user = the user
	 * @param array $parentAccess = inherited rights
	 * @param string $str = The access key to "determine"
	 * @param bool $noneIsEverybody = defines what means an empty selection (false mean nobody, true mean everybody
	 * @access protected
	 * @return boolean
	 */
	function readSingleAccess(&$user, $parentAccess, $access, $noneIsEverybody) {
		/* Declare */
		$res  = false;
		$mode = isset($this->data[$access . 'access_mode']) ? $this->data[$access . 'access_mode'] : 'erase';
		$list = array_filter(t3lib_div::intExplode(',', $this->data[$access.'access']));

		/* Begin */
		if ($mode != 'inherit' || !$this->id) { //Mode erase (forum 0 is always set to erase)
			if (count($list)) {
				if ($user->id && count(array_intersect($list, array_keys($user->userGroups)))) {
					$res = true;
				}
			} elseif ($noneIsEverybody) {
				$res = true;
			}
		} else { //Inherit mode
			$res = $parentAccess[$access];
		}

		return $res;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return boolean 
	 */
	function readSingleRestrict($currentAccess, $name) {
		/* Declare */
		$result   = false;
		$fieldVal = isset($this->data[$name.'_restrict']) ? $this->data[$name.'_restrict'] : 'inherit';
	
		/* Begin */
		if (!$this->id && $fieldVal == 'inherit') {
			$fieldVal = 'everybody'; // Root forum can't inherit
		}

		switch ($fieldVal) {
		case 'everybody': 
			$result = true;
			break;
		case 'guard': 
			$result = $currentAccess['guard'];
			break;
		case 'admin': 
			$result = $currentAccess['admin'];
			break;
		case 'inherit':
			if ($this->id) {
				$result = $this->forum->readSingleRestrict($currentAccess, $name);
			} else {
				$result = true;
			}
			break;
		default:
			break;
		}

		return $result;
	}

	/**
	 * 
	 * 
	 * @access public
	 * @return tx_ppforum_forum 
	 */
	function &getFirstVisibleParent() {
		/* Declare */
		$res = &$this;
	
		/* Begin */
		while (is_object($res) && !$res->isVisible()) {
			$res = &$res->forum;
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
	function alternateRendering($topicId) {
		/* Declare */
		$res = array(
			'topicId' => null,
			'forumId' => null,
		);

		/* Begin */
		switch ($this->data['ftype']) {
		case 'topic_shortcut':
			// Shortcut to first topic : only if no current topic
			if (!$topicId) {
				$this->loadTopicList();

				if (count($this->topicList)) {
					$res['topicId'] = reset(array_keys($this->topicList));
				}
			}
			break;
		default:
			break;
		}

		return $res;
	}

	/**
	 * Generate a link to this forum
	 *
	 * @access public
	 * @return string 
	 */
	function getLink($title = false, $addParams=Array(), $parameter = null) {
		if (!isset($addParams['forum']) && $this->id) {
			$addParams['forum'] = $this->id;
		}
		
		if (!isset($addParams['lightMode'])) $addParams['lightMode'] = $this->parent->getVars['lightMode'];
		return $this->parent->pp_linkTP_piVars(
			$title,
			$addParams,
			TRUE,
			$parameter
		);
	}

	/**
	 * Generate a link to this forum
	 *
	 * @access public
	 * @return string 
	 */
	function getTitleLink() {
		return $this->getLink($this->getTitle());
	}

	/**
	 * Returns the forum's title
	 * 
	 * @param bool $hsc = if true, title will be passed throught htmlspecialchars
	 * @access public
	 * @return string 
	 */
	function getTitle($hsc = true) {
		return $hsc ? tx_pplib_div::htmlspecialchars($this->data['title']) : $this->data['title'];
	}

	/**
	 * Returns the forum's title especially for the page's title
	 * 
	 * @access public
	 * @return string 
	 */
	function getPageTitle() {
		return $this->getTitle(false);
	}

	/**
	 * Builds a link to a topic (including anchor)
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @param array $addParams = additional url parameters.
	 * @access public
	 * @return string 
	 */
	function getTopicLink($title = false, $addParams=array(), $parameter = null) {
		if (!isset($addParams['forum'])) {
			if ($this->options['keepCurrentForumId']) {
				$addParams['forum']=$this->parent->getCurrentForum();
			} elseif ($this->options['unsetForumId']) {
				$addParams['forum']='';
			}
		}

		return $this->getLink(
			$title,
			$addParams, //overrule piVars
			$parameter
			);
	}


	/**
	 * Load counters for a forum (nb topics, posts, etc)
	 *
	 * @param int $forumId = forum's uid
	 * @param bool $clearCache = set to true to force reloading of all counters, or to 'clearCache' to unset cache without calculating
	 * @access public
	 * @return array 
	 */
	function getCounters($clearCache = false) {
		if ($clearCache || is_null($this->counters)) {
			$this->counters = array(
				'topics' => 0,
				'posts'  => 0,
			);

			$subForums = $this->parent->getRecursiveForumChilds($this->id, $clearCache);
			$subForums[] = $this->id;

			$fullTopicList = $this->parent->getForumTopics($subForums, $clearCache);

			$this->counters['topics'] = count($fullTopicList);
			$this->counters['posts'] = $this->parent->getTopicMessages($fullTopicList, true);

			$this->parent->pp_playHookObjList('forum_getCounters', $this->counters, $this);
		} else {
			tx_pplib_div::debug('forum:' . $this->id, 'cached counter');
		}

		return $this->counters;
	}

	/**
	 * Access check : Check if current user can write in forum
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanWriteInForum() {
		//Load basic access
		$this->initAccesses();
		$res = $this->access['write'];

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('forum_userCanWriteInForum', $res, $this);

		return $res;
	}

	/**
	 * Access check : Check if current user can create a new topic
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanPostInForum() {
		$res = $this->userCanWriteInForum();
		$res = $res && !$this->data['notopic'] && $this->access['restrict']['newtopic'];

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('forum_userCanPostInForum', $res, $this);
		
		return $res;
	}

	/**
	 *
	 *
	 * @param bool $dontCheckWriteAccess = TRUE if no need to check write access before (maybe it has already been done)
	 * @access public
	 * @return bool 
	 */
	function userCanReplyInForum($dontCheckWriteAccess=FALSE) {
		if ($dontCheckWriteAccess) {
			$res = true;
		} else {
			$res = $this->userCanWriteInForum();
		}

		$res = $res && $this->access['restrict']['reply'];
	
		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('forum_userCanReplyInForum', $res, $this);
		
		return $res;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanEditMessage($messageId) {
		return $this->userCanWriteInForum() && $this->access['restrict']['edit'];
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanDeleteMessage($messageId) {
		return $this->userCanWriteInForum() && $this->access['restrict']['delete'];
	}


	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function topicIsVisible($topicId) {
		return in_array($topicId, $this->parent->getForumTopics($this->id));
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanDeleteTopic($topicId) {
		return $this->userCanWriteInForum() && $this->access['restrict']['delete'];
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanEditTopic($topicId) {
		return $this->userCanWriteInForum() && $this->access['restrict']['edit'];
	}


	/**
	 * Access check : Check if user is a "Guard"
	 *
	 * @access public
	 * @return boolean 
	 */
	function userIsGuard() {
		// Check cached result
		if (is_null($this->userIsGuard)) {
			//Load basic access
			$this->initAccesses();
			$this->userIsGuard = $this->access['guard'] ? true : false;

			//Plays hook list : Allows to change the result
			$this->parent->pp_playHookObjList('forum_userIsGuard', $this->userIsGuard, $this);
		}
		
		return $this->userIsGuard;
	}


	/**
	 * Access check : Check if user is an Admin
	 *
	 * @access public
	 * @return boolean 
	 */
	function userIsAdmin() {
		// Check cached result
		if (is_null($this->userIsAdmin)) {
			//Load basic access
			$this->initAccesses();
			$this->userIsAdmin = $this->access['admin'];

			//Plays hook list : Allows to change the result
			$this->parent->pp_playHookObjList('forum_userIsAdmin', $this->userIsAdmin, $this);
			
		}
		
		return $this->userIsAdmin;
	}


	/**
	 * Launched when a topic is inserted
	 *
	 *
	 * @param int $topicId = topic uid
	 * @access public
	 * @return void 
	 */
	function event_onNewTopic($topicId) {
		$null = null;
		$this->parent->pp_playHookObjList('forum_event_onNewTopic', $null, $this);

		$this->event_onUpdateInForum();
	}

	/**
	 * Launched when a forum (or a topic in this forum) is modified
	 *
	 * @param int $forumId = forum's uid
	 * @access public
	 * @return void 
	 */
	function event_onUpdateInForum() {
		/* Declare */
		$null=NULL;
		$paramKey=array();

		/* Begin */
		if ($this->id) {
			$paramKey = array('forum'=>intval($this->id));
		}

		tx_pplib_cachemgm::clearItemCaches($paramKey, false);

		$this->parent->pp_playHookObjList('forum_event_onUpdateInForum', $null, $this);

		if (is_object($this->forum)) {
			$this->forum->event_onUpdateInForum();
		}

	}


	/**
	 * Display this forum (title, sub forums, topics, etc)
	 *
	 * @access public
	 * @return string 
	 */
	function display() {
		/* Declare */
		$content = '';
	
		/* Begin */
		$content .= '<div class="top-level-forum">';
		if ($this->parent->getVars['clearCache'] && $this->userIsAdmin()) {
			$this->event_onUpdateInForum();
			unset($this->parent->getVars['clearCache']);
		}
		$content .= $this->displayHeader();
		$content .= $this->displayChildList();
		if (!$this->data['notopic']) $content.=$this->displayTopicList();
		if (!$this->data['notoolbar']) $content.=$this->displayForumTools();

		$content.='</div>';
		return $content;
	}

	/**
	 * Print the forum title (wrapped in a link)
	 *
	 * @access public
	 * @return string 
	 */
	function displayHeader() {
		$content='<h2 class="forum-title">'.$this->getTitleLink().'</h2>';
		if (trim($this->data['description'])) {
			$content.='<div class="forum-description">'.tx_pplib_div::htmlspecialchars($this->data['description']).'</div>';
		}

		return $content;
	}

	/**
	 * Print sub-forum list
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayChildList() {
		/* Declare */
		$content='<div class="child-list">';
		$counter=0;
		$data=array();
		$childList=array();
		$child=NULL;
	
		/* Begin */
		foreach ($this->parent->getForumChilds($this->id) as $childId) {
			$child=&$this->parent->getForumObj($childId);//Build sub-forum object
			if ($child->id && $child->isVisible()) {
				$childList[$childId]=$child;
			}
		}

		if (count($childList)) {
			//Print title
			$content.='<div class="child-list-title">'.$this->parent->pp_getLL('forum.list.title','Forum list',TRUE).'</div>';
			//Print table start tag
			$content.='<table summary="'.$this->parent->pp_getLL('forum.child-list.summary','Forum childs',TRUE).'">';
			//Print columns headers
			$content.='<thead>'.$this->displayChildListHead().'</thead>';

			$content.='<tbody>';
			foreach (array_keys($childList) as $childId) {
				$data=array();
				$data['child']=&$childList[$childId];
				$data['classes']=array();
				$data['counter']=$counter;

				//Add some classes to the child tag (may be used in CSS)
				if ($counter%2) {
					$data['classes'][]='row-alt';
				}	else {
					$data['classes'][]='row';
				}

				if (!$counter) $data['classes'][]='row-first';
				if ($counter==$nbTopics-1) $data['classes'][]='row-last';

				//Play a hook list : allows to add more classes to the child row
				$this->parent->pp_playHookObjList('forum_displayChildList', $data, $this);
	
				//Print child row
				$content.=$this->displaySingleChild($data['child'],$data['classes']);

				$counter++;
				unset($data);
			}

			$content.='</tbody></table></div>';
			return $content;
		} else {
			return '';
		}
	}

	/**
	 * Print the columns header for the sub-forum list
	 *
	 * @access public
	 * @return string 
	 */
	function displayChildListHead() {
		/* Declare */
		//Basic columns
		$data=array(
			'child-title'=>$this->parent->pp_getLL('forum.title','Title',TRUE),
			'child-topics'=>$this->parent->pp_getLL('forum.topics','Topics',TRUE),
			'child-posts'=>$this->parent->pp_getLL('forum.posts','Posts',TRUE),
			'child-lastmessage'=>$this->parent->pp_getLL('topic.lastmessage','Last message :',TRUE),
			);
		$content='<tr class="child-list-head">';

		/* Begin */
		//Allows to add some columns
		$this->parent->pp_playHookObjList('forum_displayChildListHead', $data, $this);

		//Render columns
		foreach ($data as $class=>$text) {
			$content.='<td class="single-col '.htmlspecialchars($class).'">'.$text.'</td>';
		}
		$content.='</tr>';
		return $content;
	}

	/**
	 * Print a single sub-forum
	 *
	 * @param object $child = the sub-forum object
	 * @param array $addClasses = values to add in the table-row's class param
	 * @access public
	 * @return string
	 */
	function displaySingleChild(&$child,$addClasses) {
		/* Declare */
		$data=Array(
			'counters' => $child->getCounters(),
			'cols'=>array(),
			'child'=>&$child,
			'lastTopic'=>NULL,
			'lastMessage'=>NULL
			);
		$addClasses[]='single-forum-child';
		$content='<tr class="'.htmlspecialchars(implode(' ',$addClasses)).'">';

		/* Begin */
		$lastTopicId=$child->getLastTopic();
		if ($lastTopicId) {
			//Loading last topic and last
			$data['lastTopic']=&$this->parent->getTopicObj($lastTopicId);
			$data['lastMessage']=&$this->parent->getMessageObj($data['lastTopic']->getLastMessage());
		}

		//Render basic columns
		$data['cols']['child-title']=$child->getTitleLink();
		$data['cols']['child-topics']=$data['counters']['topics'];
		$data['cols']['child-posts']=$data['counters']['posts'];

		$data['cols']['child-lastmessage']='';
		if ($data['lastTopic']->id) {
			if ($data['lastMessage']->id) {
				$data['cols']['child-lastmessage']=$this->parent->pp_getLL('message.postedby','By ',TRUE).
					$data['lastMessage']->author->displayLight().
					' '.$this->parent->pp_getLL('message.postedwhen','the ',TRUE).
					$this->parent->renderDate($data['lastMessage']->data['crdate']);
			} else {
				$data['cols']['child-lastmessage']=$this->parent->pp_getLL('message.postedby','By ',TRUE).
					$data['lastTopic']->author->displayLight().
					' '.$this->parent->pp_getLL('message.postedwhen','the ',TRUE).
					$this->parent->renderDate($data['lastTopic']->data['crdate']);
			}

			$data['cols']['child-lastmessage'].=
				$this->parent->pp_getLL('message.postedin',' in ',TRUE).$data['lastTopic']->getTitleLink();
			
			if ($data['lastMessage']->id) $data['cols']['child-lastmessage'] .= ' - ' . $data['lastMessage']->getLink($this->parent->pp_getLL('topic.viewLastMessage','(View last message)'));
//				' '.$data['lastMessage']->getLink('-&gt;');
		}

		//Allow to add/modify/sort/delete columns
		$this->parent->pp_playHookObjList('forum_displaySingleChild', $data, $this);

		//Render columns
		foreach ($data['cols'] as $class=>$text) {
			$content.='<td class="single-col '.htmlspecialchars($class).'">'.$text.'</td>';
		}
		$content.='</tr>';
		return $content;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayTopicList() {
		/* Declare */
		$topicList=array();
		$content='<div class="topic-list">';
		$counter=0;
		$data=array(
			'classes'=>array()
			);
	
		/* Begin */
		$content.='<div class="topic-list-title">'.$this->parent->pp_getLL('topic.list.title','Topic list',TRUE).'</div>';
		$content.='<table summary="'.$this->parent->pp_getLL('forum.topic-list.summary','Forum topics',TRUE).'">';
		$content.='<thead>'.$this->displayTopicListHead().'</thead>';

		$this->loadTopicList();

		if ($nbTopics=count($this->topicList)) {
			$content.='<tbody>';

			$tempStr=$this->parent->displayPagination($nbTopics,$this->parent->config['display']['maxTopics'],$this,array('topic-browser'));
			list($start,$length)=explode(':',$this->recordRange);

			foreach (array_slice(array_keys($this->topicList),$start,$length) as $topicId) {
				$data['classes']=array();
				$data['counter']=$counter;
				$data['topic']=&$this->topicList[$topicId];

				if ($counter%2) $data['classes'][]='row-alt';
				else $data['classes'][]='row';
				if (!$counter) $data['classes'][]='row-first';
				if ($counter==$nbTopics-1) $data['classes'][]='row-last';

				$this->parent->pp_playHookObjList('forum_displayTopicList', $data, $this);

				$content.=$this->displaySingleTopic($data['topic'],$data['classes']);
				$counter++;
			}
			$content.='</tbody></table>'.$tempStr;
		} else {
			$content.='</table><div class="topic-list-isempty">'.$this->parent->pp_getLL('topic.list.isempty','No topics in this forum !',TRUE).'</div>';
		}

		$content.='</div>';
		return $content;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayTopicListHead() {
		/* Declare */
		$data=array(
			'topic-title'=>$this->parent->pp_getLL('topic.title','Title',TRUE),
			'topic-posts'=>$this->parent->pp_getLL('topic.posts','Posts',TRUE),
			'topic-author'=>$this->parent->pp_getLL('topic.author','Author',TRUE),
			'topic-lastmessage'=>$this->parent->pp_getLL('topic.lastmessage','Last message :',TRUE),
			);
		$content='<tr class="topic-list-head">';
		/* Begin */
		$this->parent->pp_playHookObjList('forum_displayTopicListHead', $data, $this);

		foreach ($data as $class=>$text) {
			$content.='<td class="single-col '.htmlspecialchars($class).'">'.$text.'</td>';
		}
		$content.='</tr>';
		return $content;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displaySingleTopic(&$topic,$addClasses) {
		/* Declare */
		$data=Array(
			'topic'=>&$topic,
			'conf'=>Array()
			);
		$addClasses[]='single-forum-topic';
		$content='<tr class="'.htmlspecialchars(implode(' ',$addClasses)).'">';
	
		/* Begin */
		if (!$data['topic']->id) {
			return '';
		}
		$data['counters'] = $topic->getCounters();

		$data['conf']['topic-title']=$data['topic']->getTitleLink(true);
		$data['conf']['topic-posts']=$data['counters']['posts'];
		$data['conf']['topic-author']=$data['topic']->author->displayLight();

		$data['conf']['topic-lastmessage']='';
		if ($messageId = $topic->getLastMessage()) {
			$data['lastMessage'] = &$this->parent->getMessageObj($messageId);
		}
		if ($data['lastMessage']->id) {
			$data['conf']['topic-lastmessage'] = $this->parent->pp_getLL('message.postedby','By ') .	$data['lastMessage']->author->displayLight().
				' '.$this->parent->pp_getLL('message.postedwhen','The ') . $this->parent->renderDate($data['lastMessage']->data['crdate']) .
				' ' .	$data['lastMessage']->getLink($this->parent->pp_getLL('topic.viewLastMessage','(View last message)'));
		}

		$this->parent->pp_playHookObjList('forum_displaySingleTopic', $data, $this);

		foreach ($data['conf'] as $class=>$text) {
			$content.='<td class="single-col '.htmlspecialchars($class).'">'.$text.'</td>';
		}
		$content.='</tr>';
		return $content;
	}

	/**
	 * Displays forum footer tools (new topic, etc)
	 *  Call  USER_INT cObj
	 *
	 * @access public
	 * @return string 
	 */
	function displayForumTools() {
		$conf=Array(
			'cmd'=>'callObj',
			'cmd.'=>Array(
					'object'=>'forum',
					'uid'=>$this->id,
					'method'=>'_displayForumTools',
				),
			);
		return $this->parent->callINTpart($conf);
	}


	/**
	 * Callback for displayForumTools
	 *
	 * @access public
	 * @return string 
	 */
	function _displayForumTools($conf) {
		/* Declare */
		$content='';
		$param=array('forum'=>intval($this->id));
		$data=Array(
			'toolbar'=>Array(),
			'hiddentools'=>Array()
			);

		/* Begin */
		if ($this->userCanPostInForum()) {
			$data['toolbar']['reply-link']='<div class="button" onclick="return tx_ppforum.showhideTool(this,\'newtopic-form\');">'.$this->parent->pp_getLL('forum.newtopic','New topic',TRUE).'</div>';
			$data['hiddentools']['newtopic-form']=$this->displayNewTopicForm();
		}

		if ($this->userIsAdmin()) {
			$nbVersions = count(tx_pplib_cachemgm::getHashList($param, false));

			$url=$this->getLink(
				FALSE,
				array('clearCache'=>1,'pointer'=>$this->parent->getVars['pointer'])
				);
			$data['toolbar']['clearcache-link']='<div class="button" onclick="window.location=\''.htmlspecialchars(addslashes($url)).'\';">'.
				str_replace(
					'###NBPAGES###',
					$nbVersions,
					$this->parent->pp_getLL('forum.clearCache','Refresh Forum\'s cache (###NBPAGES### versions)',TRUE)
					).
				'</div>';
		
		}

		$this->parent->pp_playHookObjList('forum_displayForumTools', $data, $this);

		$content.='<div class="forum-toolbar toolbar">';
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

		return $content;
	}

	/**
	 * Display the "New Topic" display array (for use in _displayForumTools)
	 *
	 * @access public
	 * @return array 
	 */
	function displayNewTopicForm() {
		$obj=&$this->parent->getTopicObj(0);
		if (!is_object($obj->forum) || $obj->forum->id!=$this->id) {
			$obj->forum=&$this;
		}
		return array(
			'content'=>'<div class="tool-title">'.$this->parent->pp_getLL('forum.newtopic.title','Open a new Topic',TRUE).'</div>'.$obj->display(),
			'display'=>(count($obj->validErrors))
			);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function isVisible() {
		$res=FALSE;

		$this->initAccesses();

		if (!$this->data['deleted']) {
			if ((!is_object($this->forum) || $this->forum->isVisible()) && $this->access['read']) {
				$res=TRUE;
			}

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
	function messageIsVisible($messageId) {
		return TRUE;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onNewPostInTopic($topicId, $messageId) {
		$param = Array(
			'topicId' => $topicId,
			'messageId' => $messageId,
		);

		//Playing hook list
		$this->parent->pp_playHookObjList('forum_event_onNewPostInTopic', $param, $this);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onTopicDisplay($topicId) {
		$param = Array(
			'topicId' => $topicId,
		);

		//Playing hook list
		$this->parent->pp_playHookObjList('forum_event_onTopicDisplay', $param, $this);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageDisplay($topicId, $messageId) {
		$param = Array(
			'topicId' => $topicId,
			'messageId' => $messageId,
		);

		//Playing hook list
		$this->parent->pp_playHookObjList('forum_event_onMessageDisplay', $param, $this);
	}

	/**
	 * Return TRUE if the message has been deleted here (see tx_ppforum_message->delete, tx_ppforum_topic->deleteMessage)
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function deleteMessage($messageId) {
		$res=FALSE;

		if (is_object($this->forum) && $this->forum->deleteMessage($messageId)) {
			$res=TRUE;
		}

		return $res;
	}

	/**
	 * Return TRUE if the topic has been deleted here (see tx_ppforum_topic->delete)
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function deleteTopic($topicId) {
		$res=FALSE;

		if (is_object($this->forum) && $this->forum->deleteTopic($messageId)) {
			$res=TRUE;
		}

		return $res;
	}
}

tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_forum.php');
?>