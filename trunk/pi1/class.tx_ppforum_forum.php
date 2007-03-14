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


/**
 * Class 'tx_ppforum_forum' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_forum {
	var $id=0; //The forum uid
	var $data=array(); //The forum data
	var $processMessage=array(); //Error message storage

	var $parent=NULL; //Pointer to the plugin object
	var $forum=NULL; //Pointer to parent forum object

	var $options=Array(
		'unsetForumId'=>TRUE,
		);

	/**
	 * Loads the forum data from DB
	 *
	 * @param int $id = Forum uid
	 * @param boolean $clearCache = @see tx_pplib::do_cachedQuery
	 * @access public
	 * @return int = loaded uid
	 */
	function load($id,$clearCache=FALSE) {
		if ($id && ($this->data=$this->parent->getSingleForum($id,$clearCache))) {
			$this->id=intval($id);
			$this->forum=&$this->parent->getForumObj($this->data['parent']);
		}
		return $this->id;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function loadTopicList($clearCache=FALSE) {
		if (!is_array($this->topicList) || $clearCache) {
			$this->topicList=Array();
			foreach ($this->parent->getForumTopics($this->id) as $topicId) {
				$topic=&$this->parent->getTopicObj($topicId);
				if ($topic->isVisible() && $this->topicIsVisible($topicId)) {
					$this->topicList[$topicId]=&$topic;
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
	function getLastTopic() {
		//$this->loadTopicList();
		//return reset(array_keys($this->topicList));

		return reset($this->parent->getForumTopics($this->id,FALSE,'nopinned'));
	}

	/**
	 * Make some access-check
	 *
	 * @access public
	 * @return void 
	 */
	function readAccess() {
		if (!is_array($this->access) || !count($this->access)) {
			if (is_object($this->forum)) {
				$this->forum->readAccess();
			}

			$this->access['admin']=$this->readSingleAccess('admin',FALSE);
			$this->access['guard']=$this->access['admin'] || $this->readSingleAccess('guard',FALSE);
			$this->access['write']=$this->access['guard'] || $this->readSingleAccess('write');
			$this->access['read']= $this->access['guard'] || $this->readSingleAccess('read');

			$this->readRestricts();
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function readRestricts() {
		if (!is_array($this->access['restrict'])) {
			$this->access['restrict']=Array();
			if (is_object($this->forum)) {
				$this->forum->readRestricts();
			}

			foreach (array('newtopic','edit','delete') as $name) {
				$this->readSingleRestrict($name);
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
	function readSingleRestrict($name) {
		/* Declare */
		$fieldVal=$this->data[$name.'_restrict'];
		$result=FALSE;
	
		/* Begin */
		if (!trim($fieldVal)) {
			$fieldVal='inherit'; //Back to default value
		}
		if (!is_object($this->forum) && $fieldVal=='inherit') {
			$fieldVal='everybody'; //Can't inherit
		}
		switch ($fieldVal) {
		case 'inherit':
			$result=$this->forum->access['restrict'][$name];
			break;
		case 'everybody': 
			$result=TRUE;
			break;
		case 'guard': 
			$result=$this->access['guard'];
			break;
		case 'admin': 
			$result=$this->access['admin'];
			break;
		default:
			break;
		}

		$this->access['restrict'][$name]=$result;
	}

	/**
	 * Read a list of fe_groups and fe_users and return TRUE if the current user (or one of his groups) is in
	 *
	 * @param string $str = The string to parse
	 * @access protected
	 * @return boolean
	 */
	function readSingleAccess($access,$noneIsEverybody=TRUE) {
		/* Declare */
		$res=FALSE;
		$mode=$this->data[$access.'access_mode'];
		$list=array_filter(explode(',',$this->data[$access.'access']),'intval');
		global $TSFE;

		/* Begin */
		if (($mode=='erase') || !is_object($this->forum)) { //Mode erase or current forum is forum id 0
			if (count($list)) {
				if ($this->parent->getCurrentUser() && is_array($TSFE->fe_user->groupData['uid']) && count(array_intersect($list,$TSFE->fe_user->groupData['uid']))) {
					$res=TRUE;
				}
			} elseif ($noneIsEverybody) {
				$res=TRUE;
			}
		} else { //Inherit mode
			$res=$this->forum->access[$access];
		}

		return $res;
	}

	/**
	 * Generate a link to this forum
	 *
	 * @access public
	 * @return string 
	 */
	function getLink($title='',$addParams=Array(),$parameter='') {
		if (!isset($addParams['forum'])) {
			$addParams['forum']=$this->id;
		}
		
		if (!isset($addParams['lightMode'])) $addParams['lightMode']=$this->parent->piVars['lightMode'];
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
		return $this->getLink($this->parent->htmlspecialchars($this->data['title']));
	}

	/**
	 * Builds a link to a topic (including anchor)
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @param array $addParams = additional url parameters.
	 * @access public
	 * @return string 
	 */
	function getTopicLink($title='',$addParams=array(),$parameter='') {
		if (!isset($addParams['forum']) && $this->options['unsetForumId']) {
			$addParams['forum']='';
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
	function getCounters($forumId=0,$clearCache=FALSE) {
		if (!$forumId) $forumId=$this->id;
		if (!strcmp($clearCache,'clearCache')) {
			unset($GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]);
		} elseif ($clearCache || !is_array($GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId])) {
			$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]=array('topics'=>0,'posts'=>0,'newposts'=>0);
			foreach ($this->parent->getForumChilds($forumId,$clearCache) as $child) {
				$tmp=$this->getCounters($child,$clearCache);
				$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]['topics']+=$tmp['topics'];
				$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]['posts']+=$tmp['posts'];
				$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]['newposts']+=$tmp['newposts'];
			}

			$obj=$this->parent->makeInstance('tx_ppforum_topic');
			foreach ($this->parent->getForumTopics($forumId,$clearCache) as $topic) {
				$tmp=$obj->getCounters($topic,$clearCache);
				$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]['topics']++;
				$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]['posts']+=$tmp['posts'];
				$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId]['newposts']+=$tmp['newposts'];
			}

			$data=Array(
				'counters'=>&$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId],
				'forum'=>$forumId
				);
			$this->parent->st_playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['getCounters'],$data,$this);
		}
		return $GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['FORUMS'][$forumId];
	}

	/**
	 * Access check : Check if current user can write in forum
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanWriteInForum() {
		//Load basic access
		$this->readAccess();
		$res=$this->access['write'];

		//Plays hook list : Allows to change the result
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['userCanWriteInForum'],
			$res,
			$this
			);
		
		return $res;
	}

	/**
	 * Access check : Check if current user can create a new topic
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanPostInForum() {
		$res=$this->userCanWriteInForum();
		$res=$res && !$this->data['notopic'] && $this->access['restrict']['newtopic'];

		//Plays hook list : Allows to change the result
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['userCanPostInForum'],
			$res,
			$this
			);
		
		return $res;
	}

	/**
	 *
	 *
	 * @param bool $dontCheckWriteAccess = TRUE if no need to check write access before (maybe it has already been done)
	 * @access public
	 * @return void 
	 */
	function userCanReplyInForum($dontCheckWriteAccess=FALSE) {
		if ($dontCheckWriteAccess) {
			$res=TRUE;
		} else {
			$res=$this->userCanWriteInForum();
		}
	
		//Plays hook list : Allows to change the result
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['userCanReplyInForum'],
			$res,
			$this
			);
		
		return $res;
	}
	/**
	 * Access check : Check if user is a "Guard"
	 *
	 * @access public
	 * @return boolean 
	 */
	function userIsGuard() {
		//Load basic access
		$this->readAccess();
		$res=$this->access['guard'];

		//Plays hook list : Allows to change the result
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['userIsGuard'],
			$res,
			$this
			);
		
		return $res;
	}


	/**
	 * Access check : Check if user is an Admin
	 *
	 * @access public
	 * @return boolean 
	 */
	function userIsAdmin() {
		//Load basic access
		$this->readAccess();
		$res=$this->access['admin'];

		//Plays hook list : Allows to change the result
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['userIsAdmin'],
			$res,
			$this
			);
		
		return $res;
	}

	/**
	 * Launched when a forum (or a topic in this forum) is modified
	 *
	 * @param int $forumId = forum's uid
	 * @access public
	 * @return void 
	 */
	function event_onUpdateInForum() {
		$paramKey=array();
		if ($this->id) {
			$paramKey=array('forum'=>intval($this->id));
		}

		$this->parent->clearHashList($paramKey);

		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['event_onUpdateInForum'],
			$forum,
			$this
			);

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
		$content.='<div class="top-level-forum">';
	
		/* Begin */
		if ($this->parent->piVars['clearCache'] && $this->userIsAdmin()) {
			$this->event_onUpdateInForum();
			unset($this->parent->piVars['clearCache']);
		}
		$content.=$this->displayHeader();
		$content.=$this->displayChildList();
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
			$content.='<div class="forum-description">'.$this->parent->htmlspecialchars($this->data['description']).'</div>';
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
				$this->parent->st_playHooks(
					$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['displayChildList:classes'],
					$data,
					$this
					);
	
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
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['displayChildListHead'],
			$data,
			$this
			);

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
			'counters'=>$this->getCounters($child->id),
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
			$data['lastTopic']->loadAuthor();
			$data['lastMessage']=&$this->parent->getMessageObj($data['lastTopic']->getLastMessage());
			$data['lastMessage']->loadAuthor();
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
			
			if ($data['lastMessage']->id) $data['cols']['child-lastmessage'].=' '.$data['lastMessage']->getLink('-&gt;');
		}

		//Allow to add/modify/sort/delete columns
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['displaySingleChild'],
			$data,
			$this
			);

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

				$this->parent->st_playHooks(
					$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['displayTopicList:classes'],
					$data,
					$this
					);

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
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['displayTopicListHead'],
			$data,
			$this
			);
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
		$data['counters']=$data['topic']->getCounters($topic->id);
		$data['topic']->loadAuthor();
		$data['topic']->loadMessages();

		$data['conf']['topic-title']=$data['topic']->getTitleLink();
		$data['conf']['topic-posts']=$data['counters']['posts'];
		$data['conf']['topic-author']=$data['topic']->author->displayLight();

		$data['conf']['topic-lastmessage']='';
		if ($messageId=$topic->getLastMessage()) {
			$data['lastMessage']=&$this->parent->getMessageObj($messageId);
			$data['lastMessage']->loadAuthor();
		}
		if ($data['lastMessage']->id) {
			$data['conf']['topic-lastmessage']=$this->parent->pp_getLL('message.postedby','By ',TRUE).
				$data['lastMessage']->author->displayLight().
				' '.$this->parent->pp_getLL('message.postedwhen','The ',TRUE).
				$this->parent->renderDate($data['lastMessage']->data['crdate']).' '.
				$data['lastMessage']->getLink('-&gt;');
		}

		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['displaySingleTopic'],
			$data,
			$this
			);

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
			$data['toolbar']['reply-link']='<div class="button" onclick="return ppforum_showhideTool(this,\'newtopic-form\');">'.$this->parent->pp_getLL('forum.newtopic','New topic',TRUE).'</div>';
			$data['hiddentools']['newtopic-form']=$this->displayNewTopicForm();
		}

		if ($this->userIsAdmin()) {
			$param=array_filter($param,'trim');
			ksort($param);
			$paramKey=serialize($param);
			$this->parent->loadHashList(TRUE);
			$nbVersions=is_array($this->parent->_storedHashes[$paramKey])?array_sum(array_map('count',$this->parent->_storedHashes[$paramKey])):0;

			$url=$this->getLink(
				FALSE,
				array('clearCache'=>1,'pointer'=>$this->parent->piVars['pointer'])
				);
			$data['toolbar']['clearcache-link']='<div class="button" onclick="window.location=\''.htmlspecialchars(addslashes($url)).'\';">'.
				str_replace(
					'###NBPAGES###',
					$nbVersions,
					$this->parent->pp_getLL('forum.clearCache','Refresh Forum\'s cache (###NBPAGES### versions)',TRUE)
					).
				'</div>';
		
		}

		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forum']['_displayForumTools'],
			$data,
			$this
			);

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
			'display'=>(is_array($this->processMessage[$obj->datakey]) && count($this->processMessage[$obj->datakey]))
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

		$this->readAccess();

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
	function userCanEditMessage($messageId,$res) {
		$res=$res && $this->access['restrict']['edit'];
		if (is_object($this->forum)) {
			$res=$this->forum->userCanEditMessage($messageId,$res);
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
	function userCanDeleteMessage($messageId,$res) {
		$res=$res && $this->access['restrict']['delete'];
		if (is_object($this->forum)) {
			$res=$this->forum->userCanDeleteMessage($messageId,$res);
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
	function topicIsVisible($topicId) {
		return in_array($topicId,$this->parent->getForumTopics($this->id));
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanDeleteTopic($topicId,$res) {
		$res=$res && $this->access['restrict']['delete'];
		if (is_object($this->forum)) {
			$res=$this->forum->userCanDeleteTopic($topicId,$res);
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
	function userCanEditTopic($topicId,$res) {
		$res=$res && $this->access['restrict']['edit'];
		if (is_object($this->forum)) {
			$res=$this->forum->userCanEditTopic($topicId,$res);
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
	function event_onNewPostInTopic($topicId) {
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



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_forum.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_forum.php']);
}

?>