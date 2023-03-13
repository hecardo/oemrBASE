<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Labcorp;

use Document;
use phpseclib\Net\SFTP;

use OpenEMR\Common\Logging\SystemLogger;

use WMT\Objects\Patient;
use WMT\Objects\Insurance;

use WMT\Classes\Options;

/**
 * The class OrderClient submits lab order (HL7 messages) to the laboratory.
 */
class LabcorpClient 
{
	private $STATUS;
	private $ENDPOINT;
	private $USERNAME;
	private $PASSWORD;
	private $PORT;
	private $PROTOCOL;
	private $SENDING_APPLICATION;
	private $SENDING_FACILITY;
	private $RECEIVING_APPLICATION;
	private $RECEIVING_FACILITY;	
	private $ORDERS;
	private $RESULTS;
	private $NPI;	
	
	// Document storage directory
	private $DOCUMENT_CATEGORY;
	private $REPOSITORY;
	
	private $logger;
	
	private $insurance = array();
	private $orders = array();
	private $request = null;
	private $response = null;
	private $documents = array();
	
	static $DEBUG = false;

	/**
	 * Constructor for the 'order client' class
	 *
	 * @package LabService
	 * @access public
	 */
	public function __construct($lab_id) {
		$this->lab_id = $lab_id;
		$this->logger = new SystemLogger();
		$this->REPOSITORY = $GLOBALS['oer_config']['documents']['repository'];

		// retrieve processor data
		$processor = sqlQuery("SELECT * FROM procedure_providers WHERE ppid = ?",array($lab_id));
		
		// for now !!
		if ($processor['protocol'] == 'FS2') $processor['protocol'] = 'FSS';
		if ($processor['protocol'] == 'FC2') $processor['protocol'] = 'FSC';
		
		$this->STATUS = 'D'; // default training/development
		if ($processor['DorP']) $this->STATUS = $processor['DorP']; // production
		$this->SENDING_APPLICATION = $processor['send_app_id'];
		$this->SENDING_FACILITY = $processor['send_fac_id'];
		$this->RECEIVING_APPLICATION = $processor['recv_app_id'];
		$this->RECEIVING_FACILITY = $processor['recv_fac_id'];
		$this->ENDPOINT = $processor['remote_host'];
		$this->PROTOCOL = $processor['protocol'];
		$this->PORT = $processor['remote_port'];
		$this->USERNAME = $processor['login'];
		$this->PASSWORD = $processor['password'];
		$this->ORDERS = $processor['orders_path'];
		$this->RESULTS = $processor['results_path'];
		$this->NPI = $processor['npi']; // needed for special processing form specific labs
			
		$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?",array($lab_data->name));
		if (!$category['id']) {
			$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?",array('Lab Report'));
		}
		$this->DOCUMENT_CATEGORY = $category['id'];
		
		// sanity check (download, sFTP client, web service)
		if ($processor['protocol'] == 'DL' || $processor['protocol'] == 'FSC' || $processor['protocol'] == 'WS') {
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->RECEIVING_APPLICATION ||
					!$this->RECEIVING_FACILITY ||
					!$this->SENDING_APPLICATION ||
					!$this->SENDING_FACILITY ||
					!$this->USERNAME ||
					!$this->PASSWORD ||
					!$this->ENDPOINT ||
					!$this->ORDERS ||
					!$this->RESULTS ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				throw new Exception ("Order Interface Not Properly Configured [".$processor['protocol']."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
		// validate (sFTP server)
		elseif ($processor['protocol'] == 'FSS') {
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->RECEIVING_APPLICATION ||
					!$this->RECEIVING_FACILITY ||
					!$this->SENDING_APPLICATION ||
					!$this->SENDING_FACILITY ||
					!$this->ORDERS ||
					!$this->RESULTS ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				throw new Exception ("Order Interface Not Properly Configured [".$processor['protocol']."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
		// internal (no transmission)
		elseif ($processor['protocol'] != 'INT') {
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->RECEIVING_APPLICATION ||
					!$this->RECEIVING_FACILITY ||
					!$this->SENDING_APPLICATION ||
					!$this->SENDING_FACILITY ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				throw new Exception ("Order Interface Not Properly Configured [".$processor['protocol']."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
		else { // internal only
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				throw new Exception ("Order Interface Not Properly Configured [".$processor['protocol']."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
			
		return;
	}

	/**
	 * Appends NTE segments to the end of the current request HL7 message.
	 */
	public function addNotes($notes) {
		if (!$notes) return;
		
		$seq = 1;
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $notes) as $note) {
			$NTE = "NTE|".$seq++."|EMR|".$note."|\r";
			$this->request .= $NTE;
			if ($DEBUG) echo $NTE . "\n";

			if ($seq > 10) break; // maximum segments
		}
		
		return;
	}
	
	/**
	 * Constructs a valid HL7 message string for this order.
 	 *
 	 * @access public
 	 * @param object $order_data Data from form input
	 */
	public function buildRequest(&$order_data) {
		// create the order object
		$this->request = new \stdClass(); // default empty object
		$this->request->hl7Order = '';
		
		// retrieve additional data records
		$patient_data = Patient::getPidPatient($order_data->pid);
		
		if (strtotime($order_data->date_transmitted))
			$odate = date('YmdHis',strtotime($order_data->date_transmitted));
		else 
			$odate = date('YmdHis');
		
		$order_num = $order_data->order_number;
		$account = ($order_data->request_account) ? $order_data->request_account : $this->SENDING_FACILITY;
		if ($this->NPI == 'BIOREF') $account = $this->SENDING_FACILITY; // BioReference Laboratory
		if ($this->NPI == '1194769497' || $this->NPI == 'CPL' || $this->NPI == 'SHIEL') 
			$order_num = $account."-".$order_data->order_number;
			
		// message segment
		$MSH = "MSH|^~\\&|%s|%s|%s|%s|$odate||ORM^O01|$order_num|%s|2.3\r";
		$this->request = sprintf($MSH, $this->SENDING_APPLICATION, $account, $this->RECEIVING_APPLICATION, $this->RECEIVING_FACILITY, $this->STATUS);
		if ($DEBUG) echo $this->request . "\n"; // DEBUG
				
		$pname = $patient_data->lname;
		$pname .= "^".$patient_data->fname;
		$pname .= "^".$patient_data->mname;
		
		$paddress = $patient_data->street;
		$paddress .= "^".$patient_data->street2;
		$paddress .= "^".$patient_data->city;
		$paddress .= "^".$patient_data->state;
		$paddress .= "^".$patient_data->postal_code;
		
		$dob = '';
		if (strtotime($patient_data->DOB))
			$dob = date('Ymd',strtotime($patient_data->DOB));
		
		$sex = substr(strtoupper($patient_data->sex),0,1);
		if (!$sex) $sex = 'U';

		$pat_race = Options::getListItem('Lab_Race', $patient_data->race);
		if ($this->NPI == 'CPL') $pat_race = Options::getListItem('CPL_Race', $patient_data->race);
		$pat_ethnicity = Options::getListItem('Lab_Ethnicity',$patient_data->ethnicity);
		
		if ($pat_race == '* Not Found *') $pat_race = '';
		if ($pat_ethnicity == '* Not Found *') $pat_ethnicity = '';
		
		$phone_home = preg_replace('/[^\d]/','',$patient_data->phone_home);
		if (empty($phone_home) || $phone_home == '0000000' || $phone_home == '0000000000') {
			$phone_home = preg_replace('/[^\d]/','',$patient_data->phone_cell);
		}
		if (empty($phone_home)) $phone_home = '0000000000';
		
		if (strlen($phone_home) > 7) {
			$phone_home = preg_replace ('/^(\d{3})(\d{3})(\d{4})$/', '($1) $2-$3', $phone_home);
		} else {
			$phone_home = preg_replace ('/^(\d{3})(\d{4})$/', '$2-$3', $phone_home);
		}
		if ($this->NPI == 'BIOREF') {
			$phone_home = str_replace('-', '', $phone_home);
			$phone_home .= '^^^';
			$phone_home .= $patient_data->email;
		}
		
		$pat_ss = preg_replace('/[^\d]/','',$patient_data->ss);
		$pat_ss = preg_replace ('/^(\d{3})(\d{2})(\d{4})$/', '$1-$2-$3', $pat_ss);
		
		// patient segment
		if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800') {
			$PID = "PID|1||$patient_data->pubpid|$patient_data->pid|$pname||$dob|$sex||$pat_race|$paddress||$phone_home|||||$order_data->account^^^$order_data->request_billing^$order_data->abn_signed|$pat_ss||||$pat_ethnicity|\r";
		} elseif ($this->NPI == 'BIOREF') {
			$PID = "PID|1|$patient_data->pid||$patient_data->pubpid|$pname||$dob|$sex|||$paddress||$phone_home|||||||||||\r";
		} elseif ($this->NPI == 'CPL') {
			$PID = "PID|1||$patient_data->pubpid|$patient_data->pid|$pname||$dob|$sex||$pat_race|$paddress||$phone_home||$order_data->request_billing|||$pat_ss|$patient_data->drivers_license|||$pat_ethnicity|\r";
		} elseif ($this->NPI == 'BAYLOR') {
			$bg_dob = $dob . '000000';
			$bg_res = sqlQueryNoLog("SELECT `notes` FROM `list_options` WHERE `list_id` LIKE 'race' AND `option_id` LIKE ?", array($patient_data->race));
			$bg_race = (!empty($bg_res['notes']))? $bg_res['notes'] : 'Other';
			$PID = "PID|1||$patient_data->pubpid|$patient_data->pid|$pname||$bg_dob|$sex||$bg_race|$paddress||$phone_home||$order_data->request_billing|||$order_data->account^^^$order_data->request_billing^$order_data->abn_signed|$pat_ss|$patient_data->drivers_license|||$pat_ethnicity|\r";
		} else {
			$PID = "PID|1||$patient_data->pubpid|$patient_data->pid|$pname||$dob|$sex||$pat_race|$paddress||$phone_home|||||$order_data->account^^^$order_data->request_billing^$order_data->abn_signed|||||$pat_ethnicity|\r";
		}
		
		$this->request .= $PID;
		if ($DEBUG) echo $PID . "\n";  // DEBUG

		$notes = '';
		if ($order_data->request_handling == 'stat') {
			$notes = "*** STAT ORDER ***";
			if ($order_data->request_notes) $notes .= "\n".$order_data->request_notes;
		}
		else {
			$notes = $order_data->request_notes;
		}
		
		if ($notes) {
			$this->addNotes($notes);
		}

		if ($this->NPI == 'CERNER') {
			$PV1 = "PV1|1|||||||||||||||||||||||||||||||||||||||||||||||||||||\r";
			$this->request .= $PV1;
		}
		elseif ($this->NPI == 'BIOREF') {
			$PV1 = "PV1|1||$account|||||||||||||||||||||||||||||||||||||||||||||||||||\r";
			$this->request .= $PV1;
		}
			
		return;
	}
	
	/**
	 * Appends IN1 insurance segments to the end of the current request HL7 message.
	 * 
	 * @param int $setid Sequence number of this segment
	 * @param object $ins_data Insurance data object
	 */
	public function addInsurance($setid, $key) {
		if (!$key) return;
		
		// special for BioRef Lab
		if ($this->NPI == 'BIOREF' && ($key == 'P' || $key == 'C')) {
			$ins_identifier = ($key == 'P')? '031' : '333';
			$IN1 = "IN1|$setid||$ins_identifier|||||||||||||||||||||||||||||||||||||||||||\r";
			$this->request .= $IN1;
			if ($DEBUG) echo $IN1 . "\n"; // DEBUG
			return;
		}
		
		// normal processing
		$ins_data = new Insurance($key);
		
		$sname = $ins_data->subscriber_lname;
		$sname .= "^".$ins_data->subscriber_fname;
		$sname .= "^".$ins_data->subscriber_mname;
		
		$iaddress = $ins_data->line1;
		$iaddress .= "^".$ins_data->line2;
		$iaddress .= "^".$ins_data->city;
		$iaddress .= "^".$ins_data->state;
		$iaddress .= "^".$ins_data->zip;
		
		$relation = '3'; // assume other
		if ($ins_data->subscriber_relationship == 'Self') $relation = '1'; 
		if ($ins_data->subscriber_relationship == 'Spouse') $relation = '2';
		
		$dob = '';
		if (strtotime($ins_data->subscriber_DOB))
			$dob = date('Ymd',strtotime($ins_data->subscriber_DOB));
		
		$sex = substr(strtoupper($ins_data->subscriber_sex),0,1);
		if (!$sex) $sex = 'U';

		if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800') {
			$relation = strtoupper($ins_data->subscriber_relationship);
			if ($relation != 'SELF' && $relation != 'SPOUSE' && $relation != 'CHILD') $relation = 'OTHER';
		}

		$ins_identifier = $ins_data->company_id; // --or-- cms_id ?
		if ($this->NPI == 'BIOREF') {
			$ins_identifier = $ins_data->lab_identifier;
		}
		
		$policy = preg_replace('/[^A-Za-z0-9\/]/', '', $ins_data->policy_number);
		$IN1 = "IN1|$setid||$ins_identifier|$ins_data->company_name|$iaddress|||$ins_data->group_number||||||||$sname|$relation|$dob|$iaddress|||||||||||||||||$policy|||||||$sex|||P\r";
			
		$this->request .= $IN1;
		if ($DEBUG) echo $IN1 . "\n"; // DEBUG
	}
	
	/**
	 * Appends GT1 guarantor segment to the end of the current request HL7 message.
	 * 
	 * @param object $guarantor_data Order data object (contains guarantor data)
	 */
	public function addGuarantor($pid) {
		if (!$pid) return;
		$pat_data = Patient::getPidPatient($pid);
		
		$dob = '';
		$sec = 'U'; // assume unknown
		$relation = '3'; // assume other

		if ($pat_data->guarantor_relation == 'Self') $relation = '1'; 
		if ($pat_data->guarantor_relation == 'Spouse') $relation = '2';
		
		if ($pat_data->guarantor_lname) { // if guarantor provided
			$gname = $pat_data->guarantor_lname;
			$gname .= "^".$pat_data->guarantor_fname;
			$gname .= "^".$pat_data->guarantor_mname;
			
			$gaddress = $pat_data->guarantor_street;
			$gaddress .= "^".$pat_data->guarantor_street2;
			$gaddress .= "^".$pat_data->guarantor_city;
			$gaddress .= "^".$pat_data->guarantor_state;
			$gaddress .= "^".$pat_data->guarantor_zip;
		
			if (strtotime($pat_data->guarantor_dob))
				$dob = date('Ymd',strtotime($pat_data->guarantor_dob));
		
			$sex = substr(strtoupper($pat_data->guarantor_sex),0,1);
		}
		else { // default to patient
			$relation = '1'; // self
			$gname = $pat_data->lname;
			$gname .= "^".$pat_data->fname;
			$gname .= "^".$pat_data->mname;
			
			$gaddress = $pat_data->street;
			$gaddress .= "^".$pat_data->street2;
			$gaddress .= "^".$pat_data->city;
			$gaddress .= "^".$pat_data->state;
			$gaddress .= "^".$pat_data->postal_code;
				
			if (strtotime($pat_data->DOB))
				$dob = date('Ymd',strtotime($pat_data->DOB));
				
			$sex = substr(strtoupper($pat_data->sex),0,1);
		}

		if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800') {
			$relation = strtoupper($pat_data->guarantor_relation);
			if ($relation != 'SELF' && $relation != 'SPOUSE' && $relation != 'CHILD') $relation = 'OTHER';
		}
		elseif ($this->NPI == 'BIOREF') {
			$relation = $pat_data->guarantor_relation;
			if ($relation != 'Self' && $relation != 'Spouse' && $relation != 'Child') $relation = 'Other';
		}
			
			
		$GT1 = "GT1|1||$gname||$gaddress|$pat_data->phone_home||$dob|$sex||$relation|\r";
		
		$this->request .= $GT1;
		if ($DEBUG) echo $GT1 . "\n"; // DEBUG
	}
	
	/**
	 * Appends ORC, OBR, DG1 segments to the end of the current request HL7 message.
	 *
	 * @param int $setid Sequence number of this segment
	 * @param object $order_data Order data object
	 * @param array $test_data Test data 
	 */
	public function addOrder($setid, &$order_data, &$item_data, &$aoe_list) {
		if (strtotime($order_data->date_transmitted))
			$odate = date('YmdHis',strtotime($order_data->date_transmitted));
		else 
			$odate = date('YmdHis');
		
		$order_num = $order_data->order_number;
		$account = ($order_data->request_account) ? $order_data->request_account : $this->SENDING_FACILITY;  
		if ($this->NPI == '1194769497' || $this->NPI == 'CPL' || $this->NPI == 'SHIEL') 
			$order_num = $account."-".$order_data->order_number;
		elseif ($this->NPI == 'BIOREF') // BioReference Laboratory
			$order_num = $order_data->order_number."^".$this->SENDING_FACILITY;
						
		// retrieve provider data
		$user_data = sqlQuery("SELECT * FROM users WHERE id = $order_data->provider_id LIMIT 1");
		$provider = $user_data['npi']."^".$user_data['lname']."^".$user_data['fname']."^".$user_data['mname']."^^^^^NPI";
		
		$abn_signed = ''; // default
		if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800')
			$abn_signed = ($order_data->abn_signed) ? '2' : '4';
		
		// determine dates
		$cdate = $pdate = '';
		if (strtotime($order_data->date_collected) !== false) $cdate = date('YmdHis',strtotime($order_data->date_collected));
		if (strtotime($order_data->date_pending) !== false) $pdate = date('YmdHis',strtotime($order_data->date_pending));
			
		// common order segment
		if ($this->NPI == 'BIOREF')
			$ORC = "ORC|NW|$order_num|||||||$odate|||$provider|||$cdate||||||\r";
		else
			$ORC = "ORC|NW|$order_num|||||||$odate|||$provider||||||||$abn_signed|\r";
		
		$this->request .= $ORC;
		if ($DEBUG) echo $ORC . "\n";  // DEBUG

		// observation request segment
		$service_id = $item_data->procedure_code . "^";
		$service_id .= $item_data->procedure_name; 
		$service_id .= "^LAB"; 
		
		// order request (test ordered)
		if ($this->NPI == 'CERNER') {
			$cdate = ''; // NOT USED WITH CERNER
			if (!$pdate) $pdate = date('YmdHis');
			$OBR = "OBR|$setid|$order_num||$service_id|||||$order_data->specimen_volume||||||$order_data->specimen_source^^^^^|$provider|||||||||||^^^$pdate^^RT^|\r";
		}
		elseif ($this->NPI == 'BIOREF') {
			$order_num = $order_data->order_number; // they want different values in ORC and OBR
			$OBR = "OBR|$setid|$order_num||$service_id|||$cdate||$order_data->specimen_volume|||||||$provider|\r";
		}
		else {
			if (!$cdate) $cdate = $pdate;
			$OBR = "OBR|$setid|$order_num||$service_id|||$cdate||||||||$order_data->specimen_source|$provider|\r";
		}
		
		$this->request .= $OBR;
		if ($DEBUG) echo $OBR . "\n";  // DEBUG

		// notes and comments
		$notes = '';
		if ($order_data->request_handling == 'stat') {
			$notes = "*** STAT ORDER ***";
			if ($order_data->clinical_hx) $notes .= "\n".$order_data->clinical_hx;
		}
		else {
			$notes = $order_data->clinical_hx;
		}
		
		if ($notes) {
			$this->addNotes($notes);
		}
			
		// diagnosis
		$drg_array = array();
		if ($item_data->diagnoses)	{ // have diagnosis
			if (strpos($item_data->diagnoses,"|") === false) { // single code
				$drg_array = array($item_data->diagnoses);
			}
			else { // multiple diagnoses
				$drg_array = explode("|", $item_data->diagnoses); // code & text
			}
		}
		
		$drgid = 1;
		foreach($drg_array AS $diag) {
			list($code,$dx_text) = explode("^",$diag);
			if (!$code) continue;
			
			if (strpos($code, ":") === false) { // type not provided (assume ICD9)
				$dx_type = "I9";
				$dx_code = $code;
			}
			else {
				list($dx_type,$dx_code) = explode(":", $code); // split type and code
				$dx_type = str_replace("CD", "", strtoupper($dx_type)); // I9 or I10
			}
			
			if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800' || $this->NPI == 'BIOREF')
				$DG1 = "DG1|".$drgid++."|$dx_type|$dx_code|$dx_text|\r";
			elseif ($this->NPI == 'CERNER')
				$DG1 = "DG1|".$drgid++."|$dx_type|$dx_code^$dx_text|\r";
			else
				$DG1 = "DG1|".$drgid++."||$dx_code^$dx_text^$dx_type|\r";
			
			$this->request .= $DG1;
			if ($DEBUG) echo $DG1 . "\n";  // DEBUG
		}
		
		// aoe responses
		$aoeid = 1;
		if (is_array($aoe_list)) {
			foreach ($aoe_list AS $aoe_data) {
				if ($aoe_data['procedure_code'] == $item_data->procedure_code && $aoe_data['answer']) {
					
					if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800')
						$OBX = "OBX|".$aoeid++."|ST|".$aoe_data['question_code']."||".$aoe_data['answer']."\r";
					else
						$OBX = "OBX|".$aoeid++."|ST|".$aoe_data['question_code']."^".$aoe_data['question_text']."|1|".$aoe_data['answer']."||||||F||\r";
					
					$this->request .= $OBX;
					if ($DEBUG) echo $OBX . "\n";  // DEBUG
				}
			}
		}
	}
	
	/**
	 *
 	 * The submitOrder() method sends the requisition.
	 *
	 */
	public function submitOrder(&$order_data) {
		echo "Process: Submit Electronic Order\n";
		
		if ($this->STATUS == 'D') { // don't send development orders
			echo "Status: TRAINING \n";
			echo "Message: Order not sent to laboratory interface. \n";
		}
		else {
			try {
				// create upload file name
				$docName = '';
				$account = ($order_data->request_account) ? $order_data->request_account : $this->SENDING_FACILITY;
				$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
				if ($this->NPI == 'PATHGROUP' || $this->NPI == '1235186800') $docName = $account;
				$docName .= $order_data->order_number . "_ORDER";
				$file = $docName."_".$unique.".hl7";

				switch ($this->PROTOCOL) {
					case 'FSS': // sFTP server
						$this->putFSSOrder($file, $this->request);
						break;
					case 'FSC': // sFTP client
						$this->putFSCOrder($file, $this->request);
						break;
					case 'INT': // Internal (don't send anywhere)
						break;
					default:
						throw new Exception("Lab Protocol Not Implemented");
				}
					
			}
			catch (Exception $e) {
				$msg = $e->getMessage();
				$this->logger($msg);
				die("\n\nFATAL ERROR: ".$msg);
			}
		}
		
		echo "Status: COMPLETE\n\n";
	}
	
	/**
	 * putFSSOrder()
	 * sFTP file server just put into orders directory
	 * 
	 */
	private function putFSSOrder($file, $request) {
		// write output
		if (($fp = fopen($this->ORDERS."/".$file, "w")) == false) {
			throw new Exception('Could not create order file ('.$this->ORDERS."/".$file.')');
		}
		fwrite($fp, $request);
		fclose($fp);
		
		return;
	}
	
	/**
	 * putFSCOrder()
	 * sFTP file client must transfer to lab server
	 *
	 */
	private function putFSCOrder($file, $request) {
		// scanity check before doing anything 
		if ( isset($this->USERNAME) && isset($this->PASSWORD) && isset($this->ENDPOINT) && isset($this->PORT)) {
			
			// open the sFTP server connection
			//$sftp = new Net_SFTP($this->ENDPOINT,$this->PORT);
			$sftp = new SFTP($this->ENDPOINT,$this->PORT);
			if (!$sftp->login($this->USERNAME, $this->PASSWORD)) {
				throw new Exception("sFTP session did not initialize!!");
			}
					
			// write the file to server
			$sftp->put($this->ORDERS."/".$file, $request);
		}
			
		return;
	}
	
	
	/**
	 *
 	 * The getOrderDocuments() method will:
 	 *
	 * 1. Create a PDF requisition document
	 * 2. Store the document in the repository
	 * 3. Return a reference to the document
	 *
 	 * @access public
 	 * @param Order $order original order data object
 	 * @param array $test_list order test information
 	 * @param array $zseg_list aoe data by zseg 
 	 * @return int $docId document identifier
	 */
	public function getOrderDocuments(&$order_data,$type='REQ',&$test_list,&$aoe_list) {
		echo "Process: Generate Documents\n";
		
		// validate the respository directory
		$file_path = $this->REPOSITORY . preg_replace("/[^A-Za-z0-9]/","_",$order_data->pid) . "/";
		if (!file_exists($file_path)) {
			if (!mkdir($file_path,0700)) {
				throw new Exception("The system was unable to create the directory for this order, '" . $file_path . "'.\n");
			}
		}
				
		$document = null;
		
		try {
			$document = OrderRequest::makeOrderDocument($order_data,$test_list,$aoe_list);
			if ($document) {
				$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
				$docName = $order_data->order_number . "_ORDER";
				$file = $docName."_".$unique.".pdf";
					
				$docnum = 0;
				while (file_exists($file_path.$file)) { // don't overlay duplicate file names
					$docName = $order_data->order_number . "_ORDER_".$docnum++;
					$file = $docName."_".$unique.".pdf";
				}
		
				if (($fp = fopen($file_path.$file, "w")) == false) {
					throw new Exception('\nERROR: Could not create local file ('.$file_path.$file.')');
				}
				fwrite($fp,$document);
				fclose($fp);
				if ($DEBUG) echo "\nDocument Name: " . $file;

				// register the new document
				$d = new Document();
				$d->name = $docName;
				$d->storagemethod = 0; // only hard disk sorage supported
				$d->url = "file://" .$file_path.$file;
				$d->mimetype = "application/pdf";
				$d->size = filesize($file_path.$file);
				$d->owner = $_SESSION['authUserID'];
				$d->hash = sha1_file( $file_path.$file );
				$d->type = $d->type_array['file_url'];
				$d->set_foreign_id($order_data->pid);
				$d->persist();
				$d->populate();

				$doc_data = $d; // save for later
						
				// update cross reference
				$query = "REPLACE INTO categories_to_documents set category_id = '".$this->DOCUMENT_CATEGORY."', document_id = '" . $d->get_id() . "'";
				sqlStatement($query);
			}
		} 
		catch (Exception $e) {
			$msg = $e->getMessage();
			$this->logger->error($msg);
			die("FATAL ERROR " . $msg);
		}
		
		echo "Status: COMPLETE\n\n";
		return [$doc_data];
	}
}
