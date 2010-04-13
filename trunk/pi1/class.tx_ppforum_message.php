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
 * Class 'tx_ppforum_message' for the 'pp_forum' extension.
 * 'message' record object
 *
 * @author Popy <popy.dev@gmail.com>
 * @package TYPO3
 * @subpackage tx_ppforum
 */
class tx_ppforum_message extends tx_ppforum_base {
	/**
	 * Used to build forms and to get data in piVars
	 * @access public
	 * @var string
	 */
	var $datakey = 'editpost';

	/**
	 * True if the message has been inserted during this process
	 * @access public
	 * @var boolean
	 */
	var $isNew = false;

	/**
	 * Message/Topic data merged with POST data (only allowed fields)
	 * @access public
	 * @var array
	 */
	var $mergedData = Array(); //Message/Topic data merged with POST data

	/**
	 * Event handler directives
	 * @access public
	 * @var array
	 */
	var $forceReload = Array();

	/**
	 * Parent topic
	 * @access public
	 * @var object
	 */
	var $topic = null;

	/**
	 * Message author object
	 * @access public
	 * @var object
	 */
	var $author = null;

	/**
	 * List of allowed incomming fields from forms(Other fields will be ignored)
	 * @access public
	 * @var array
	 */
	var $allowedFields=Array(
		'message'   => '',
		'nosmileys' => '',
		'parser'    => '',
		'hidden'    => 'guard',
	);
	
	/**
	 * 
	 * @access public
	 * @var string
	 */
	var $validErrors = Array();

	/**
	 * Loads the message data
	 * 
	 * @param array $data = the record row
	 * @param boolean $delaySubs = if TRUE, sub object loading should be delayed.
	 *           This option is used by the list loader (loadRecordObjectList) to load all sub objects at same time
	 * @access public
	 * @return int = the loaded id 
	 */
	function loadData($data, $delaySubs = false) {
		if (parent::loadData($data, $delaySubs)) {
			//** Load topic
			if ($this->type == 'message') {
				$this->topic = &$this->parent->getRecordObject(
					intval($this->data['topic']),
					'topic',
					false,
					$delaySubs
				);
			}

			$this->author = &$this->parent->getRecordObject(
				intval($this->data['author']),
				'user',
				false,
				$delaySubs
			);

			//** init mergedData
			$this->mergedData = $this->data;
		}

		return $this->id;
	}

	/**
	 * Merge allowed fields from incomming message data
	 * $this->mergedData need to be set ! (done by load())
	 *
	 * @access public
	 * @return void 
	 */
	function mergeData($incommingData) {
		if (is_array($incommingData)) {
			//$incommingData = $this->parent->piVars[$this->datakey];

			//** Init bool vars
			if ($this->type == 'message') {
				$isAdmin = $this->topic->forum->userIsAdmin();
				$isGuard = $this->topic->forum->userIsGuard();
			} else {
				$isAdmin = $this->forum->userIsAdmin();
				$isGuard = $this->forum->userIsGuard();
			}

			foreach ($this->allowedFields as $key=>$val) {
				//** Field access check
				switch ($val){
				case 'admin': 
					$allowed = $isAdmin;
					break;
				case 'guard': 
					$allowed = $isGuard;
					break;
				default:
					$allowed = true;
					break;
				}

				//** Merging field
				if (isset($incommingData[$key]) && $allowed) {
					$this->mergedData[$key] = $incommingData[$key];
				}
			}

			//Playing hook list
			$this->parent->pp_playHookObjList('message_mergeData', $incommingData, $this);
		}
	}

	/**
	 * Saves the message to the DB (and call event function)
	 *
	 * @access public
	 * @return int/boolean = the message uid or false when an error occurs 
	 */
	function save($forceReload = true) {
		/* Declare */
		$result = false;
		$mode = 'update';

		/* Begin */
		if (!$this->id) {
			$mode = 'create';
		} elseif ($this->mergedData['deleted'] && !$this->data['deleted']) {
			$mode = 'delete';
		}

		// Plays hook list : Allow to change some field before saving
		$this->parent->pp_playHookObjList('message_save', $mode, $this);

		$this->mergedData['author'] = $this->author->id;
		$this->mergedData['topic'] = $this->topic->id;

		$result = $this->basic_save();

		if ($forceReload) {
			$this->forceReload['topic'] = true;
		}

		//Launch topic event handler
		$this->event_onUpdateInMessage($mode);

		return $result;
	}

