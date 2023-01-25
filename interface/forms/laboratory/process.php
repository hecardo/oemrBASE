<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\Logging\SystemLogger;

use WMT\Objects\Insurance;

use WMT\Laboratory\Quest\QuestClient;
use WMT\Laboratory\Labcorp\LabcorpClient;
use WMT\Laboratory\Generic\OrderClient;
use Monolog\Handler\SyslogHandler;

// set processing date/time
$order_data->date_transmitted = date('Y-m-d H:i:s');

// get all AOE questions and answers
$query = "SELECT * FROM `procedure_order_code` pc ";
$query .= "LEFT JOIN `procedure_questions` pq ON pq.`lab_id` = ? AND pc.`procedure_code` = pq.`procedure_code` ";
$query .= "LEFT JOIN `procedure_answers` pa ON pa.`question_code` = pq.`question_code` AND pa.`procedure_order_id` = pc.`procedure_order_id` ";
$query .= "		AND pa.`procedure_order_seq` = pc.`procedure_order_seq` ";
$query .= "WHERE pc.`procedure_order_id` = ? ";
$query .= "ORDER BY pa.`procedure_order_id`, pa.`procedure_order_seq`, pa.`answer_seq`";

$binds = array();
$binds[] = $order_data->lab_id;
$binds[] = $order_item->procedure_order_id;
$results = sqlStatement($query,$binds);

$aoe_list = array();
while ($data = sqlFetchArray($results)) {
	if ($data['answer']) $aoe_list[] = $data;
}

// validate aoe responses (loop)
$aoe_errors = "";
if (count($aoe_list) > 0) {
	foreach ($aoe_list as $aoe_data) {
		if ($aoe_data['required'] && !$aoe_data['answer']) {
			$aoe_errors .= "\nQuestion [".$aoe_data['question_text']."] for test [".$aoe_data['procedure_code']."] requires a valid response.";
		}
	}
}
	
if ($aoe_errors) { // oh well .. have to terminate process with errors
	echo "The following errors must be corrected before submitting:";
	echo "<pre>\n";
	echo $aoe_errors;
	exit; 
}

echo "<pre>\n";

