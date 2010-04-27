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

//require_once(t3lib_extMgm::extPath('pp_lib').'class.tx_pplib.php');
//require_once(t3lib_extMgm::extPath('pp_lib').'class.tx_pplib2.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_forum.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_forumsim.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_message.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_topic.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_user.php');
//require_once(t3lib_extMgm::extPath('pp_forum').'pi1/class.tx_ppforum_smileys.php');


/**
 * Plugin 'Popy Forum' for the 'pp_forum' extension.
 * Plugin wrapper : ensure that only one instance of the real plugin object is built
 *
 * @author	Popy <popy.dev@gmail.com>
 * @package	TYPO3
 * @subpackage	tx_ppforum
 */
class tx_ppforum_pi1 {
	/**
	 * The mother tslib_cObj instance
	 * @access public
	 * @var object
	 */
	var $cObj = null;

	/**
	 * The real plugin object
	 * @access public
	 * @var object
	 */
	var $pluginObj = null;

	/**
	 * The plugin's extension key
	 * @access public
	 * @var string
	 */
	var $extKey = 'pp_forum';

	/**
	 * 
	 * 
	 * @access public
	 * @return void 
	 */
	function init() {
		$cacheKey = 'tx_ppforum_rpi1('.tx_pplib_div::strintval($this->cObj->data['uid']).')';

		if (!tx_pplib_instantcache::isInCache($cacheKey, 'PI_SINGLETON')) {
			$this->pluginObj = &tx_pplib_div::makeInstance('tx_ppforum_rpi1');
			$this->pluginObj->cObj = &$this->cObj;
			tx_pplib_instantcache::storeInCache($this->pluginObj, $cacheKey, 'PI_SINGLETON');
		} else {
			$this->pluginObj = &tx_pplib_instantcache::getFromCache($cacheKey, 'PI_SINGLETON');
		}

	}

	/**
	 * Wrapper method for "main" method
	 * @see tx_ppforum_rpi1::main
	 */
	function main($content, $conf)	{
		$this->init();
		return $this->pluginObj->main($conf);
	}

	/**
	 * Wrapper method for "mainCallback" method
	 * @see tx_ppforum_rpi1::mainCallback
	 */
	function mainCallback($content, $conf)	{
		$this->init();
		return $this->pluginObj->mainCallback($conf);
	}

	/**
	 * Wrapper method for "rss_getList" method
	 * @see tx_ppforum_rpi1::rss_getList
	 */
	function rss_getList($conf,&$ref)	{
		$this->init();
		return $this->pluginObj->rss_getList($conf, $ref);
	}

	/**
	 * Wrapper method for "doSearch" method
	 * @see tx_ppforum_rpi1::doSearch
	 */
	function doSearch($content, $conf)	{
		$this->init();
		return $this->pluginObj->doSearch($conf);
	}

}


tx_pplib_div::XCLASS('ext/pp_forum/pi1/class.tx_ppforum_pi1.php');
?>