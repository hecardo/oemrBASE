<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Common;

/**
 * Provides standardized processing for procedure order forms.
 */
class LabResultItem {
	public $procedure_result_id;
	public $procedure_report_id;
	public $result_data_type; 
	public $result_code;
	public $result_text;
	public $date;
	public $facility;
	public $units;
	public $result;
	public $normal; // range is a reserved word
	public $abnormal;
	public $comments;
	public $result_status;
	
	/**
	 * Constructor for the class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param int $id record identifier
	 * @return object instance of form class
	 */
	public function __construct($proc_result_id = false) {
		// create empty record with no id
		if (!$proc_result_id) return false;
		
		// retrieve data
		$query = "SELECT * FROM `procedure_result` WHERE `procedure_result_id` = ?";
		$results = sqlStatement($query, array($proc_result_id));

		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = $value;
			}
		}
		else {
			throw new \Exception('mdtsLabResultItem::_construct - no procedure result item record with key ('.$proc_result_id.')');
		}

		return;
	}

	/**
	 * Inserts data from a form object into the database.
	 *
	 * @return int $id identifier for new object
	 */
	public function store() {
		$insert = true;
		if ($this->procedure_result_id) $insert = false;

		// create record
		$sql = '';
		$binds = array();
		$this->activity = 1;
		$fields = $this->listFields();
		
		// selective updates
		foreach ($this AS $key => $value) {
			if ($key == 'procedure_result_id') continue;
			if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";

			// both object and database
			if (array_search($key, $fields) !== false) {
				$sql .= ($sql)? ", `$key` = ? " : "`$key` = ? ";
				$binds[] = ($value == 'NULL')? "" : $value;
			}
		}
		
		// run the child insert
		if ($insert) { // do insert
			$this->procedure_result_id = sqlInsert("REPLACE `procedure_result` SET $sql", $binds);
		} else { // do update
			$binds[] = $this->proc_result_id;
			sqlStatement("UPDATE `procedure_result` SET $sql WHERE `procedure_result_id` = ?", $binds);
		}
				
		return $this->procedure_result_id;
	}


	/**
	 * Returns an array list of procedure order item objects associated with the
	 * given order.
	 *
	 * @static
	 * @param int $proc_report_id Procedure report identifier (parent result)
	 * @return array $objectList list of selected objects
	 */
	public static function fetchItemList($proc_report_id = false) {
		if (!$proc_report_id) return false;

		$query = "SELECT `procedure_result_id` FROM `procedure_result` ";
		$query .= "WHERE `procedure_report_id` = ? ORDER BY `procedure_result_id` ";

		$results = sqlStatement($query, array($proc_report_id));

		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new LabResultItem($data['procedure_result_id']);
		}

		return $objectList;
	}

	/**
	 * Returns an array of valid database fields for the object.
	 *
	 * @static
	 * @return array list of database field names
	 */
	public static function listFields() {
		$fields = sqlListFields('procedure_result');
		return $fields;
	}
}