try { // catch any processing errors
	
	// get a handle to processor
	$client = null;
	switch ($lab_data->type) {
		case 'Q':
			$client = new QuestClient($lab_id);
			break;
		case 'L':
			$client = new LabcorpClient($lab_id);
			break;
		case 'G':
			$client = new OrderClient($lab_id);
			break;
	}

	// process client required
	if (empty($client)) {
		$msg = "Unsupported laboratory client processor!!";
		$logger->error($msg);
		die($msg);
	}

	// create request message
	$client->buildRequest($order_data);

	// determine third-party payment information
	$ins_primary_type = 0; // default self
	if ($order_data->work_flag) { // workers comp claim
		$order_data->billing_type = 'T';

		// build workers comp insurance record
		$ins_data = new Insurance($work_insurance);
		$ins_data->plan_name = "Workers Comp"; // IN1.08
		$ins_data->group_number = ""; // IN1.08
		$ins_data->work_flag = "Y"; // IN1.31
		$ins_data->policy_number = $order_data->work_case; // IN1.36

		// create hl7 segment
		$client->addInsurance(1, $order_data->billing_type, $ins_data);
	} else { // normal insurance
		// get current insurance
		$ins = Insurance::getPid($pid);
		if (isset($ins[0])) $order_data->ins_primary = $ins[0]->id;
		if (isset($ins[1])) $order_data->ins_secondary = $ins[1]->id;
		
		// SFA PROPERLY ORDER INSURANCE
		if ($lab_data->lab_pick_ins === 'Y') { // special processing for sfa
			if ( !in_array($order_data->billing_type, array('C','T','P','')) ) {
				// assume its an insurance id
				$ins_primary = $order_data->billing_type; // use selected
				$order_data->ins_primary = $ins_primary;
				$ins_secondary = null;  // no secondary
				$order_data->ins_secondary = $ins_secondary;
				$order_data->billing_type = 'T'; // make third-party
			}
		}
		
		$ins_primary = false;
		if ($order_data->ins_primary) {
			$ins_primary = new Insurance($order_data->ins_primary);
			$ins_primary_type = $ins_primary->ins_type_code; // save for ABN check
		}

		$ins_secondary = false;
		if ($order_data->ins_secondary) {
			$ins_secondary = new Insurance($order_data->ins_secondary);
		}

		// create insurance records
		if ( $order_data->billing_type != 'C' && !$ins_primary ) {
			$order_data->billing_type = 'P'; // if not client bill and no insurance must be patient bill
		}
		if ($order_data->billing_type == 'T' && $ins_primary ) { // only add insurance for third-party bill with insurance
			$client->addInsurance(1, $order_data->billing_type, $ins_primary);
			if ($ins_secondary)
				$client->addInsurance(2, $order_data->billing_type, $ins_secondary);
		} else {
			$client->addInsurance(1, $order_data->billing_type, false);
		}
	}
	
	// add guarantor (use insured if available, patient otherwise)
	$client->addGuarantor($order_data->pid, $ins_primary);

	// create orders (loop)
	$seq = 1;
	$test_list = array(); // for requisition
	foreach ($item_list as $item_data) {
		$client->addOrder($seq++, $order_data, $item_data, $aoe_list);
		$test_list[] = array('code'=>$item_data->procedure_code,'name'=>$item_data->procedure_name);
	}
	
	$abn_needed = false;
	// ABN support not available for generic interface
	if ($lab_data->type != 'G' && $ins_primary_type == 2 && !$order_data->work_flag) { // medicare but not workers comp
		$doc_list = $client->getOrderDocuments($order_data->pid,'ABN');
		if (count($doc_list)) {
			$order_data->order_abn_id = $doc_list[0]->get_id();
			$order_data->store();	
			
			if (!$order_data->order_abn) {
				echo "\n\nMedicare 'Advance Beneficiary Notice of Noncoverage' required.";
				echo "\nPlease print the ABN document and obtain the patient's signature.";
				echo "\nThen resubmit this order with the ABN SIGNED checkbox marked.\n\n\n";	
				$abn_needed = true;		
			}
		}
	}
	
	if (!$abn_needed || $order_data->order_abn) { // only submit if ABN not necessary or signed
		// generate requisition
		$doc_list = $client->getOrderDocuments($order_data,'REQ',$test_list,$aoe_list);

		if (empty($doc_list)) { // got a document so suceess
			$msg = "Laboratory processing failed to generate requisition document!!";
			$logger->error($msg);
			die($msg);
		}
		
		// separate submit needed for generic laboratory
		if ($lab_data->type == 'G') {
			$client->submitOrder($order_data);
		}
		
		// submission complete
		$order_data->status = 's'; // processed
		$order_data->order_req_id = $doc_list[0]->get_id();
		$order_data->order_status = 'processed';
		$order_data->store();	
		
		// SFA Automatic lab draw billing!!
		if ($lab_data->auto_draw_bill === 'Y' && $order_data->specimen_draw === 'int') {
			// include the FeeSheet class
			require_once($GLOBALS['srcdir']."/FeeSheet.class.php");
		
			// create a new billing object (PID and ENC required)
			$fs = new FeeSheet($pid, $encounter);
		
			// build billing fee item
			$fs->addServiceLineItem(array(
					'codetype'  => 'CPT4',
					'code'  => '36415',  // code item number
					'auth'  => '1',
					'units'  => '1', // as appropriate
					'justify'  => $drg_string,  // ICD10|123.45:ICD10|9876 (not required)
					'provider_id' => $provider_id  // if missing uses enc provider
			));
		
			// create dx entries if present
			if (count($drg_array) > 0) {
				foreach ($drg_array AS $dx_code => $dx_text) {
					// insert diagnosis code
					$fs->addServiceLineItem(array(
							'codetype'  => 'ICD10',
							'code'  => $dx_code,  // as listed in the ICD10 table
							'auth'  => '1',
							'provider_id' => $provider_id  // if missing uses enc provider
					));
				}
			}
		
			// save billing after all items added (service items & product items generated above)
			$fs->save($fs->serviceitems, $fs->productitems);
		}
		// End SFA billing
		
	}
}
catch (Exception $e) {
	$logger = new SystemLogger();
	$msg = $e->getMessage();
	$logger->error($msg);
	die($msg);
}

?>
</pre>
<h2 class='mt-1'>Processing Successful!!</h2>
<form>
<input type='hidden' id='order_req_id' value='<?php echo $order_data->order_req_id; ?>'/>
<input type='hidden' id='order_abn_id' value='<?php echo $order_data->order_abn_id; ?>'/>
</form>