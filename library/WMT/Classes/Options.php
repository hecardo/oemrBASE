<?php
/**
 * Common class used to access list options.
 *
 * @package   WMT\Common
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/**
 * All new classes are defined in the WMT namespace
 */
namespace WMT\Classes;

/** 
 * Provides general utility functions related to the list_option table and its contents.
 *
 * @package mdts
 * @subpackage Options
 */
class Options {
	/** 
	 * Class variables
	 */	
	public $id;  // list identifier
	public $list; // content of list by key
	
	/**
	 * Creates the list class variables and initializes the object. 
	 *
	 * @param id - list table id
	 */
	public function __construct($id=false, $entry=false) {
		// special for insurance companies
		if (strtolower($id) == 'insurance') return $this->listInsurance();
		
		// list id is required
		if (!$id || $id == '')
			throw new \Exception("mdtsOptions::__construct - no list identifier provided");

		// set default class variables
		$this->id = $id;
		$this->list = array();
		
		// retrieve list contents
		$binds = array($id);
		$query = "SELECT * FROM `list_options` WHERE `list_id` LIKE ? AND `activity` = 1 ";
		if ($entry) {
			$query .= "AND `option_id` LIKE ? ";
			$binds[] = $entry;
		}
		$query .= "ORDER BY `seq`, `title`";
		$result = sqlStatementNoLog($query,$binds);
		
		// store results
		if (!$result || sqlNumRows($result) == 0) {
			return false;
//			throw new \Exception("mdtsOptions::__construct - no list '$id' contents found");
		} else {
			while ($row = sqlFetchArray($result)) {
				if ($entry) {
					$this->entry = $row;
					break;
				} else {
					$this->list[$row['option_id']] = $row;
				}
			}
		}
	}		
	
	
	/**
	 * Creates the list class variables and initializes the object. 
	 *
	 */
	public function listInsurance() {
		// set default class variables
		$this->id = 'Insurance_Companies';
		$this->list = array();
		
		// retrieve list contents
		$query = "SELECT `id`, `name` FROM `insurance_companies` WHERE `inactive` != 1 ";
		$query .= "ORDER BY `name`";
		$result = sqlStatementNoLog($query);
		
		// store results
		if (!$result || sqlNumRows($result) == 0) {
			throw new \Exception("mdtsOptions::__construct - no 'insurance_companies' contents found");
		} else {
			$this->list['none'] = array('option_id'=>0,'title'=>'NO INSURANCE','is_default'=>true,'notes'=>'');
			$this->list['other'] = array('option_id'=>999999999,'title'=>'INSURANCE COMPANY NOT LISTED','is_default'=>'','notes'=>'');
			
			while ($row = sqlFetchArray($result)) {
				$name = strtoupper($row['name']);
				if (strpos($name, 'SCHEDULE') !== false) continue;
				$this->list[$row['id']] = array('option_id'=>$row['id'],'title'=>$row['name'],'is_default'=>true,'notes'=>'');
			}
		}
		
		return;
	}		
	
	/**
	 * Returns the data for a list key value.
	 *
	 * @param itemId - entry id in table
	 * 
	 */
	public function getRecord($id) {
		$result = false;
		if ($this->list[$id])
			$result = $this->list[$id]; // data from list

		return $result;
	}
	
	/**
	 * Returns the data for a default value.
	 *
	 */
	public function getDefault() {
		$record = sqlQuery('SELECT `option_id` FROM `list_options` WHERE `list_id` LIKE ? AND `is_default` = 1', array($this->id));
		
		return $this->getRecord($record['option_id']);
	}
	
	/**
	 * Returns the translation of a list key value.
	 *
	 * @param itemId - entry id in table
	 * @param result - default value if none found
	 * 
	 */
	public function getItem($id, $result='') {
		if ($this->list[$id])
			$result = $this->list[$id]['title']; // title from list

		return xl($result);
	}
	
	/**
	 * Returns the translation of a list key value.
	 *
	 * @param itemId - entry id in table
	 * @param result - default value if none found
	 * 
	 */
	public function showItem($id, $result='') {
		echo $this->getItem($id, $result);
	}
	
