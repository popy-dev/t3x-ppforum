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
class tx_ppforum_base {
	/**
	 * Item's uid
	 * @access public
	 * @var int
	 */
	var $id = 0;

	/**
	 * Record's data
	 * @access public
	 * @var array
	 */
	var $data = array();

	/**
	 * Record's table
	 * @access public
	 * @var string
	 */
	var $tablename = '';

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
	 * Loads the record's data from DB
	 *
	 * @param int $id = Record's uid
	 * @param boolean $clearCache = if TRUE, cached data will be overrided
	 * @access public
	 * @return int = loaded uid
	 */
	function load($id, $clearCache = false) {
		$this->tablename = $this->parent->tables[$this->type];

		if ($id && $this->data = $this->parent->pp_getRecord($id, $this->tablename, $clearCache)) {
			$this->id = intval($id);
		} else {
			$this->data = array();
		}
		return $this->id ? $this->id : false;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_message.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pp_forum/pi1/class.tx_ppforum_message.php']);
}

?>