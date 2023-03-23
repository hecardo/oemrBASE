<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Global setup
require_once("../../globals.php");

use OpenEMR\Common\Logging\SystemLogger;

use WMT\Classes\Tools;

use WMT\Laboratory\Common\LabOrderItem;
use WMT\Laboratory\Common\Processor;
use WMT\Laboratory\Common\LabOrder;

// grab important data
$authuser = $_SESSION['authUser'];	
$groupname = $_SESSION['authProvider'];
$authorized = $_SESSION['userauthorized'];

$id = $_POST["id"];
$mode = $_POST['mode'];
$print = $_POST['print'];
$siteid = $_POST['siteid'];
$pop = $_POST['pop'];

$pid = $_POST['pid'];
$lab_id = $_POST['lab_id'];
$encounter = $_POST['encounter'];
$provider_id = $_POST['provider_id'];

$logger = new SystemLogger();

if (empty($lab_id)) {
	$msg = "Save order laboratory identifier missing!!";
	$logger->error($msg);
	die($msg);
}
if (empty($pid)) {
	$msg = "Save order patient identifier missing!!";
	$logger->error($msg);
	die($msg);
}
if (empty($encounter)) {
	$msg = "Save order encounter number missing!!";
	$logger->error($msg);
	die($msg);
}

// place holders
$order_data = null;

// get lab information
$lab_data = new Processor($lab_id);
if ($lab_data->name) $form_title = $lab_data->name;

// order already submitted
if ($mode == 'update') { 
	// fetch order
	$order_data = new LabOrder('laboratory', $id);
	
	// review order
	$order_data->reviewed_datetime = '';
	$order_data->reviewed_id = ($_POST['reviewed_id'] != '_blank') ? $_POST['reviewed_id'] : '';
	$order_data->review_notes = $_POST['review_notes'];
	$order_data->notified_datetime = '';
	$order_data->notified_person = $_POST['notified_person'];
	$order_data->notified_id = ($_POST['notified_id'] != '_blank') ? $_POST['notified_id'] : '';
	$order_data->patient_notes = $_POST['review_notes'];
	if ($order_data->reviewed_id) $order_data->status = 'v'; // reviewed
	if ($order_data->notified_id) $order_data->status = 'n'; // notified
	$order_data->portal_flag = $_POST['portal_flag'];
	
	if ($order_data->reviewed_id) {
		$order_data->reviewed_datetime = Tools::FormatDateTime($_POST['reviewed_date']);
	}
	if ($order_data->notified_id) {
		$order_data->notified_datetime = Tools::FormatDateTime($_POST['notified_date']);
	}
		
	// lab notification required
	if ($lab_data->lab_sms_notify === 'Y') {
		// portal flag set for first time
		if ($current_portal != 1 && 
			($order_data->portal_flag > 0 && $order_data->reviewed_id > 0 && 
				$order_data->notified_id > 0) ) {
			if ($current_portal != 1 && $order_data->portal_flag == 1) {
				$sms = new wmt\Nexmo();
				$sms->labNotice($pid, 'lab_sms_notify');
			}
		}
	}
			
	// save the new information
	$order_data->store(); 
	echo "<html><body><script>";
	if ($pop) {
		echo "window.parent.dlgclose(); // in modal";
	} else {
		echo "window.parent.closeTab(window.name, true); // in tab";
	}
	echo "</script></body></html>";
	exit;
}
		
if (empty($id)) {
	// create order
	$order_data = new LabOrder('laboratory');
	$order_data->form_title = $lab_data->name;
	
	// default form settings
	$order_data->date = date('Y-m-d H:i:s');
	
	$order_data->pid = $pid;
	$order_data->user = $authuser;
	$order_data->groupname = $groupname;
	$order_data->authorized = $authorized;
	$order_data->activity = 1;
	$order_data->status = 'i';
	$order_data->priority = 'n';
	
	// set default order data
	$order_data->lab_id = $lab_id;
	$order_data->order_priority = 'normal';
	$order_data->order_status = 'pending';
	$order_data->date_ordered = date('Y-m-d H:i:s');
	$order_data->encounter = $_SESSION['encounter'];
	
	// get order datetime value
	$date_ordered = $_POST['date_ordered'];
	if ($date_ordered) {
		$order_data->date_ordered = date('Y-m-d H:i:s',strtotime($date_ordered));
	} else {
		$order_data->date_ordered = date('Y-m-d H:i:s');
	}
} else {
	$order_data = new LabOrder('laboratory', $id);
}

// store master account
$order_data->account = $lab_data->lab_account;

