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
 * Class 'tx_ppforum_message' for the 'pp_forum' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_message {
	var $id=0; //Message uid
	var $datakey='editpost';  //Used to build forms and to get data in piVars
	var $type='message'; //Use to dinstinct topics and message object in generic methods
	var $tablename='tx_ppforum_messages'; //Table corresponding to this type
	var $isNew=FALSE;

	var $data=array(); //Message/Topic data
	var $mergedData=Array(); //Message/Topic data merged with POST data
	var $forceReload=array(); //Event handler directives

	var $topic=NULL; //Pointer to parent topic
	var $parent=NULL; //Pointer to plugin object

	/**
	 * List of allowed incomming fields from forms(Other fields will be ignored)
	 * @access public
	 * @var array
	 */
	var $allowedFields=Array(
		'message'=>'',
		'nosmileys'=>'',
		'parser'=>'',
		'hidden'=>'guard',
		);

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function init() {
		$null=NULL;

		//Playing hook list
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_'.$this->type]['init'],
			$null,
			$this
			);
	}

	/**
	 * Loads the message data from DB
	 *
	 * @param int $id = Message uid
	 * @param boolean $clearCache = @see tx_pplib::do_cachedQuery
	 * @access public
	 * @return int = loaded uid
	 */
	function load($id,$clearCache=FALSE) {
		if ($this->data=$this->parent->getSingleMessage($id,$clearCache)) {
			$this->id=intval($id);
			$this->topic=&$this->parent->getTopicObj($this->data['topic']);
			$this->mergedData=$this->data;			
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
	function mergeData() {
		if (isset($this->parent->piVars[$this->datakey]) && is_array($this->parent->piVars[$this->datakey])) {
			$incommingData=$this->parent->piVars[$this->datakey];
			if ($this->type=='message') {
				$isAdmin=$this->topic->forum->userIsAdmin();
				$isGuard=$this->topic->forum->userIsGuard();
			} else {
				$isAdmin=$this->forum->userIsAdmin();
				$isGuard=$this->forum->userIsGuard();
			}

			foreach ($this->allowedFields as $key=>$val) {
				//** Field access check
				$allowed=FALSE;
				switch ($val){
				case 'admin': 
					$allowed=$isAdmin;
					break;
				case 'guard': 
					$allowed=$isGuard;
					break;
				default:
					$allowed=TRUE;
					break;
				}

				//** Merging field
				if (isset($incommingData[$key]) && $allowed) {
					$this->mergedData[$key]=$incommingData[$key];
				}
			}

			//Playing hook list
			$null=NULL;
			tx_pplib_div::playHooks(
				$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_'.$this->type]['init'],
				$null,
				$this
				);

			//t3lib_div::debug($this->mergedData, '');
		}
	}

	/**
	 * Loads the message/topic author
	 *
	 * @access public
	 * @return void 
	 */
	function loadAuthor($clearCache=FALSE) {
		if (!is_object($this->author) || $clearCache) {
			$this->author=&$this->parent->getUserObj($this->mergedData['author']);
		}
	}

	/**
	 * Saves the message to the DB (and call event function)
	 *
	 * @access public
	 * @return int/boolean = the message uid or false when an error occurs 
	 */
	function save($forceReload=TRUE) {
		/* Declare */
		$null=NULL;
		$result=FALSE;

		/* Begin */
		//Plays hook list : Allow to change some field before saving
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_'.$this->type]['save'],
			$null,
			$this
			);
		
		//Updating tstamp field
		$this->mergedData['tstamp']=$GLOBALS['SIM_EXEC_TIME'];

		if ($this->id) {
			//*** Optimistic update :
			//This part of code is here only for explaination, everything is in the same row (for memory consumming reasons)
			/*
			//Calculating diff
			$diff=array_diff_assoc(
				$this->data,
				$this->data
				);
			/**/

			//Updating db row
			$result=$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				$this->tablename,
				'uid='.$this->id,
				array_diff_assoc(
					$this->mergedData,
					$this->data
					)
				);

			$this->parent->log('UPDATE');
		} else {
			//Initialize some fields
			/*
			if ($this->type=='message') {
				$this->mergedData['topic']=$this->topic->id;
			} else {
				$this->mergedData['forum']=$this->forum->id;
			}
			$this->mergedData['author']=$this->parent->getCurrentUser();
			$this->mergedData['crdate']=$GLOBALS['SIM_EXEC_TIME'];
			*/

			$this->mergedData['pid']=$this->parent->config['savepage'];

			//Insert db row
			$result=$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->tablename,$this->mergedData);
			$this->parent->log('INSERT');

			//Initialize id. Maybe we should load the full row, but for now this will not be usefull
			$this->id=$this->mergedData['uid']=$GLOBALS['TYPO3_DB']->sql_insert_id();
			$this->isNew=TRUE;

			//Reloading list (may have change because of the new row)
			if ($forceReload) $this->forceReload['list']=1;
		}

		$this->data=$this->mergedData;

		//Launch the event func
		if ($this->type=='message') {
			//Launch topic event handler
			if ($forceReload) $this->forceReload['topic']=1;
			$this->event_onUpdateInMessage();
		} else {
			//Launch forum event handler
			if ($forceReload) $this->forceReload['forum']=1;
			$this->event_onUpdateInTopic($this->isNew,FALSE);
		}

		return $result?$this->id:FALSE;
	}

	/**
	 * Deletes the message
	 *
	 * @param boolean $forceReload = if TRUE, will clear topic's message list
	 * @access public
	 * @return int/boolean @see tx_ppforum_message::save 
	 */
	function delete($forceReload=TRUE) {
		if ($this->id) {
			//check if topic can delete message
			if ($this->topic->deleteMessage($this->id)) {
				return TRUE;
			} else {
				//Normal delete
				$this->mergedData['deleted']=1;
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
	 * Launched when a message is created/modified
	 *
	 * @access protected
	 * @return void
	 */
	function event_onUpdateInMessage() {
		/* Declare */
		$null=NULL;
	
		/* Begin */
		//Playing hook list
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['event_onUpdateInMessage'],
			$null,
			$this
			);

		//If needed (deletion/creation of a message), clear the message list query cache
		if ($this->forceReload['list']) $this->topic->loadMessages(TRUE);

		//Forcing data and object reload. Could be used to ensure that data is "as fresh as possible"
		//Useless if oject wasn't builded with 'getMessageObj' function
		//In this case, you should use $this->parent->getSingleMessage($this->id,'clearCache');
		if ($this->forceReload['data']) $this->load($this->id,TRUE);

		//Launch topic event function only if needed (eg: don't enter here when deleting messages from topic::delete)
		if ($this->forceReload['topic']) $this->topic->event_onUpdateInTopic($this->isNew,$this->id);

		//Resets directives
		$this->forceReload=array();
	}

	/****************************************/
	/*********** Links functions ************/
	/****************************************/

	/**
	 * Builds a link to a message (including anchor)
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @param array $addParams = additional url parameters.
	 * @access public
	 * @return string 
	 */
	function getLink($title=FALSE,$addParams=array(), $parameter = null) {
		//** Message anchor
		if (is_null($parameter) && $this->id) {
			$parameter = $this->parent->_displayPage . '#ppforum_message_'.$this->id;
		}

		//** Page pointer
		if ($pointer = $this->getPageNum()) {
			$addParams['pointer'] = $pointer;
		}

		return $this->topic->getMessageLink(
			$title,
			$addParams, //overrule piVars
			$parameter
			);
	}

	/**
	 * Builds a link to the message edit form
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @param boolean $forceReload = an additional parameter, used to fix an anchor bug (message edit link after editing it)
	 * @access public
	 * @return string
	 */
	function getEditLink($title = false, $forceReload = false) {
		$addParams = array('editmessage' => -1);
		if ($this->id) {
			$addParams['forceReload'] = $forceReload;
			$addParams['editmessage'] = $this->id;
		}
		return $this->getLink($title, $addParams);
	}

	/**
	 * Builds a link to the message deleting form
	 *
	 * @param string $title = The link text. If empty, the function will return the url (instead of the A tag)
	 * @access public
	 * @return string 
	 */
	function getDeleteLink($title = false) {
		if ($this->id) {
			$addParams = array('deletemessage' => $this->id);
			return $this->getLink($title, $addParams);
		} else {
			return '';
		}
	}

	/****************************************/
	/************ Div functions *************/
	/****************************************/


	/**
	 * Search in wich page of the topic this message will appear (used by link functions)
	 *
	 * @access public
	 * @return int = pointer value 
	 */
	function getPageNum() {
		return $this->topic->getMessagePageNum($this->id);
	}

	/**
	 * Parse function : here will come parsing function for markup languages (BBcode, Wiki, etc)
	 *
	 * @param string $text : the text to parse
	 * @access public
	 * @return string
	 */
	function processMessage($text,$curParser=FALSE) {
		//Load parser array
		$this->parent->loadParsers();
		//Get parser val from message data
		if (!is_string($curParser)) {
			$curParser=$this->mergedData['parser'];
		}
		//Check current parser
		$parser=in_array($curParser,array_keys($this->parent->parsers))?$curParser:0; //Get a valid parser

		if ($parser && $parser!='0' && in_array($curParser,array_keys($this->parent->parsers))) {
			$parserArr=&$this->parent->parsers[$parser]; //Get parser conf
			$method=$parserArr['conf']['messageParser'];
			$parserArr['object']->caller=&$this;
			$text=$parserArr['object']->$method($text);
		} else {
			$text=tx_pplib_div::htmlspecialchars($text);
//			$text=str_replace("\r",'',$text);
			$text=ereg_replace("\n[\n[:space:]]+",'</p><p>',$text);
			$text='<p>'.nl2br($text).'</p>';
		}

		if (!$this->mergedData['nosmileys']) {
			$text=$this->parent->smileys->processMessage($text);
		}
		//No hook there. API coming soon :)
		return $text;
	}

	/****************************************/
	/*********** Display functions **********/
	/****************************************/

	/**
	 * Displays a message
	 *
	 * @access public
	 * @return string 
	 */
	function display($addClasses=array()) {
		/* Declare */
		$content='';
		$data=array(
			'conf'=>array(),
			'mode'=>'view'
			);
	
		/* Begin */
		//Checking mode (default : view, others : delete, new, edit)
		//Display will be different regarding the mode
		if (!$this->id) {
			$data['mode']='new';
		} elseif (!intval($this->id)) {
			//New message preview
			$data['mode']='preview';	
			$this->id=0;
		} elseif ($this->type=='message' && $this->id==intval($this->parent->getVars['editmessage']) && $this->userCanEdit()) {
			$data['mode']='edit';
		} elseif ($this->id==intval($this->parent->getVars['deletemessage']) && $this->userCanDelete()) {
			$data['mode']='delete';
		} elseif (count(array_diff_assoc($this->data,$this->mergedData))) {
			//Editing preview
			$data['mode']='preview';
		}

		//Loading author
		$this->loadAuthor();

		//Anchor & classes :
		$addClasses[]='single-message';

		if (in_array($data['mode'],array('view','preview'))) {
			if ($this->mergedData['hidden']) {
				$addClasses[]='hidden-message';
			}
		}

		if ($data['mode']=='preview') {
			$content.='
	<div class="'.htmlspecialchars(implode(' ',$addClasses)).'" id="ppforum_message_preview_'.$this->id.'">';
		} else {
			$content.='
	<div class="'.htmlspecialchars(implode(' ',$addClasses)).'" id="ppforum_message_'.$this->id.'">';
		}

		if (in_array($data['mode'],array('new','edit'))) {
			//Opening form tag. The second parameter of getEditLink ensures that the "action" url will be different of the edit link
			$content.='<form method="post" action="'.htmlspecialchars($this->getEditLink(FALSE,TRUE)).'" class="message-edit">';
		} elseif ($data['mode']=='delete') {
			$content.='<form method="post" action="'.htmlspecialchars($this->getDeleteLink()).'" class="message-delete">';
		}

		//Standards parts
		$data['conf']['head-row']=$this->display_headRow($data['mode']);
		$data['conf']['parser-row']=$this->display_parserRow($data['mode']);
		$data['conf']['main-row']=$this->display_mainRow($data['mode']);
		$data['conf']['options-row']=$this->display_optionsRow($data['mode']);
		$data['conf']['tools-row']=$this->display_toolsRow($data['mode']);
		
		//Playing hooks : Allows to manipulate parts (add, sort, etc)
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['display'],
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

		//Closes form
		if ($data['mode']=='edit' || $data['mode']=='new') $content.='</form>';
		//Closes div
		$content.='
	</div>';


		if (in_array($data['mode'],array('preview'))) {
			$this->parent->getVars['editmessage']=$this->id;

			$content.=$this->display();

			unset($this->parent->getVars['editmessage']);
		}
		unset($data);

		$this->topic->event_onMessageDisplay($this->id);

		return $content;
	}

	/**
	 * Displays a standard detail-part (two columns)
	 *
	 * @param array $data = columns content (keys are 'left' and 'right', others will be ignored)
	 * @access public
	 * @return string 
	 */
	function display_stdPart($data) {
		/* Declare */
		$content='';

		/* Begin */
		//Forcing to array
		//if (!is_array($data['left'])) $data['left']=array($data['left']);
		//if (!is_array($data['right'])) $data['right']=array($data['right']);

		//If we have something to display
		if ((is_array($data['left']) && count($data['left'])) || (is_array($data['right']) && count($data['right']))) {
			//Browses columnls
			foreach (array('left','right') as $part) {
				if (isset($data[$part])) {
					if (is_array($data[$part])) {
						$partContent = implode(' ',$data[$part]);
					} else {
						$partContent = $data[$part];
					}
					$content .= '
			<div class="col '.$part.'-col">
				'.$partContent.'
			</div>';
				}
			}
		}

		if ($data['asJs']) {
			return $this->wrapForNoJs($content);
		} else {
			return $content;
		}
	}

	/**
	 * Ensure that the content will not be printed if user-agent don't execute javascript
	 * 
	 * @param string $content = the content
	 * @access public
	 * @return string 
	 */
	function wrapForNoJs($content) {
		return '
<script type="text/javascript">
	/*<![CDATA[*/
	<!--
	// By doing this, old browser with no javascript (and maybe no CSS !) will not display this part
	//   this is a good thing because this part is useless without javascript !!
	document.write(\''.addslashes(str_replace(array("\r","\n"),array('',''),$content)).'\');
	//-->
	/*]]>*/
</script>';
	}

	/**
	 * Display the head part of a message
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return string 
	 */
	function display_headRow($mode) {
		/* Declare */
		$content='';
		$data=array('mode'=>$mode,'left'=>array(),'right'=>array());
	
		/* Begin */
		if (in_array($mode,array('view','preview','delete', 'edit'))) {
			$data['left']['author'] = $this->author->displayLight();
			$data['right']['crdate'] = $this->parent->renderDate($this->mergedData['crdate']);
		}
		
		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		tx_pplib_div::playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['display_headRow'],$data,$this);

		return $this->display_stdPart($data);
	}

	/**
	 *
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return void 
	 */
	function display_parserRow($mode) {
		if (!in_array($mode,array('edit','new'))) {
			return '';
		}

		$data=array('mode'=>$mode,'left'=>array(),'right'=>array());

		$this->parent->loadParsers();


		if (isset($this->mergedData['parser'])) {
			$parser=in_array($this->mergedData['parser'],array_keys($this->parent->parsers))?$this->mergedData['parser']:'0'; //Get a valid parser
		} else {
			//*** Selecting default parser
			$profile=$this->parent->currentUser->getUserPreference('profil');
			if (isset($profile['pref_parser']) && in_array($profile['pref_parser'],array_keys($this->parent->parsers))) {
				$parser=$profile['pref_parser'];
			} else {
				$parser='0';
			}
		}

		//Parser selector
		$data['left']['parser-selector']=$this->parent->pp_getLL('message.fields.parser','Parser : ',TRUE).' <select name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[parser]" onchange="ppforum_switchParserToolbar(this,\'parser-toolbar-\'+this.options[this.selectedIndex].value);">';
		$data['right']='';

		foreach (array_keys($this->parent->parsers) as $key) {
			$val=&$this->parent->parsers[$key];
			$val['object']->caller=&$this;

			//Options
			$selected='';
			$display=' style="display: none;"';
			if (!strcmp($key,$parser)) {
				$selected=' selected="selected"';
				$display='';
			}
			$data['left']['parser-selector'].='<option value="'.htmlspecialchars($key).'"'.$selected.'>'.$val['label'].'</option>';

			//We build toolbars at same time
			if ($val['conf']['printToolbar'] && method_exists($val['object'],$val['conf']['printToolbar'])) {
				//We have a valid parser-object and the method exists, so it can print the toolbar
				$val['object']->conf=$val['conf']; //Init conf
				$methodName=$val['conf']['printToolbar'];
				//Generate toolbar
				$data['right'].='<div class="parser-toolbar parser-toolbar-'.htmlspecialchars($key).'"'.$display.'>'.
					$val['object']->$methodName().
					'</div>';
			} else {
				//$data['right'].='<div class="parser-toolbar parser-toolbar-'.htmlspecialchars($key).'"'.$display.'>&nbsp;</div>';
			}

		}

		$data['left']['parser-selector'] .= '</select>&nbsp;';
		$data['right'] = $this->wrapForNoJs($data['right']);
		$data['right'] .= '&nbsp;';


		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		tx_pplib_div::playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['display_parserRow'],$data,$this);

		return $this->display_stdPart($data);
	}

	/**
	 * Display the main part of a message (the message in fact :])
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return string
	 */
	function display_mainRow($mode) {
		/* Declare */
		$content='';
		$data = array('mode'=>$mode,'left'=>array(),'right'=>array());
	
		/* Begin */
		if (in_array($mode,array('view','preview'))) {
			if (!$this->parent->config['.lightMode']) $data['left']['author-details']=$this->author->displaySmallProfile();
			$data['right']['message']='<div class="message">'.$this->processMessage($this->mergedData['message']).'</div>';
		} elseif ($mode == 'delete') {
			$data['right']['message']=$this->parent->pp_getLL('message.confirmDelete','Are you sure to delete this message ?',TRUE);
		} else {
			$tmp_id='fieldId_'.md5(microtime());
			$data['left']['smileys'] = $this->wrapForNoJs($this->parent->smileys->displaySmileysTools($this->datakey));
			$data['right']['message']='<label for="'.$tmp_id.'">'.$this->parent->pp_getLL('message.message','Enter your message here :',TRUE).'</label><br /><textarea id="'.$tmp_id.'" onmouseout="if (document.selection){this.selRange=document.selection.createRange().duplicate();}" cols="50" rows="10" name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[message]">'.tx_pplib_div::htmlspecialchars($this->mergedData['message'])/*(is_array($this->parent->piVars[$this->datakey])?tx_pplib_div::htmlspecialchars($this->parent->piVars[$this->datakey]['message']):tx_pplib_div::htmlspecialchars($this->data['message']))*/.'</textarea>';
		}

		$this->loadAuthor();
		if ($this->author->id && !$this->parent->config['.lightMode']) {
			$profilData=$this->author->getUserPreference('profil');
			if (in_array($data['mode'],array('view','preview')) && trim($profilData['signature'])) {
				$profilData['signature']=$this->processMessage($profilData['signature'],$profilData['signature_parser']);
				$data['right']['signature']='<div class="user-signature"><hr class="separator" />'.$profilData['signature'].'</div>';
			}
		}

		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		tx_pplib_div::playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['display_mainRow'],$data,$this);

		if ($this->parent->config['.lightMode']) {
			unset($data['left']);
		}

		return $this->display_stdPart($data);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function display_optionsRow($mode) {
		if (!in_array($mode,array('edit','new'))) {
			return '';
		}
		$baseName=htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']');
		$prefId='prefId_'.md5(microtime());

		$data=array('mode'=>$mode,'left'=>array(),'right'=>array());
		
		if (isset($this->mergedData['nosmileys'])) {
			$checked=$this->mergedData['nosmileys']?' checked="checked"':'';
		} else {
			$profile=$this->parent->currentUser->getUserPreference('profil');
			$checked=$profile['def_disableSimeys']?' checked="checked"':'';
		}

		$data['right']['div']='<input type="hidden" value="" name="'.$baseName.'[nosmileys]" /><input id="'.$prefId.'_nosmileys" type="checkbox" value="1" name="'.$baseName.'[nosmileys]"'.$checked.' /><label for="'.$prefId.'_nosmileys">'.$this->parent->pp_getLL('message.fields.nosmileys','Desactivate smileys',TRUE).'</label>';

		if (($this->type=='topic') && $this->forum->userIsGuard()) {
			if (isset($this->mergedData['pinned']) && $this->mergedData['pinned']) {
				$checked=' checked="checked"';
			} else {
				$checked='';
			}

			$data['right']['div'].='<input type="hidden" value="" name="'.$baseName.'[pinned]" /><input id="'.$prefId.'_pinned" type="checkbox" value="1" name="'.$baseName.'[pinned]"'.$checked.' /><label for="'.$prefId.'_pinned">'.$this->parent->pp_getLL('topic.fields.pinned','Pinned',TRUE).'</label>';

			if (!$this->parent->config['.lightMode']) {
				$data['left']['status']=$this->parent->pp_getLL('topic.status','Change state : ',TRUE).'<select name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[status]" />';
				foreach (array(0=>'normal',1=>'hidden',2=>'closed') as $key=>$val) {
					$selected='';
					if (intval($this->mergedData['status'])==$key) {
						$selected=' selected="selected"';
					}

					$data['left']['status'].='<option value="'.strval(intval($key)).'"'.$selected.'>'.$this->parent->pp_getLL('topic.status.'.$val,$val,TRUE).'</option>';
				}
				$data['left']['status'].='</select>';
			}
		}

		if (($this->type=='message') && $this->topic->forum->userIsGuard()) {
			if (isset($this->mergedData['hidden']) && $this->mergedData['hidden']) {
				$checked=' checked="checked"';
			} else {
				$checked='';
			}
			$data['right']['div'].='<input type="hidden" value="" name="'.$baseName.'[hidden]" /><input id="'.$prefId.'_hidden" type="checkbox" value="1" name="'.$baseName.'[hidden]"'.$checked.' /><label for="'.$prefId.'_hidden">'.$this->parent->pp_getLL('message.fields.hidden','Hide',TRUE).'</label>';
		}

		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		tx_pplib_div::playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['display_optionRow'],$data,$this);

		if ($this->parent->config['.lightMode']) {
			unset($data['left']);
		}

		return $this->display_stdPart($data);
	}

	/**
	 * Display the bottom part of a message
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return string
	 */
	function display_toolsRow($mode) {
		//Builds a conf array
		$conf=array();
		$conf['cmd']='callObj';
		$conf['cmd.']['method']='_display_toolsRow';
		$conf['cmd.']['div.']['mode']=$mode;

		if ($this->type=='message') {
			$conf['cmd.']['object']='message';
			//Simulating reference to parent topic :
			if (!$this->id) $conf['cmd.']['div.']['topic']=$this->topic->id;
		} else {
			$conf['cmd.']['object']='topic';
			//Simulating reference to parent forum :
			if (!$this->id) $conf['cmd.']['div.']['forum']=$this->forum->id;
		}
		$conf['cmd.']['uid']=$this->id;

		//Calls the plugin as USER int with this special config
		// Also the content will be generated each time a user request the page
		return $this->parent->callINTpart($conf);
	}

	/**
	 * Callback function of "display_toolsRow" : this is this function which will generate the final content
	 *
	 * @access public
	 * @return string 
	 */
	function _display_toolsRow($conf) {
		/* Declare */
		$content='';
		$mode=$conf['div.']['mode'];
		$data=array('mode'=>$mode,'left'=>array(),'right'=>array());
	
		/* Begin */
		//Loading topic/forum object (checking type because this function is called for topics too !)
		if (!$this->id) {
			if ($this->type=='message') {
				$this->topic=&$this->parent->getTopicObj($conf['div.']['topic']);
			} else {
				$this->forum=&$this->parent->getForumObj($conf['div.']['forum']);
			}
		}

		$data['left']['toolbar-1']='&nbsp;';

		if (in_array($mode,array('edit','new','delete'))) {
			$tmp_id='tmpId_'.md5(microtime());
			//Prints the 'Submit' button
			$data['right']['toolbar-2']='<input id="'.htmlspecialchars($tmp_id).'" type="submit" name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[submit]" value="'.$this->parent->pp_getLL('message.edit.submit','Submit',TRUE).'" />';
			//Cancel button
			if ($this->id) {
				$data['right']['toolbar-2'].=' <button onclick="document.location=\''.htmlspecialchars($this->getLink()).'\';return false;">'.$this->parent->pp_getLL('message.edit.cancel','Cancel',TRUE).'</button>';
			}

			if ($mode!='delete') {
				$data['right']['toolbar-2'].='<input type="submit" name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[preview]" value="'.$this->parent->pp_getLL('message.edit.preview','Preview',TRUE).'" />';
			}
			$data['right']['toolbar-2'].='<script type="text/javascript">/*<![CDATA[*/<!--'.chr(10).'var temp=document.getElementById(\''.htmlspecialchars($tmp_id).'\');while(temp && temp.nodeName!=\'FORM\') temp=temp.parentNode;var i=0;while(i<temp.elements.length) {temp[i].setAttribute(\'autocomplete\',\'off\');i++;}'.chr(10).'//-->/*]]>*/</script>';			

		} elseif ($mode=='view') {
			$temp=array();
			//Prints 'edit' and 'delete' links (regarding permissions)
			if ($this->userCanEdit()) $temp[]=$this->getEditLink($this->parent->pp_getLL('message.edit','Edit',TRUE));
			if ($this->userCanDelete()) $temp[]=$this->getDeleteLink($this->parent->pp_getLL('message.delete','Delete',TRUE));

			if (count($temp)) $data['right']['toolbar-2']=implode(' ',$temp);
		}

					
		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		tx_pplib_div::playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['_display_toolsRow'],$data,$this);

		if ($this->parent->config['.lightMode']) {
			unset($data['left']);
		}

		return $this->display_stdPart($data);
	}



	/****************************************/
	/******* Access check functions *********/
	/****************************************/

	/**
	 * Checks if this message/topic is visible (not deleted, etc...)
	 *
	 * @access public
	 * @return bool 
	 */
	function isVisible() {
		$res=FALSE;

		switch ($this->type){
		case 'message':
			if ($this->id && !$this->mergedData['deleted']) {
				if (!$this->data['hidden'] || $this->topic->forum->userIsGuard()) {
					$res=TRUE;
				}
			}
			break;
		case 'topic': 
			if ($this->id && !$this->mergedData['deleted']) { //topic is valid
				if ($this->data['status']!=1) { //Topic is "normal"
					$res=TRUE;
				} elseif ($this->data['status']==1 && $this->forum->userIsGuard()) { //Topic is hidden and user is guard
					$res=TRUE;
				}
			}
			break;
		}

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['isVisible'],
			$res,
			$this
			);

		return $res;
	}

	/**
	 * Check if this message/topic AND his parents are visibles
	 *
	 * @access public
	 * @return bool 
	 */
	function isVisibleRecursive() {
		$res=$this->isVisible();
		if ($res) {
			switch ($this->type){
			case 'message':
				$res=is_object($this->topic) && $this->topic->isVisibleRecursive() && $this->topic->messageIsVisible($this->id);
				break;
			case 'topic': 
				$res=is_object($this->forum) && $this->forum->isVisible() && $this->forum->topicIsVisible($this->id);
				break;
			}
		}

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['isVisibleRecursive'],
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

		if (!$this->id) {
			$res=$this->topic->userCanReplyInTopic();
		} else {
			//Check if user can write in parent topic (can be closed, etc...)
			if ($this->topic->userCanWriteInTopic()) {
				//If user is author is author or guard
				if ($this->parent->getCurrentUser() && (intval($this->data['author'])==$this->parent->getCurrentUser())) {
					$res=TRUE;
				} elseif ($this->topic->forum->userIsGuard()) {
					$res=TRUE;
				}
			}
		}

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['getBasicWriteAccess'],
			$res,
			$this
			);

		return $res;
	}


	/**
	 * Access check : Check if current user can edit this message
	 *
	 * @access public
	 * @return boolean = TRUE if user can edit 
	 */
	function userCanEdit() {
		$res=$this->getBasicWriteAccess();
		$res=$this->topic->userCanEditMessage($this->id,$res);

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['userCanEdit'],
			$res,
			$this
			);

		return $res;
	}

	/**
	 * Access check : Check if current user can delete this message
	 * For now it's a simple alias of userCanEdit, but it can change :)
	 *
	 * @access public
	 * @return void 
	 */
	function userCanDelete() {
		$res=$this->getBasicWriteAccess();
		$res=$this->topic->userCanDeleteMessage($this->id,$res);

		//Plays hook list : Allows to change the result
		tx_pplib_div::playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_message']['userCanDelete'],
			$res,
			$this
			);

		return $res;
	}


}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_message.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_message.php']);
}

?>