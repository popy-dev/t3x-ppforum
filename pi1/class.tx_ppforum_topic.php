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
 * Class 'tx_ppforum_topic' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_topic extends tx_ppforum_message {
	var $id=0; //Topic uid
	var $processMessage=array(); //Error message storage
	var $datakey='edit_topic'; //Used to build forms and to get data in piVars
	var $type='topic'; //Used in generic functions herited from tx_ppforum_message
	var $tablename='tx_ppforum_topics'; //Table corresponding to this type

	var $forum=NULL; //Pointer on parent forum
	var $messageList=FALSE;

	/**
	 * List of allowed incomming fields from forms(Other fields will be ignored)
	 * @access public
	 * @var array
	 */
	var $allowedFields=Array(
		'title'=>'',
		'message'=>'',
		'nosmileys'=>'',
		'parser'=>'',
		'pinned'=>'guard',
		'status'=>'guard',
		);

	/**
	 * Loads the topic data from DB
	 *
	 * @param int $id = Topic uid
	 * @param boolean $clearCache = @see tx_pplib::do_cachedQuery
	 * @access public
	 * @return int = loaded uid
	 */
	function load($id) {
		$this->tablename=$this->parent->tables['topics'];
		if ($this->data=$this->parent->getSingleTopic($id)) {
			$this->id=intval($id);
			$this->forum=&$this->parent->getForumObj($this->data['forum']);
			$this->mergedData=$this->data;			
		}
		return $this->id;
	}

	/**
	 * Deletes the message
	 *
	 * @param boolean $forceReload = if TRUE, will clear forum's topic list
	 * @access public
	 * @return int/boolean @see tx_ppforum_topic::save 
	 */
	function delete($forceReload=TRUE) {
		if ($this->id) {
			if ($this->forum->deleteTopic($this->id)) {
				return TRUE;
			} else {
				$this->mergedData['deleted']=1;

				$this->loadMessages(TRUE,TRUE);

				//Deleting topic messages
				foreach ($this->messageList as $messageId) {
					$temp=&$this->parent->getMessageObj($messageId);
					$temp->delete(FALSE);
					unset($temp);
				}

				//Clear topic message list
				$this->parent->getTopicMessages($this->id,'clearCache');
				if ($forceReload) $this->forceReload['list']=1;
				return $this->save($forceReload);
			}
		} else {
			return FALSE;
		}
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
	function event_onUpdateInTopic($isNewMessage=FALSE,$messageId=0) {
		$null=NULL;
		if ($messageId) {
			$this->save(); //Will re-launch this function !!
			if ($isNewMessage) {
				$this->forum->event_onNewPostInTopic($this->id,$messageId);
			}
		} else {
			//Playing hook list
			tx_pplib_div::playHooks(
				$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['event_onUpdateInTopic'],
				$null,
				$this
				);

			//Clear cached page (but only where this topic is displayed !)
			$this->parent->clearHashList(array('topic'=>intval($this->id)));

			//If needed (deletion/creation of a message), clear the message list query cache
			if ($this->forceReload['list']) $this->forum->loadTopicList(TRUE);

			//Forcing data and object reload. Could be used to ensure that data is "as fresh as possible"
			//Useless if oject wasn't builded with 'getMessageObj' function
			//In this case, you should use $this->parent->getSingleMessage($this->id,'clearCache');
			if ($this->forceReload['data']) $this->load($this->id,TRUE);

			if ($isNewMessage) {
				$this->forum->event_onNewTopic($this->id);
			} elseif ($this->forceReload['forum']) {
				$this->forum->event_onUpdateInForum();
			}

			//Resets directives
			$this->forceReload=array();
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function event_onMessageDisplay($messageId) {
		$this->forum->event_onMessageDisplay($topicId,$messageId);
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
			$addParams['forum']=$this->forum->id;
			$addParams['edittopic']=1;
		}

		return $this->forum->getTopicLink(
			$title,
			$addParams, //overrule piVars
			$parameter // Message anchor
			);
	}

	/**
	 * Prints the topic title (as a link to the topic detail)
	 *
	 * @access public
	 * @return string 
	 */
	function getTitleLink() {
		$addText='';
		if ($this->data['pinned']) {
			$addText.='(pinned) ';
		}
		if ($this->data['status']==1) {
			$addText.='(hidden) ';
		} elseif ($this->data['status']==2) {
			$addText.='(closed) ';
		}
		return $addText . $this->getLink(tx_pplib_div::htmlspecialchars($this->mergedData['title']));
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
	function getCounters($topicId,$clearCache=FALSE) {
		if (!$topicId) $topicId=$this->id;
		if ($clearCache || !is_array($GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['TOPICS'][$topicId])) {
			$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['TOPICS'][$topicId]=array('posts'=>0);
			$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['TOPICS'][$topicId]['posts']=count($this->parent->getTopicMessages($topicId));

			$data=array(
				'counters'=>&$GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['TOPICS'][$topicId],
				'topic'=>$topicId
				);
			tx_pplib_div::playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['getCounters'],$data,$this);
		}
		return $GLOBALS['CACHE']['PP_FORUM'][$this->parent->cObj->data['uid']]['COUNTERS']['TOPICS'][$topicId];
	}


	/**
	 * Check if a message is modified/posted/deleted
	 *
	 * @access public
	 * @return void 
	 */
	function checkIncommingData() {
		/* Declare */
		$data=Array(
				'currentTopic'=>$this->data,
				'errors'=>array(),
				'message'=>NULL,
				'mode'=>'new',
				'shouldContinue'=>TRUE
				);
	
		/* Begin */
		$data['message']=&$this->parent->getMessageObj(0);

		//If we have nothing in piVars, nothing to do
		if (!is_array($this->parent->piVars[$data['message']->datakey]) || !count($this->parent->piVars[$data['message']->datakey])) return FALSE;

		//Current topic isn't valid, so we can't append (or modify) a child to it !
		if (!$this->id) return FALSE;

		$data['errors']=&$this->processMessage[$data['message']->datakey];

		//Checking mode (don't check permissions, it wouldbe check later)
		if (intval($this->parent->getVars['editmessage'])>0) {
			$data['mode']='edit';
			unset($data['message']);
			$data['message']=&$this->parent->getMessageObj($this->parent->getVars['editmessage']);
			$data['message']->mergeData();
		} elseif (intval($this->parent->getVars['deletemessage'])) {
			$data['mode']='delete';
			unset($data['message']);
			$data['message']=&$this->parent->getMessageObj($this->parent->getVars['deletemessage']);
		} else {
			//New message : the message object already exists, also it just need a parent topic (current topic)
			$data['message']->topic=&$this;
			$data['message']->mergeData();
		}

		//Checking data validity
		if (($data['mode']!='delete') && ($GLOBALS['TSFE']->fe_user->getKey('ses','ppforum/justPosted')==$this->parent->piVars[$data['message']->datakey])) {
			//*** Incomming data is the same as last time -> user has probably refreshed the page
			//Cleaning incomming vars
			$this->parent->piVars[$data['message']->datakey]=array();
			unset($this->parent->piVars['editmessage']);
			unset($this->parent->piVars['deletemessage']);

			$this->parent->getMessageObj(0,TRUE);
			if ($data['mode']=='new') return FALSE;
		}

		switch ($data['mode']){
		case 'edit':
			if ((!$data['message']->id) || (!$data['message']->userCanEdit())) {
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
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['checkIncommingData:checkValidityAndAccess'],
			$data,
			$this
			);

		//Allows a hook ordering to exit
		if (!$data['shouldContinue']) {
			return FALSE;
		}

		//If we have no errors :
		if (!count($data['errors'])) {

			if ($data['mode']!='delete') {
				//*** Checking fields
				if (!trim($data['message']->mergedData['message'])) {
					$data['errors']['field']['message']='You should enter a message';
				}
				$data['message']->mergedData['message']=str_replace("\r",'',$data['message']->mergedData['message']);

				if (!isset($data['message']->mergedData['hidden'])) {
					if (intval($this->forum->data['hidemessage'])) {
						$data['message']->mergedData['hidden']=1;
					} else {
						$data['message']->mergedData['hidden']=0;
					}
				}
			}

			if ($data['mode']=='new') {
				$data['message']->mergedData['topic']=$this->id;
				$data['message']->mergedData['author']=$this->parent->getCurrentUser();
				$data['message']->mergedData['crdate']=$GLOBALS['SIM_EXEC_TIME'];
			}

			//Playing hook list : Allows to fill other fields
			tx_pplib_div::playHooks(
				$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['checkIncommingData:checkAndFetch'],
				$data,
				$this
				);

			if (!count($data['errors'])) {
				if ($data['mode']=='delete') {
					$data['message']->delete();
				} else {
					if (!isset($this->parent->piVars[$data['message']->datakey]['preview'])) {
						$data['message']->save();
						//Saving data for validity check (protection against multi-post)
						$GLOBALS['TSFE']->fe_user->setKey('ses','ppforum/justPosted',$this->parent->piVars['editpost']);

						if ($data['mode']=='new') {
							// Clearing object cache (because now the message has an id !)
							$this->parent->getMessageObj(0,TRUE);
						}
					} elseif (!$data['message']->id) {
						//Preview mode for new topics
						$this->loadMessages();
						$this->messageList[]=0;
						$data['message']->id='preview';
					}
				}

				//Cleaning incomming vars
				$this->parent->piVars[$data['message']->datakey]=array();
				unset($this->parent->getVars['editmessage']);
				unset($this->parent->getVars['deletemessage']);
			}
		}
	}

	/**
	 * Check if topic is modified/created/deleted and update it (if needed)
	 *
	 * @access public
	 * @return void 
	 */
	function checkTopicData() {
		/* Declare */
		$data=Array(
				'currentForum'=>$this->forum->data,
				'errors'=>'',
				'mode'=>'new',
				'shouldContinue'=>TRUE
				);
	
		/* Begin */
		//If we have no data it's useless to continue
		if (!is_array($this->parent->piVars[$this->datakey]) || !count($this->parent->piVars[$this->datakey])) return FALSE;

		//If current topic isn't valid AND parent forum too, exit function
		if (!($this->id || $this->forum->id)) return FALSE;

		$this->mergeData();

		//Store errors in forum object
		$data['errors']=&$this->forum->processMessage[$this->datakey];

		//Checking mode (permissions will be checked later)
		if (!$this->id) {
			//Nothing :)
		} elseif ($this->parent->getVars['edittopic']) {
			$data['mode']='edit';
		} elseif ($this->parent->getVars['deletetopic']) {
			$data['mode']='delete';
		}

		//Checking data validity
		if (($data['mode']!='delete') && ($GLOBALS['TSFE']->fe_user->getKey('ses','ppforum/lastTopic')==$this->parent->piVars[$this->datakey])) {
			unset($this->parent->piVars[$this->datakey]);
			unset($this->parent->getVars['edittopic']);
			unset($this->parent->getVars['deletetopic']);

			if ($data['mode']=='new') $this->getTopicObj(0,TRUE);
			return FALSE;
		}

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
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['checkTopicData:checkValidityAndAccess'],
			$data,
			$this
			);

		//Allows a hook ordering to exit
		if (!$data['shouldContinue']) {
			return FALSE;
		}

		if (!count($data['errors'])) {

			if ($data['mode']!='delete') {
				if (!trim($this->mergedData['title'])) {
					$data['errors']['field']['title']='You should enter a title';
				}
				if (!trim($this->mergedData['message'])) {
					$data['errors']['field']['message']='You should enter a message';
				}

				if (isset($this->mergedData['status'])) {
					$this->mergedData['status']=max(0,min(2,intval($this->mergedData['status'])));
				} else {
					if (intval($this->forum->data['hidetopic'])) {
						$this->mergedData['status']=1;
					} else {
						$this->mergedData['status']=0;
					}
				}
			}

			if ($data['mode']=='new') {
				$this->mergedData['forum']=$this->forum->id;
				$this->mergedData['author']=$this->parent->getCurrentUser();
				$this->mergedData['crdate']=$GLOBALS['SIM_EXEC_TIME'];
			}

			//Playing hook list : Allows to fill other fields
			tx_pplib_div::playHooks(
				$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['checkTopicData:checkAndFetch'],
				$data,
				$this
				);

			if (!count($data['errors'])) {
				if ($data['mode']=='delete') {
					$this->delete();
				} else {
					//Preview support
					if (!isset($this->parent->piVars[$this->datakey]['preview'])) {
						$this->save();
					
						//Saving data for validity check (protection against multi-post)
						$GLOBALS['TSFE']->fe_user->setKey('ses','ppforum/lastTopic',$this->parent->piVars[$this->datakey]);
					} elseif (!$this->id) {
						$this->id='preview';
					}
				}

				//Cleaning incomming data
				$this->parent->piVars[$this->datakey]=array();
				unset($this->parent->getVars['edittopic']);
				unset($this->parent->getVars['deletetopic']);
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
			'conf'=>array(),
			'mode'=>'view'
			);
	
		/* Begin */
		if ($this->forum->id < 0) {
			$GLOBALS['TSFE']->set_no_cache();
		}
		//Loads author
		$this->loadAuthor();

		if (!$this->id) {
			$data['mode'] = 'new';
		} elseif (!intval($this->id)) {
			$data['mode'] = 'preview';
			$this->id=0;
		} elseif ($this->parent->getVars['edittopic'] && $this->userCanEdit()) {
			$data['mode'] = 'edit';
		} elseif ($this->parent->getVars['deletetopic'] && $this->userCanDelete()) {
			$data['mode'] = 'delete';
		} elseif (count(array_diff_assoc($this->data,$this->mergedData))) {
			$data['mode'] = 'preview';
		}

		$content.=$this->displaySingle($data);
		if ($data['mode'] == 'preview') {
			$data['mode'] = 'edit';
			$content .= $this->displaySingle($data);
		}

		if ($this->id) {
			$this->loadMessages();
			//Generate mesage-browser
			$tempStr = $this->parent->displayPagination(
				count($this->messageList),
				$this->parent->config['display']['maxMessages'],
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

		if ($data['mode']!='new') {
			$this->checkIncommingData();

			if ($this->parent->getVars['clearCache'] && $this->forum->userIsAdmin()) {
				$this->forceReload['forum']=1;
				$this->event_onUpdateInTopic(FALSE,FALSE);
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

		//Building standard parts
		$data['conf']['title-row'] = $this->display_titleRow($data['mode']);
		$data['conf']['head-row'] = $this->display_headRow($data['mode']);
		$data['conf']['parser-row'] = $this->display_parserRow($data['mode']);
		$data['conf']['main-row'] = $this->display_mainRow($data['mode']);
		$data['conf']['options-row'] = $this->display_optionsRow($data['mode']);
		$data['conf']['tools-row'] = $this->display_toolsRow($data['mode']);

		//Playing hooks : Allows to manipulate parts (add, sort, etc)
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['display'],
			$data,
			$this
			);

		//Printing parts
		foreach ($data['conf'] as $key=>$val) {
			if (trim($val)) {
				$content.='
		<div class="row '.htmlspecialchars($key).'">'.$val.'
		</div>';
			}
		}

		//Closing tags
		if ($data['mode']!='view') $content.='
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
			return $this->parent->pp_getLL('topic.title','Title : ',TRUE).' <input value="'.tx_pplib_div::htmlspecialchars($this->mergedData['title']).'" type="text" size="30" maxlength="200" name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[title]" />';
		}
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
		$res='last'; //Default value
		$i=0;
		$resPerPage=0;

		/* Begin */
		$this->loadMessages();
		if ($messageId) {
			$resPerPage=max(1,intval($this->parent->config['display']['maxMessages']));

			if (in_array($messageId,$this->messageList)) {
				while (($i<count($this->messageList)) && ($messageId!=intval($this->messageList[$i]))) $i++;

				if ($i<count($this->messageList)) {
					$res=intval($i/$resPerPage);
				}
			}
		}

		return $res;
	}

	/**
	 * Display message list
	 *
	 * @access public
	 * @return string 
	 */
	function displayMessages() {
		/* Declare */
		$content='<div class="message-list">';
		$counter=0;
		$obj=NULL;
		list($start,$length)=explode(':',$this->recordRange);
	
		/* Begin */
		$this->loadMessages();
		if (count($this->messageList)) {
			//Using recordRange to limit message list
			foreach (array_slice($this->messageList,$start,$length) as $message) {
				$data=array();
				$data['message']=&$this->parent->getMessageObj($message);
				$data['classes']=array();
				$data['counter']=$counter;

				//Add some classes to the child tag (may be used in CSS)
				if (!($counter%2)) {
					$data['classes'][]='row-alt';
				}	else {
					$data['classes'][]='row';
				}

				if (!$counter) $data['classes'][]='row-first';
				if ($counter==$length-1) $data['classes'][]='row-last';

				//Play a hook list : allows to add more classes to the child row
				tx_pplib_div::playHooks(
					$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['displayMessages:classes'],
					$data,
					$this
					);

				$content.=$data['message']->display($data['classes']);

				unset($data['message']);
				$counter++;
			}

			$content.='</div>';
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
		return $this->parent->callINTPlugin($conf);
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
			$data['toolbar']['reply-link']='<div class="button" onclick="return ppforum_showhideTool(this,\'reply-form\');">'.$this->parent->pp_getLL('topic.newpost','Reply',TRUE).'</div>';
			$data['hiddentools']['reply-form']=$this->displayReplyForm();
		}

		if ($this->parent->config['.lightMode']) {
			$url=$this->getLink(FALSE,Array('lightMode'=>!$this->parent->getVars['lightMode'],'pointer'=>$this->parent->getVars['pointer']));
			$data['toolbar']['lightmode-link']='<div class="button" onclick="window.location=\''.htmlspecialchars(addslashes($url)).'\';">'.$this->parent->pp_getLL('topic.stdMode','Normal Mode',TRUE).'</div>';
		} else {
			$url=$this->getLink(FALSE,Array('lightMode'=>!$this->parent->getVars['lightMode'],'pointer'=>$this->parent->getVars['pointer']));
			$data['toolbar']['lightmode-link']='<div class="button" onclick="window.location=\''.htmlspecialchars(addslashes($url)).'\';">'.$this->parent->pp_getLL('topic.lightMode','Light Mode',TRUE).'</div>';
		}

		if ($this->forum->userIsAdmin()) {
			$param=array_filter($param,'trim');
			ksort($param);
			$paramKey=serialize($param);
			$this->parent->loadHashList(TRUE);
			//t3lib_div::debug($this->parent->_storedHashes[$paramKey], '');
			$nbVersions=is_array($this->parent->_storedHashes[$paramKey])?array_sum(array_map('count',$this->parent->_storedHashes[$paramKey])):0;

			$url=$this->getLink(
				FALSE,
				array('clearCache'=>1,'pointer'=>$this->parent->getVars['pointer'])
				);
			$data['toolbar']['clearcache-link']='<div class="button" onclick="window.location=\''.htmlspecialchars(addslashes($url)).'\';">'.
				str_replace(
					'###NBPAGES###',
					$nbVersions,
					$this->parent->pp_getLL('topic.clearCache','Refresh Topic\'s cache (###NBPAGES### versions)',TRUE)
					).
				'</div>';
		}


		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['_displayTopicTools'],
			$data,
			$this
			);

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
		$obj=&$this->parent->getMessageObj(0);

		//Force the parent topic to this (message functions must it to be set !)
		if (!is_object($obj->topic) || $obj->topic>id!=$this->id) {
			$obj->topic=&$this;
		}
		return array(
			'content'=>'<div class="tool-title">'.$this->parent->pp_getLL('topic.newpost.title','Reply',TRUE).'</div>'.$obj->display(), //The form
			'display'=>(is_array($this->processMessage[$obj->datakey]) && count($this->processMessage[$obj->datakey])) //If true, the form will not be hidden
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
		$res=FALSE;

		//Checking write access in parent forum
		if ($this->forum->userCanWriteInForum()) {
			//Then, checking topic status
			if ($this->data['status']!=2 || $this->forum->userIsGuard()) {
				$res=TRUE;
			}
		}

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['userCanWriteInTopic'],
			$res,
			$this
			);

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
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['userCanReplyInTopic'],
			$res,
			$this
			);

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
			} elseif ($this->parent->currentUser->id && ($this->data['author']==$this->parent->currentUser->id)) {
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
		$res=$this->getBasicWriteAccess();
		$res=$this->forum->userCanEditTopic($this->id,$res);

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['userCanReplyInTopic'],
			$res,
			$this
			);

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
		$res=$this->getBasicWriteAccess();
		$res=$this->forum->userCanDeleteTopic($this->id,$res);

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['userCanDelete'],
			$res,
			$this
			);

		return $res;
	}

	/****************************************/
	/******* Messages related funcs *********/
	/****************************************/

	/**
	 * Load the topic's message list and put it in $this->messageList (array of uids)
	 *
	 * @param boolean $clearCache = Set to true to clear query cache
	 * @access public
	 * @return void 
	 */
	function loadMessages($clearCache=FALSE,$noCheck=FALSE) {
		if (!is_array($this->messageList) || $clearCache) {
			//Init
			$this->messageList=Array();
			//Get raw list
			foreach ($this->parent->getTopicMessages($this->id,$clearCache) as $messageId) {
				//Additional check
				if ($noCheck || $this->messageIsVisible($messageId)) {
					$this->messageList[]=$messageId;
				}
			}
		}
	}

	/**
	 * Return the last posted message's uid (in this topics)
	 *
	 * @access public
	 * @return int 
	 */
	function getLastMessage() {
		$this->loadMessages();
		return end($this->messageList);
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
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['messageIsVisible'],
			$res,
			$this
			);

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
	function userCanEditMessage($messageId,$res) {
		$res=$this->forum->userCanEditMessage($messageId,$res);

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['userCanEditMessage'],
			$res,
			$this
			);

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
	function userCanDeleteMessage($messageId,$res) {
		$res=$this->forum->userCanDeleteMessage($messageId,$res);

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['userCanDeleteMessage'],
			$res,
			$this
			);

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
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_topic']['deleteMessage'],
			$data,
			$this
			);
		
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



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_topic.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_topic.php']);
}

?>