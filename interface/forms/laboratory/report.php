<?php
/**
 * @package   WMT
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\Logging\SystemLogger;

use WMT\Classes\Tools;
use WMT\Classes\Reports;
use WMT\Classes\Options;

use WMT\Objects\Encounter;
use WMT\Objects\Insurance;
use WMT\Objects\Patient;
use WMT\Objects\User;

use WMT\Laboratory\Common\Processor;
use WMT\Laboratory\Common\LabOrder;
use WMT\Laboratory\Common\LabOrderItem;
use WMT\Laboratory\Common\LabResult;
use WMT\Laboratory\Common\LabResultItem;

function laboratory_report($pid, $encounter, $cols, $id) {
	$form_name = 'laboratory';
	$form_table = 'form_laboratory';
	$form_title = 'Laboratory Order';

	/* RETRIEVE FORM DATA */
	try {
		$order_data = new LabOrder('laboratory', $id);
		$order_number = $order_data->order_number;
		$lab_data = new Processor($order_data->lab_id);
		$lab_id = $lab_data->ppid;
		$ins_data = new Insurance($order_data->ins_primary);
		$form_title = $lab_data->name;
		
		$pat_data = Patient::getPid($pid);
		$enc_data = Encounter::getEncounter($encounter);
		
		$status_list = new Options('Lab_Form_Status');
		$item_list = LabOrderItem::fetchItemList($order_data->order_number);
	}
	catch (Exception $e) {
		$logger = new SystemLogger();
		$logger->error($e->getMessage());
		die($e->getMessage());
	}

	print <<<EOD
		<style>
		#lab_report legend {
			font-size: 1rem !important;
			margin-bottom: 0 !important;
		}
		
		#lab_report table,
		#lab_report table td {
			font-size: .8rem;
		}

		#lab_report table th {
			font-size: .6rem;
			font-weight: normal;
			text-transform: uppercase;
			vertial-align: bottom;
			white-space: nowrap;
			padding-top: .2rem;
		}
		
		#lab_report {
			font-size: 14px;
		}

		#lab_report .max-small {
			max-width: 800px;
		}

		#lab_report .max-medium {
			max-width: 1100px;
		}

		#lab_report .max-large {
			max-width: 1500px;
		}

		</style>
