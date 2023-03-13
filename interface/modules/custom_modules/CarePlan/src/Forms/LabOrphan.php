<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Common;

class LabOrphan extends LabOrder {
	/* Inherited from 'form'
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
	
	/* Inherited from 'form_laboratory'
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
	public $result_datetime;
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
	*/
	
	/* Inherited from 'procedure_order'
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
	*/
	
	// Stored in 'form_orphans'
	public $pat_lname;
	public $pat_mname;
	public $pat_fname;
	public $pat_suffix;
	public $pat_title;
	public $pat_DOB;
	public $doc_npi;
	public $doc_lname;
	public $doc_mname;
	public $doc_fname;
	public $doc_suffix;
	public $doc_title;
	public $pat_pubpid;
	public $pat_namespace;
	public $pat_id_type;
	public $pat_race;
	public $pat_ethnicity;
	public $order_namespace;
	public $lab_namespace;
	public $lab_id_type;
	public $group_number;
	public $group_namespace;
	public $doc_name_type;
	public $doc_id_type;
	
	/**
	 * Constructor for the 'order' class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param string $form_table database table
	 * @param int $id record identifier
	 * @return object instance of form class
	 */
	public function __construct($id=false) {

		// run parent create/retrieve
		parent::__construct('orphans', $id);

		// create empty record with no id
		if (!$id) return false;
	
		// retrieve remaining data
		if (!$this->order_number)
			throw new \Exception('mdtsLabOrphan::_construct - no procedure order number.');
		
		$query = "SELECT * FROM `procedure_order` WHERE `procedure_order_id` = ?";
		$data = sqlQuery($query, array($this->order_number));
		if (!$data['procedure_order_id'])
			throw new \Exception('mdtsLabOrphan::_construct - no procedure order record with procedure_order_id ('.$this->order_number.').');
		
		// load everything returned into object
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}

		return;
		// preformat commonly used data elements
		$this->created = (strtotime($this->created) !== false)? date('Y-m-d H:i:s',strtotime($this->created)) : date('Y-m-d H:i:s');
		$this->date = (strtotime($this->date) !== false)? date('Y-m-d H:i:s',strtotime($this->date)) : date('Y-m-d H:i:s');

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
		parent::store();
				
		return $this->id;
	}

}
