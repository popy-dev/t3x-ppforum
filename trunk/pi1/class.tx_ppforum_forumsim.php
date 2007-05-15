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

require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_forum.php');

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
		'unsetForumId'=>TRUE,
		'keepCurrentForumId'=>FALSE,
	);

	/**
	 * Loads the forum data from DB
	 *
	 * @param int $id = Forum uid
	 * @param boolean $clearCache = if TRUE, cached data will be overrided
	 * @access public
	 * @return int = loaded uid
	 */
	function load($id, $clearCache = false) {
		if ($id) {
			$this->id = intval($id);
			$this->userId = -$id;
			$this->user = &$this->parent->getUserObj($this->userId, $clearCache);

			$isInbox = $this->userId == $this->parent->getCurrentUser();

			$this->data = array(
				'notoolbar' => $isInbox,
				'notopic' => false,
				'description' => $this->parent->pp_getLL('forumsim.description.'.($isInbox ? 'inbox' : 'outbox')),
			);
			$this->data['description'] = str_replace(
				'###user###',
				$this->user->displayLight(true),
				$this->data['description']
			);
			//t3lib_div::debug($this->data, '');
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

		$newPms=$this->parent->currentUser->countNewPms($data['topic']->id);
		$data['conf']['topic-title']=$data['topic']->getTitleLink().($newPms?(' ['.$newPms.']'):'');
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
			return $user->pmIsVisible($messageId,'message');
		} else {
			return $this->parent->currentUser->pmIsVisible($messageId,'message');
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
			return $user->pmIsVisible($topicId,'topic');
		} else {
			return $this->parent->currentUser->pmIsVisible($topicId,'topic');
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
	 * Launched when a topic is inserted
	 *
	 *
	 * @param int $topicId = topic uid
	 * @access public
	 * @return void 
	 */
	function event_onNewTopic($topicId) {
		if ($this->parent->currentUser->id==$this->userId) {
			$topic=&$this->parent->getTopicObj($topicId);
			$topic->loadAuthor();
			
			$topic->author->registerNewPm($topicId,'topic');
		} else {
			$this->user->registerNewPm($topicId,'topic');
		}

		parent::event_onNewTopic($topicId);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onNewPostInTopic($topicId,$messageId=0) {
		if ($this->parent->currentUser->id == $this->userId) {
			$topic=&$this->parent->getTopicObj($topicId);
			$topic->loadAuthor();

			$topic->author->unDeletePm($topicId,'topic');
			$topic->author->registerNewPm($messageId,'message',$topicId);

		} else {
			$this->user->unDeletePm($topicId,'topic');
			$this->user->registerNewPm($messageId,'message',$topicId);
		}

	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onTopicDisplay($topicId) {
		$this->parent->currentUser->viewPm($topicId,'topic');

		parent::event_onTopicDisplay($topicId);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageDisplay($topicId,$messageId) {
		$this->parent->currentUser->viewPm($messageId,'message');

		parent::event_onMessageDisplay($topicId,$messageId);
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
		if ($this->parent->currentUser->id == $this->userId) {
			$message->loadAuthor();

			//** Real delete if other user has already deleted this message
			if (!$message->author->pmIsVisible($messageId,'message') || !$message->topic->isVisible()) {
				$message->author->clearPmData($messageId,'message');
				return FALSE;
			}
		} else {
			//** Real delete if other user has already deleted this message
			if (!$this->user->pmIsVisible($messageId,'message') || !$message->topic->isVisible()) {
				$this->user->clearPmData($messageId,'message');
				return FALSE;
			}
		}

		//** user-delete
		$this->parent->currentUser->deletePm($messageId,'message');
		$message->topic->loadMessages(TRUE);

		return TRUE;
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
		if ($this->parent->currentUser->id == $this->userId) {
			$topic->loadAuthor();

			//** Real delete if other user has already deleted this message
			if (!$topic->author->pmIsVisible($topicId,'topic')) {
				$topic->author->unDeletePm($topicId,'topic');
				return FALSE;
			}
		} else {
			//** Real delete if other user has already deleted this message
			if (!$this->user->pmIsVisible($topicId,'topic')) {
				$this->user->unDeletePm($topicId,'topic');
				return FALSE;
			}
		}

		$this->parent->currentUser->deletePm($topicId,'topic');

		//** Unread message should not be counted there !
		$topic->loadMessages();
		foreach ($topic->messageList as $messageId) {
			$this->parent->currentUser->viewPm($messageId,'message');
		}
		$this->loadTopicList(TRUE);
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
			if ($temp=$this->user->countNewPms()) {
				$title.=' ['.$temp.']';
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
	 * 
	 * 
	 * @param bool $dontCheckWriteAccess = TRUE if no need to check write access before (maybe it has already been done)
	 * @access public
	 * @return bool 
	 */
	function userCanReplyInForum($dontCheckWriteAccess = false) {
		return $dontCheckWriteAccess || $this->userCanWriteInForum();
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