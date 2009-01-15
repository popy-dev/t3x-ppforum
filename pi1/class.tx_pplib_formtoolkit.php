<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Popy (popy.dev@gmail.com)
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
 * 'pp_lib' extension.
 *
 * @author	Popy <popy.dev@gmail.com>
 */


class tx_pplib_formtoolkit {
	/**
	 * namePrefix
	 * dataPrefix
	 * hashKeyAutoCheck
	 * hashKeyName
	 */
	var $conf=array();
	var $data=array();
	var $registeredFields=array();
	var $validErrors=array();


	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function setData($data) {
		$this->data=$data;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function setConf($conf) {
		$this->conf=$conf;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function setConfKey($key,$val) {
		$this->conf[$key]=$val;
	}

	/**
	 *
	 * @param array $checks  ['fieldname' => 'rules', ...]
	 * @param array $altErrorMessages ['fieldname' => 'Error Message', ...]
	 * @param string $mode validation mode ('' is default, 'onlyValid' will register only valid fields)
	 * @access public
	 * @return void 
	 */
	function checkIncommingData($checks=array(), $altErrorMessages=array(), $mode='') {
		/* Declare */
		$result=TRUE;
	
		/* Begin */
		if ($this->conf['hashKeyAutoCheck']) {
			$result=$this->checkHashKey();
		}
		foreach ($checks as $key => $val) {
			$altErrorMessage = isset($altErrorMessages[$key]) ? $altErrorMessages[$key] : null;
			$res = $this->checkField($key,$val, $altErrorMessage);

			//Mode onlyValid : register only validated fields
			if ($mode == 'onlyValid') {
				if (!is_null($this->getFieldVal($key)) && $res) {
					$this->registerField($key);
				}
			} else {
				$this->registerField($key);
			}

			$result = $result && $res;
		}

		return $result;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function registerField($key) {
		$this->registeredFields[] = reset(explode('|',$key));
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function fetchFieldsInArray(&$target) {
		foreach ($this->registeredFields as $name) $target[$name]=$this->getFieldVal($name);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function fetchFieldsInObject(&$target) {
		foreach ($this->registeredFields as $name) $target->$name=$this->getFieldVal($name);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getLL($key,$default) {
		$ret=$GLOBALS['TSFE']->sL('LLL:EXT:pp_lib/locallang_db.php:'.$key);
		if (!$ret) {
			$ret=$default;
		}
		return tx_pplib_div::htmlspecialchars($ret);
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function checkField($name,$checks='trim', $altErrorMessage=null) {
		/* Declare */
		$res=TRUE;
		$value=$this->getFieldVal($name);
		$stack=explode(',',$checks);
		$i=0;
	
		/* Begin */
		while (($i<count($stack)) && $res) {
			$val=$stack[$i++];
			list($val,$params)=explode('|',$val,2);
			switch ($val){
			case 'trim':
				list($min,$max)=array_map('intval',explode('-',$params));
				if (!trim($value)) {
					$this->validErrors[$name]=$this->getLL('error.empty','You must enter a value.');
					$res=FALSE;
				} elseif ($min && (strlen($value)<$min)) {
					$this->validErrors[$name]=str_replace('///MIN///',$min,$this->getLL('error.trim.min','You must enter at least ///MIN/// chars'));
					$res=FALSE;	
				} elseif ($max && (strlen($value)>$max)) {
					$this->validErrors[$name]=str_replace('///MAX///',$max,$this->getLL('error.trim.max','You can\'t enter more than ///MAX/// chars'));
					$res=FALSE;	
				}
				break;
			case 'password':
				list($mode,$param)=explode(':',$params,2);
				switch ($mode){
				case 'md5': 
					if (strcmp(md5($value),$param)) {
						$this->validErrors[$name]=$this->getLL('error.pass.wrong','Wrong password.');
						$res=FALSE;
					}
					break;
				case 'confirm': 
					if (strcmp($value,$this->getFieldVal($param))) {
						$this->validErrors[$name]=$this->getLL('error.pass.confirm','You failed to confirm the password !');
						$res=FALSE;
					}
					break;
				}
				break;
			case 'integer':
				list($min,$max)=array_map('intval',explode('-',$params));
				if (t3lib_div::testInt($value)) {
					$this->validErrors[$name]=$this->getLL('error.integer.wrong','You must enter an integer');
					$res=FALSE;
				} elseif ($min && (intval($value)<$min)) {
					$this->validErrors[$name]=str_replace('///MIN///',$min,$this->getLL('error.integer.min','You must enter a value greater than ///MIN///'));
					$res=FALSE;	
				} elseif ($max && intval($value)>$max) {
					$this->validErrors[$name]=str_replace('///MAX///',$max,$this->getLL('error.integer.max','You must enter a value lower than ///MAX///'));
					$res=FALSE;	
				}
				break;
			case 'float':
				list($min,$max)=array_map('floatval',explode('-',$params));
				if (strcmp(trim($value),floatval($value))) {
					$this->validErrors[$name]=$this->getLL('error.float.wrong','You must enter a decimal');
					$res=FALSE;
				} elseif ($min && (floatval($value)<$min)) {
					$this->validErrors[$name]=str_replace('///MIN///',$min,$this->getLL('error.float.min','You must enter a value greater than ///MIN///'));
					$res=FALSE;	
				} elseif ($max && floatval($value)>$max) {
					$this->validErrors[$name]=str_replace('///MAX///',$max,$this->getLL('error.float.max','You must enter a value lower than ///MAX///'));
					$res=FALSE;	
				}
				break;
			case 'select':
				if (!$this->checkField($name, $params, $altErrorMessage)) {
					$res=FALSE;
					$this->validErrors[$name]=$this->getLL('error.select','You must select one of theses options');
				}
				break;
			case 'selectMultiples':
				list($min,$max)=array_map('intval',explode('-',$params));
				if ($min && (!is_array($value) || count($value)<$min)) {
					$this->validErrors[$name]=str_replace('///MIN///',$min,$this->getLL('error.selectMultiples.min','You must select at least ///MIN/// of these options'));
					$res=FALSE;
				} elseif ($max && is_array($value) && count($value)>$max) {
					$this->validErrors[$name]=str_replace('///MAX///',$max,$this->getLL('error.selectMultiples.max','You can\'t select moire than ///MAX/// of these options'));
					$res=FALSE;
				}
				break;
			case 'date':
				/*
				* Date format : d<separator>m<separator>Y
				* Params :
				* <separator>|<after>
				*
				*	separator : string
				* after : 'now'/'yesterday'/<fieldname>|<separateur2>/any timestamp
				*	separator2 : string
				*/
				list($separateur,$after)=explode('|',$params,2);
				$separateur=trim($separateur)?$separateur:'-';
				list($day,$month,$year)=array_map('intval',explode($separateur,$value));
				if (!checkdate($month,$day,$year)) {
					$res=FALSE;
					$this->validErrors[$name]=$this->getLL('error.date.invalid','You must enter a valid date');
				} elseif ($after) {
					if ($after=='now') {
						$compTo=mktime(0,0,0,date('m'),date('d'),date('Y'));
					} elseif ($after=='yesterday') {
						$compTo=mktime(0,0,0,date('m'),date('d'),date('Y'))-(3600*24);
					} elseif (intval($after)) {
						$compTo=$after;
					} else {
						list($after,$separateur2)=explode('|',$after,2);
						$separateur2=trim($separateur2)?$separateur2:$separateur;
						list($day2,$month2,$year2)=array_map('intval',explode($separateur2,$this->getFieldVal($after)));
						$compTo=mktime(0,0,0,$month2,$day2,$year2);
					}
					if ($compTo>mktime(0,0,0,$month,$day,$year)) {
						$res=FALSE;
						$this->validErrors[$name]=str_replace('///DATE///',date('d'.$separateur.'m'.$separateur.'Y',$compTo),$this->getLL('error.date.tooearly','You must enter date after ///DATE///'));
					}
				}
				break;
			case 'email':
				if (!t3lib_div::validEmail($value)) {
					$this->validErrors[$name]=$this->getLL('error.email.invalid','You must enter a valid email');
					$res=FALSE;
				}
				break;
			case 'none': //Juste pour dire que le champ doit être enregistré
				break;
			}
		}

		if (! $res && ! is_null($altErrorMessage)) {
			$this->validErrors[$name] = $altErrorMessage;
		}

		return $res;
	}

	function checkHashKey() {
		//*** USefull for preventing multiple submit/insert
		$value=$this->getFieldVal($this->conf['hashKeyName']);
		if (strcmp($value,$_SESSION[$this->conf['namePrefix'].$this->conf['hashKeyName']])) {
			$this->validErrors[$this->conf['hashKeyName']]=$this->getLL('error.hashkey','Wrong key.');
			return FALSE;
		}
		$_SESSION[$this->conf['namePrefix'].$this->conf['hashKeyName']]='';
		return TRUE;
	}

	function getHashKey() {
		$hashKey=($_SESSION[$this->conf['namePrefix'].$this->conf['hashKeyName']]=md5(microtime()));
		$realName=$this->getFieldName($this->conf['hashKeyName']);
		$error=$this->getFieldError($this->conf['hashKeyName']);
		return '<input type="hidden" name="'.htmlspecialchars($realName).'" value="'.$hashKey.'" />'.$error;
	}

	function getSubmit($text='Submit',$name='submit',$others=array()) {
		if (trim($text)) {
			$others['value']=$text;
		} else {
			$others['value']='Submit';
		}
		if (trim($name)) {
			$others['name']=$this->getFieldName($name,FALSE);
		} else {
			$others['name']=$this->getFieldName('submit',FALSE);
		}
		$others['type']='submit';

		if (trim($others['class'])) $others['class'].=' '.$others['type'];
		else $others['class']=$others['type'];

		return '<input '.$this->getAttribs($others).' />'.$this->getFieldError($name);
	}

	function getInput($name,$size=20,$maxlen=2048,$others=array()) {
		$others['size']=intval($size)?intval($size):20;
		$others['maxlength']=intval($maxlen)?intval($maxlen):2048;
		$others['value']=$this->getFieldVal($name);
		$others['name']=$this->getFieldName($name);
		$others['type']='text';

		if (trim($others['class'])) $others['class'].=' '.$others['type'];
		else $others['class']=$others['type'];

		return '<input'.$this->getAttribs($others).' />'.$this->getFieldError($name);
	}

	function getPassword($name,$maxlen=2048,$others=array()) {
		$others['maxlength']=intval($maxlen)?intval($maxlen):2048;
		$others['value']=$this->getFieldVal($name);
		$others['name']=$this->getFieldName($name);
		$others['type']='password';

		if (trim($others['class'])) $others['class'].=' '.$others['type'];
		else $others['class']=$others['type'];

		return '<input'.$this->getAttribs($others).' />'.$this->getFieldError($name);
	}

	function getCheckbox($name,$others=array()) {
		$others['type']='checkbox';
		$others['class']=trim($others['class'])?($others['class'].' check'):'check';
		$others['name']=$this->getFieldName($name);
		if ($this->getFieldVal($name)) {
			$others['checked']='checked';
		} else {
			unset($others['checked']);
		}
		if (!$others['value']) $others['value']=1;

		if (trim($others['class'])) $others['class'].=' '.$others['type'];
		else $others['class']=$others['type'];

		return '<input'.$this->getAttribs($others).' />'.$this->getFieldError($name);
	}

	function getTextarea($name,$cols=60,$rows=3,$others=array()) {
		$others['cols']=intval($cols)?intval($cols):60;
		$others['rows']=intval($rows)?intval($rows):3;
		$others['name']=$this->getFieldName($name);
		$value=$this->getFieldVal($name);
		$error=$this->getFieldError($name);
		return (trim($error)?($error.'<br />'):'').'<textarea'.$this->getAttribs($others).'>'."\n".tx_pplib_div::htmlspecialchars($value).'</textarea>';
	}

	function getSelect($name,$options,$multiple=FALSE,$rows=5,$withOther=FALSE,$size=30,$others=array()) {
		/* Declare */
		$value=$this->getFieldVal($name);
		$others['name']=$this->getFieldName($name).($multiple?'[]':'');
		$error=$this->getFieldError($name);
	
		/* Begin */
		if (!is_array($options) || !count($options)) $options=array(''=>'');
		if ($multiple) {
			if (!is_array($value)) $value=array();
		} else {
			$value=array($value);
		}

		if ($withOther) {
			//*** Création des valeurs supplémentaires
			foreach ($value as $key=>$val) {
				if (trim($val) && !isset($options[$val])) {
					$options[$val]=$val;
				}
			}
		}

		if ($multiple) {
			$others['multiple']='multiple';
			$rows=intval($rows);
			if ($rows && $rows>0) {
				$others['size']=$rows;
			} elseif ($rows<0) {
				$others['size']=min(count($options),-$rows);
			}
		} else {
			unset($others['multiple']);
			unset($others['size']);
		}
		$content='<select '.$this->getAttribs($others).'>';

		foreach ($options as $key=>$val) {
			$content.='<option value="'.tx_pplib_div::htmlspecialchars($key).'"'.(in_array($key,$value)?' selected="selected"':'').'>'.tx_pplib_div::htmlspecialchars($val).'</option>';
		}
		$content.='</select>';
		if ($withOther) {
			$content.=' <input type="button" value="&lt;-" title="'.$this->getLL('select.addtolist','Add to list').'" onclick="var select=this.previousSibling;while (select &amp;&amp; (select.nodeName!=\'SELECT\')) select=select.previousSibling; var input=this.nextSibling; while (input &amp;&amp; (input.nodeName!=\'INPUT\')) input=input.nextSibling; if (select &amp;&amp; input &amp;&amp; (input.value!=\'\')) {var option=document.createElement(\'OPTION\');option.setAttribute(\'value\',input.value);option.appendChild(document.createTextNode(input.value));select.appendChild(option);input.value=\'\'}"/>';
			$content.=' <input type="text" size="'.intval($size).'" />';
		}

		if (trim($error)) $content.='<br />'.$error;

		return $content;
	}

	function getRadioSelect($name,$options,$wrap='|<br />',$noHSC=FALSE,$others=array()) {
		$others['name']=$this->getFieldName($name);
		$others['type']='radio';
		list($before,$after)=explode('|',$wrap);
		$value=$this->getFieldVal($name);
		if (!is_array($options)) $options=array();
		foreach ($options as $key=>$val) {
			unset($others['checked']);
			if (!strcmp($value,$key)) {
				$others['checked']='checked';
			}
			$others['value']=$key;
			$content.=$before.'<input'.$this->getAttribs($others).' />'.($noHSC?$val:tx_pplib_div::htmlspecialchars($val)).$after;
		}

		if (trim($others['class'])) $others['class'].=' '.$others['type'];
		else $others['class']=$others['type'];

		return $this->getFieldError($name).$content;
	}

	/**
	 *
	 *
	 * @param array $attribs
	 * @access public
	 * @return void 
	 */
	function getAttribs($attribs) {
		/* Declare */
		$content='';
		$forbidden=array('border'); //Forbidden attribs (forcing XHTML)
	
		/* Begin */
		if (!is_array($attribs)) return '';
		foreach ($attribs as $key=>$val) {
			if (!in_array($key,$forbidden)) $content.=' '.tx_pplib_div::htmlspecialchars($key).'="'.tx_pplib_div::htmlspecialchars($val).'"';
		}
		return $content;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getFieldName($name,$isData=TRUE) {
		/* Declare */
		$res=$this->conf['namePrefix'];
		$parts=explode('|',$name);
	
		/* Begin */
		if ($isData && trim($this->conf['dataPrefix'])) {
			$parts=array_merge(explode('|',$this->conf['dataPrefix']),explode('|',$name));
		}
		foreach ($parts as $val) {
			if (trim($res)) {
				$res.='['.rawurlencode($val).']';
			} else {
				$res.=rawurlencode($val);
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
	function getFieldVal($name) {
		/* Declare */
		$val = $this->data;
	
		/* Begin */
		foreach (explode('|',$name) as $key) {
			if (is_object($val) && isset($val->$key)) {
				$val = $val->$key;
			} elseif(is_array($val) && isset($val[$key])) {
				$val = $val[$key];
			} else {
				$val = null;
			}
		}

		return $val;
	}

	/**
	 *
	 *
	 * @param 
	 * @access public
	 * @return void 
	 */
	function getFieldError($name) {
		if (trim($this->validErrors[$name])) {
			return ' <span class="error">'.tx_pplib_div::htmlspecialchars($this->validErrors[$name]).'</span> ';
		} else {
			return '';
		}
	}
}

tx_pplib_div::XCLASS('ext/pp_lib/tools/class.tx_pplib_formtoolkit.php');
?>