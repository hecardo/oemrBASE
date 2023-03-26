<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Quest;

use Document;

use WMT\Objects\Patient;

use WMT\Laboratory\Common\Processor;

require_once('OrderService.php');

/**
 * The class QuestOrder submits lab order (HL7 messages) to the MedPlus Hub
 * platform.  Encapsulates the sending of an HL7 order to a Quest Lab
 * via the Hub�s SOAP Web service.
 *	
 * @package laboratory
 * @subpackage Quest
 * 
 */
class QuestClient {
	/** 
	 * Class variables
	 */	
	private $STATUS;
	private $ENDPOINT;
	private $USERNAME;
	private $PASSWORD;
	private $SENDING_APPLICATION;
	private $SENDING_FACILITY;
	private $RECEIVING_APPLICATION;
	private $RECEIVING_FACILITY;
	private $WSDL;
		
	// Document storage directory
	private $DOCUMENT_CATEGORY;
	private $WORK_DIRECTORY;
	private $REPOSITORY;
		
	private $order_number = null;
	private $insurance = array();
	private $orders = array();
	private $service = null;
	private $request = null;
	private $response = null;
	private $documents = array();
	
	private $pat_data;

	private $DEBUG = false;
		
	/**
	 * Constructor for the 'order client' class which initializes a reference 
	 * to the Quest Hub web service.
	 *
	 * @package QuestWebService
	 * @access public
	 */
	public function __construct($lab_id) {
		$this->lab_id = $lab_id;
		$this->REPOSITORY = $GLOBALS['oer_config']['documents']['repository'];
				
		// retrieve processor data
		$processor = new Processor($lab_id);
		$this->STATUS = 'D'; // default training/development
		if ($processor->DorP) $this->STATUS = $processor->DorP; // production
		$this->SENDING_APPLICATION = $processor->send_app_id;
		$this->SENDING_FACILITY = $processor->send_fac_id;
		$this->RECEIVING_APPLICATION = $processor->recv_app_id;
		$this->RECEIVING_FACILITY = $processor->recv_fac_id;
		$this->ENDPOINT = $processor->remote_host . $processor->orders_path;
		$this->USERNAME = $processor->login;
		$this->PASSWORD = $processor->password;
			
		$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?",array($processor->name));
		if (!$category['id']) {
			$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?",array('Lab Report'));
		}
		$this->DOCUMENT_CATEGORY = $category['id'];
		$this->WORK_DIRECTORY = $processor->lab_work_path .DIRECTORY_SEPARATOR. $processor->ppid;
				
		// initialize the web service
		$options = array();
		$options['wsdl_local_copy'] = 'wsdl_quest_orders';
		$options['wsdl_path'] = $processor->lab_work_path."/labs/".$lab_id;
		$options['login'] = $this->USERNAME;
		$options['password'] = $this->PASSWORD;
		if ($this->STATUS != 'D') {
			$this->service = new OrderService($this->ENDPOINT,$options);
		}
		$this->request = new OrderSupportServiceRequest();
		$this->response = new OrderSupportServiceResponse();	

		// sanity check (web service)
		if ( !$this->DOCUMENT_CATEGORY ||
				!$this->RECEIVING_FACILITY ||
				!$this->SENDING_APPLICATION ||
				!$this->SENDING_FACILITY ||
				!$this->USERNAME ||
				!$this->PASSWORD ||
				!$this->ENDPOINT ||
				!$this->STATUS ||
				!$this->REPOSITORY )
			throw new \Exception ("Quest Interface Not Properly Configured!!\n\n<pre>".var_dump($this)."</pre>\n\n");
			
		return;
	}

