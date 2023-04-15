<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Get parameters
$batch = false;
$ignoreAuth = false;
if (defined('STDIN')) {
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
	$batch = true;
	$ignoreAuth = true;
}

if (!isset($_GET['site'])) $_GET['site'] = 'default';
$site_id = ($_SESSION['site_id']) ? $_SESSION['site_id'] : $_GET['site'];

// Global setup
require_once(dirname(__FILE__, 6)."/globals.php");

use OpenEMR\Core\Header;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Common\Logging\EventAuditLogger;

use WMT\Classes\Tools;
use WMT\Classes\Options;

use WMT\Objects\Patient;
use WMT\Objects\Encounter;
use WMT\Objects\Facility;

use WMT\Laboratory\Common\Processor;
use WMT\Laboratory\Common\LabOrphan;
use WMT\Laboratory\Common\LabOrder;
use WMT\Laboratory\Common\LabOrderItem;
use WMT\Laboratory\Common\LabResult;
use WMT\Laboratory\Common\LabResultItem;

use WMT\Laboratory\Generic;
use WMT\Laboratory\Quest;
use WMT\Laboratory\Labcorp;

// Set defaults
if (!empty($_REQUEST['from']) && strtotime($_REQUEST['from']) !== false) {
	$from_date = date('Y-m-d', Tools::GetSeconds($_REQUEST['from']));
}
if (!empty($_REQUEST['thru']) && strtotime($_REQUEST['thru']) !== false) {
	$thru_date = date('Y-m-d', Tools::GetSeconds($_REQUEST['thru']));
}
$lab_id = (isset($_REQUEST['lab']))? $_REQUEST['lab'] : false;
$debug = (isset($_REQUEST['debug']))? $_REQUEST['debug'] : false;

