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
require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_message.php');


/**
 * Class 'tx_ppforum_user' for the 'pp_forum' extension.
 *
 * @author Popy <popy.dev@gmail.com>
 * @package TYPO3
 * @subpackage tx_ppforum
 */
class tx_ppforum_user extends tx_ppforum_base {
	var $ucSave = FALSE;

	/**
	 * Store user data as it was at load time
	 * @access public
	 * @var array
	 */
	var $oldData = array();

	/**
	 *
	 *
	 * @param 
	 * @param boolean $clearCache = if TRUE, cached data will be overrided
	 * @access public
	 * @return void 
	 */
	function load($id, $clearCache = false) {
		if (parent::load($id, $clearCache)) {
			$this->id=intval($id);
			$this->uc=unserialize($this->data['uc']);
			if (!is_array($this->uc)) {
				$this->uc=Array();
			}

			$this->oldData = $this->data;
		}
		return $this->id;
	}

	/**
	 * Saves the user to the DB
	 *
	 * @access public
	 * @return int/boolean = the message uid or false when an error occurs 
	 */
	function save() {
		/* Declare */
		$null=NULL;
		$result=FALSE;

		/* Begin */
		//Plays hook list : Allow to change some field before saving
		$this->parent->pp_playHookObjList('user_save', $null, $this);

		//Updating tstamp field
		$this->data['tstamp']=$GLOBALS['SIM_EXEC_TIME'];


		if ($this->id) {
			//*** Optimistic update :
			//*** Updating uc
			if ($this->ucSave) {
				$this->data['uc']=serialize($this->uc);
			}
			$this->ucSave=FALSE;

			//Updating db row
			$result=$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'fe_users',
				'uid='.$this->id,
				array_diff_assoc(
					$this->data,
					$this->oldData
					)
				);

			$this->parent->log('UPDATE');
		} else {
			//Nothing
		}

