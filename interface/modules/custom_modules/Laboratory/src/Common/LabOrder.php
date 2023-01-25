<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Common;

use WMT\Objects\Form;

class LabOrder extends Form {
	/* Inherited from Form
	public $id;
	public $created;
	public $date;
	public $pid;
	public $user;
	public $provider;
	public $encounter;
	public $groupname;
	public $authorized;
	public $activity;
	public $status;
	public $priority;
	public $approved_by;
	public $approved_dt;
	
	public $form_title;
	public $form_name;
	public $form_table;
	*/
	
	// stored in 'form_laboratory'
	public $order_number;			// xref to procedure_order_id
	public $facility_id;
	public $lab_id;					// duplicate
	public $ins_primary;
	public $ins_secondary;
	public $order_type;
	public $order_notes;
	public $work_flag;
	public $work_insurance;
	public $work_date;
	public $work_employer;
	public $work_case;
	public $received_datetime;
	public $report_datetime;
	public $result_abnormal;
	public $reviewed_datetime;
	public $reviewed_id;
	public $review_notes;
	public $notified_datetime;
	public $notified_id;
	public $notified_person;
	public $order_abn_id;
	public $order_req_id;
	public $result_doc_id;
	
	// stored in 'procedure_order'
	public $procedure_order_id;
	public $uuid;
	public $provider_id;
	public $patient_id;
	public $encounter_id;
	public $date_collected;
	public $date_ordered;
	public $order_priority;
	public $order_status; // 'pending,routed,complete,canceled',
	public $patient_instructions;
	public $activity;
	public $control_id;
//	public $lab_id;  // duplicate
	public $specimen_draw;
	public $specimen_type;
	public $specimen_location;
	public $specimen_volume;
	public $date_pending;
	public $date_transmitted;
	public $clinical_hx;
	public $specimen_fasting;
	public $specimen_duration;
	public $specimen_transport;
	public $specimen_source;
	public $external_id;
	public $history_order;
	public $portal_flag;
	public $tav_done;
	public $order_diagnosis;
	public $billing_type;
	public $order_psc;
	public $order_abn;
	public $collector_id;
	public $account;
	public $account_facility;
	public $provider_number;  // npi
	public $procedure_order_type;
	
	/**
	 * Constructor for the 'order' class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param string $form_table database table
	 * @param int $id record identifier
	 * @return object instance of form class
	 */
	public function __construct($form='laboratory', $id=false) {

		// run parent create/retrieve
		parent::__construct($form, $id);

		// create empty record with no id
		if (!$id) return false;

		// retrieve remaining data
		if (!$this->order_number)
			throw new \Exception('LabOrder::_construct - no procedure order number.');
		
		$query = "SELECT * FROM `procedure_order` WHERE `procedure_order_id` = ?";
		$data = sqlQuery($query, array($this->order_number));
		if (!$data['procedure_order_id'])
			throw new \Exception('LabOrder::_construct - no procedure order record with procedure_order_id ('.$this->order_number.').');
		
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
		if($this->id) $insert = false;

		// insert form through parent
		if (empty($this->encounter_id) && $this->encounter) {
			// necessary for forms insert in parent
			$this->encounter_id = $this->encounter;
		}
		parent::store();
				
		// build sql insert for child
		$sql = '';
		$binds = array();
		$fields = sqlListFields('procedure_order'); // need only sup rec fields
		
		// merge form data
		foreach ($this as $key => $value) {
			if ($key == 'id') continue;
			if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";
				
			if ($key == 'procedure_order_id') $value = $this->order_number;
			if ($key == 'patient_id') $value = $this->pid; 
			
			// both object and database
			if (array_search($key, $fields) !== false) {
				$sql .= ($sql)? ", `$key` = ? " : "`$key` = ? ";
				$binds[] = ($value == 'null')? "" : $value;
			}
		}

		// run the child insert
		if ($insert) { // do insert
			sqlInsert("INSERT INTO `procedure_order` SET $sql", $binds);
		} else { // do update
			$binds[] = $this->order_number;
			sqlStatement("UPDATE `procedure_order` SET $sql WHERE `procedure_order_id` = ?", $binds);
		}
				
		return $this->id;
	}


