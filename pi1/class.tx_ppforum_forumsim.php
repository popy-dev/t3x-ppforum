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
 * Forum simulator
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_forumsim extends tx_ppforum_forum {
	var $id=0; //The forum uid
	var $userId=0; //The forum uid
	var $processMessage=array(); //Error message storage

	var $user=NULL; //Pointer to the dest-user
	var $parent=NULL; //Pointer to the plugin object
	var $forum=NULL; //Pointer to parent forum object

	var $options=Array(
		'unsetForumId'=>FALSE,
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
		if ($id) {
			$this->id=intval($id);
			$this->userId=-$id;
			$this->user=&$this->parent->getUserObj($this->userId);
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
	function display() {
		$this->user->setUserPreference('pmdata/newMessages',0);
		return parent::display();
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
			'topic-with'=>$this->parent->pp_getLL('inbox.topic.with','Discuss with',TRUE),
			'topic-title'=>$this->parent->pp_getLL('inbox.topic.title','Discussion title',TRUE),
			'topic-posts'=>$this->parent->pp_getLL('inbox.topic.posts','Messages',TRUE),
			'topic-author'=>$this->parent->pp_getLL('inbox.topic.author','Started by',TRUE),
			'topic-lastmessage'=>$this->parent->pp_getLL('topic.lastmessage','Last message :',TRUE),
			);
		$content='<tr class="topic-list-head">';
		/* Begin */
		$this->parent->st_playHooks(
			//Specific hook
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forumsim']['displayTopicListHead'],
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

		$data['conf']['topic-with']='';
		if ($data['topic']->author->id==$this->parent->currentUser->id) {
			$data['conf']['topic-with']=$data['topic']->forum->user->displayLight();
		} else {
			$data['conf']['topic-with']=$data['topic']->author->displayLight();
		}

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
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_forumsim']['displaySingleTopic'],
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
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function messageIsVisible($messageId,$userId=0) {
		if ($userId) {
			$user=$this->parent->getUserObj($userId);
			$temp=$user->getUserPreference('pmdata/deletedMessageList');
		} else {
			$temp=$this->parent->getUserPreference('pmdata/deletedMessageList');
		}
		if (is_array($temp)) {
			return !in_array($messageId,$temp);
		} else {
			return TRUE;
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanDeleteMessage($messageId,$res) {
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
		return FALSE;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function topicIsVisible($topicId,$userId=0) {
		if ($userId) {
			$user=$this->parent->getUserObj($userId);
			$temp=$user->getUserPreference('pmdata/deletedTopicList');
		} else {
			$temp=$this->parent->getUserPreference('pmdata/deletedTopicList');
		}
		if (is_array($temp)) {
			return !in_array($topicId,$temp);
		} else {
			return TRUE;
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanDeleteTopic($topicId,$res) {
		return TRUE;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanEditTopic($topicId,$res) {
		return FALSE;
	}


	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onNewPostInTopic($topicId) {
		if ($this->parent->currentUser->id==$this->userId) {
			$topic=&$this->parent->getTopicObj($topicId);
			$topic->loadAuthor();
			$temp=$topic->author->getUserPreference('pmdata/deletedTopicList');
			if (is_array($temp)) {
				$temp=array_diff($temp,array($topicId));
				$topic->author->setUserPreference('pmdata/deletedTopicList',$temp);
			}
			$temp=$topic->author->getUserPreference('pmdata/newMessages');
			$topic->author->setUserPreference('pmdata/newMessages',$temp+1);
		} else {
			$temp=$this->user->getUserPreference('pmdata/deletedTopicList');
			if (is_array($temp)) {
				$temp=array_diff($temp,array($topicId));
				$this->user->setUserPreference('pmdata/deletedTopicList',$temp);
			}
			$temp=$this->user->getUserPreference('pmdata/newMessages');
			$this->user->setUserPreference('pmdata/newMessages',$temp+1);
		}

	}

	/**
	 * Return TRUE if the message has been deleted here (see tx_ppforum_message->delete, tx_ppforum_topic->deleteMessage)
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function deleteMessage($messageId) {
		$message=&$this->parent->getMessageObj($messageId);
		if ($this->parent->currentUser->id==-$this->id) {
			$message->loadAuthor();
			$theOtherUser=$message->author->id;
		} else {
			$theOtherUser=$this->user->id;
		}

		if (!$this->messageIsVisible($messageId,$theOtherUser)) {
			return FALSE;
		} else {
			$messageList=$this->parent->getUserPreference('pmdata/deletedMessageList');
			if (!is_array($messageList)) {
				$messageList=Array($messageId);
			} else {
				$messageList[]=$messageId;
			}
			$this->parent->setUserPreference('pmdata/deletedMessageList',$messageList);
			$message->topic->loadMessages(TRUE);
			return TRUE;
		}
	}

	/**
	 * Return TRUE if the topic has been deleted here (see tx_ppforum_topic->delete)
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function deleteTopic($topicId) {
		$topic=&$this->parent->getTopicObj($topicId);
		if ($this->parent->currentUser->id==$this->userId) {
			$topic->loadAuthor();
			$theOtherUser=$topic->author->id;
		} else {
			$theOtherUser=$this->user->id;
		}

		if (!$this->topicIsVisible($topicId,$theOtherUser)) {
			return FALSE;
		} else {
			$topicList=$this->parent->getUserPreference('pmdata/deletedTopicList');
			if (!is_array($topicList)) {
				$topicList=Array($topicId);
			} else {
				$topicList[]=$topicId;
			}
			$this->parent->setUserPreference('pmdata/deletedTopicList',$topicList);
			$this->loadTopicList(TRUE);
		}

		return TRUE;
	}

	/**
	 * Generate a link to this forum
	 *
	 * @access public
	 * @return string 
	 */
	function getTitleLink() {
		if ($this->userId==$this->parent->currentUser->id) {
			$title=$this->parent->pp_getLL('inbox.self.title','Inbox');
			if ($temp=intval($this->user->getUserPreference('pmdata/newMessages'))) {
				$title.=' ('.$temp.')';
			}
			return $this->getLink($title);
		} else {
			return $this->getLink($this->parent->pp_getLL('inbox.other.title','Outbox : ').$this->user->displayLight(TRUE));
		}
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
		return array();
	}

	/**
	 * Access check : Check if current user can write in forum
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanWriteInForum() {
		return $this->isVisible();
	}

	/**
	 * Access check : Check if current user can create a new topic
	 *
	 * @access public
	 * @return bool 
	 */
	function userCanPostInForum() {
		return $this->userCanWriteInForum() && $this->parent->currentUser->id!=$this->userId;
	}

	/**
	 * Print the forum title (wrapped in a link)
	 *
	 * @access public
	 * @return string 
	 */
	function displayHeader() {
		return '<h2 class="forum-title">'.$this->getTitleLink().'</h2>';
	}

	/**
	 * Print sub-forum list
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayChildList() {
		return '';
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function isVisible() {
		return $this->parent->currentUser->id;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_forumsim.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_forumsim.php']);
}

?>