// Process specified laboratory
if (!empty($lab_id)) {
	try {
		if (!$batch) {
			echo "<html><head><title>Result Processing</title><head><body><pre>"; 
		}
		echo "START OF BATCH PROCESSING: ".date('Y-m-d H:i:s')."\n";
		
		$reprocess = FALSE;
		if ($from_date && !$thru_date) { // must have both
			$thru_date = date('Y-m-d');
		}

		// Retrieve the laboratory information
		$lab_data = new Processor($lab_id);
		
		// Load the correct client module
		switch ($lab_data->type) {
			case 'Q':
				$client = new Quest\ResultClient($lab_id);
				$messages = $client->getResults(25, $from_date, $thru_date, $debug);
				$reporter = false; // not needed with Quest
				break;
			case 'L':
				$client = new Labcorp\ResultClient($lab_id);
				$messages = array();
				$reporter = new Labcorp\ResultReport();
				break;
			case 'G':
			case 'F':
				$client = new Generic\ResultClient($lab_id);
				$messages = $client->getResults(25, $debug);
				$reporter = new Generic\ResultReport();
				break;
			default:
				throw new \Exception("No client available for type: ($lab_data->type)");
		}
		
		// Process the results
		foreach ($messages as $message) {
			
			$errors = false;
			ob_start(); // buffer the output
			
			// med-manager check
			$message->pid = str_replace('.', '0', $message->pid);
			$message->order_number = str_replace($message->facility_id."-", "", $message->order_number);
			$message->order_number = preg_replace("/[^0-9]/", "", $message->order_number);
			
			// deal with facility
			if (!$message->account) $message->account = $lab_data->send_fac_id; // sending_facility is default account
			
			$response_id = $message->response_id; // the same value in all messages
			if ($debug) {
				echo "\n----------------------------------------------------------------------\n";
				echo "Processing Results for Patient: ".$message->name[0].", ".$message->name[1]." ".$message->name[2]."\n";
				echo "Patient PID: ".$message->pid;
				if ($message->pubpid) echo " (PUB: ".$message->pubpid.")";
				if ($message->extpid) echo " (EXT: ".$message->extpid.")";
				echo "\n";
				if ($message->dob)
					echo "Patient DOB: ".date('Y-m-d',strtotime($message->dob))."\n";
					if ($message->sex)
						echo "Patient Sex: $message->sex \n";
						echo "Order Number: $message->order_number \n";
						echo "Lab number : $message->control_id \n";
						if ($message->lab_received)
							echo "Lab Received: ".date('Y-m-d', strtotime($message->lab_received))." \n";
							echo "Provider: ".$message->provider[0]." - ".$message->provider[1]." ".$message->provider[2]."\n";
							echo "Account: $message->account \n\n";
			}
			
			$pid = '';
			$pubpid = '';
			$pat_DOB = '';
			$provider_id = '';
			$order_id = 0;
			$site_id = 0;
			$encounter = 0;
			$final_status = '';
			$request_priority = '';
			$images = array(); // addl doc images
			
			$order_data = null;
			$patient_data = null;
			$doctor_data = null;
			$facility_data = null;
			
			$order_number = $message->order_number;
			$control_id = $message->control_id;
			
			$doc_npi = $message->provider[8];
			$doc_name_type = $message->provider[9];
			$doc_id_type = $message->provider[12];
			
			$doc_lname = $message->provider[1];
			$doc_fname = $message->provider[2];
			$doc_mname = $message->provider[3];
			$doc_suffix = $message->provider[4];
			$doc_title = $message->provider[5];
			
			$pat_lname = $message->name[0];
			$pat_fname = $message->name[1];
			$pat_mname = $message->name[2];
			$pat_suffix = $message->name[3];
			$pat_title = $message->name[4];
			if (strtotime($message->dob) !== false) {
				$pat_DOB = date('Y-m-d',strtotime($message->dob));
			}
			$pat_sex = strtoupper(substr($message->sex,0,1));
				
			// validate pid (need patient to verify order)
			if ($message->pid) { // check pid
				$patpid = $message->pid;
				if (is_array($message->pid)) $patpid = $message->pid[0];
				$patient = sqlQuery("SELECT `pid` FROM `patient_data` WHERE(`pubpid` = ? OR `pid` = ?) and `DOB` = ?", array($patpid, $patpid, $pat_DOB) );
				if ($patient['pid']) {
					$pid = $patient['pid'];
					echo "NOTICE: PATIENT MATCHED USING PID AND BIRTHDATE\n";
				}
			}
			
			if (!$pid && $message->pubpid && $pat_DOB) { // maybe they used pubpid
				$pubpid = $message->pubpid;
				if (is_array($message->pubpid)) $pubpid = $message->pubpid[0];
				$patient = sqlQuery("SELECT `pid` FROM `patient_data` WHERE (`pubpid` = ? OR `pid` = ?) and `DOB` = ?", array($pubpid, $pubpid, $pat_DOB) );
				if ($patient['pid']) {
					$pid = $patient['pid'];
					echo "NOTICE: PATIENT MATCHED USING PUBPID AND BIRTHDATE\n";
				}
			}
			
			if (!$pid && $message->extpid) { // maybe they used extpid
				$extpid = $message->extpid;
				if (is_array($message->extpid)) $extpid = $message->extpid[0];
				$patient = sqlQuery("SELECT `pid` FROM `patient_data` WHERE (`pubpid` = ? OR `pid` = ?) and `DOB` = ?", array($extpid, $extpid, $pat_DOB) );
				if ($patient['pid']) {
					$pid = $patient['pid'];
					echo "NOTICE: PATIENT MATCHED USING EXTERNAL PID AND BIRTHDATE\n";
				}
			}
			
			if (!$pid && $pat_lname && $pat_fname && $pat_DOB && $pat_sex) { // try data lookup without id
				$query = "SELECT `pid` FROM `patient_data` WHERE ";
				$query .= "`lname` LIKE ? AND `fname` like ? AND `DOB` = ? AND LEFT(`sex`,1) LIKE ? ";
				$patient = sqlQuery($query, array($pat_lname, $pat_fname, $pat_DOB, $pat_sex));
				if ($patient['pid']) {
					$pid = $patient['pid'];
					echo "NOTICE: PATIENT MATCHED USING PATIENT DATA \n";
				}
			}
			
			// look for original order
			$ordered = array();
			$last_updated = false;
			if ($order_number && $pid) {
				$order = sqlQuery("SELECT `id` FROM `form_laboratory` WHERE `order_number` = ? AND `pid` = ? AND `lab_id` = ? ",array($message->order_number, $pid, $lab_id));
				if ($order['id']) {
					$order_id = $order['id'];
					echo "NOTICE: ORDER MATCHED USING ORDER NUMBER AND PID\n";
				}
			}
			
			if (!$order_id && $order_number) {
				$order = sqlQuery("SELECT `id` FROM `form_laboratory` WHERE `order_number` = ? AND `lab_id` = ? ",array($message->order_number, $lab_id));
				if ($order['id']) {
					$order_id = $order['id'];
					echo "NOTICE: ORDER MATCHED USING ORDER NUMBER AND ACCESSION\n";
				}
			}
			
			// order record found
			if ($order_id) {
				$order_data = new LabOrder('laboratory', $order_id);
				$encounter = $order_data->encounter_id;
				$last_updated = Tools::GetSeconds($order_data->report_datetime);
				
				// use order pid if not otherwise determined
				if (!$pid && $order_data->pid) {
					$patient = sqlQuery("SELECT `pid` FROM `patient_data` WHERE `pid` = ? and `DOB` = ?", array($order_data->pid, $pat_DOB) );
					if ($patient['pid']) {
						$pid = $patient['pid'];
						echo "NOTICE: PATIENT MATCHED USING ORDER PID \n";
					}
				}
			} else {
				$order_id = '';
				$order_data = null;
				$ordered = array();
				echo "WARNING: NO MATCHING ORDER FOUND FOR THESE RESULTS \n";
			}
			
			// patient record located
			if ($pid) { // patient found
				$message->pid = $pid;
				$patient_data = Patient::getPid($pid);
			} else {
				$patient_data = null; // make SURE no patient
				$message->pid = false; // make sure no bogus pid is present
				echo "WARNING: NO MATCHING PATIENT FOUND FOR THIS PATIENT DATA \n";
			}
			
			// validate result provider
			$provider_id = '';
			if ($message->provider[8]) {
				$provider = sqlQuery("SELECT `id` FROM `users` WHERE `npi` LIKE ?",array($message->provider[8]));
				if ($provider['id']) {
					$provider_id = $provider['id']; // use result provider if found
				}
			}
			
			if (!$provider_id && $patient['providerID']) {
				$provider_id = $patient['providerID'];
			}
			
			if (!$provider_id && $order_data->provider_id) {
				$provider_id = $order_data->provider_id;
			}
			
			if ($provider_id) { // provider found
				$doctor_data = new Provider($provider_id);
			} else {
				$provider_id = null;
				$provider_username = '';
			}
			
			// validate account
			$facility_id = '';
			if ($message->account) { // from result record
				$acct_list = new Options('Lab_'.$lab_data->lab_recv_app_id.'_Accounts');
				foreach ($acct_list->list AS $item) {
					if ($message->account == $item['title']) {
						$facility_id = $item['option_id']; // OpenEMR facility id
					}
				}
			}
			
			if (!$facility_id && $order_data->facility_id) { // use original order if available
				$facility_id = $order_data->facility_id;
			}
			
			if (!$facility_id && $provider['facility_id']) {
				$facility_id = $provider['facility_id'];
			}
			
			if ($facility_id) { // facility found
				$facility_data = new Facility($facility_id);
				$facility_name = $facility_data->name;
			} else {
				$facility_id = null;
				$facility_name = "UNKNOWN";
			}
				
			/* --------------------------------------------------------------------------- *
			 *   Basic validation
			 * --------------------------------------------------------------------------- */
			
			if (!$message->order_number && !$message->control_id) {
				$errors = true;
				echo "FATAL ERROR: NO CLINIC OR LAB ORDER IDENTIFIER \n\n";
				continue;  // on to the next one
			}
			if (!$message->name && !$message->pid) {
				$errors = true;
				echo "FATAL ERROR: NO PATIENT IDENTIFIER OR PATIENT NAME \n\n";
				continue;  // on to the next one
			}
			
			/* --------------------------------------------------------------------------- *
			 *   Store processing laboratory information
			 * --------------------------------------------------------------------------- */
			
			// store lab facility data
			$labs = array(); // for new labs
			
			if ($message->labs) {
				foreach ($message->labs AS $lab) {
					$lab_phone = '';
					if ($lab->phone) $lab_phone = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $lab->phone);
					
					$lab_director = '';
					$lab_npi = '';
					if (is_array($lab->director)) {
						if ($lab->director[5]) $lab_director = $lab->director[5]." ";
						$lab_director .= $lab->director[2]." ".$lab->director[3]." ".$lab->director[1];
						$lab_npi = $lab->director[0];
					} else {
						$lab_director = $lab->director;
					}
					
					$lab_street = '';
					$lab_state = '';
					$lab_city = '';
					$lab_zip = '';
					if (is_array($lab->address)) {
						$lab_street = $lab->address[0];
						$lab_city = $lab->address[2];
						$lab_state = $lab->address[3];
						$lab_zip = $lab->address[4];
					}
					
					if ($debug) {
						echo "\nLab Code: $lab->code \n";
						echo "Lab Name: $lab->name \n";
						if ($lab_phone) echo "Phone: $lab_phone \n";
						if ($lab_street) {
							echo "Address: $lab_street \n";
							echo "$lab_city, $lab_state $lab_zip \n";
						}
						echo "Director: $lab_director \n";
					}
					
					// create/update lab facility record
					$query = "REPLACE INTO `procedure_facility` SET `code` = ?, `type` = ?, `name` = ?, `street` = ?, `city` = ?, `state` = ?, `zip` = ?, `phone` = ?, `director` = ?, `npi` = ?, `lab_id` = ?";
					
					$binds = array();
					$binds[] = $lab->code;
					$binds[] = $lab->code_type;
					$binds[] = $lab->name;
					$binds[] = $lab->address[0];
					$binds[] = $lab->address[2];
					$binds[] = $lab->address[3];
					$binds[] = $lab->address[4];
					$binds[] = $lab_phone;
					$binds[] = $lab_director;
					$binds[] = $lab_npi;
					$binds[] = $lab_id;
					
					// run the database command
					sqlStatement($query, $binds);
				}
			}
			
			/* --------------------------------------------------------------------------- *
			 *   Update original order as necessary
			 * --------------------------------------------------------------------------- */
			
			// set order date
			$odate = ($order_data->date_ordered) ? $order_data->date_ordered : $message->specimen_datetime;
			
			// create orphan record (no patient && no order)
			if (!$pid && !$order_id) {
				$new_order = '';
				if (!$message->order_number) {
					$new_order = 'NONE';
					echo "WARNING: NO NUMBER PROVIDED - ORDER NUMBER GENERATED FOR THESE RESULTS \n";
					$message->order_number = $GLOBALS['adodb']['db']->GenID('order_seq');
				}
				
				// watch out for generated order number already assigned
				$dupchk = sqlQuery("SELECT `procedure_order_id` AS `id` FROM `procedure_order` WHERE `procedure_order_id` = ?",array($message->order_number));
				if ($dupchk) {
					$new_order = $message->order_number;
					echo "WARNING: ORDER NUMBER EXISTS - NEW NUMBER GENERATED FOR THESE RESULTS \n";
					while ($dupchk) { // loop until a good number found
						$new_order = $GLOBALS['adodb']['db']->GenID('order_seq');
						$dupchk = sqlQuery("SELECT `procedure_order_id` AS `id` FROM `procedure_order` WHERE `procedure_order_id` = ?",array($new_order));
					}
					$message->order_number = $new_order;
				}
				
				// generate a new orphan order
				$order_data = new LabOrphan();
				
				// indicate orphan record
				$order_data->pid = $lab_data->lab_orphan_pid;
				
				// orphan matching data
				$order_data->pat_lname = $pat_lname;
				$order_data->pat_mname = $pat_mname;
				$order_data->pat_fname = $pat_fname;
				$order_data->pat_suffix = $pat_suffix;
				$order_data->pat_title = $pat_title;
				$order_data->pat_DOB = $pat_DOB;
				$order_data->pat_sex = $pat_sex;
				$order_data->doc_npi = $doc_npi;
				$order_data->doc_lname = $doc_lname;
				$order_data->doc_mname = $doc_mname;
				$order_data->doc_fname = $doc_fname;
				$order_data->doc_suffix = $doc_suffix;
				$order_data->doc_title = $doc_title;
				
				// store MU-2 stuff
				$order_data->pat_pubpid = $message->pubpid;
				$order_data->pat_namespace = $message->namespace;
				$order_data->pat_id_type = $message->idtype;
				$order_data->pat_race = $message->race;
				$order_data->pat_ethnicity = $message->ethnicity;
				$order_data->order_namespace = $message->order_namespace;
				$order_data->lab_namespace = $message->lab_namespace;
				$order_data->lab_id_type = $message->lab_id_type;
				$order_data->group_number = $message->group_number;
				$order_data->group_namespace = $message->group_namespace;
				$order_data->doc_name_type = $doc_name_type;
				$order_data->doc_id_type = $doc_id_type;
				
				$order_data->received_datetime = date('Y-m-d H:i:s');
				$reported = Tools::GetSeconds($message->reported_datetime);
				if ($reported === false) $reported = strtotime('NOW');
				$order_data->report_datetime = date('Y-m-d H:i:s', $reported);
				
				// add tag
				$order_data->request_notes = "ORDER GENERATED FROM UNSOLICITED RESULT (PATIENT UNKNOWN)";
				
			}
			
			// create missing order (patient found && no order)
			if ($pid && !$order_id) {
				// build dummy encounter for this patient/result
				$enc_data = new Encounter();
				
				$enc_data->pid = $pid;
				$enc_data->user = 'SYSTEM';
				$enc_data->grouname = 'Default';
				$enc_data->authorized = 1;
				$enc_data->provider_id = $provider_id;
				$enc_data->facility_id = $facility_id;
				$enc_data->billing_facility = $facility_id;
				$enc_data->pc_catid = $lab_data->lab_cat_id;
				$enc_data->form_title = $lab_data->lab_cat_name;
				$enc_data->date = Tools::FormatDateTime($odate);
				$enc_data->form_name = 'encounter';
				$enc_data->reason = 'GENERATED ENCOUNTER FOR '.strtoupper($lab_data->name).' RESULT';
				$enc_data->sensitivity = 'normal';
				
				$enc_data->store();
				$encounter = $enc_data->encounter;
				
				// build dummy order for this patient/result
				$new_order = '';
				if (!$message->order_number) {
					$new_order = 'NONE';
					echo "WARNING: NO NUMBER PROVIDED - ORDER NUMBER GENERATED FOR THESE RESULTS \n";
					$message->order_number = $GLOBALS['adodb']['db']->GenID('order_seq');
				}
				
				// watch out for generated order number already assigned
				$dupchk = sqlQuery("SELECT `procedure_order_id` AS `id` FROM `procedure_order` WHERE `procedure_order_id` = ?",array($message->order_number));
				if ($dupchk) {
					$new_order = $message->order_number;
					echo "WARNING: ORDER NUMBER EXISTS - NEW NUMBER GENERATED FOR THESE RESULTS \n";
					while ($dupchk) { // loop until a good number found
						$new_order = $GLOBALS['adodb']['db']->GenID('order_seq');
						$dupchk = sqlQuery("SELECT `procedure_order_id` AS `id` FROM `procedure_order` WHERE `procedure_order_id` = ?",array($new_order));
					}
					$message->order_number = $new_order;
				}
				
				// generate a new order
				$order_data = new LabOrder();
				
				// add tag
				$order_data->request_notes = "ORDER GENERATED FROM UNSOLICITED RESULT (PATIENT FOUND)";
				
			}
			
			// finish new order or orphan record
			if ($order_data && !$order_data->id) {
				
				// build dummy order for this result
				$order_data->date = date('Y-m-d H:i:s');
				$order_data->activity = 1;
				$order_data->user = ($authuser)? $authuser: 'SYSTEM';
				$order_data->groupname = ($groupname)? $groupname: 'Default';
				$order_data->authorized = $authorized;
				$order_data->facility_id = ($facility_id)? $facility_id : null;
				$order_data->lab_id = $lab_id;
				$order_data->pid = ($pid)? $pid : 0;
				$order_data->encounter = $encounter;
				$order_data->doc_npi = $message->provider[0];
				
				// order specific information
				$order_data->order_number = $message->order_number;
				$order_data->control_id = $message->control_id;
				$order_data->date_ordered = Tools::FormatDateTime($odate);
				$order_data->date_collected = Tools::FormatDateTime($message->specimen_datetime);
				$order_data->date_transmitted = Tools::FormatDateTime($message->received_datetime);
				$order_data->account = ($message->account) ? $message->account : $message->facility_id;
				$order_data->billing_type = $message->bill_type;
				$order_data->request_notes = $message->additional_data;
				$order_data->order_status = 'complete';
				
				// save order/orphan record
				$order_id = $order_data->store();
			}
			
			/* --------------------------------------------------------------------------- *
			 *   Process each of the result items (one report per ordered item)
			 * --------------------------------------------------------------------------- */
			
			$items = array(); // for new tests
			$count = (is_countable($message->reports))? count($message->reports) : 0;
			if ($count > 0) { // do we have anything to process?
				
				// remove non-client order items from this order (lab added items)
				sqlStatement("DELETE FROM `procedure_order_code` WHERE `procedure_order_id` = ? AND `procedure_source` != 1",
					array($order_data->order_number));
				// remove old result data for this order
				sqlStatement("DELETE FROM `procedure_result` WHERE `procedure_report_id` IN ".
					"(SELECT `procedure_report_id` FROM `procedure_report` WHERE `procedure_order_id` = ?);",
					array($order_data->order_number));
				/* ---- remove old specimen data
				 sqlStatement("DELETE FROM `procedure_specimen` WHERE `procedure_report_id` IN ".
				 "(SELECT `procedure_report_id` FROM `procedure_report` WHERE `procedure_order_id` = ?);",
				 array($message->order_number));
				 ----- */
				// remove the old report
				sqlStatement("DELETE FROM `procedure_report` WHERE `procedure_order_id` = ?",
					array($order_data->order_number));
				
				$next = sqlQuery("SELECT MAX(`procedure_order_seq`) AS seq FROM `procedure_order_code` WHERE `procedure_order_id` = ?", array($order_data->order_number));
				$next_seq = $next['seq']; // used for unmatched result items (added tests)
				$final_status = 'z';
				$items_abnormal = 0;
				
				foreach ($message->reports as $report) {
					$parent_code = $parent_set = '';
					if ($report->parent_id) {
						$parent = explode('&', $report->parent_id[0]);
						$parent_code = $parent[0];
						$parent_name = $parent[1];
						$parent_set = $report->parent_id[1];
					}
					
					if ($debug) {
						echo "\nTest Ordered: ".$report->service_id[0]." - ".$report->service_id[1]."\n";
						echo "Specimen Date: ".date('Y-m-d H:i:s', strtotime($report->specimen_datetime))." \n";
						echo "Resulted Date: ".date('Y-m-d H:i:s', strtotime($report->result_datetime))." \n";
						echo "Result Status: $report->result_status \n";
						if ($parent_code)
							echo "Parent Code: $parent_code ($parent_set) - $parent_name \n";
							if ($ordered[$report->service_id[0]])
								echo "NOTICE: FOUND ORDER DETAIL RECORD\n";
							else
								echo "NOTICE: CREATED NEW ORDER DETAIL\n";
					}
					
					// check for order item record
					if ($ordered[$report->service_id[0]]) {
						$item_seq = $ordered[$report->service_id[0]];
					}
					else { // no order item so created one
						$next_seq++;
						$item_data = new LabOrderItem();
						$item_data->lab_id = $lab_id;
						$item_data->procedure_order_id = $order_data->order_number;
						$item_data->procedure_order_seq = $next_seq;
						$item_data->procedure_code = $report->service_id[0];
						$item_data->procedure_name = $report->service_id[1];
						$item_data->procedure_name .= ($parent_code)? " [REFLEX]" : " [ADDED]";
						$item_data->procedure_source = ($parent_code)? 3 : 2; // reflex or other add
						$item_data->reflex_code = $parent_code;
						$item_data->reflex_set = $parent_set;
						$item_data->reflex_name = $parent_name;
						$item_seq = $next_seq;
						
						$item_data->store();
					}
					
					// create new report record
					$report_data = new LabResult();
					
					// create report data
					$report_data->procedure_order_id = $order_data->order_number;
					$report_data->procedure_order_seq = $item_seq;
					$report_data->source = 0; // userid of clinician
					$report_data->lab_id = $lab_id;
					$report_data->specimen_num = $control_id;
					if (!$report_data->specimen_num) $report_data->specimen_num = $order_data->order_number;
					
					$report_data->report_status = 'Preliminary';
					if ($report->result_status == 'F') {
						$report_data->report_status = 'Final';
					}
					
					$report_data->review_status = 'Received';
					
					$report_data->date_collected = Tools::FormatDateTime($message->specimen_datetime);
					
					$report_data->date_report = '';
					$reported = Tools::GetSeconds($message->reported_datetime);
					if ($reported === false) $reported = strtotime('NOW');
					$report_data->date_report = date('Y-m-d H:i:s', $reported);
					
					// store general notes
					$report_data->report_notes = ''; // combine notes
					if ($report->notes) {
						$note_text = '';
						foreach ($report->notes AS $note) {
							if ($note_text) $note_text .= "<br/>";
							$note_text .= htmlentities($note->comment);
						}
						$report_data->report_notes = $note_text;
					}
					
					// cumulative status from all result items (F=final, C=corrected, X=cancelled by lab)
					if ($final_status == 'z') { // still default
						if ($report->result_status == 'X') $final_status = 'c';
						if ($report->result_status != 'F' && $report->result_status != 'C' && $report->result_status != 'X') $final_status = 'x';
					}
					
					// save result report record
					$report_id = $report_data->store();
					
					/* --------------------------------------------------------------------------- *
					 *   Process each discrete result for the current report item
					 * --------------------------------------------------------------------------- */
					
					$count = (is_countable($report->results))? count($report->results) : 0;
					if ($count > 0) { // do we have results for this order?
						foreach ($report->results as $result) {
							
							// merge notes into a single field
							$notes = '';
							if ($result->notes) {
								foreach ($result->notes as $note) {
									if ($notes) $notes .= "<br/>";
									$notes .= htmlentities($note->comment);
								}
							}
							
							if ($debug) {
								echo "\nValue Type: $result->value_type \n";
								echo "LOINC Code: ".$result->observation_id[0]." \n";
								echo "LOINC Text: ".$result->observation_id[1]." \n";
								if ($result->observation_id[3]) {
									echo "Observation Code: ".$result->observation_id[3]." \n";
									echo "Observation Text: ".$result->observation_id[4]." \n";
								}
								echo "Observed Value: $result->observation_value \n";
								echo "Observed Units: $result->observation_units \n";
								echo "Observed Range: $result->observation_range \n";
								echo "Observed Status: $result->observation_status \n";
								echo "Observed Abnormal: $result->observation_abnormal \n";
								echo "Observed Date: " .Tools::FormatDateTime($result->observation_datetime). "\n";
								echo "Observed Lab: $result->producer_id \n";
								if ($notes) echo "NOTES:\n $notes\n";
							}
							
							// fetch existing result data
							$result_id = '';
							$results = sqlQuery("SELECT `procedure_result_id` FROM `procedure_result` WHERE `procedure_report_id` = ? AND `result_code` LIKE ?",
								array($report_id,$result_code));
							if ($results) $result_id = $results['procedure_result_id'];
							$result_data = new LabResultItem($result_id);
							
							// default form data
							$result_data->facility = $result->producer_id;
							$result_data->procedure_report_id = $report_id;
							$result_data->result_data_type = $result->value_type;
							$result_data->result_code = $result->observation_id[0];
							$result_data->result_text = $result->observation_id[1];
							if ($result->observation_id[4]) $result_data->result_text = $result->observation_id[4];
							
							$result_data->result_set = $result->observation_set;
							
							$result_data->date = Tools::FormatDateTime($reported); // default to report date
							$result_data->date = Tools::FormatDateTime($result->observation_datetime);
							
							$obvalue = $result->observation_value;
							if (is_array($obvalue)) {
								$obvalue = $obvalue[0]; // save text portion
							}
							$result_data->result = $obvalue;
							
							$result_data->units = $result->observation_units;
							if (is_array($result_data->units)) $result_data->units = $result_data->units[1];
							$result_data->range = $result->observation_range;
							
							$result_data->result_status = 'Preliminary';
							if ($result->observation_status == 'F')	$result_data->result_status = 'Final';
							if ($result->observation_status == 'X')	$result_data->result_status = 'Cancel';
							if ($result->observation_status == 'C')	$result_data->result_status = 'Corrected';
							
							$result_data->abnormal = $result->observation_abnormal;
							if ($result_data->abnormal && $result_data->abnormal != 'N') $items_abnormal++;
							if ($notes) $result_data->comments = $notes;
							
							$result_id = $result_data->store();
							$items[] = $result_id;
							
							
						} // end result loop
					} // end results check
					
					
				} // end order loop
			} // end order check
			
			/* --------------------------------------------------------------------------- *
			 *   Generate result pdf document(s) except for Quest
			 * --------------------------------------------------------------------------- */
			if ($lab_data->type == 'G' || $lab_data->type == 'L') {
				
				// store the final result
				$message->report_status = 'PRELIMINARY';
				
				// SPECIAL FOR CERNER BUT DOES NOT HURT ANYBODY ELSE
				if ($message->order_control == 'CA') $final_status = 'c'; // order cancelled by lab
				
				if ($final_status == 'z') $message->report_status = 'FINAL REPORT';
				if ($final_status == 'c') $message->report_status = 'ORDER CANCELLED';
				
				// SPECIAL FOR BOYCE BYNUM
				if ($lab_data->npi == 'BBPL') {
					// use BBPL document
					$documentData = base64_decode($message->images[0]);
				}
				else {
					// generate observation results document
					$documentData = $reporter->makeResultDocument($message, $lab_data);
				}

				// Generic labs have only one document
				$document = new stdClass;
				$document->documentData = $documentData;
				$message->documents[] = $document;
			}
			
			/* --------------------------------------------------------------------------- *
			 *   Store the result pdf document(s)
			 * --------------------------------------------------------------------------- */
			// validate the respository directory
			$repository = $GLOBALS['oer_config']['documents']['repository'];
			$file_path = $repository . preg_replace("/[^A-Za-z0-9]/","_",$pid) . "/";
			if (!file_exists($file_path)) {
				if (!mkdir($file_path,0700)) {
					throw new Exception("The system was unable to create the directory for this result, '" . $file_path . "'.\n");
				}
			}
			
			$docnum = 0;
			$documents = array();
			// store all of the documents
			foreach ($message->documents as $document) {
				if ($document->documentData) {
					$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
					$doc_name = $order_data->order_number . "_RESULT";
					
					$docnum++;
					$file = $doc_name."_".$unique.".pdf";
					while (file_exists($file_path.$file)) { // don't overlay duplicate file names
						$doc_name = $order_data->order_number . "_RESULT_".$docnum++;
						$file = $doc_name."_".$unique.".pdf";
					}
					
					if (($fp = fopen($file_path.$file, "w")) == false) {
						throw new Exception('Could not create local file ('.$file_path.$file.')');
					}
					fwrite($fp,$document->documentData);
					fclose($fp);
					
					if ($debug) echo "\nDocument Name: " . $file;
					
					// register the new document
					$d = new Document();
					$d->name = $doc_name;
					$d->storagemethod = 0; // only hard disk sorage supported
					$d->url = "file://" .$file_path.$file;
					$d->mimetype = "application/pdf";
					$d->size = filesize($file_path.$file);
					$d->owner = 'system';
					$d->hash = sha1_file( $file_path.$file );
					$d->type = $d->type_array['file_url'];
					$d->set_foreign_id($pid);
					$d->persist();
					$d->populate();
					
					$documents[] = $d; // save for later
					
					// update cross reference
					$doc_id = $d->get_id();
					$category = sqlQuery("SELECT id FROM categories WHERE name LIKE ?",array($lab_data->name));
					if (!$category) {
						$category = sqlQuery("SELECT id FROM categories WHERE name LIKE 'Lab Report'");
					}
					if ($category['id'] && $doc_id)
						sqlInsert("REPLACE INTO categories_to_documents SET category_id = ?, document_id = ?",array($category['id'], $doc_id) );
					else
						die ("\n\nMISSING DOCUMENT CATEGORY FOR [$lab_data->name] OR DOCUMENT [$doc_id] MISSING !!");
							
					if ($debug) echo "\nDocument Completion: SUCCESS\n";
				}
			}
			
			/* --------------------------------------------------------------------------- *
			 *   Update the order with the revised status
			 * --------------------------------------------------------------------------- */
			$order_data->received_datetime = date('Y-m-d H:i:s');
			$order_data->report_datetime = ($last_updated)? Tools::FormatDateTime($last_updated) : date('Y-m-d H:i:s');
			$order_data->result_abnormal = ($items_abnormal > 0)? $items_abnormal : 0;
			if ($order_data->review_notes) {
				$order_data->review_notes .= "\nNEW RESULTS RECEIVED: ".date('Y-m-d H:i:s');
			}
			$order_data->reviewed_id = '';
			$order_data->reviewed_datetime = 'NULL';
			$order_data->notified_id = '';
			$order_data->notified_datetime = 'NULL';
			$order_data->notified_person = '';
			
			if ($final_status) {
				$order_data->status = $final_status; // final results completed
				if ($final_status == 'z') $order_data->order_status = 'complete';
			}
			
			// store only the first document reference
			if ($docnum > 0) $order_data->result_doc_id = $documents[0]->get_id();
			
			$order_data->store();
			
			/* --------------------------------------------------------------------------- *
			 *   Display final processing results
			 * --------------------------------------------------------------------------- */
			$doccnt = 0;
			foreach ($documents as $document) {
				$doccnt++;
				if ($debug) {
					echo "Document Title: ".$document->name." \n";
					echo "Document link: /controller.php?document&retrieve&patient_id=".$pid."&document_id=".$document->get_id()." \n\n";
				}
			}
			
			// LAST... prepare acknowledgement
			// Quest, collect and send later
			// CPL, send immediately with each result
			if ($lab_data->type == 'Q') {
				$acks[] = $client->buildResultAck($message->message_id);
			} elseif ($lab_data->type == 'G') {
				$client->ackResult($message->message_id, $debug);
			}
			
			$count = (is_countable($items))? count($items) : 0;
			if ($debug) {
				// display final results
				echo "\n\n";
				echo "STORED RECORDS: ".$count;
				echo "\nTOTAL DOCUMENTS: ".$doccnt;
				echo "\nACKNOWLEDGMENT: [CA] Result processed (ORDER: ".$order_data->order_number." LAB: ".$message->control_id.")";
			}
			else {
				echo "DATE: ".date('Y-m-d H:i:s')." -- ORDER: ".$order_data->order_number." -- LAB: ".$message->control_id." -- PID: ".$message->pid." -- DOCUMENTS: ".$doccnt." -- RESULTS: ".$count."\n";
			}
			
			$output = ob_get_flush();
			
			// Falling through to here indicates success!!
			$user = ($_SESSION['authUser'])? $_SESSION['authUser']: 'system';
			$event = "Batch Result - DATE: ".date('Y-m-d H:i:s')." -- ORDER: ".$order_data->order_number." -- LAB: ".$message->control_id." -- PID: ".$message->pid;
			EventAuditLogger::instance()->newEvent("lab-results", $user, "DEFAULT", true, $event, $message->pid);
			
			$params = array();
			$query = "INSERT INTO procedure_batch SET ";
			$query .= "date = ?,";				$params[] = date('Y-m-d H:i:s');
			$query .= "pid = ?, ";				$params[] = $pid;
			$query .= "user = ?, ";				$params[] = $user;
			$query .= "lab_id = ?, ";			$params[] = $lab_id;
			$query .= "facility_id = ?, ";		$params[] = $order_data->facility_id;
			$query .= "order_number = ?, ";		$params[] = $order_data->order_number;
			$query .= "order_date = ?, ";		$params[] = $order_data->date_ordered;
			$query .= "report_date = ?, ";		$params[] = $report_data->date_report;
			$query .= "provider_id = ?, ";		$params[] = $order_data->provider_id;
			$query .= "provider_npi = ?, ";		$params[] = $doc_npi;
			$query .= "pat_dob = ?, ";			$params[] = $order_data->pat_DOB;
			$query .= "pat_first = ?, ";		$params[] = $pat_fname;
			$query .= "pat_middle = ?, ";		$params[] = $pat_mname;
			$query .= "pat_last = ?, ";			$params[] = $pat_lname;
			$query .= "lab_number = ?, ";		$params[] = $report_data->specimen_num;
			$query .= "lab_status = ?, ";		$params[] = $report_data->report_status;
			$query .= "hl7_message = ? ";		$params[] = $message->hl7message;
			sqlStatementNoLog($query,$params);
			
			/* --------------------------------------------------------------------------- *
			 *   Send message to provider when possible
			 * --------------------------------------------------------------------------- */
			if ($pid && $pid != '1') {
				$link_ref = "../../forms/laboratory/view.php?pop=1&id=".$order_data->id."&pid=".$pid."&enc=".$encounter;
				
				$note = "\n\n";
				$note .= $lab_data->name." results received for patient '".$pat_fname." ".$pat_lname."' (pid: ".$pid.") order number '".$order_data->order_number."'. ";
				$note .= "To review these results click on the following link: ";
				$note .= "<a href='". $link_ref ."' target='_blank' class='link_submit' onclick='top.restoreSession()'>". $lab_data->name ." Results - ". $order_data->order_number ." (". $message->control_id .")</a>\n\n";
				labPnote($pid, $note, $doctor_data->username);
			}
			else {
				$note = "Laboratory results received for an unknown patient. ";
				$note .= "\n\nThe information provided indicates the results are for patient '".$message->name[1]." ".$message->name[0]."' (pid: ".$message->pid.") ";
				$note .= "and order number '".$order_data->order_number."'. ";
				$note .= "Please use the Orphan Lab Results report to assign these results to a valid patient.\n\n";
				labPnote($pid, $note, $doctor_data->username);
			}
		}
		
		
		// send the acknowledgements
		$count = (is_countable($acks))? count($acks) : 0;
		if ($lab_data->type == 'Q' && empty($from_date) && $count > 0) {
			if ($debug) {
				echo "\nACK RESPONSE ID: ".$response_id;
				foreach ($acks AS $ack) {
					echo "\nACK MESSAGE ID: ".$ack->resultId." - CODE: ".$ack->ackCode;
				}
			}
			$client->sendResultAck($response_id, $acks, $debug);
		}
		
		echo "\n\nEND OF BATCH PROCESSING: ".date('Y-m-d H:i:s')."\n\n\n";
		exit();
		
	} catch (Exception $e) {
		$logger = new SystemLogger();
		$msg = $e->getMessage();
		$logger->error($msg);
		die($msg);
	}
}