	/**
	 * Constructs a valid HL7 message string for this order.
	 *
	 * @access public
	 * @param object $order_data Data from form input
	 */
	public function buildRequest(&$order_data) {
		// store order identifier
		$this->order_number = $order_data->order_number;
		
		// create the order object
		$this->request->hl7Order = '';
		
		// retrieve additional data records
		$this->pat_data = Patient::getPidPatient($order_data->pid);
			
		$pname = $this->pat_data->lname;
		$pname .= "^".$this->pat_data->fname;
		$pname .= "^".$this->pat_data->mname;
			
		$paddress = $this->pat_data->street;
		$paddress .= "^".$this->pat_data->street2;
		$paddress .= "^".$this->pat_data->city;
		$paddress .= "^".$this->pat_data->state;
		$paddress .= "^".$this->pat_data->postal_code;
			
		$dob = '';
		if (strtotime($this->pat_data->DOB))
			$dob = date('Ymd',strtotime($this->pat_data->DOB));
			
		$sex = substr(strtoupper($this->pat_data->sex),0,1);
		if (!$sex) $sex = 'U';
	
		if (strtotime($order_data->date_transmitted))
			$odate = date('YmdHis',strtotime($order_data->date_transmitted));
		else
			$odate = date('YmdHis');
			
		// determine transaction type
		$trans = ($this->STATUS == 'P')? 'P' : 'T'; // P=production, T=training/testing
		
		// determine sender account
		$account = ($order_data->request_account) ? $order_data->request_account : $this->SENDING_FACILITY;
		
		// determine receiver (if PSC then send to PSC)
		$recvr = ($order_data->order_psc)? 'PSC' : $this->RECEIVING_APPLICATION;
			
		// message segment
		$MSH = "MSH|^~\\&|%s|%s|%s|%s|$odate||ORM^O01|$order_data->order_number|%s|2.3.1\r";
		$this->request->hl7Order = sprintf($MSH, $this->SENDING_APPLICATION, $account, $recvr, $this->RECEIVING_FACILITY, $trans);
		if ($this->DEBUG) error_log("HL7: " . $this->request->hl7Order); // DEBUG
			
		// patient segment
		$email = '';
		if ($this->pat_data->hipaa_allowemail == 'YES') $email = $this->pat_data->email;
		$PID = "PID|1||%s|%s|$pname||$dob|$sex||%s|$paddress||%s^^^$email|||||$order_data->request_account^^^$order_data->request_billing^$order_data->abn_signed|%s||||%s|\r";
		$this->request->hl7Order .= sprintf($PID, $this->pat_data->pubpid, $this->pat_data->pid, $this->pat_data->race, $this->pat_data->phone_home, $this->pat_data->ss, $this->pat_data->ethnicity);
		if ($this->DEBUG) error_log("PID: " . $PID);  // DEBUG
			
		// clinic notes (add to the end)
		$notes = '';
		if ($order_data->request_handling == 'stat') {
			$notes = "*** STAT ORDER ***";
			if ($order_data->clinical_hx) $notes .= "\n".$order_data->clinical_hx;
		}
		else {
			$notes = $order_data->clinical_hx;
		}
		
		if ($notes) {
			$this->addNotes('I',$notes);
		}

		if ($order_data->patient_instructions) {
			$this->addNotes('R',$order_data->patient_instructions);
		}
	
		return;
	}
	