	/**
	 * Saves the message to the DB
	 *
	 * @access public
	 * @return int/boolean = the record uid or false when an error occurs 
	 */
	function basic_save($noTstamp = false) {
		/* Declare */
		$result = false;
		$CTRL = isset($GLOBALS['TCA'][$this->tablename]['ctrl']) ? $GLOBALS['TCA'][$this->tablename]['ctrl'] : array();
		$tstampField = isset($CTRL['tstamp']) ? $CTRL['tstamp'] : false;
		$crdateField = isset($CTRL['crdate']) ? $CTRL['crdate'] : false;

		/* Begin */
		// Updating tstamp field
		if ($tstampField && !$noTstamp) {
			// Updating tstamp field
			$this->mergedData[$tstampField] = $GLOBALS['SIM_EXEC_TIME'];
		}

		if ($this->id) {
			//** Optimistic update :
			$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				$this->tablename,
				'uid=' . strval($this->id),
				array_diff_assoc(
					$this->mergedData,
					$this->data
				)
			);

			$this->parent->log('UPDATE');
		} else {
			if ($crdateField) {
				$this->mergedData[$crdateField] = $GLOBALS['SIM_EXEC_TIME'];
			}

			//** Set pid
			$this->mergedData['pid'] = $this->parent->config['savepage'];

			// Insert db row
			$result = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
				$this->tablename,
				$this->mergedData
			);
			$this->parent->log('INSERT');

