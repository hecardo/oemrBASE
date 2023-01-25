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
class LabResult {
	public $procedure_report_id;
	public $procedure_order_id;
	public $procedure_order_seq;
	public $date_collected;
	public $date_report;
	public $source;
	public $specimen_num;
	public $report_status;
	public $review_status;
	public $report_notes;
	
	/**
	 * @param int $id record identifier
	 * @return object instance of result class
	 */
	public function __construct($id = false) {
		// create empty record with no id
		if (!$id) return false;

		$query = "SELECT * FROM `procedure_report` WHERE `procedure_report_id` = ?";
		$data = sqlQuery($query,array($id));
		if (!$data['procedure_report_id'])
			throw new \Exception('mdtsLabResult::_construct - no procedure report record with procedure_report_id ('.$id.').');
		
		// load everything returned into object
		foreach ($data as $key => $value) {
			$this->$key = $value;
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
		if($this->procedure_report_id) $insert = false;

		// build sql insert for child
		$sql = '';
		$binds = array();
		$fields = self::listFields(); // need only sup rec fields
		
		// merge form data
		foreach ($this as $key => $value) {
			if ($key == 'id') continue;
			if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";
			if ($key == 'patient_id') $value = $this->pid; 
			
			// both object and database
			if (array_search($key, $fields) !== false) {
				$sql .= ($sql)? ", `$key` = ? " : "`$key` = ? ";
				$binds[] = ($value == 'null')? "" : $value;
			}
		}

		// run the child insert
		if ($insert) { // do insert
			$this->procedure_report_id = sqlInsert("REPLACE `procedure_report` SET $sql", $binds);
		} else { // do update
			$binds[] = $this->procedure_report_id;
			sqlStatement("UPDATE `procedure_report` SET $sql WHERE `procedure_report_id` = ?", $binds);
		}
				
		return $this->procedure_report_id;
	}

	/**
	 * Search and retrieve an order object by order number
	 *
	 * @static
	 * @parm string $order_num Order number for the order
	 * @return LabResult $object
	 */
	public static function fetchResult($order_num, $order_seq) {
		if(!$order_num) return false;

		$result = sqlQuery("SELECT `procedure_report_id` FROM `procedure_report` WHERE `procedure_order_id` = ? AND `procedure_order_seq` = ?",
				array($order_num, $order_seq));
		
		if (!$result['procedure_report_id']) return false;
		$result_data = new LabResult($result['procedure_report_id']);

		return $result_data;
	}

	/**
	 * Search and retrieve an order object by order number
	 *
	 * @static
	 * @parm string $order_num Order number for the order
	 * @return LabResult $object
	 */
	public static function fetchReflex($order_num, $reflex_code, $reflex_set) {
		if(!$order_num || !$reflex_code) return false;

		$query = "SELECT `procedure_result_id` FROM `procedure_report` rep ";
		$query .= "LEFT JOIN `procedure_result` res ON rep.`procedure_report_id` = res.`procedure_report_id` ";
		$query .= "WHERE rep.`procedure_order_id` = ? AND res.`result_code` = ? AND res.`result_set` = ? ";
		$result = sqlQuery($query, array($order_num, $reflex_code, $reflex_set));
		
		if (!$result['procedure_result_id']) return false;
		$result_data = new LabResultItem($result['procedure_result_id']);

		return $result_data;
	}

	/**
	 * Returns an array of valid database fields for the object.
	 *
	 * @static
	 * @return array list of database field names
	 */
	public static function listFields() {
		$fields = sqlListFields('procedure_report');
		return $fields;
	}

}