	/**
	 * Search and retrieve an order object by order number
	 *
	 * @static
	 * @parm string $order_num Order number for the order
	 * @return LabOrder $object
	 */
	public static function fetchOrder($form_name = "order", $order_num, $lab_id, $pid, $pat_DOB = false) {
		if(! $order_num)
			throw new \Exception ("LabOrder::fetchOrder - no order number provided");

		if(! $lab_id)
			throw new \Exception ("LabOrder::fetchOrder - no lab identifier provided");

		$table = "form_".$form_name;

		$query = ("SELECT id FROM $table WHERE order_number = ? AND lab_id = ? AND (pid = '1' OR pid = ?) ");
		$params[] = $order_num;
		$params[] = $lab_id;
		$params[] = $pid;

		if ($pat_DOB) { 
			$query .= "AND pat_DOB = ? ";
			$params[] = $pat_DOB;
		}
		
		$order = sqlQuery($query,$params);
		if (!$order || !$order['id']) return false;
		
		return new LabOrder($form_name, $order['id']);
	}

	/**
	 * Search and retrieve an order object by encounter and pid
	 *
	 * @static
	 * @param string $enc_num Encounter number for the order
	 * @param int $pid Patient identifier
	 * @param int $enc Encounter identifier
	 * @param int $lab Lab identifier
	 * @param string Form type name
	 * @return LabOrder $object
	 */
	public static function fetchOrderEncounter($enc, $pid, $lab, $form) {
		if(! $enc)
			throw new \Exception ("LabOrder::fetchOrderEncounter - no encounter number provided");

		if(! $form)
			throw new \Exception ("LabOrder::fetchEncounter - no form name provided");

		if(! $pid)
			throw new \Exception ("LabOrder::fetchEncounter - no patient identifier provided");

		if(! $lab)
			throw new \Exception ("LabOrder::fetchEncounter - no laboratory identifier provided");

		if ($form == 'internal') $form = 'laboratory';
		$form_table = 'form_' . $form;
		
		$query = "SELECT `form_id` FROM `forms` fr ";
		$query .= "LEFT JOIN `$form_table` ft ON ft.id = fr.form_id ";
		$query .= "WHERE fr.`encounter` = ? AND fr.`pid` = ? AND fr.`formdir` = ? AND ft.`lab_id` = ? ";
		$params[] = $enc;
		$params[] = $pid;
		$params[] = $form;
		$params[] = $lab;
		
		$order = sqlQuery($query,$params);

		// check for results
		$id = ($order['form_id'])? $order['form_id'] : false;
		
		// creates an new order
		return new LabOrder($form, $id);
	}

	/**
	 * Returns the next available order number.
	 *
	 * @static
	 * @return int order number
	 */
	public static function nextOrdNum() {
		$ordnum = $GLOBALS['adodb']['db']->GenID('order_seq');
	
		// duplicate checking
		$dupchk = sqlQuery("SELECT `procedure_order_id` AS id FROM `procedure_order` WHERE `procedure_order_id` = ?",array($ordnum));
		while ($dupchk !== false) {
			$ordnum = $GLOBALS['adodb']['db']->GenID('order_seq');
			$dupchk = sqlQuery("SELECT `procedure_order_id` AS id FROM `procedure_order` WHERE `procedure_order_id` = ?",array($ordnum));
		}
		
		return $ordnum;
	}

	/**
	 * Returns an array of valid database fields for the object.
	 *
	 * @static
	 * @return array list of database field names
	 */
	public function listFields() {
		$fields = sqlListFields($this->form_table);
		return $fields;
	}

}

?>