			// Initialize id. Maybe we should load the full row, but no need for now
			$this->id = $this->mergedData['uid'] = $GLOBALS['TYPO3_DB']->sql_insert_id();
			$this->isNew = true;
		}

		// As we have save mergedData, the item data now equals mergedData
		$this->data = $this->mergedData;

		return $result ? $this->id : false;
	}

	/**
	 * Deletes the message
	 *
	 * @param boolean $forceReload = if TRUE, will clear topic's message list
	 * @access public
	 * @return int/boolean @see tx_ppforum_message::save 
	 */
	function delete($forceReload = true) {
		if ($this->id) {
			//check if topic can delete message
			if ($this->topic->deleteMessage($this->id)) {
				return true;
			} else {
				//Normal delete
				$this->mergedData['deleted'] = 1;

				return $this->save($forceReload);
			}
		} else {
			return false;
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
	function event_onUpdateInMessage($mode) {
		/* Declare */
		$null = null;
	
		/* Begin */
		//Playing hook list
		$this->parent->pp_playHookObjList('message_event_onUpdateInMessage', $mode, $this);

		//Forcing data and object reload. Could be used to ensure that data is "as fresh as possible"
		//Useless if oject wasn't builded with 'getMessageObj' function
		//In this case, you should use $this->parent->getSingleMessage($this->id,'clearCache');
		if ($this->forceReload['data']) $this->load($this->id, true);

		//Launch topic event function only if needed (eg: don't enter here when deleting messages from topic::delete)
		if ($this->forceReload['topic']) {
			switch ($mode){
			case 'create':
				$this->author->incrementMessageCounter();
				$this->topic->event_onMessageCreate($this->id);
				break;
			case 'update': 
				$this->topic->event_onMessageModify($this->id);
				break;
			case 'delete': 
				$this->topic->event_onMessageDelete($this->id);
				break;
			}
		}

		//Resets directives
		$this->forceReload = array();
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
	function getLink($title = false,$addParams = array(), $parameter = null) {
		//** Message anchor
		if (is_null($parameter) && $this->id) {
			$parameter = $this->parent->_displayPage . '#ppforum_message_'.$this->id;
		}

		//** Page pointer (don't set param if equals 0)
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
	 * @param boolean $forceReload = an additional parameter, used to fix an anchor "bug" (message edit link after editing it)
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
		if ($this->id) {
			return $this->topic->getMessagePageNum($this->id);
		} else {
			return 'last';
		}
	}

	/**
	 * Parse function : here will come parsing function for markup languages (BBcode, Wiki, etc)
	 *
	 * @param string $text : the text to parse
	 * @access public
	 * @return string
	 */
	function processMessage($text, $curParser = false) {
		//Load parser array
		$this->parent->loadParsers();

		//Get parser val from message data
		if (!is_string($curParser)) {
			$curParser = $this->mergedData['parser'];
		}

		//Check current parser
		$curParser = isset($this->parent->parsers[$curParser]) ? $curParser : '0'; //Get a valid parser

		if (trim($curParser)) {
			$text = $this->parent->parsers[$curParser]->parse($text);
		} else {
			// Escape html
			$text = tx_pplib_div::htmlspecialchars($text);

			// Clean CR/LF
			$text = tx_pplib_div::normalizeLineBreaks($text);

			// Paragraphs splitting
			$text = preg_replace('/\n[[:space:]]*\n[\n[:space:]]+/','</p><p>',$text);
			$text = '<p>' . nl2br($text) . '</p>';
		}

		$text = preg_replace_callback('/<a\s[^>]+>/i', array(&$this, 'processMessage_linkCallback'), $text);

		$this->parent->pp_playHookObjList('processMessage', $text, $this);


		if (!$this->mergedData['nosmileys']) {
			$text = $this->parent->smileys->processMessage($text);
		}

		return $text;
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function processMessage_linkCallback($matches) {
		/* Declare */
		$attributes = t3lib_div::get_tag_attributes($matches[0]);
		$res = null;
	
		/* Begin */
		$this->parent->pp_playHookObjList('processMessage_linkCallback', $attributes, $this);

		if (isset($attributes['href'])) {
			$linkParts = explode(':', $attributes['href']);

			switch ($linkParts[0]) {
			case 'forum':
				$res = &$this->parent->getForumObj(intval($linkParts[1]));
				$attributes['href'] = $res->getLink();
				break;
			case 'topic':
				$res = &$this->parent->getTopicObj(intval($linkParts[1]));
				$attributes['href'] = $res->getLink();
				break;
			case 'message':
				$res = &$this->parent->getMessageObj(intval($linkParts[1]));
				$attributes['href'] = $res->getLink();
				break;
			}

			$matches[0] = tx_pplib_div::buildXHTMLTag('a', $attributes);

		}

		return $matches[0];
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function checkData(&$errors) {
		//*** Checking message field
		if (!trim($this->mergedData['message'])) {
			$errors['field']['message'] = $this->parent->pp_getLL('errors.fields.message');
		} else {
			//** Clean text (correct CR/LF)
			$this->mergedData['message'] = str_replace(
				array("\r\n", "\r"),
				Array(chr(10), chr(10)),
				$this->mergedData['message']
			);
		}


		if ($this->type == 'topic') {
			//** Checking Topic fields
			//* Title
			if (!trim($this->mergedData['title'])) {
				$errors['field']['title'] = $this->parent->pp_getLL('errors.fields.title');
			}

			//**
			if (isset($this->mergedData['status'])) {
				$this->mergedData['status'] = t3lib_div::intInRange($this->mergedData['status'], 0, 2);
			} else {
				if ($this->forum->data['hidetopic']) {
					$this->mergedData['status'] = 1;
				} else {
					$this->mergedData['status'] = 0;
				}
			}
		} else {
			//*** If hidden field is not set, get its default value
			if (!isset($this->mergedData['hidden'])) {
				if (intval($this->topic->forum->data['hidemessage'])) {
					$this->mergedData['hidden'] = 1;
				} else {
					$this->mergedData['hidden'] = 0;
				}
			}
		}

		//Playing hook list : Allows to fill other fields
		$this->parent->pp_playHookObjList('message_checkData', $errors, $this);
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
		$content = '';
		$data = array(
			'data' => array(),
			'mode' => 'view'
		);
	
		/* Begin */
		//Checking mode (default : view, others : delete, new, edit)
		//Display will be different regarding the mode
		if (!$this->id) {
			$data['mode'] = 'new';
		} elseif (!intval($this->id)) {
			//New message preview
			$data['mode'] = 'preview';	
			$this->id = 0;
		} elseif ($this->type == 'message' && $this->id == intval($this->parent->getVars['editmessage']) && $this->userCanEdit()) {
			$data['mode'] = 'edit';
		} elseif ($this->id == intval($this->parent->getVars['deletemessage']) && $this->userCanDelete()) {
			$data['mode'] = 'delete';
		} elseif (count(array_diff_assoc($this->data,$this->mergedData))) {
			//Editing preview
			$data['mode'] = 'preview';
		}
	
		//Anchor & classes :
		$addClasses[] = 'single-message';

		if (in_array($data['mode'], array('view','preview'))) {
			if ($this->mergedData['hidden']) {
				$addClasses[] = 'hidden-message';
			}
		}

		if ($data['mode'] == 'preview') {
			$content .= '
	<div class="'.htmlspecialchars(implode(' ',$addClasses)).'" id="ppforum_message_preview_'.$this->id.'">';
		} else {
			$content .= '
	<div class="'.htmlspecialchars(implode(' ',$addClasses)).'" id="ppforum_message_'.$this->id.'">';
		}

		if (in_array($data['mode'], array('new','edit'))) {
			// Opening form tag. The second parameter of getEditLink ensures that the "action" url will be different of the edit link
			$content .= '<form method="post" action="'.htmlspecialchars($this->getEditLink(FALSE,TRUE)).'" class="message-edit">';
		} elseif ($data['mode'] == 'delete') {
			$content .= '<form method="post" action="'.htmlspecialchars($this->getDeleteLink()).'" class="message-delete">';
		}

		if (in_array($data['mode'], array('new','edit','delete'))) {
			//** Adding a no_cache hidden field : prevents the page to be pre-cached
			$content .= '
		<div style="display: none;"><input type="hidden" name="no_cache" value="1" /></div>';
		}

		// Standards parts
		$data['data']['head-row']    = $this->display_headRow($data['mode']);
		$data['data']['parser-row']  = $this->display_parserRow($data['mode']);
		$data['data']['main-row']    = $this->display_mainRow($data['mode']);
		$data['data']['options-row'] = $this->display_optionsRow($data['mode']);
		$data['data']['tools-row']   = $this->display_toolsRow($data['mode']);
		
		// Playing hooks : Allows to manipulate parts (add, sort, etc)
		$this->parent->pp_playHookObjList('message_display', $data, $this);

		// Printing parts
		foreach ($data['data'] as $key => $val) {
			if (trim($val)) {
				$content .= '
		<div class="row '.htmlspecialchars($key).'">'.$val.'
		</div>';
			}
		}

		// Closes form
		if (in_array($data['mode'] ,array('new','edit','delete'))) $content.='</form>';

		// Closes div
		$content.='
	</div>';


		if (in_array($data['mode'], array('preview'))) {
			// Recursive self call : preview mode need the form to be displayed
			$this->parent->getVars['editmessage'] = $this->id;

			$content .= $this->display();

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
		$content = '';
		$leftIsOk = isset($data['left']) && is_array($data['left']) && count($data['left']);
		$rightIsOk = isset($data['right']) && is_array($data['right']) && count($data['right']);

		/* Begin */
		//If we have something to display
		if ($leftIsOk || $rightIsOk) {
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
		$content = '';
		$data = array(
			'mode'  => $mode,
			'left'  => array(),
			'right' => array()
		);
	
		/* Begin */
		if (in_array($mode,array('view','preview','delete', 'edit'))) {
			$data['left']['author']  = $this->author->displayLight();
			$data['right']['crdate'] = $this->parent->renderDate($this->mergedData['crdate']);
		}
		
		// Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('message_display_headRow', $data, $this);

		return $this->display_stdPart($data);
	}

	/**
	 * Print the parser-selector form row
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return string 
	 */
	function display_parserRow($mode) {
		if (!in_array($mode,array('edit','new'))) {
			return '';
		}

		$data=array(
			'mode'  => $mode,
			'left'  => array(),
			'right' => array(),
		);

		$this->parent->loadParsers();


		if (isset($this->mergedData['parser'])) {
			$parser = isset($this->parent->parsers[$this->mergedData['parser']]) ? $this->mergedData['parser'] : '0'; //Get a valid parser
		} else {
			//*** Selecting default parser
			$profile = $this->parent->currentUser->getUserPreference('profil');
			if (isset($profile['pref_parser']) && isset($this->parent->parsers[$profile['pref_parser']])) {
				$parser = $profile['pref_parser'];
			} else {
				$parser = '0';
			}
		}

		//Parser selector
		$data['left']['parser-selector'] = $this->parent->pp_getLL('message.fields.parser','Parser : ') . ' <select name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']') . '[parser]" onchange="tx_ppforum.switchParserToolbar(this,\'parser-toolbar-\'+this.options[this.selectedIndex].value);">';
		$data['right'] = '';

		foreach (array_keys($this->parent->parsers) as $key) {
			//Options
			$selected = '';
			$display  = ' style="display: none;"';
			if (!strcmp($key, $parser)) {
				$selected = ' selected="selected"';
				$display  = '';
			}

			if (is_object($this->parent->parsers[$key])) {
				$optionTitle = $this->parent->parsers[$key]->parser_getTitle(true);
				$data['right'] .= '<div class="parser-toolbar parser-toolbar-'.htmlspecialchars($key).'"'.$display.'>'.
					$this->parent->parsers[$key]->printToolbar($this->datakey).
					'</div>';
				
			} else {
				$optionTitle = $this->parent->parsers[$key];
			}
			$data['left']['parser-selector'] .= '<option value="'.htmlspecialchars($key).'"'.$selected.'>' . $optionTitle . '</option>';
		}

		$data['left']['parser-selector'] .= '</select>&nbsp;';
		$data['right'] = $this->wrapForNoJs($data['right']);
		$data['right'] .= '&nbsp;';


		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('message_display_parserRow', $data, $this);

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
		$content = '';
		$data = array(
			'mode'  => $mode,
			'left'  => array(),
			'right' => array()
		);
		
	
		/* Begin */
		if ($this->type == 'message') {
			$lang = $this->topic->forum->metaData['force_language'];
		} else {
			$lang = $this->forum->metaData['force_language'];
		}

		if ($lang) {
			$lang = ' lang="' . $lang . '" xml:lang="' . $lang . '"';
		}
		if (in_array($mode,array('view','preview'))) {
			if (!$this->parent->config['.lightMode']) {
				$data['left']['author-details'] = $this->author->displaySmallProfile();
			}
			$data['right']['message'] = '<div class="message"' . $lang . '>'.$this->processMessage($this->mergedData['message']).'</div>';
		} elseif ($mode == 'delete') {
			$data['right']['message'] = $this->parent->pp_getLL('message.confirmDelete','Are you sure to delete this message ?');
		} else {
			$tmp_id = 'fieldId_'.md5(microtime());

			$data['left']['smileys'] = $this->wrapForNoJs($this->parent->smileys->displaySmileysTools($this->datakey));
			$data['right']['message'] = '<label for="'.$tmp_id.'">' . $this->parent->pp_getLL('message.message','Enter your message here :') . '</label><br />' .
				strval($this->error_getFieldError('message')) .
				'<textarea id="'.$tmp_id.'" onmouseout="if (document.selection){this.selRange=document.selection.createRange().duplicate();}" cols="50" rows="10" name="'.htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']').'[message]">'.tx_pplib_div::htmlspecialchars($this->mergedData['message']).'</textarea>';
		}

		//*** Author signature
		if ($this->author->id && !$this->parent->config['.lightMode']) {
			$profilData = $this->author->getUserPreference('profil');
			if (in_array($data['mode'], array('view','preview')) && trim($profilData['signature'])) {
				// Apply parser
				$profilData['signature'] = $this->processMessage($profilData['signature'],$profilData['signature_parser']);
				$data['right']['signature'] = '<div class="user-signature"><hr class="separator" />'.$profilData['signature'].'</div>';
			}
		}

		// Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('message_display_mainRow', $data, $this);

		// Disable left column if light mode is activated
		if ($this->parent->config['.lightMode']) {
			unset($data['left']);
		}

		return $this->display_stdPart($data);
	}

	/**
	 * Display the "options" row
	 *
	 * @param string $mode = display mode (new, view, edit, delete, etc...)
	 * @access public
	 * @return string 
	 */
	function display_optionsRow($mode) {
		if (!in_array($mode,array('edit','new'))) {
			return '';
		}

		//*** fieldname prefix
		$baseName = htmlspecialchars($this->parent->prefixId.'['.$this->datakey.']');
		$prefId   = 'prefId_'.md5(microtime());
		$data = array(
			'mode'  => $mode,
			'left'  => array(),
			'right' => array()
		);
		
		// Topic move
		if (($this->type == 'topic') && ($mode == 'edit') && $this->forum->userIsGuard()) {
			$data['right']['move-topic'] = $this->parent->pp_getLL('topic.fields.move-topic', 'Move topic :') . chr(10);

			// Build forum flat tree
			$forumTree = array();
			$this->display_optionsRow_addForumItems(
				$forumTree,
				$this->parent->getForumObj(0)
			);
			unset($forumTree[0]);

			// Generate select field
			$data['right']['move-topic'] .= '<select name="' . $baseName . '[move-topic]">' . chr(10);
			$data['right']['move-topic'] .= chr(9) . '<option value=""></option>' . chr(10);

			foreach ($forumTree as $k => $v) {
				if ($v['forum']->userIsGuard()) {
					$temp_selected = ($v['id'] == $this->forum->id) ? ' selected="selected"' : '';
					$temp_indent = str_repeat('-', $v['level']) . ' ';
					$data['right']['move-topic'] .= chr(9) .
						'<option value="' . $v['id'] . '">' .
						$temp_indent . tx_pplib_div::htmlspecialchars($v['forum']->data['title']) .
						'</option>' . chr(10);
				}
			}

			unset($temp_selected);
			unset($temp_indent);

			$data['right']['move-topic'] .= '</select>' . chr(10);
		}

		if (isset($this->mergedData['nosmileys'])) {
			$checked = $this->mergedData['nosmileys'] ? ' checked="checked"' : '';
		} else {
			$profile = $this->parent->currentUser->getUserPreference('profil');
			$checked = $profile['def_disableSimeys'] ? ' checked="checked"' : '';
		}

		$data['right']['div'] = '<input type="hidden" value="" name="'.$baseName.'[nosmileys]" /><input id="'.$prefId.'_nosmileys" type="checkbox" value="1" name="'.$baseName.'[nosmileys]"'.$checked.' /><label for="'.$prefId.'_nosmileys">'.$this->parent->pp_getLL('message.fields.nosmileys','Desactivate smileys').'</label>';

		// Topic "status" selector
		if (($this->type == 'topic') && $this->forum->userIsGuard()) {
			if (isset($this->mergedData['pinned']) && $this->mergedData['pinned']) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}

			$data['right']['div'] .= '<input type="hidden" value="" name="'.$baseName.'[pinned]" /><input id="'.$prefId.'_pinned" type="checkbox" value="1" name="'.$baseName.'[pinned]"'.$checked.' /><label for="'.$prefId.'_pinned">'.$this->parent->pp_getLL('topic.fields.pinned','Pinned').'</label>';

			if (!$this->parent->config['.lightMode']) {
				$data['left']['status'] = $this->parent->pp_getLL('topic.status','Change state : ').'<select name="'.$baseName.'[status]" />';
				foreach (array(0=>'normal',1=>'hidden',2=>'closed') as $key => $val) {
					$selected = '';
					if (intval($this->mergedData['status']) == $key) {
						$selected = ' selected="selected"';
					}

					$data['left']['status'] .= '<option value="'.strval(intval($key)).'"'.$selected.'>'.$this->parent->pp_getLL('topic.status.'.$val,$val).'</option>';
				}
				$data['left']['status'] .= '</select>';
			}
		}

		// "hidden" checkbox
		if (($this->type == 'message') && $this->topic->forum->userIsGuard()) {
			if (isset($this->mergedData['hidden']) && $this->mergedData['hidden']) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}
			$data['right']['div'] .= '<input type="hidden" value="" name="'.$baseName.'[hidden]" /><input id="'.$prefId.'_hidden" type="checkbox" value="1" name="'.$baseName.'[hidden]"'.$checked.' /><label for="'.$prefId.'_hidden">'.$this->parent->pp_getLL('message.fields.hidden','Hide').'</label>';
		}

		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('message_display_optionRow', $data, $this);

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
	function display_optionsRow_addForumItems(&$items, &$forum, $level = -1) {
		$items[] = array(
			'id' => $forum->id,
			'forum' => &$forum,
			'level' => $level,
		);

		$forumIdList = $this->parent->getForumChilds($forum->id);
		$this->parent->loadRecordObjectList($forumIdList, 'forum');
		$this->parent->flushDelayedObjects();

		foreach ($forumIdList as $child) {
			$theChild = &$this->parent->getForumObj($child);

			$this->display_optionsRow_addForumItems($items, $theChild , $level + 1);

			unset($theChild);
		}
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
		$conf = array(
			'cmd'  => 'callObj',
			'cmd.' => Array(
				'uid' => $this->id,
				'method' => '_display_toolsRow',
				'div.'   => array('mode' => $mode),
			),
		);

		if ($this->type == 'message') {
			$conf['cmd.']['object'] = 'message';
			//Simulating reference to parent topic :
			if (!$this->id) $conf['cmd.']['div.']['topic'] = $this->topic->id;
		} else {
			$conf['cmd.']['object'] = 'topic';
			//Simulating reference to parent forum :
			if (!$this->id) $conf['cmd.']['div.']['forum'] = $this->forum->id;
		}

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
		} elseif ($mode=='view') {
			$temp=array();
			//Prints 'edit' and 'delete' links (regarding permissions)
			if ($this->userCanEdit()) $temp[]=$this->getEditLink($this->parent->pp_getLL('message.edit','Edit',TRUE));
			if ($this->userCanDelete()) $temp[]=$this->getDeleteLink($this->parent->pp_getLL('message.delete','Delete',TRUE));

			if (count($temp)) $data['right']['toolbar-2']=implode(' ',$temp);

		}

					
		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('message_display_toolsRow', $data, $this);

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
	function error_getFieldError($fieldName, $wrapIt = true) {
		$error = isset($this->validErrors['field'][$fieldName]) ? $this->validErrors['field'][$fieldName] : null;

		if (!is_null($error) && $wrapIt) {
			$error = '<div class="error">' . $error . '</div>';
		}

		return $error;
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
		$this->parent->pp_playHookObjList('message_isVisible', $res, $this);

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
		$this->parent->pp_playHookObjList('message_isVisibleRecursive', $res, $this);

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
		$this->parent->pp_playHookObjList('message_getBasicWriteAccess', $res, $this);

		return $res;
	}


	/**
	 * Access check : Check if current user can edit this message
	 *
	 * @access public
	 * @return boolean = TRUE if user can edit 
	 */
	function userCanEdit() {
		$res = $this->getBasicWriteAccess();
		$res = $res && $this->topic->userCanEditMessage($this->id);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('message_userCanEdit', $res, $this);

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
		$res = $this->getBasicWriteAccess();
		$res = $res && $this->topic->userCanDeleteMessage($this->id);

		//Plays hook list : Allows to change the result
		$this->parent->pp_playHookObjList('message_userCanDelete', $res, $this);

		return $res;
	}
}

tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_message.php');
?>