EOD;
	
	// Report outter frame
	print "<div id='lab_report' class='mt-2'>\n";
	
	// Order summary
	$header = "<tr><td style='min-width:150px'></td><td style='min-width:395px'></td><td style='min-width:140px'></td><td class='w-100'></td></tr>\n";
	$processed = (strtotime($order_data->date_transmitted) !== false && $order_data->date_transmitted != '0000-00-00 00:00:00')? date('Y-m-d g:ia',strtotime($order_data->date_transmitted)): 'PENDING';

	$content = '';
	$content .= Reports::do_columns(date('Y-m-d',strtotime($order_data->date_ordered)),'Order Date',$order_data->order_number,'Order Number');
	$content .= Reports::do_columns($status_list->getItem($order_data->status),'Order Status',$processed,'Processed Date');
	$content .= Reports::do_columns($order_data->order_number,'Requisition',$lab_data->name,'Processing Vendor');
	$ordby = "UNKNOWN";
	if ($order_data->provider_id && $order_data->provider_id != '999999999') {
		$doc_data = new User($order_data->provider_id);
		$ordby = $doc_data->fname .' '. $doc_data->lname;
	}
	$content .= Reports::do_columns($ordby,'Ordering Provider',$order_data->request_account,'Billing Account');
	$entby = "UNKNOWN";
	if ($order_data->user && $order_data->user != '999999999') {
		$user_data = User::fetchUserName($order_data->user);
		$entby = $user_data->fname .' '. $user_data->lname;
	}
	if ($ordby == "UNKNOWN" || $ordby == $entby) $entby = "";
	$handle = '';
	if ($order_data->request_handling) {
		$handle_list = new Options('Lab_Handling');
		$handle = $handle_list->getData($order_data->request_handling);
	}
	// SFA SPECIFIC
	if ($GLOBALS['wmt::lab_ins_pick']) {
		$bill_list = new Options('Lab_Billing');
		$billing = $bill_list->getData($order_data->request_handling);
		if ( ($billing == '' || $billing == '*Not Found*') && is_numeric($order_data->request_billing) ) {
			$billing = ($ins->company_name) ? $ins->company_name : "INSURANCE MISSING";
		}
		$content .= Reports::do_columns($entby,'Entering Clinician',$billing,'Bill To');
	} else {
		$content .= Reports::do_columns($entby,'Entering Clinician',$handle,'Special Handling');
	}
	
	$notes = ($order_data->order_notes)? "<div style='white-space:pre-wrap'>".$order_data->order_notes."</div>" : "";
	$content .= Reports::do_line($notes,'Clinic Notes');

	// Generate summary section
	Reports::do_section($header . $content, 'Order Summary', 'max-medium');
	
	// Loop through diagnosis
	$header = "<tr><td style='min-width:150px'></td><td style='min-width:110px'></td><td style='min-width:90px'></td><td class='w-100'></td></tr>\n";
	$content = '';
	
	$diag_array = array();
	if ($order_data->order_diagnosis) {
		$diag_array = explode("|", $order_data->order_diagnosis); // code & text

		foreach ($diag_array AS $diag) {
			list($code,$text) = explode("^", $diag);
			if (empty($code)) continue;
			if (strpos($code,":") !== false)	
				list($dx_type,$dx_code) = explode(":", $code);
	
			if (!$dx_type) $dx_type = 'ICD9';
	 
			$content .= Reports::do_columns($dx_code, $dx_type.' Code',$text, 'Description');
		}	
	}

	// Generate diagnosis section
	if (!empty($content)) Reports::do_section($header . $content, 'Order Diagnosis', 'max-small');
	
	// Order specimen
	$header = "<tr><td style='min-width:150px'></td><td style='min-width:120px'></td><td style='min-width:90px'></td><td class='w-100'></td></tr>\n";
	$content = '';
	
	if ($order_data->order_psc) {
		$pending = Tools::FormatDate($order_data->date_pending);
		$content .= Reports::do_columns('Yes','PSC Hold Order', $pending, 'Planned Date');
	} elseif (Tools::GetSeconds($order_data->date_collected)) {  // returns false if not a valid date
		if ($order_data->collector_id) {
			$col_data = new User($order_data->collector_id);
			$collector = $col_data->format_name;
		}
		$fasting = ($order_data->order_fasting)? 'YES' : 'NO';
		$collected = Tools::FormatDateTime($order_data->date_collected, 'Y-m-d g:ia');
		if (empty($collected)) {
			$collected = Tools::FormatDateTime($order_data->date_ordered, 'Y-m-d g:ia');
		}
		
		if ($order_data->order_abn) {
			$content .= "<tr><td colspan='4'><table class='w-100'><tr>";
			$content .= "<td class='text-nowrap font-weight-bold' style='min-width:150px;width:150px'>ABN Form Signed: </td><td style='min-width:120px'>YES</td>";
			$content .= "</tr></table></td></tr>";
		}
		$content .= "<tr><td colspan='4'><table class='w-100'><tr>";
		$content .= "<td class='text-nowrap font-weight-bold' style='min-width:150px'>Sample Collected: </td><td style='min-width:120px'>YES</td>";
		$content .= "<td class='text-nowrap font-weight-bold' style='min-width:90px'>Date/Time: </td><td style='min-width:180px'>$collected</td>";
		$content .= "<td class='text-nowrap font-weight-bold' style='min-width:135px'>Collected By: </td><td class='text-nowrap w-100'>$collector</td>";
		$content .= "</tr></table></td></tr>";
	}

	// items available
	if ($item_list) {
		if ($content) $content .= Reports::do_break();

		// loop through requisitions
		foreach ($item_list AS $order_item) {
			$need_blank = false;
			
			// Test section
			$type = ($order_item->procedure_type == 'pro')? "Profile " : "Test ";
			$content .= Reports::do_columns($order_item->procedure_code,$type.'Code',$order_item->procedure_name,'Description');
	
			// add profile tests if necessary
			if ($order_item->procedure_type == 'pro') {
				// retrieve all component test if profile
				$codes = "";
				$comps = array();
				$record = sqlQuery("SELECT `related_code` AS components FROM `procedure_type` WHERE `procedure_code` = ? AND `lab_id` = ? AND `procedure_type` = 'pro' ",
						array($order_item->procedure_code, $lab_id));
				if ($record['components']) {
					$list = explode("^", $record['components']);
					if (!is_array($list)) $list = array($list); // convert to array if necessary
					foreach ($list AS $comp) $comps[$comp] = "'UNIT:$comp'";
					$codes = implode(",", $comps);
				}
				
				// component codes found
				if ($codes) {
					$query = "SELECT `procedure_type_id` AS id, `procedure_code` AS component, `description`, `name` AS title FROM `procedure_type` ";
					$query .= "WHERE `activity` = 1 AND `lab_id` = ".$lab_id." AND `procedure_type` = 'ord' ";
					$query .= "AND `standard_code` IN ( ".$codes." ) ";
					$query .= "GROUP BY `procedure_code` ORDER BY `procedure_code` ";
					$result = sqlStatement($query);
						
					while ($profile = sqlFetchArray($result)) {
						$description = ($profile['description'])? $profile['description'] : $profile['title'];
						$content .= Reports::do_columns("","",$description,"Component: ",true);
						$need_blank = true;
					}
				}
			}
		
			// add AOE questions if necessary
			$result = sqlStatement("SELECT aoe.`procedure_code` AS code, aoe.`question_code`, aoe.`question_text`, aoe.`tips`, ans.`answer` FROM `procedure_questions` aoe ".
				"LEFT JOIN `procedure_answers` ans ON aoe.`question_code` = ans.`question_code` AND ans.`procedure_order_id` = ? AND ans.`procedure_order_seq` = ? ".
				"WHERE aoe.`lab_id` = ? AND aoe.`procedure_code` = ? AND aoe.`activity` = 1 ORDER BY aoe.`seq`, ans.`answer_seq`",
					array($order_item->procedure_order_id, $order_item->procedure_order_seq, $lab_id, $order_item->procedure_code));
					
			$aoe_out = '';
			while ($aoe = sqlFetchArray($result)) {
				$question = str_replace(':','',$aoe['question_text']);
				if ($question && $aoe['answer']) {
					$aoe_out .= "<tr><td class='wmtLabel' style='width:200px;white-space:nowrap'>".$question.": </td>\n";
					$aoe_out .= "<td class='wmtOutput' style='white-space:nowrap'>".$aoe['answer']."</td></tr>\n";
					$need_blank = true;
				}
			}
			if ($aoe_out) {
				$content .= "<tr><td colspan=3></td><td><table>$aoe_out</table></td></tr>";
			}
	
			if ($need_blank) $content .= Reports::do_blank(); // skip first time
		}
	} // end if items
	
	// lab notes
	if ($order_data->clinical_hx || $order_data->patient_instructions)
		$content .= Reports::do_break();
	
	if ($order_data->clinical_hx) {
		$content .= "<tr><td class='font-weight-bold align-text-top pt-1 pb-1'>Order Comments: </td><td class='pt-1 pb-1' colspan='3' style='white-space:pre-wrap'>".$order_data->clinical_hx."</td></tr>";
	}
	
	// patient instructions
	if ($order_data->patient_instructions) {
		$content .= "<tr><td class='font-weight-bold align-text-top pt-1 pb-1'>Patient Instructions: </td><td class='pt-1 pb-1' colspan='3' style='white-space:pre-wrap'>".$order_data->patient_instructions."</td></tr>";
	}
	
	if (!empty($content)) Reports::do_section($header . $content, 'Order Requisition', 'max-medium');
	
	// observations available
	if ($order_data->status != 'i' && $order_data->status != 's' && $order_data->status != 'p' ) { // skip until we have a result
?>
	<fieldset class="border p-2 bg-white">
		<legend class="w-auto">Observation Report</legend>
		<table class="control-table">
			<tr><td style='min-width:150px'></td><td style='min-width:90px'></td><td style='min-width:90px'></td><td class='w-100'></td></tr>
			<tr><td colspan='4'>
				<table class='w-100'>
					<tr>
						<td class='text-nowrap font-weight-bold' style='min-width:150px'>Acession Number: </td><td style='min-width:120px'><?php echo $order_data->control_id ?></td>
						<td class='text-nowrap font-weight-bold' style='min-width:90px'>Date/Time: </td><td style='min-width:180px'><?php echo FormatDateTime($order_data->received_datetime, 'Y-m-d g:ia') ?></td>
						<td class='text-nowrap font-weight-bold' style='min-width:135px'>Report Status: </td><td class='text-nowrap w-100'><?php echo $status_list->getItem($order_data->status) ?></td>
					</tr>
				</table>
			</td></tr>
			<tr><td colspan="4" style="height:15px"><hr style="border-color:#eee"></td></tr>
			<tr><td colspan="4">
<?php
		if (count($item_list) > 0) {
			// loop through each ordered item
			$last_code = "****";
			$facility_list = array();
			foreach ($item_list as $order_item) {
				$report_data = LabResult::fetchResult($order_item->procedure_order_id, $order_item->procedure_order_seq);
				if (!$report_data) continue; // no results yet
?>
				<div class="control-label font-weight-bold">
					<?php echo $order_item->procedure_code ?> - <?php echo $order_item->procedure_name ?>
				</div>			
<?php 
				$last_code = $order_item->procedure_code;
				$result_list = LabResultItem::fetchItemList($report_data->procedure_report_id);
				if (!result_list) continue; // no details yet
	
				// process each observation
				$first = true;
				foreach ($result_list AS $result_data) {
					// collect facility information
					if ($result_data->facility && !$facility_list[$result_data->facility]) {
						$facility = sqlQuery("SELECT * FROM procedure_facility WHERE code = ?",array($result_data->facility));
						if ($facility) $facility_list[$facility['code']] = $facility;
					}
					
					// do we need a header?
					if ($first) { // changed test code
						$first = false;
?>
				<table class="table table-sm w-100">
					<thead>
						<tr>
							<th scope="col" style="width:140px;padding-left:0">
								RESULT
							</th>
							<th scope="col" style="width:300px">
								DESCRIPTION
							</th>
							<th scope="col" style="width:140px">
								VALUE
							</th>
							<th scope="col" style="width:100px">
								REFERENCE
							</th>
							<th scope="col" style="width:80px">
								FLAG
							</th>
							<th scope="col" style="width:80px">
								STATUS
							</th>
							<th scope="col" style="width:80px">
								LAB
							</th>
						</tr>
					</thead>
					<tbody>
<?php 
						$last_code = $result_data->result_code;
					}
		
					$abnormal = $result_data->abnormal; // in case they sneak in a new status
					if ($result_data->abnormal == 'H') $abnormal = 'High';
					if ($result_data->abnormal == 'L') $abnormal = 'Low';
					if ($result_data->abnormal == 'HH') $abnormal = 'Alert High';
					if ($result_data->abnormal == 'LL') $abnormal = 'Alert Low';
					if ($result_data->abnormal == '>') $abnormal = 'Panic High';
					if ($result_data->abnormal == '<') $abnormal = 'Panic Low';
					if ($result_data->abnormal == 'A') $abnormal = 'Abnormal';
					if ($result_data->abnormal == 'AA') $abnormal = 'Critical';
					if ($result_data->abnormal == 'S') $abnormal = 'Susceptible';
					if ($result_data->abnormal == 'R') $abnormal = 'Resistant';
					if ($result_data->abnormal == 'I') $abnormal = 'Intermediate';
					if ($result_data->abnormal == 'NEG') $abnormal = 'Negative';
					if ($result_data->abnormal == 'POS') $abnormal = 'Positive';
?>
						<tr class="<?php if ($abnormal) echo 'table-danger'; ?>">
							<td style="padding-left:0">
								<?php echo $result_data->result_code ?>
							</td>
							<td>
								<?php echo $result_data->result_text ?>
							</td>
<?php 
					if ($result_data->result_data_type) { // there is an observation
						$value = $result_data->result;
						if ($result_data->units) $value .= " ".$result_data->units;
						if ($result_data->range || $abnormal) {
?>
							<td>
								<?php echo htmlspecialchars($value) ?>
							</td>
							<td>
								<?php echo htmlspecialchars($result_data->range) ?>
							</td>
							<td>
								<?php echo $abnormal ?>
							</td>
<?php 
						} else {
?>
							<td colspan='3'>
								<?php echo htmlspecialchars($value) ?>
							</td>
<?php 
						}
?>
							<td>
								<?php echo htmlspecialchars($result_data->result_status) ?>
							</td>
							<td>
								<?php echo htmlspecialchars($result_data->facility) ?>
							</td>
						</tr>
<?php
						if ($result_data->comments) { // put comments below test line
?>
						<tr <?php if ($abnormal) echo 'style="font-weight:bold;color:#bb0000"'?>>
							<td></td>
							<td colspan="6" style="font-family:monospace;">
								<?php echo nl2br($result_data->comments); ?>
							</td>
						</tr>
<?php 
						} // end if comments
					} else { // end if obser value
?>	
							<td></td>
							<td colspan="6" style="text-align:left;font-family:monospace">
								<?php echo nl2br($result_data->comments); ?>
							</td>
							<td style="font-family:monospace;text-align:center;width:10%">
								<?php echo htmlspecialchars($result_data->facility) ?>
							</td>
							<td></td>
						</tr>
<?php
					} // end if observ 
				} // end result foreach
?>
					</tbody>
				</table>
<?php 
			} // end foreach ordered item

			// do we need a facility box at all?
			if (count($facility_list) > 0) {
?>
				<div class="control-label font-weight-bold">
					PROCESSING FACILITIES
				</div>			

				<table class="table table-sm w-100">
					<thead>
						<tr>
							<th scope="col" style="padding-left:0;width:140px">
								LAB 
							</th>
							<th scope="col">
								FACILITY NAME
							</th>
							<th scope="col">
								CONTACT INFORMATION
							</th>
							<th scope="col">
								FACILITY DIRECTOR
							</th>
							<th style="width:25%"></th>
						</tr>
					</thead>
					<tbody>
<?php 
				foreach ($facility_list AS $facility_data) {
					if ($facility['phone']) {
						$phone = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $facility['phone']);
					}
						
					$director = $facility['director'];
					if ($facility['npi']) $director .= "<br/>NPI: ".$facility['npi']; // identifier
	
					$address = '';
					if ($facility['street']) $address .= $facility['street']."<br/>";
					if ($facility['street2']) $address .= $facility['street2']."<br/>";
					if ($facility['city']) $address .= $facility['city'].", ";
					$address .= $facility['state']."&nbsp;&nbsp;";
					if ($facility['zip'] > 5) $address .= preg_replace('~.*(\d{5})(\d{4}).*~', '$1-$2', $facility['zip']);
					else $address .= $facility['zip'];
?>					
						<tr>
							<td style="padding-left:0">
								<?php echo $facility['code'] ?>
							</td>
							<td>
								<?php echo $facility['name'] ?>
							</td>
							<td>
								<?php echo $address ?>
							</td>
							<td>
								<?php echo $director ?>
							</td>
						</tr>	
					</tbody>
				</table>	
<?php
				} // end facility foreach
	 		} // end facilities
		} else { // no results
?>
			<tr><td class="font-weight-bold" colspan="4">
				NO RESULTS AVAILABLE
<?php 
		}