// special pnote insert function
function labPnote($pid, $newtext, $assigned_to = '', $datetime = '') {
	if ($pid < 2) return; // do not generate messages without a pid
	
	$note_list = new Options('Lab_Notification');
	$default = $note_list->getDefault();
	
	$message_sender = 'SYSTEM';
	$message_group = 'Default';
	$authorized = '0';
	$activity = '1';
	$title = 'Lab Results';
	$message_status = 'New';
	if (empty($datetime)) $datetime = date('Y-m-d H:i:s');
	
	// notify doctor or doctor's nurse or default?
	$notify = $assigned_to; // provider
	$notify = $note_list->getItem($assigned_to); // nurse
	if (!$notify) $notify = $default['title']; // default
	if (!$notify) return;  // nobody to send message to
	
	$body = date('Y-m-d H:i') . ' (Laboratory to '. $notify .') ' . $newtext;
	return sqlInsert("INSERT INTO pnotes (date, body, pid, user, groupname, " .
		"authorized, activity, title, assigned_to, message_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		array($datetime, $body, $pid, $message_sender, $message_group, $authorized, $activity, $title, $notify, $message_status) );
}

// Start processing

?><!DOCTYPE html>
<head>
	<?php Header::setupHeader(); ?>

	<title>Laboratory Processing</title>
	<style>
		#report_inputs { float:left;padding:6px 10px;border-radius:8px;border:solid var(--gray300) 1px;box-shadow:2px 2px 2px var(--light);max-width:85%;margin-right:20px; }
		#report_inputs div { float:left;margin: 2px 10px 2px 0; }
		#report_buttons { float:left;margin:4px 0; }
	</style>
	
	<!-- Set system variables in local scope -->
	<script>
		var currentMonth = "<?php echo ltrim(date('m'), '0') ?>";
		var currentYear = "<?php echo date('Y') ?>";
		var errorMessage = "<?php echo $errorMessage ?>";
		
		var alertMessage = "<?php echo $alertMessage ?>";
		if (alertMessage != "") alert(alertMessage);
	</script>

</head>

<body class="body_top">
	<div id="container">

		<header>
			<!-- HEADER (if desired) -->
			<span class="title" style="margin-left:10px">Laboratory - Batch Processing</span>
		</header>

		<div id="content">
			<form method='post' name='theform' id='theform'>
				<input type="hidden" id="process" name="process" value="report" />
	
				<div id="report_parameters" class="clearfix">
	
					<!-- REPORT PARAMETERS -->
					<div id="report_inputs">
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Processor:</div>
							<select class="form-control form-control-sm w-auto" name='lab' id='lab_id'>
								<option value=''>-- select --</option>
<?php 
	// Build a drop-down list of processor names.
	$query = "SELECT `ppid`, `name` FROM `procedure_providers` ";
	$query .= "WHERE NULLIF(`type`,'') IS NOT NULL ORDER BY `name`";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$ppid = $row['ppid'];
		echo "    <option value='$ppid'";
		if ($ppid == $form_processor) echo " selected";
		echo ">" . $row['name'] . "\n";
	}
