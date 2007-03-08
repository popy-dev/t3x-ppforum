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
 * Class 'tx_ppforum_user' for the 'pp_forum' extension.
 *
 * @author Popy <popy.dev@gmail.com>
 * @package TYPO3
 * @subpackage tx_ppforum
 */
class tx_ppforum_user {
	var $id=0;
	var $data=array();
	var $ucSave=FALSE;

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function load($id,$clearCache=FALSE) {
		if ($this->data=$this->parent->getSingleUser($id,$clearCache)) {
			$this->id=intval($id);
			$this->uc=unserialize($this->data['uc']);
			if (!is_array($this->uc)) {
				$this->uc=Array();
			}
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
		$this->parent->st_playHooks(
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_user']['save'],
			$null,
			$this
			);
		
		//Updating tstamp field
		$this->data['tstamp']=$GLOBALS['SIM_EXEC_TIME'];

		//
		if ($this->ucSave) {
			$this->data['uc']=serialize($this->uc);
		}
		$this->ucSave=FALSE;

		if ($this->id) {
			//*** Optimistic update :
			//Loading old data
			$oldData=$this->parent->getSingleUser($this->id);

			//Updating db row
			$result=$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'fe_users',
				'uid='.$this->id,
				array_diff_assoc(
					$this->data,
					$oldData
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
				$this->ucSave=TRUE;
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
	function displayLight($noLink=FALSE) {
		if ($this->id) {
			$result=$this->parent->htmlspecialchars($this->data['username']);

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
	function _displaySmallProfile() {
		$rows=array();
		$nbMessages=intval(reset(reset(tx_pplib::doCachedQuery(
			array(
				'select'=>'count(uid) AS nb',
				'from'=>$this->parent->tables['messages'],
				'where'=>'author='.intval($this->id)
				)
			))))+intval(reset(reset(tx_pplib::doCachedQuery(
			array(
				'select'=>'count(uid) AS nb',
				'from'=>$this->parent->tables['topics'],
				'where'=>'author='.intval($this->id)
				)
			))));

		$rows[]=$this->parent->pp_getLL('user.nbmessages','Messages:',TRUE).$nbMessages;
		$profileData=$this->getUserPreference('profil');

		if ($image=$this->print_avatarImg()) {
			$rows[]=$image;
		}

		$this->parent->st_playHooks($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['pp_forum']['tx_ppforum_user']['_printSmallProfile'],$rows,$this);

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
	function displayProfile($mode='') {
		/* Declare */
		$data=Array();
		$toolkit=NULL;
		$profileData=$this->getUserPreference('profil');
	
		/* Begin */
		if (!$this->id) {
			return '';
		}
		if (($mode!='edit') || !$this->userCanEdit()) {
			$mode='view';
		}

		//t3lib_div::debug($this->parent->config, 'plop');
		$content.='<div class="user-profile">';
		if ($mode=='edit') {
			$content.='<form action="'.htmlspecialchars($this->parent->pp_linkTP_thisPiVars('',Array('editProfile','backUrl'),array('editProfile'=>$this->id))).'" method="post" enctype="multipart/form-data">';
		}

		$toolkit=&$this->parent->st_getFormToolkit($mode=='view');
		$toolkit->setConf(
			Array(
				'namePrefix'=>$this->parent->prefixId,
				'dataPrefix'=>'profile',
				)
			);

		$tempVar=Array();
		$this->parent->loadParsers();
		foreach (array_keys($this->parent->parsers) as $key) {
			$tempVar[$key]=$this->parent->parsers[$key]['label'];
		}

		if ($mode=='edit' && isset($this->parent->piVars['profile']) && is_array($this->parent->piVars['profile'])) {
			$toolkit->setData($this->parent->piVars['profile']);
			$checks=Array(
				'pref_parser'=>'inlist|'.implode('|',array_keys($tempVar)),
				'signature_parser'=>'inlist|'.implode('|',array_keys($tempVar)),
				'signature'=>'none',
				'def_disableSimeys'=>'none',
				);
			$toolkit->checkIncommingData($checks,'onlyValid');

			//Will register only valid fields, due to the 'onlyValid' checking mode
			$toolkit->fetchFieldsInArray($profileData);

			$avatarUrl=$toolkit->getFieldVal('avatar_url');


			//*** Avatar upload
			list($avatarImg,$reason)=$this->uploadAvatarImg('upload_ppforum_avatar',$avatarUrl);
			if ($avatarImg) {
				$profileData['avatar']=$avatarImg;
			} elseif ($toolkit->getFieldVal('avatar_drop')) {
				$profileData['avatar']='';
			} elseif(trim($reason)) {
				$toolkit->validErrors['avatar_url']=$this->parent->pp_getLL('profile.avatar.error.'.$reason,$reason);
			}

			$this->setUserPreference('profil',$profileData);
			$this->save();
		} else {
			$toolkit->setData($profileData);
		}

		$data['header']=$this->parent->pp_getLL('profile.title','Profile : ',TRUE).' '.$this->parent->htmlspecialchars($this->data['username']);

		if ($mode=='edit') {
			$data['options']=Array();
			
			$data['options']['pref_parser']=Array();
			$data['options']['pref_parser']['left']=$this->parent->pp_getLL('profile.options.pref_parser','Prefered markup language : ',TRUE);
			$data['options']['pref_parser']['right']=$toolkit->getSelect('pref_parser',$tempVar);

			$data['options']['def_disableSimeys']=Array();
			$data['options']['def_disableSimeys']['left']=$this->parent->pp_getLL('profile.options.def_disableSimeys','Disable smileys by default : ',TRUE);
			$data['options']['def_disableSimeys']['right']=$toolkit->getCheckbox('def_disableSimeys');

			$data['options']['signature_parser']=Array();
			$data['options']['signature_parser']['left']=$this->parent->pp_getLL('profile.options.signature_parser','Use this markup for my signature :',TRUE);
			$data['options']['signature_parser']['right']=$toolkit->getSelect('signature_parser',$tempVar);

			$data['options']['signature']=Array();
			$data['options']['signature']['left']=$this->parent->pp_getLL('profile.options.signature','Signature : ',TRUE);
			$data['options']['signature']['right']=$toolkit->getTextarea('signature');

			if ($image=$this->print_avatarImg()) {
				$data['options']['cur_avatar']=Array();
				$data['options']['cur_avatar']['left']=$this->parent->pp_getLL('profile.options.cur_avatar','Current avatar : ');
				$data['options']['cur_avatar']['right']=$image;
			}

			$data['options']['avatar']=Array();
			$data['options']['avatar']['left']=$this->parent->pp_getLL('profile.options.avatar','Upload avatar : ',TRUE);
			$data['options']['avatar']['right']='<input type="file" name="upload_ppforum_avatar" accept="image/*" />';

			$data['options']['avatar_url']=Array();
			$data['options']['avatar_url']['left']=$this->parent->pp_getLL('profile.options.avatar_url','Get Avatar from this url : ',TRUE);
			$data['options']['avatar_url']['right']=$toolkit->getInput('avatar_url');

			$data['options']['avatar_drop']=Array();
			$data['options']['avatar_drop']['left']=$this->parent->pp_getLL('profile.options.avatar_drop','Remove Avatar : ',TRUE);
			$data['options']['avatar_drop']['right']=$toolkit->getCheckbox('avatar_drop');

			$data['misc']=Array();

			$data['misc']['submit']=Array();
			$data['misc']['submit']['left']='&nbsp;';
			$data['misc']['submit']['right']='<input type="submit" value="'.$this->parent->pp_getLL('profile.options.submit','Save',TRUE).'">';
			if (isset($this->parent->piVars['backUrl']) && trim($this->parent->piVars['backUrl'])) {
				$data['misc']['submit']['right'].=' <a href="'.htmlspecialchars($this->parent->piVars['backUrl']).'">'.$this->parent->pp_getLL('profile.options.back','Return to forum').'</a>';
			}
		} else {
			$obj=$this->parent->getForumObj(-($this->id));
			$data['misc']=$obj->getLink('Test');
		}


		foreach ($data as $partClass=>$part) {
			$classArr=Array('part');
			if (!t3lib_div::testInt($partClass)) $classArr[]=$partClass;
			$content.='<div class="'.htmlspecialchars(implode(' ',$classArr)).'">';

			if (is_array($part)) {
				foreach ($part as $rowClass=>$row) {
					$classArr=Array('row');
					if (!t3lib_div::testInt($rowClass)) $classArr[]=$rowClass;
					$content.='<div class="'.htmlspecialchars(implode(' ',$classArr)).'">';
					if (is_array($row)) {
						foreach (array('left','right') as $part) {
							$content.='
							<div class="col '.$part.'-col">';

							if (trim($row[$part])) {
								$content.=$row[$part];
							} else {
								//Empty div : used to fix IE height bug
								$content.='&nbsp;';
							}
							$content.='
							</div>';
						}
					} else {
						$content.=$row;
					}
					$content.='</div>';
				}
			} else {
				$content.=$part;
			}

			$content.='</div>';
		}

		if ($mode=='edit') {
			$content.='</form>';
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
	function displayEditLink() {
		if ($this->id) {
			$backUrl=$this->parent->pp_linkTP_keepPIvars(FALSE);
			return '<a class="edit-link" href="'.htmlspecialchars($this->parent->pp_linkTP_piVars(FALSE,array('editProfile'=>$this->id,'backUrl'=>$backUrl))).'">'.$this->parent->pp_getLL('user.edit','Edit profile',TRUE).'</a>';
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
	function displayInboxLink($altId=0) {
		$obj=$this->parent->getForumObj($altId?-$altId:-$this->id);
		return $obj->getTitleLink();
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
				$fileName=FALSE;
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

		return array($fileName,$reason);

	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_user.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_user.php']);
}

?>