		return $result?$this->id:FALSE;
	}

	/**
	 * 
	 * 
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getMainUserGroupLabel($hsc = true) {
		$groupUid = reset(t3lib_div::intExplode(',', $this->data['usergroup']));
		if (!isset($GLOBALS['T3_VAR']['CACHE']['pp_forum']['fegroups'][$groupUid])) {
			$GLOBALS['T3_VAR']['CACHE']['pp_forum']['fegroups'][$groupUid] = $this->parent->pp_getRecord($groupUid, 'fe_groups');
		}
		
		if ($hsc) {
			return tx_pplib_div::htmlspecialchars($GLOBALS['T3_VAR']['CACHE']['pp_forum']['fegroups'][$groupUid]['title']);
		} else {
			return $GLOBALS['T3_VAR']['CACHE']['pp_forum']['fegroups'][$groupUid]['title'];
		}

	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getUserPreference($prefkey) {
		list($firstKey,$path)=explode('|',$prefkey,2);
		if (isset($this->uc['ppforum/userprefs/'.$firstKey])) {
			$val=$this->uc['ppforum/userprefs/'.$firstKey];

			//Get the value form the array. Path is used as a list of keys separated by the | char
			if (trim($path)) {
				foreach (explode('|',$path) as $key) {
					if (is_array($val) && isset($val[$key])) {
						$val=$val[$key];
					} else {
						$val=NULL;
						break;
					}
				}
			}
			return $val;
		} else {
			return NULL;
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function setUserPreference($prefkey,$data) {
		if ($this->id) {
			$this->uc['ppforum/userprefs/'.$prefkey]=$data;
			if (!$this->ucSave) {
				$this->parent->registerCloseFunction('save',$this);
				$this->ucSave = TRUE;
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
	function registerNewPm($id,$table,$parent=0) {
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_ppforum_userpms',array('rel_id'=>$id,'rel_table'=>$table,'rel_type'=>'new','user_id'=>$this->id,'parent'=>$parent));
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function viewPm($id,$table) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_ppforum_userpms',
			'rel_id='.strval(intval($id)).' AND rel_table='.$GLOBALS['TYPO3_DB']->fullQuoteStr($table,'tx_ppforum_userpms').' AND rel_type=\'new\' AND user_id='.strval($this->id)
			);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function clearPmData($id,$table) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tx_ppforum_userpms',
			'rel_id='.strval(intval($id)).' AND rel_table='.$GLOBALS['TYPO3_DB']->fullQuoteStr($table,'tx_ppforum_userpms')
			);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function deletePm($id,$table) {
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_ppforum_userpms',array('rel_id'=>$id,'rel_table'=>$table,'rel_type'=>'delete','user_id'=>$this->id));
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
			'rel_id='.strval($id).' AND rel_table='.$GLOBALS['TYPO3_DB']->fullQuoteStr($table,'tx_ppforum_userpms').' AND rel_type=\'delete\' AND user_id='.strval($this->id)
			);
	}
	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function pmIsVisible($id,$table) {
		$tabRes=$GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'rel_id',
			'tx_ppforum_userpms',
			'rel_id='.strval($id).' AND rel_table='.$GLOBALS['TYPO3_DB']->fullQuoteStr($table,'tx_ppforum_userpms').' AND rel_type=\'delete\' AND user_id='.strval($this->id)
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
	function countNewPms($inTopic=0) {
		$addWhere=$inTopic?' AND rel_table=\'message\' AND parent='.strval($inTopic):'';
		$tabRes=$GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'count(*)',
			'tx_ppforum_userpms',
			'rel_type=\'new\' AND user_id='.strval($this->id).$addWhere
			);

		if (is_array($tabRes) && count($tabRes)) {
			return intval(reset(reset($tabRes)));
		} else {
			return 0;
		}
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
	function _displaySmallProfile($conf) {
		$rows=array();
		//*** @TODO : should rewrite this ?
		$nbMessages=intval(reset(reset($this->parent->doCachedQuery(
			array(
				'select'=>'count(uid) AS nb',
				'from'=>$this->parent->tables['message'],
				'where'=>'author='.intval($this->id)
				)
			))))+intval(reset(reset($this->parent->doCachedQuery(
			array(
				'select'=>'count(uid) AS nb',
				'from'=>$this->parent->tables['topic'],
				'where'=>'author='.intval($this->id)
				)
			))));

		$rows[] = $this->parent->pp_getLL('user.nbmessages').$nbMessages;

		$rows[] = $this->parent->pp_getLL('user.mainUserGroup','Group: ').$this->getMainUserGroupLabel();

		if ($image = $this->print_avatarImg()) {
			$rows[] = $image;
		}

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
		if ($this->id && ($this->id==$this->parent->currentUser->id)) {
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
		$toolkit = &tx_pplib_div::getFormToolkit();
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
				'left' => $this->parent->pp_getLL('profile.message.pref_parser','Prefered markup language : '),
				'right' => $toolkit->getSelect('pref_parser', $parserList),
			);

			$conf['data']['def_disableSimeys'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.def_disableSimeys','Disable smileys by default : '),
				'right' => $toolkit->getCheckbox('def_disableSimeys'),
			);

			$conf['data']['signature_parser'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.signature_parser','Use this markup for my signature :'),
				'right' => $toolkit->getSelect('signature_parser',$parserList),
			);

			$conf['data']['signature'] = Array(
				'left' => $this->parent->pp_getLL('profile.message.signature','Signature : '),
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
		$avatarImg=$forceImg?$forceImg:$this->getUserPreference('profil|avatar');
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
		/* Déclarations */
		$fileUploaded=FALSE;
		$reason='';
		$fileSent=(isset($_FILES[$fieldName]['name']) && trim($_FILES[$fieldName]['name']));
		$fileName=FALSE; //Will be the final result
		$maxFileSize = (isset($GLOBALS['TYPO3_CONF_VARS']['FE']['maxFileSize'])?intval($GLOBALS['TYPO3_CONF_VARS']['FE']['maxFileSize']):intval($GLOBALS['TYPO3_CONF_VARS']['BE']['maxFileSize']))*1024;
		$resizeBeforeSave=$this->parent->config['avatar']['resizeImg'];
		$allowedExt=strtolower(trim($this->parent->config['avatar']['allowedExt'])?$this->parent->config['avatar']['allowedExt']:$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']);

		//By forcing the filename, we are sure that malicious users can't fill the server disk space (also each upload will erase the previous upload)
		$basename = 'uploads/tx_ppforum/'.ereg_replace('[^.[:alnum:]_-]','_',trim($this->data['username']));
	
		/* Début */
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



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_user.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_user.php']);
}

?>