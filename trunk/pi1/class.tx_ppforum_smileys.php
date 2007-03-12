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
 * Class 'tx_ppforum_smileys' for the 'pp_forum' extension.
 *
 * @author Popy <popy.dev@gmail.com>
 * @package TYPO3
 * @subpackage tx_ppforum
 */
class tx_ppforum_smileys {
	var $disable=FALSE;
	var $basedir='EXT:pp_forum/res/smilets/';
	var $ressources=Array(
    '(ff)'=>'aime-firefox-pas-ie.gif',
    //''=>'angry.gif',
    ':D'=>'biggrin.gif',
    '(Oo)'=>'blink.gif',
    ':$'=>'blushing.gif',
    'B)'=>'cool.gif',
    ':*('=>'crying.gif',
    ':S'=>'dry.gif',
    //''=>'ermm.gif',
    //''=>'fear.gif',
    '^^'=>'happy.gif',
    //''=>'huh.gif',
    '(lol)'=>'laugh.gif',
    //''=>'pinch.gif',
    ':)'=>'rolleyes.gif',
    //''=>'sleep.gif',
    ':p'=>'tongue.gif',
    //''=>'wacko.gif',
    //''=>'whistling.gif',
    ';)'=>'wink.gif'
	);

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function tx_ppforum_smileys() {
		$this->basedir=substr(t3lib_div::getFileAbsFileName($this->basedir),strlen(PATH_site));

		foreach ($this->ressources as $key=>$val) {
			$this->ressources[$key]='<img src="'.htmlspecialchars($this->basedir.$val).'" alt="'.htmlspecialchars($key).'" title="'.htmlspecialchars($key).'" />';
		}
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function processMessage($text) {
		if ($this->disable) return $text;
		/* Declare */
		$pointer=0;
		$this->_text=$text;
	
		/* Begin */
		while ($temp=$this->mstrpos($pointer)) {
			list($pointer,$current)=$temp;
			$this->_text=substr_replace($this->_text,$this->ressources[$current],$pointer,strlen($current));
			$pointer+=strlen($this->ressources[$current])-strlen($current);
		}

		return $this->_text;
		return str_replace(
			array_keys($this->ressources),
			array_values($this->ressources),
			$text
			);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function mstrpos($pointer=0) {
		/* Declare */
		$tmpPos=array();
		$temp=0;
		foreach (array_keys($this->ressources) as $key) {
			$temp=$pointer+1;
			$temp=strpos(' '.$this->_text,$key,$temp);

			if ($temp && substr($key,0,1)==';') { //Special cond : htmlspecialchars can produce a ; !
				$ok=FALSE;
				while (!$ok) {
					$i=$temp-2;
					while ($i>=0 && !in_array($this->_text[$i],array('&',';'))) $i--;
					if ($i<0 || $this->_text[$i]==';') {
						$ok=TRUE; //this is a real smiley
					} else {
						//COming frm htmspecialchars, check again and loop
						$temp=strpos(' '.$this->_text,$key,$temp+1);
					}
				}
			}

			$tmpPos[$key]=$temp;
		}

		$tmpPos=array_filter($tmpPos,'intval');
		if (count($tmpPos)) {
			natsort($tmpPos);
			return array(reset($tmpPos)-1,key($tmpPos));
		} else {
			return FALSE;
		}
	}


	/**
	 * Prints the list of available smilets and allow to add it into message textarea by clicking them
	 *
	 * @param string $datakey = used to find the right name of the textarea
	 * @access public
	 * @return string 
	 */
	function displaySmileysTools($datakey) {
		/* Declare */
		$list=Array();
	
		/* Begin */
		foreach ($this->ressources as $key=>$val) {
			$list[]='<a href="#" onclick="return ppforum_wrapSelected(\''.htmlspecialchars(addslashes($key)).'\',\'\',this,\''.htmlspecialchars(addslashes($datakey)).'\');">'.$val.'</a>';
		}

		return '<div class="smileys-buttons">'.implode(' ',$list).'</div>';
	}


}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_smileys.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_smileys.php']);
}

?>