?>
							</select>
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Start Date:</div>
							<input class="form-control form-control-sm w-auto" type='date' name='from' id='start_date'
									value='<?php echo $form_start ?>' />
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">End Date:</div>
							<input class="form-control form-control-sm w-auto" type='date' name='thru' id='end_date'
									value='<?php echo $form_end ?>' />
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Include Details:</div>
							<input type='checkbox' class="form-check-input" id='form_detail' name='debug' 
										value="1" <?php if ($form_detail) echo "checked" ?> />
						</div>
					</div>

					<!-- REPORT BUTTON -->
					<div id="report_buttons">
						<button type="button" class="btn btn-primary" id="btn_report" onclick="doSubmit()">Submit</button>
					</div>

				</div>

				<div class="m-2">
					Leave the date fields <b>BLANK</b> for normal processing.
					Enter dates <b>ONLY</b> if previously processed results must be re-processed.  
					<br/>
					The dates entered represent the dates the result transactions where originally processed by the gateway.
					<br/> 
 					Select whether to display processing details using the checkbox and click <b>Submit</b>.
				</div>
				
				<input type="hidden" name="browser" value="1" />
			</form>

			<div id="dynamic" style="visibility:hidden">

				<!-- GENERATED OUTPUT -->
				<table style="width:100%" class="display" id="report">
					<thead>  </thead>
					<tbody>  </tbody>
					<tfoot>  </tfoot>
				</table>

			</div>
		</div>

		<footer>
			<!-- FOOTER (if desired) -->
		</footer>

	</div> <!-- end of #container -->


<script>
	function doSubmit() {
		if ($('#lab_id').val() == '') {
			alert("Processor required for execution!!");
			return false;
		} else {
			$('#theform').submit();
		}
	}
	
<?php 
	if ($alertmsg) { echo " alert('$alertmsg');\n"; } 
?>

</script>
</body>

</html>

