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
tx_pplib_div::dynClassLoad('tx_ppforum_base');
tx_pplib_div::dynClassLoad('tx_ppforum_message');
tx_pplib_div::dynClassLoad('tx_pplib_feuser');
tx_pplib_div::dynClassLoad('t3lib_BEfunc');

/**
 * Class 'tx_ppforum_user' for the 'pp_forum' extension.
 *
 * @author Popy <popy.dev@gmail.com>
 * @package TYPO3
 * @subpackage tx_ppforum
 */
class tx_ppforum_user extends tx_pplib_feuser {

	/**
	 * Pointer to caller object (the plugin object)
	 * @access public
	 * @var &object
	 */
	var $parent = null;

	/**
	 * Record type
	 * @access public
	 * @var string
	 */
	var $type = '';

	/**
	 * Internal cache
	 * @access protected
	 * @var string
	 */
	var $cache = array();

	/**
	 * Loads the record's data from DB
	 *
	 * @param int $id = Record's uid
	 * @param boolean $clearCache = if TRUE, cached data will be overrided
	 * @param boolean $delaySubs = if TRUE, sub object loading should be delayed.
	 *           This option is used by the list loader (loadRecordObjectList) to load all sub objects at same time
	 * @access public
	 * @return int = loaded uid
	 */
	function load($id, $clearCache = false, $delaySubs = false) {
		$this->tablename = $this->parent->tables[$this->type];

		return $this->loadData($this->parent->pp_getRecord($id, $this->tablename), $delaySubs);
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function loadData($data, $delaySubs = false) {
		if (!$this->tablename && $this->type) {
			$this->tablename = $this->parent->tables[$this->type];
		}

		return parent::loadData($data);
	}

	/**
	 * Saves the user to the DB
	 *
	 * @access public
	 * @return int/boolean = the message uid or false when an error occurs 
	 */
	function save() {
		//Plays hook list : Allow to change some field before saving
		$this->parent->pp_playHookObjList('user_save', $null, $this);

		if ($this->id) {
			$this->parent->internalLogs['querys']++;
			$this->parent->internalLogs['realQuerys']++;
			return parent::save();
		} else {
			return false;
		}
	}


	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function loadUserGroups() {
		/* Declare */
		$feUserObj = &tx_pplib_div::makeInstance('tslib_feuserauth');
		$this->userGroups = Array();

		/* Begin */
		// Init object
		$feUserObj->user = $this->data;
		$feUserObj->loginType = 'FE';

		// Group loading
		$feUserObj->fetchGroupData();

		// Reading result
		foreach ($feUserObj->groupData['title'] as $k => $v) {
			$this->userGroups[intval($k)] = Array(
				'uid' => intval($k),
				'pid' => intval($feUserObj->groupData['pid'][$k]),
				'title' => $v,
			);
		}

		unset($feUserObj);
	}


	/**
	 * Returns the main user group label
	 * 
	 * @param bool $hsc = 
	 * @access public
	 * @return string 
	 */
	function getMainUserGroupLabel($hsc = true) {
		/* Declare */
		$groupUid = $this->getMainUserGroupId();
		$label = $this->userGroups[$groupUid]['title'];

		/* Begin */
		if ($hsc) {
			$label = tx_pplib_div::htmlspecialchars($label);
		}

		return $label;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getUserPreference($prefkey) {
		if (isset($this->_uc['pp_forum'][$prefkey])) {
			return $this->_uc['pp_forum'][$prefkey];
		} else {
			return null;
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function setUserPreference($prefkey, $data) {
		$this->_uc['pp_forum'][$prefkey] = $data;

		if (!$this->_saveUc) {
			$this->parent->registerCloseFunction(array(&$this, 'save'));
			$this->_saveUc = true;
		}
	}

	/**
	 *
	 *
	 * @param int $id = PM id
	 * @param string $table = PM table
	 * @access public
	 * @return void 
	 */
	function registerNewPm($id, $table, $parent = 0) {
		$GLOBALS['TYPO3_DB']->exec_INSERTquery(
			'tx_ppforum_userpms',
			array(
				'rel_id' => $id,
				'rel_table' => $table,
				'rel_type' => 'new',
				'user_id' => $this->id,
				'parent' => $parent
			)
		);
	}

	/**
	 *
	 *
	 * @param int $id = PM id
	 * @param string $table = PM table
	 * @access public
	 * @return void 
	 */
	function viewPm($id, $table) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_ppforum_userpms',
			'rel_id=' . strval($id) . ' AND rel_table=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'tx_ppforum_userpms') . ' AND rel_type=\'new\' AND user_id=' . strval($this->id)
		);
	}

	/**
	 *
	 *
	 * @param int $id = PM id
	 * @param string $table = PM table
	 * @access public
	 * @return void 
	 */
	function clearPmData($id, $table) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_ppforum_userpms',
			'rel_id=' . strval($id) . ' AND rel_table=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'tx_ppforum_userpms')
		);
	}