?>
			</td></tr>
		</table>
	</fieldset>
<?php 
		if (GetSeconds($order_data->reviewed_datetime)) {
			$header = "<tr><td style='min-width:150px'></td><td style='min-width:395px'></td><td style='min-width:140px'></td><td class='w-100'></td></tr>\n";
			$content = Reports::do_columns(User::getUserName($order_data->reviewed_id),'Reviewing Provider',User::getUserName($order_data->notified_id),'Notification By');
			$content .= Reports::do_columns(FormatDate($order_data->reviewed_datetime),'Reviewed Date',FormatDate($order_data->notified_datetime),'Notified Date');
			$content .= Reports::do_columns(false,false,$order_data->notified_person, 'Person Notified');
	
			if ($order_data->review_notes) {
				$content .= "<tr><td class='text-nowrap pt-1 pb-1 font-weight-bold align-text-top'>Review Notes:</td>";
				$content .= "<td colspan='3' class='pt-1 pb-1' style='whitespace:pre-wrap'>".htmlspecialchars_decode($order_data->review_notes)."</td></tr>";
			}
			
			if ($order_data->patient_notes) {
				$content .= "<tr><td class='text-nowrap pt-1 pb-1 font-weight-bold align-text-top'>Patient Notes:</td>";
				$content .= "<td colspan='3' class='pt-1 pb-1' style='whitespace:pre-wrap'>".htmlspecialchars_decode($order_data->patient_notes)."</td></tr>";
			}
			
			Reports::do_section($header . $content, 'Review Information', 'max-medium');
		}
	} // end results
?>
<?php 
} // end declaration 

?>