// retrieve the post data for the fields
$fields = get_object_vars($order_data);
foreach ($_POST as $key => $value) {
	if (array_key_exists($key, $fields) !== false) {
		if (in_array($key, ['id','pid','lab_id'])) continue;
		$order_data->$key = htmlspecialchars($value);
		if ($order_data->$key == '_blank') $order_data->$key = '';
	}
}

// get ordering provider information
$doc_data = null;
if ($provider_id) {
	$doc_data = new Provider($provider_id);
	$order_data->provider_number = $doc_data->npi;
}

// checkboxes with no value unchecked
$order_data->order_psc = (isset($_POST['order_psc']))? 1 : 0;

// get diagnosis data
$diagnoses = '';
if (is_array($_POST['dx_code'])) {
	for ($d = 0; $d < count($_POST['dx_code']); $d++) {
		$dx_code = trim($_POST['dx_code'][$d]);
		if (strpos($dx_code,":") === false) $dx_code = 'ICD10:'.$_POST['dx_code'][$d]; 
		$diagnoses .= $dx_code."^".$_POST['dx_text'][$d]."|";
	}
}
elseif ($_POST['dx_code'] != '') {
	$dx_code = trim($_POST['dx_code']);
	if (strpos($dx_code,":") === false) $dx_code = 'ICD10:'.$_POST['dx_code']; 
	$diagnoses = $dx_code."^".$_POST['dx_text']."|";
}
$order_data->order_diagnosis = $diagnoses;

// get specimen datetime value
if (!$order_data->order_psc) {
	if ($_POST['collector_id']) {
		$order_data->collector_id = $_POST['collector_id'];
		$sdate = $_POST['date_collected'].' '.$_POST['time_collected'];
		$seconds = Tools::GetSeconds($sdate);
		if (!$seconds) $seconds = strtotime($order_data->date_ordered); // fallback default
		$order_data->date_collected = date('Y-m-d H:i:s', $seconds);
		
	}
} else {
	$pdate = $_POST['date_pending'];
	if (strtotime($pdate) !== false)
		$order_data->date_pending = date('Y-m-d H:i:s',strtotime($pdate));
}

// store form
$id = $order_data->store();

// remove old test records
LabOrderItem::removeItems($order_data->order_number);

// create test records
$test_code = $_POST['test_code'];
$test_text = $_POST['test_text'];
$test_profile = $_POST['test_profile'];
if (is_array($test_code) && count($test_code) > 0) {
	for ($t = 0; $t < count($test_code); $t++) {
		// create a new test record
		$seq = $t +1;
		$code = $test_code[$t];
		$text = $test_text[$t];
		$profile = $test_profile[$t];
		$order_item = new LabOrderItem();
		
		$order_item->procedure_order_id = $order_data->order_number;
		$order_item->lab_id = $order_data->lab_id;
		$order_item->procedure_order_seq = $seq;
		$order_item->procedure_code = $code;
		$order_item->procedure_name = $text;
		$order_item->procedure_source = 1;
		$order_item->procedure_type = $profile;
		$order_item->diagnoses = $diagnoses;
	
		$order_item->store();
		$item_list[] = $order_item;  // save for submit
		
		$code_key = "aoe".$code."_code";
		$label_key = "aoe".$code."_label";
		$text_key = "aoe".$code."_text";
		$code_count = (isset($_POST[$code_key]))? count($_POST[$code_key]) : 0;
		for ($a = 0; $a < $code_count; $a++) {
			$key = "aoe".$a."_code";
			$qcode = $_POST[$code_key][$a];
			$key = "aoe".$a."_label";
			$label = $_POST[$label_key][$a];
			$key = "aoe".$a."_text";
			$qtext = $_POST[$text_key][$a];
			
			// save answer record
			$qseq = $a +1;
			$params = array();
			$params[] = $order_item->procedure_order_id;
			$params[] = $order_item->procedure_order_seq;
			$params[] = $order_item->procedure_code;
			$params[] = $qcode;
			$params[] = $qtext;
			$params[] = $qseq;
	
			sqlInsert("INSERT INTO `procedure_answers` SET ".
				"`procedure_order_id` = ?," .
				"`procedure_order_seq` = ?, " .
				"`procedure_code` = ?, " .
				"`question_code` = ?, " .
				"`answer` = ?, " .
				"`answer_seq` = ? ",
			$params);
		}
	}
	
}

// should we send the order
if ($mode == 'process') {
	include('process.php');
	exit();
} else { 
	echo "<html><body><script>";
	if ($pop) {
		echo "window.parent.dlgclose(); // in modal";
	} else {
		echo "window.parent.closeTab(window.name, true); // in tab";
	}
	echo "</script></body></html>";
}
?>