	/**
	 * Appends NTE segments to the end of the current request HL7 message.
	 *
	 * @param int $setid Sequence number of this segment
	 * @param object $ins_data Insurance data object
	 */
	public function addNotes($type,$notes) {
		if (!$notes) return;
		if (!$type) $type = 'I'; // assume internal
			
		$seq = 1;
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $notes) as $note) {
			$NTE = "NTE|".$seq++."|$type|".$note."|\r";
			$this->request->hl7Order .= $NTE;
			if ($this->DEBUG) error_log("NTE: " . $NTE);
	
			if ($seq > 5) break; // maximum segments
		}
			
		return;
	}
	
	/**
	 * Appends IN1 insurance segments to the end of the current request HL7 message.
	 *
	 * @param int $setid Sequence number of this segment
	 * @param object $ins_data Insurance data object
	 */
	public function addInsurance($setid, $ins_type, $ins_data) {
		if ($ins_type == 'T') {
			
			$sname = $ins_data->subscriber_lname;
			$sname .= "^".$ins_data->subscriber_fname;
			$sname .= "^".$ins_data->subscriber_mname;
			
			$iaddress = $ins_data->line1;
			$iaddress .= "^".$ins_data->line2;
			$iaddress .= "^".$ins_data->city;
			$iaddress .= "^".$ins_data->state;
			$iaddress .= "^".$ins_data->zip;
			
			$relation = '8'; // assume dependent
			if ($ins_data->subscriber_relationship == 'self') $relation = '1';
			if ($ins_data->subscriber_relationship == 'spouse') $relation = '2';
			
			$dob = '';
			if (strtotime($ins_data->subscriber_DOB))
				$dob = date('Ymd',strtotime($ins_data->subscriber_DOB));
			
			$sex = substr(strtoupper($ins_data->subscriber_sex),0,1);
			if (!$sex) $sex = 'U';
			
			$IN1 = "IN1|$setid|||$ins_data->company_name|$iaddress|||$ins_data->group_number||||||||$sname|$relation|$dob|$iaddress|||||||||||||||||$ins_data->policy_number|||||||$sex||||T\r";
		}
		else { // no insurance record
			$IN1 = "IN1|$setid||||||||||||||||||||||||||||||||||||||||||||||$ins_type\r";
		}
	
		$this->request->hl7Order .= $IN1;
		if ($this->DEBUG) error_log("IN1: " . $IN1); // DEBUG
	}
	
	/**
	 * Appends GT1 guarantor segment to the end of the current request HL7 message.
	 *
	 * @param object $guarantor_data Order data object (contains guarantor data)
	 */
	public function addGuarantor($pid,$ins_data) {
		$dob = '';
		$sex = ''; // assume unknown
		$relation = '3'; // assume other
		
		if ($ins_data) {
			if ($ins_data->subscriber_relationship == 'self') $relation = '1';
			if ($ins_data->subscriber_relationship == 'spouse') $relation = '2';
					
	
			$gname = $ins_data->subscriber_lname;
			$gname .= "^".$ins_data->subscriber_fname;
			$gname .= "^".$ins_data->subscriber_mname;
			
			$gaddress = $ins_data->subscriber_street;
			$gaddress .= "^"; // no second street
			$gaddress .= "^".$ins_data->subscriber_city;
			$gaddress .= "^".$ins_data->subscriber_state;
			$gaddress .= "^".$ins_data->subscriber_postal_code;
			
			if (strtotime($ins_data->subscriber_DOB) !== false)
				$dob = date('Ymd',strtotime($ins_data->subscriber_DOB));
				
			$sex = substr(strtoupper($ins_data->subscriber_sex),0,1);
		}
		elseif ($pid) {
			$pat_data = Patient::getPidPatient($pid);

			// separate guarantor information				
			if ($pat_data->guarantor_lname) { // if guarantor provided
				if ($pat_data->guarantor_relation == 'Self') $relation = '1';
				if ($pat_data->guarantor_relation == 'Spouse') $relation = '2';

				$gname = $pat_data->guarantor_lname;
				$gname .= "^".$pat_data->guarantor_fname;
				$gname .= "^".$pat_data->guarantor_mname;
	
				$gaddress = $pat_data->guarantor_street;
				$gaddress .= "^".$pat_data->guarantor_street2;
				$gaddress .= "^".$pat_data->guarantor_city;
				$gaddress .= "^".$pat_data->guarantor_state;
				$gaddress .= "^".$pat_data->guarantor_zip;
				
				if (strtotime($pat_data->guarantor_dob) !== false)
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
				
				if (strtotime($pat_data->DOB) !== false)
					$dob = date('Ymd',strtotime($pat_data->DOB));
				
				$sex = substr(strtoupper($pat_data->sex),0,1);
			}
		}
	
		$GT1 = "GT1|1||$gname||$gaddress|$pat_data->phone_home||$dob|$sex||$relation|\r";
		$this->request->hl7Order .= $GT1;
		if ($this->DEBUG) echo $GT1 . "\n"; // DEBUG
	}
	
	/**
	 * Appends ORC, OBR, DG1, OBX segments to the end of the current request HL7 message.
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
			
		// retrieve provider data
		$user_data = sqlQuery("SELECT * FROM users WHERE id = $order_data->provider_id LIMIT 1");
		$provider = $user_data['npi']."^".$user_data['lname']."^".$user_data['fname']."^".$user_data['mname'];
			
		// common order segment
		$ORC = "ORC|NW|$order_data->order_number|||||||$odate|||$provider^^^^^NPI|\r";
		$this->request->hl7Order .= $ORC;
		if ($this->DEBUG) echo $ORC . "\n";  // DEBUG
	
		// observation request segment
		$service_id = "^^^";
		$service_id .= $item_data->procedure_code . "^";
		$service_id .= $item_data->procedure_name;
			
		$cdate = date('YmdHis');
		if (strtotime($item_data->date_collected)) $cdate = date('YmdHis',strtotime($item_data->date_collected));
	
		// order request (test ordered)
		$OBR = "OBR|$setid|$order_data->order_number||$service_id|||$cdate||$item_data->specimen_volume||||||$item_data->specimen_source|$provider^^^^^NPI|\r";
		$this->request->hl7Order .= $OBR;
		if ($this->DEBUG) echo $OBR . "\n";  // DEBUG
	
		// diagnosis segments
		$drg_array = array();
		if ($item_data->diagnoses)	{ // have diagnosis
			if (strpos($item_data->diagnoses,"|") === false) { // single code
				$drg_array = array($item_data->diagnoses);
			}
			else { // multiple diagnoses
				$drg_array = explode("|", $item_data->diagnoses); // code & text
			}
		}
			
		$seq = 1;
		foreach($drg_array AS $diag) {
			list($code,$dx_text) = explode("^",$diag);
			if (!$code) continue;
	
			if (strpos($code, ":") === false) { // type not provided (assume ICD10)
				$dx_type = "I10";
				$dx_code = $code;
			}
			else {
				list($dx_type,$dx_code) = explode(":", $code); // split type and code
				$dx_type = str_replace("CD", "", strtoupper($dx_type)); // I9 or I10
			}
	
			$dx_code = trim($dx_code);
			$dx_text = trim($dx_text);
			$DG1 = "DG1|".$seq++."||$dx_code^$dx_text^$dx_type|\r";
			$this->request->hl7Order .= $DG1;
			if ($this->DEBUG) echo $DG1 . "\n";  // DEBUG
		}

		// aoe responses
		$aoeid = 1;
		if (is_array($aoe_list)) {
			foreach ($aoe_list AS $aoe_data) {
				if ($aoe_data['procedure_code'] == $item_data->procedure_code) {
					$OBX = "OBX|".$aoeid++."|ST|^^^".$aoe_data['question_code']."^".$aoe_data['question_text']."||".$aoe_data['answer']."||||||||||||\r";
					$this->request->hl7Order .= $OBX;
					if ($this->DEBUG) echo $OBX . "\n";  // DEBUG
				}
			}
		}
		
		// identity & orientation (Quest required 3/23/2023)
		$orientation = 'D'; // default undisclosed
		$title = 'Choose not to disclose';
		$record = sqlQuery("SELECT * FROM `list_options` WHERE `list_id` LIKE 'sexual_orientation' AND `option_id` LIKE ?", array($this->pat_data->sexual_orientation));
		if (isset($record['notes'])) {
			$title = $record['title'];
			$values = explode(';', $record['notes']);
			foreach ($values as $value) {
				if (strpos($value, 'QUEST:') === FALSE) continue;
				$orientation = str_replace('QUEST:', '', $value);
				if (!in_array($orientation, ['D','BI','HOM','HET','SE','DK'])) {
					$orientation = 'D';
					$title = 'Choose not to disclose';
				}
			}
		}
		$OBX = "OBX|".$aoeid++."|CWE|76690-7^Sexual Orientation^LN|1|$orientation^$title^L||||||F|||$odate|||||||||||||||QST\r";
		$this->request->hl7Order .= $OBX;
		if ($this->DEBUG) echo $OBX . "\n";  // DEBUG
		
		$identity = 'D'; // undisclosed
		$title = 'Choose not to disclose';
		$record = sqlQuery("SELECT * FROM `list_options` WHERE `list_id` LIKE 'gender_identity' AND `option_id` LIKE ?", array($this->pat_data->gender_identity));
		if (isset($record['notes'])) {
			$title = $record['title'];
			$values = explode(';', $record['notes']);
			foreach ($values as $value) {
				if (strpos($value, 'QUEST:') === FALSE) continue;
				$identity = str_replace('QUEST:', '', $value);
				if (!in_array($identity, ['D','F','X','M','FTM','MTF','TNOS'])) {
					$identity = 'D';
					$title = 'Choose not to disclose';
				}
			}
		}
		$OBX = "OBX|".$aoeid++."|CWE|76691-5^Gender Identity^LN|1|$identity^$title^L||||||F|||$odate|||||||||||||||QST\r";
		$this->request->hl7Order .= $OBX;
		if ($this->DEBUG) echo $OBX . "\n";  // DEBUG
		
	}


	/**
	 * Helper to break comment into line array with max of 60 characters each line
	 * @param string $text
	 * @return array $lines
	 * 
	 */
	private function breakText($text) {
		$lines = array();
		if ($text) {
			$text = str_replace(array("\r\n", "\r", "\n"), " ", $text); // strip newlines
			$text = wordwrap($text,60,'^'); // mark breaks
			$lines = explode('^', $text); // make array
		}
		return $lines;
	}
	
	
	/**
	 *
 	 * The validateOrder() method will:
 	 *
	 * 1. Create a proxy for making SOAP calls
	 * 2. Create an Order request object which contains a valid HL7 Order message
	 * 3. Submit a Lab Order calling submitOrder().
	 * 4. Output response valuse to console.
	 *
	 */
	public function validateOrder() {
		$response = null;
		try {
			$response = $this->service->validateOrder($this->request);
			echo "Status: " . $response->status .
				"\nControl ID: " . $response->messageControlId .
				"\nTransaction ID: " . $response->orderTransactionUid;
			
			if ($response->responseMsg) 
				echo "\nResponse Message: " . $response->responseMsg;

			$valErrors = $response->validationErrors;
			if ($valErrors) {
				for ($ndx = 0; $ndx < count($valErrors); $ndx++) {
					echo "\tValidation Error: " . $valErrors[$ndx] . ".";
				}
			}

		} 
		catch (\Exception $e) {
			echo($e->getMessage());
		}
	}
	
	/**
	 *
 	 * The getOrderDocuments() method will:
 	 *
	 * 1. Create a proxy for making SOAP calls
	 * 2. Create an Order request object which contains a valid HL7 Order message
	 * 3. Submit a Lab Order calling submitOrder().
	 * 4. Output response values to console.
	 *
	 */
	public function getOrderDocuments(&$order_data,$type='REQ',$test_list=false,$aoe_list=false) {
		echo "Process: Retrieve Documents\n";
		
		// validate the respository directory
		$pid = $order_data->pid;
		$file_path = $this->REPOSITORY . preg_replace("/[^A-Za-z0-9]/","_",$pid) . "/";
		if (!file_exists($file_path)) {
			if (!mkdir($file_path,0700)) {
				throw new \Exception("The system was unable to create the directory for this upload, '" . $file_path . "'.\n");
			}
		}
	
		$type_array = array('REQ');
		if ($type == 'ABN') $type_array = array('ABN');
		if ($type == 'ABN-REQ') $type_array = array('ABN','REQ');
		$this->request->orderSupportRequests = $type_array;
				
		$doc_list = array();
		$response = null;
		
		// STORE ORDER MESSAGE
		$work = $this->WORK_DIRECTORY;
		if (!file_exists($work)) {
			mkdir($work);
		}
		
		// validate work/orders directory
		$work .= "/orders/";
		if (!file_exists($work)) {
			mkdir($work);
		}
		
		$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
		$docName = $order_data->order_number . "_ORDER";
		$file = $docName."_".$unique.".hl7";
		if (($fp = fopen($work.$file, "w")) == false) {
			throw new \Exception('\nERROR: Could not create local file ('.$work.$file.')');
		}
		fwrite($fp,$this->request->hl7Order);
		fclose($fp);
		
		if ($this->STATUS == 'D') { // don't send development orders
			echo "Status: TRAINING \n";
			echo "Message: Order not sent to laboratory interface. \n";
			return false;
		} else {
			try {
				$response = $this->service->getOrderDocuments($this->request);
				echo "Status: " . $response->status .
					"\nControl ID: " . $response->messageControlId .
					"\nTransaction ID: " . $response->orderTransactionUid;
			
				if ($response->responseMsg) 
					echo "\nResponse Message: " . $response->responseMsg;
			
				$valErrors = $response->validationErrors;
				if ($valErrors) {
					for ($ndx = 0; $ndx < count($valErrors); $ndx++) {
						echo "\nValidation Error: " . $valErrors[$ndx] . ".";
					}
				}
				else {
					foreach ($response->orderSupportDocuments as $document) {
						echo "\nDocument Status: " . $document->requestStatus .
							"\nDocument Type: " . $document->documentType .
							"\nDocument Response: " . $document->responseMessage;

						if ($document->documentData) {
							$type = ($document->documentType == 'ABN')?'ABN':'ORDER';
							$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
							$docName = $response->messageControlId . "_" . $type;
		
							$docnum++;
							$file = $docName."_".$unique.".pdf";
							while (file_exists($file_path.$file)) { // don't overlay duplicate file names
								$docName = $response->messageControlId . "_" . $type . "_".$docnum++;
								$file = $docName."_".$unique.".pdf";
							}
		
							if (($fp = fopen($file_path.$file, "w")) == false) {
								throw new \Exception('\nERROR: Could not create local file ('.$file_path.$file.')');
							}
							fwrite($fp,$document->documentData);
							fclose($fp);
							echo "\nDocument Name: " . $file;

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
							$d->set_foreign_id($pid);
							$d->persist();
							$d->populate();

							$doc_list[] = $d; // save for later
						
							// update cross reference
							$query = "REPLACE INTO `categories_to_documents` set `category_id` = ?, `document_id` = ?";
							sqlStatement($query,array($this->DOCUMENT_CATEGORY,$d->get_id()));
						}
					}
				}
			} 
			catch (\Exception $e) {
				$msg = $e->getMessage();
				$this->logger->error($msg);
				die("FATAL ERROR " . $msg);
			}
		
			echo "\nStatus: COMPLETE\n\n";
			return $doc_list;
		}
	}

}