	/**
	 * Build selection list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function getOptions($id, $default='') {
		$result = '';
		
		// create default if needed
		if (!$id && $default) {
			$result .= "<option value='' ";
			$result .= (!$id || $id == '')? "disabled selected hidden " : "";
			$result .= ">".xl($default)."</option>\n";
		}

		// build options
		$in_group = false;
		foreach ($this->list AS $item) {
			if (empty($item['title'])) continue;
			if (strtolower($item['notes']) == 'group') {
				if ($in_group) $result .= "</optgroup>\n";
				$result .= "<optgroup label='" . xl($item['title']) ."'>\n";	
				$in_group = true;
			} else {
				$result .= "<option value='" . $item['option_id'] . "' ";
				if ((!$id && !$default && $item['is_default']) || $id == $item['option_id']) {
					$result .= "selected ";
				}
				if (!empty($item['codes'])) {
					$result .= "code='" . $item['codes'] . "' ";
				}
				$result .= ">" . xl($item['title']) ."</option>\n";
			}
		}
		if ($in_group) $result .= "</optgroup>\n";
		
		return $result;
	}
	
	/**
	 * Echo selection option list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function showOptions($id, $default='') {
		echo $this->getOptions($id, $default);
	}
	
	
	/**
	 * Return the codes from the list option.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function getCodes($id, $default='') {
		$id = strtolower($id);
		if ($this->list[$id])
	        $result = $this->list[$id]['codes'];
	        
	        return ($result);
	}
	
	/**
	 * Build selection list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function getNotes($id, $default='') {
		$result = $default;
		if ($this->list[$id])
			$result = $this->list[$id]['notes']; // title from list

		// create default if needed
		if (!$id && $default) {
			$result .= "<option value='' ";
			$result .= (!$id || $id == '')? "disabled selected hidden " : "";
			$result .= ">".xl($default)."</option>\n";
		}

		// build options (using notes)
		foreach ($this->list AS $item) {
			$result .= "<option value='" . $item['option_id'] . "' ";
			if ((!$id && !$default && $item['is_default']) || $id == $item['option_id']) 
				$result .= "selected ";
			$result .= ">" . xl($item['notes']) ."</option>\n";
		}
		
		return $result;
	}
	
	/**
	 * Echo selection option list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function showNotes($id, $default='') {
		echo $this->getNotes($id, $default);
	}
	
	
	/**
	 * Build selection list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function getNoteOptions($id, $default='') {
		// create default if needed
		if (!$id && $default) {
			$result .= "<option value='' ";
			$result .= (!$id || $id == '')? "disabled selected hidden " : "";
			$result .= ">".xl($default)."</option>\n";
		}

		// build options (using notes)
		foreach ($this->list AS $item) {
			$result .= "<option value='" . $item['option_id'] . "' ";
			if ((!$id && !$default && $item['is_default']) || $id == $item['option_id']) 
				$result .= "selected ";
			$result .= ">" . xl($item['notes']) ."</option>\n";
	}
	
		return $result;
	}
	
	/**
	 * Echo selection option list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function showNoteOptions($id, $default='') {
		echo $this->getNoteOptions($id, $default);
	}
	
	/**
	 * Build selection list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function getChecks($name,$checked) {
		if (!is_array($checked)) $checked = array($checked);
		$result = '';
		
		// build options
		foreach ($this->list AS $item) {
			$result .= "<div style='float:left'>";
			$result .= "<label for='" . $item['option_id'] . "' class='checkbox-inline' style='margin-right:15px'>";
			$result .= "<input type='checkbox' value='" . $item['option_id'] . "' id='" . $item['option_id'] . "' name='" . $name . "[]' ";
			if (in_array($item['option_id'],$checked)) $result .= "checked ";
 			$result .= "> " . xl($item['title']) . "</label></div>\n";
		}
	
		return $result;
	}
	
	/**
	 * Echo selection option list from table data.
	 *
	 * @param itemId - current entry id
	 * @param result - string html option list
	 */
	public function showChecks($name,$checked) {
		echo $this->getChecks($name,$checked);
	}
	
	/**
	 * Print selected list from table data.
	 *
	 * @param checked items
	 * @param result - string html option list
	 */
	public function listChecks($checked) {
		if (!is_array($checked)) $checked = array($checked);
		$result = '';
		
		// build display list
		foreach ($this->list AS $item) {
			if ( !in_array($item['option_id'],$checked) ) continue;
			if ($result) $result .= "; ";
			$result .= xl($item['title']);
		}
		
		echo "<div style='float:left'>" . $result ."</div>\n";

	}
	
	/**
	 * Returns all of the list data for given item.
	 *
	 * @param itemId - entry id in table
	 * @param result - data record if none found
	 * 
	 */
	public function getData($id) {
		if ($this->list[$id]) {
			$result = $this->list[$id]; // data array
		} else {
			$result = array();
		}

		return $result;
	}
	
	/**
	 * Filter out list options by some criteria.
	 *
	 * @param string column  - the column to filter on
	 * @param string test    - the test to perform for filtering
	 * @param string compare - the value to compare for filtering
	 */
	public function removeFromList($col, $comp = '=', $val = '') {
	    foreach ($this->list as $key => $item) {
	        if($comp === '=') {
	            if($item[$col] === $val) unset($this->list[$key]);
	        }
	        if($comp === '!=') {
	            if($item[$col] !== $val) unset($this->list[$key]);
	        }
	    }
	}
	
	public static function getListItem($list, $key) {
		$value = '';
		$query = "SELECT * FROM list_options WHERE list_id LIKE ? AND option_id LIKE ? AND activity = 1 LIMIT 1";
		$result = sqlQueryNoLog($query, [$list, $key]);
		if (isset($result['title'])) $value = $result['title'];

		return $value;
	}
	
}
?>