	/**
	 *
	 *
	 * @param int $id = PM id
	 * @param string $table = PM table
	 * @access public
	 * @return void 
	 */
	function deletePm($id, $table) {
		$GLOBALS['TYPO3_DB']->exec_INSERTquery(
			'tx_ppforum_userpms',
			array(
				'rel_id' => $id,
				'rel_table' => $table,
				'rel_type' => 'delete',
				'user_id' => $this->id
			)
		);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function unDeletePm($id,$table) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_ppforum_userpms',
			'rel_id=' . strval($id) . ' AND rel_table=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'tx_ppforum_userpms') . ' AND rel_type=\'delete\' AND user_id=' . strval($this->id)
		);
	}

	/**
	 *
	 *
	 * @param int $id = PM id
	 * @param string $table = PM table
	 * @access public
	 * @return bool 
	 */
	function pmIsVisible($id, $table) {
		$tabRes = $this->parent->db_query(
			'rel_id',
			'tx_ppforum_userpms',
			'rel_id=' . strval($id) . ' AND rel_table=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($table, 'tx_ppforum_userpms').' AND rel_type=\'delete\' AND user_id=' . strval($this->id)
		);

		return !(is_array($tabRes) && count($tabRes));
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function countNewPms($inTopic = 0, $clearCache = false) {
		if (!$clearCache && isset($this->cache['countNewPms'][$inTopic])) {
			$res = $this->cache['countNewPms'][$inTopic];
		} else {
			$addWhere=$inTopic?' AND rel_table=\'message\' AND parent='.strval($inTopic):'';
			$res = $this->parent->db_query(
				'count(*)',
				'tx_ppforum_userpms',
				'rel_type=\'new\' AND user_id='.strval($this->id).$addWhere
			);
			
			$res = intval(reset(reset($res)));
			$this->cache['countNewPms'][$inTopic] = $res;
		}

		return $res;
	}

	/**
	 * Returns the user's "title"
	 * 
	 * @param bool $hsc = if true, title will be passed throught htmlspecialchars
	 * @access public
	 * @return string 
	 */
	function getTitle($hsc = true) {
		return $hsc ? tx_pplib_div::htmlspecialchars($this->data['username']) : $this->data['username'];
	}

	/**
	 * Returns the user's title especially for the page's title
	 * 
	 * @access public
	 * @return string 
	 */
	function getPageTitle() {
		return sprintf(
			$this->parent->pp_getLL('user.profilePageTitle', null, false),
			$this->getTitle(false)
		);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayLight($noLink=FALSE) {
		if ($this->id) {
			$result=tx_pplib_div::htmlspecialchars($this->data['username']);

			if (!$noLink) {
				$result=$this->parent->pp_linkTP_pivars(
					$result,
					array('viewProfile'=>$this->id)
					);
			}

			return $result;
		} else {
			return $this->parent->pp_getLL('user.guestname','Guest',TRUE);
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displaySmallProfile() {
		if ($this->id) {
			$conf=array();
			$conf['cmd']='callObj';
			$conf['cmd.']['object']='user';
			$conf['cmd.']['uid']=$this->id;
			$conf['cmd.']['method']='_displaySmallProfile';

			return $this->parent->callINTpart($conf);
		} else {
			return '&nbsp;';
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function batch_updateMessageCounter() {
		/* Declare */
		$nbMessages = intval(reset(reset($this->parent->db_query(
			'count(uid) AS nb',
			$this->parent->tables['message'],
			'author='.intval($this->id)
			))))+intval(reset(reset($this->parent->db_query(
				'count(uid) AS nb',
				$this->parent->tables['topic'],
				'author='.intval($this->id)
			))));
	
		/* Begin */
		$this->setUserPreference('messageCounter', $nbMessages);
	}

	/**
	 *
	 *
	 * @access public
	 * @return void 
	 */
	function incrementMessageCounter() {
		/* Declare */
		$nbMessages = intval($this->getUserPreference('messageCounter'));
	
		/* Begin */
		$nbMessages++;

		$this->setUserPreference('messageCounter', $nbMessages);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function _displaySmallProfile($conf) {
		/* Declare */
		$rows = array();
		$nbMessages = intval($this->getUserPreference('messageCounter'));
		$image = $this->print_avatarImg();

		/* Begin */
		//t3lib_div::debug($this->data['is_online'], '');
		if ($image) {
			$rows[] = $image;
		}

		$rows[] = $this->parent->pp_getLL('user.mainUserGroup','Group: ').$this->getMainUserGroupLabel();

		$rows[] = $this->parent->pp_getLL('user.nbmessages').$nbMessages;

		$rows[] = $this->parent->pp_getLL('user.lastConnect') . ' ' . t3lib_BEfunc::calcAge(abs($GLOBALS['EXEC_TIME'] - $this->data['is_online']), $GLOBALS['TSFE']->sL('LLL:EXT:lang/locallang_core.php:labels.minutesHoursDaysYears'));

		$this->parent->pp_playHookObjList('user_printSmallProfile', $rows, $this);

		return implode('<br />',$rows);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function userCanEdit() {
		$res=FALSE;
		if ($this->id && ($this->id==$this->parent->currentUser->getId())) {
			$res=TRUE;
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
	function displayLogout() {
		if ($this->id) {
			return '<a class="logout-link" href="'.htmlspecialchars($this->parent->pp_linkTP(FALSE,array('logintype'=>'logout'))).'">'.$this->parent->pp_getLL('user.logout','Logout',TRUE).'</a>';
		} else {
			return '';
		}
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getEditLink($title=FALSE,$addParams=Array()) {
		if ($this->id) {
			$addParams['editProfile']=$this->id;
			if (isset($this->parent->piVars['backUrl'])) {
				$addParams['backUrl']=$this->parent->piVars['backUrl'];
			} else {
				$addParams['backUrl']=$this->parent->pp_linkTP_keepPIvars(FALSE);
			}

			return $this->parent->pp_linkTP_piVars(
				$title,
				$addParams
				);
		} else {
			return '';
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayEditLink() {
		if ($this->id) {
			return '<a class="edit-link" href="'.htmlspecialchars($this->getEditLink()).'">'.$this->parent->pp_getLL('user.edit','Edit profile',TRUE).'</a>';
		} else {
			return '';
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayInboxLink($altId = 0) {
		$obj = &$this->parent->getForumObj($altId ? -$altId : -$this->id);
		return $obj->getTitleLink();
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayUnreadMessagesLink() {
		if ($this->id) {
			return $this->parent->pp_linkTP_piVars(
				$this->parent->pp_getLL('user.latestMessages'),
				array('mode' => 'latest')
			);
		} else {
			return '';
		}
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayProfile($lConf) {
		/* Declare */
		$mode = isset($lConf['mode']) ? $lConf['mode'] : '';
		$content = '';
		$infoArray = Array(
			'mode' => $mode,
			'parts' => '',
			'currentPart' => isset($this->parent->piVars['editPPart']) ? $this->parent->piVars['editPPart'] : false,
		);
		$conf = Array(
			'infoArray' => &$infoArray,
			'data' => Array(
				'title'=>$this->getEditLink($this->parent->pp_getLL('profile.title','Profile : ',true).' '.tx_pplib_div::htmlspecialchars($this->data['username']))
			),
		);
		$modeParts = Array(
			'edit' => Array('message', 'avatar-opt', 'privacy'),
			'view' => Array('user-info'),
		);

		/* Begin */
		//*** Determine mode
		if (!in_array($infoArray['mode'], array('edit', 'view'))) {
			$infoArray['mode'] = 'view';
		} elseif ($infoArray['mode'] == 'edit' && !$this->userCanEdit()) {
			$infoArray['mode'] = 'view';
		}

		//*** Allowed parts
		$infoArray['parts'] = $modeParts[$infoArray['mode']];
		unset($modeParts);

		//*** Determine current part
		if (!$infoArray['currentPart'] || !in_array($infoArray['currentPart'], $infoArray['parts'])) {
			$infoArray['currentPart'] = reset($infoArray['parts']);
		}

		//*** Output start tag
		$content .= '<div class="user-profile">';
		if ($infoArray['mode'] == 'edit') {
			$content .= '<form action="'.htmlspecialchars($this->getEditLink(false,array('editPPart'=>$infoArray['currentPart']))).'" method="post" enctype="multipart/form-data">';
		}

		$conf['data']['subtitle'] = $this->displayProfile_subtitle($infoArray);
		$conf['data']['main'] = $this->displayProfile_main($infoArray);

		if ($infoArray['mode'] == 'edit') {
			$conf['data']['submit'] = $this->displayProfile_submit($infoArray);
		}

		//Playing hooks : Allows to manipulate parts (add, sort, etc)
		$this->parent->pp_playHookObjList('user_displayProfile', $conf, $this);

		foreach ($conf['data'] as $key=>$val) {
			if (trim($val)) {
				$content .= '<div class="row '.htmlspecialchars($key.'-row').'">'.$val.'</div>';
			}
		}

		if ($infoArray['mode'] == 'edit') {
			$content .= '</form>';
		}
		$content .= '</div>';
		return $content;
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayProfile_subtitle($infoArray) {
		/* Declare */
		$content = '';
	
		/* Begin */
		$data['left']['menu-title'] = $this->parent->pp_getLL('profile.subtitlerow.menu', 'Menu');
		$data['right']['edit'] = $this->parent->pp_getLL('profile.subtitlerow.edit', 'Edit options');
		
		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('user_displayProfile_subtitle', $data, $this);

		return $this->display_stdPart($data);
	}


	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayProfile_main($infoArray) {
		/* Declare */
		$content = '';
		$data = array(
			'infos' => $infoArray,
			'left'  => array(),
			'right' => array()
		);
	
		/* Begin */
		//*** Draw menu
		$data['left']['menu']='
			<ul>';

		foreach ($infoArray['parts'] as $part) {
			$addClass = strcmp($part, $infoArray['currentPart']) ? '' : ' class="active"';
			$data['left']['menu'] .= '
				<li'.$addClass.'>'.$this->getEditLink($this->parent->pp_getLL('profile.'.$part.'.title'), array('editPPart'=>$part)).'</li>';
		}

		$data['left']['menu'].='
			</ul>';

		$data['right']['edit'] = $this->displayProfile_main_part($infoArray);

		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('user_displayProfile_main', $data, $this);

		return $this->display_stdPart($data);
	}
	
	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayProfile_main_part($infoArray) {
		/* Declare */
		$content = '';
		//$toolkit = &tx_pplib_div::getFormToolkit();
		$toolkit = &$this->parent->pp_makeInstance('tx_pplib_formtoolkit'); // berk
		$incomingData = is_array($this->parent->piVars['profile']) ? $this->parent->piVars['profile'] : false;
		$profileData = $this->getUserPreference('profil');
		$parserList = Array();
		$conf = array(
			'mode' => $infoArray['mode'],
			'currentPart' => $infoArray['currentPart'],
			'parts' => $infoArray['parts'],
			'data' => array(),
		);

		/* Begin */
		//*** Init parser list
		$this->parent->loadParsers();
		foreach (array_keys($this->parent->parsers) as $key) {
			if (is_object($this->parent->parsers[$key])) {
				$parserList[$key] = $this->parent->parsers[$key]->parser_getTitle(true);
			} else {
				$parserList[$key] = $this->parent->parsers[$key];
			}

		}

		$toolkit->setConf(
			Array(
				'namePrefix'=>$this->parent->prefixId,
				'dataPrefix'=>'profile',
			)
		);

		//*** Check submited form data
		if ($incomingData) {
			$toolkit->setData($incomingData);
			$checks = Array(
				'pref_parser' => 'inlist|'.implode('|',array_keys($parserList)),
				'signature_parser' => 'inlist|'.implode('|',array_keys($parserList)),
				'signature' => 'none',
				'def_disableSimeys' => 'none',
				'show_email' => 'none',
				);

			// Will register only valid fields, due to the 'onlyValid' checking mode
			$toolkit->checkIncommingData($checks, array(),'onlyValid');
			$toolkit->fetchFieldsInArray($profileData);

			// Special processing for avatar upload
			$avatarUrl = $toolkit->getFieldVal('avatar_url');
			if (!is_null($avatarUrl)) {
				list($avatarImg, $reason) = $this->uploadAvatarImg('upload_ppforum_avatar', $avatarUrl);

				if ($toolkit->getFieldVal('avatar_drop')) {
					$profileData['avatar'] = '';
				} elseif ($avatarImg) {
					// Upload is ok
					$profileData['avatar'] = $avatarImg;
				} elseif(trim($reason)) {
					$toolkit->validErrors['avatar_url'] = $this->parent->pp_getLL('profile.avatar-opt.avatar.error.'.$reason, $reason);
				}
			}

			$this->setUserPreference('profil', $profileData);
		}
		$toolkit->setData($profileData);


		switch ($infoArray['currentPart']) {
		case 'message':
			$conf['data']['title'] = '<h3>'.$this->parent->pp_getLL('profile.message.title','Posting config').'</h3>';

			$conf['data']['pref_parser'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.pref_parser','Prefered markup language�: '),
				'right' => $toolkit->getSelect('pref_parser', $parserList),
			);

			$conf['data']['def_disableSimeys'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.def_disableSimeys','Disable smileys by default�: '),
				'right' => $toolkit->getCheckbox('def_disableSimeys'),
			);

			$conf['data']['signature_parser'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.signature_parser','Use this markup for my signature�:'),
				'right' => $toolkit->getSelect('signature_parser',$parserList),
			);

			$conf['data']['signature'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.signature','Signature�: '),
				'right' => $toolkit->getTextarea('signature'),
			);

			break;		
		case 'avatar-opt':
			$conf['data']['title'] = '<h3>'.$this->parent->pp_getLL('profile.avatar-opt.title','Avatar options').'</h3>';

			if ($image = $this->print_avatarImg()) {
				$conf['data']['cur_avatar'] = Array(
					'left' => $this->parent->pp_getLL('profile.avatar-opt.cur_avatar','Current avatar : '),
					'right' => $image,
				);
			}

			$conf['data']['avatar'] = Array(
				'left' => $this->parent->pp_getLL('profile.avatar-opt.avatar','Upload avatar : '),
				'right' => '<input type="file" name="upload_ppforum_avatar" accept="image/*" />',
			);

			$conf['data']['avatar_url'] = Array(
				'left' => $this->parent->pp_getLL('profile.avatar-opt.avatar_url','Get Avatar from this url : '),
				'right' => $toolkit->getInput('avatar_url'),
			);

			$conf['data']['avatar_drop'] = Array(
				'left' => $this->parent->pp_getLL('profile.avatar-opt.avatar_drop','Remove Avatar : '),
				'right' => $toolkit->getCheckbox('avatar_drop'),
			);


			break;
		case 'privacy':
			$conf['data']['title'] = '<h3>'.$this->parent->pp_getLL('profile.privacy.title').'</h3>';

			$conf['data']['show_email'] = Array(
				'left' => $this->parent->pp_getLL('profile.privacy.show_email','Show email (spam protected) ?'),
				'right' => $toolkit->getCheckbox('show_email'),
			);

			break;
		case 'user-info':
			$conf['data']['title'] = '<h3>'.$this->parent->pp_getLL('profile.user-info.title','User information').'</h3>';

			$infoContent = Array(
				$this->parent->pp_getLL('profile.user-info.nickname','Nickname : ') . tx_pplib_div::htmlspecialchars($this->data['username']),
			);

			if ($this->data['name']) {
				$infoContent[] = $this->parent->pp_getLL('profile.user-info.name','Name : ') . tx_pplib_div::htmlspecialchars($this->data['name']);
			}

			if ($profileData['show_email']) {
				$infoContent[] = $this->parent->pp_linkTP($this->parent->pp_getLL('profile.user-info.email','Email'), array(), true, $this->data['email']);
			}

			if ($this->data['is_online']) {
				$infoContent[] = $this->parent->pp_getLL('profile.user-info.last-login', 'Last login : ') . $this->parent->renderDate($this->data['is_online']);
			}

			$image = $this->print_avatarImg();
			if ($image) {
				$conf['data']['informations'] = Array(
					'left' => $image,
					'right' => implode('<br />', $infoContent),
				);
			} else {
				$conf['data']['informations'] = implode('<br />', $infoContent);
			}



			//$conf['data']['div'] = t3lib_div::view_array($this->data);

			$obj = &$this->parent->getForumObj(-$this->id);
			$conf['data']['pm-link'] = $obj->getLink(
				$this->parent->pp_getLL('profile.user-info.pm-link','Send him a private message')
			);


			break;
		}

		foreach ($conf['data'] as $rowClass=>$row) {
			$classArr=Array('row');
			if (!t3lib_div::testInt($rowClass)) $classArr[] = $rowClass;
			$content.='<div class="'.htmlspecialchars(implode(' ',$classArr)).'">';
			if (is_array($row)) {
				foreach (array('left','right') as $part) {
					$content.='
					<div class="col '.$part.'-col">';

					if (trim($row[$part])) {
						$content .= $row[$part];
					} else {
						//Empty div : used to fix IE height bug
						$content.='&nbsp;';
					}
					$content.='
					</div>';
				}
			} else {
				$content .= $row;
			}
			$content .= '</div>';
		}

		return '<div class="profile-part-'.htmlspecialchars($infoArray['currentPart']).'">'.$content.'</div>';
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function displayProfile_submit() {
		/* Declare */
		$content='';
		$data=array('left'=>array(),'right'=>array());
	
		/* Begin */
		if (isset($this->parent->piVars['backUrl']) && trim($this->parent->piVars['backUrl'])) {
			$data['left']['backUrl']='<a href="'.htmlspecialchars($this->parent->piVars['backUrl']).'">'.$this->parent->pp_getLL('profile.back','Return to forum').'</a>';
		} else {
			$data['left']['backUrl']='';
		}
		$data['right']['submit']='<input type="submit" value="'.$this->parent->pp_getLL('profile.submit','Save',TRUE).'">';
		
		//Playing hooks : Allows to manipulate subparts (add, sort, etc)
		$this->parent->pp_playHookObjList('user_displayProfile_submit', $data, $this);

		return $this->display_stdPart($data);
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function display_stdPart($data) {
		return tx_ppforum_message::display_stdPart($data);
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function display_messageOptions($mode) {
		/* Declare */
	
		/* Begin */
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function print_avatarImg($forceImg=FALSE) {
		/* Declare */
		$profileData = $this->getUserPreference('profil');
		$avatarImg = $forceImg ? $forceImg : $profileData['avatar'];
		$image='';
		$attribs=array();
		$maxH=$this->parent->config['avatar']['maxHeight'];
		$maxW=$this->parent->config['avatar']['maxWidth'];
	
		/* Begin */
		if (!is_null($avatarImg) && trim($avatarImg)) {
			$dotPos=strpos(' '.$avatarImg,'.');
			$slashPos=strpos(' '.$avatarImg,'.');
			$pu=@parse_url($avatarImg);

			if (isset($pu['scheme']) || ($dotPos && $slashPos && $dotPos<$slashPos)) {
				//This is an external URL
				$fileInfos=t3lib_div::split_fileref($avatarImg);

				if (t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],$fileInfos['realFileext'])) {
					$image='<img src="'.htmlspecialchars($avatarImg).'" alt="Avatar" class="avatar-image" width="'.$maxW.'" height="'.$maxH.'" />';
				}
			} else {
				$image=$this->parent->cObj->IMAGE(
					Array(
						'file'=>$avatarImg,
						'file.'=>Array(
							'maxH'=>$maxH,
							'maxW'=>$maxW,
							),
						'altText'=>'Avatar',
						'params'=>'class="avatar-image"',
						)
					);

				//Checking image attribute (because size will be wrong if ImageMagik is not available)
				$attribs=t3lib_div::get_tag_attributes($image);

				$Hratio=$maxH/intval($attribs['height']);
				$Wratio=$maxW/intval($attribs['width']);

				if (($Hratio<1) && ($Hratio<$Wratio)) {
					$image=str_replace(' height="'.$attribs['height'].'"',' height="'.$maxH.'"',$image);
					$image=str_replace(' width="'.$attribs['width'].'"','',$image);
				} elseif (($Wratio<1) && ($Wratio<$Hratio)) {
					$image=str_replace(' width="'.$attribs['width'].'"',' width="'.$maxW.'"',$image);
					$image=str_replace(' height="'.$attribs['height'].'"','',$image);
				}				
			}
		}

		return $image;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function uploadAvatarImg($fieldName='upload_ppforum_avatar',$url='') {
		/* D�clarations */
		$fileUploaded=FALSE;
		$reason='';
		$fileSent=(isset($_FILES[$fieldName]['name']) && trim($_FILES[$fieldName]['name']));
		$fileName=FALSE; //Will be the final result
		$maxFileSize = (isset($GLOBALS['TYPO3_CONF_VARS']['FE']['maxFileSize'])?intval($GLOBALS['TYPO3_CONF_VARS']['FE']['maxFileSize']):intval($GLOBALS['TYPO3_CONF_VARS']['BE']['maxFileSize']))*1024;
		$resizeBeforeSave=$this->parent->config['avatar']['resizeImg'];
		$allowedExt=strtolower(trim($this->parent->config['avatar']['allowedExt'])?$this->parent->config['avatar']['allowedExt']:$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']);

		//By forcing the filename, we are sure that malicious users can't fill the server disk space (also each upload will erase the previous upload)
		$basename = 'uploads/tx_ppforum/'.ereg_replace('[^.[:alnum:]_-]','_',trim($this->data['username']));
	
		/* D�but */
		if ($fileSent) {
			$theFile = $_FILES[$fieldName]['tmp_name'];// filename of the uploaded file
			$theFileSize = intval($_FILES[$fieldName]['size']);// filesize of the uploaded file
			$fileInfos = t3lib_div::split_fileref(stripslashes($_FILES[$fieldName]['name']));

			$fileName=$basename.'.'.$fileInfos['realFileext'];

			//** Checking file extension
			if (t3lib_div::inList($allowedExt,strtolower($fileInfos['realFileext']))) {
				if ($maxFileSize>$theFileSize) {
					$res=t3lib_div::upload_copy_move($theFile,PATH_site.$fileName);

					if ($res) {
						$fileUploaded=TRUE;
					} else {
						$reason='unknown';
					}
				} else {
					$reason='fileTooBig';
				}
			} else {
				$reason='forbiddenExt';
			}

			if (!$fileUploaded) {
				$fileName = FALSE;
			}
		}

		if (!$fileUploaded && trim($url)) {
			$fileInfos = t3lib_div::split_fileref($url);
			$fileName=$basename.'.'.$fileInfos['realFileext'];
			//** Checking file extension
			if (t3lib_div::inList($allowedExt,strtolower($fileInfos['realFileext']))) {
				//** Downloading file
				$fileContent=t3lib_div::getUrl($url);
				$theFileSize = $fileContent?strlen($fileContent):0;

				//** Checking file size
				if ($theFileSize && ($maxFileSize>$theFileSize)) {
					//** Saving file
					$res=t3lib_div::writeFile(PATH_site.$fileName,$fileContent);

					unset($fileContent); //We don't need it anymore :)

					if ($res) {
						$fileUploaded=TRUE;
					} else {
						$reason='unknown';
					}
				} else {
					$reason='fileTooBig';
				}

			} else {
				$reason='forbiddenExt';
			}

			if (!$fileUploaded) {
				$fileName=FALSE;
			}
	
		}

		if ($fileUploaded && $resizeBeforeSave) {
			$resizedImg=$this->print_avatarImg($fileName);

			$imgParams=t3lib_div::get_tag_attributes($resizedImg);

			if (substr($imgParams['src'],0,10)=='typo3temp/') {
				//Image has been resized
				t3lib_div::upload_copy_move(PATH_site.$imgParams['src'],PATH_site.$fileName);

				@unlink(PATH_site.$imgParams['src']);


				clearstatcache();

				foreach ($GLOBALS['TSFE']->tmpl->fileCache as $key=>$val) {
					if (is_array($val)) {
						if ($val['origFile']==$fileName) {
							unset($GLOBALS['TSFE']->tmpl->fileCache[$key]);
						}
					} elseif ($val==$fileName) {
						unset($GLOBALS['TSFE']->tmpl->fileCache[$key]);
					}
				}
			}

		}

		return array($fileName, $reason);
	}
}

tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_user.php');
?>