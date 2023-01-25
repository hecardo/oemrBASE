<?php 
/** **************************************************************************
 *	LABORATORY/ResultReport.PHP
 *
 *	Copyright (c)2014 - Medical Technology Services (MDTechSvcs.com)
 *
 *	This program is licensed software: licensee is granted a limited nonexclusive
 *  license to install this Software on more than one computer system, as long as all
 *  systems are used to support a single licensee. Licensor is and remains the owner
 *  of all titles, rights, and interests in program.
 *
 *  Licensee will not make copies of this Software or allow copies of this Software 
 *  to be made by others, unless authorized by the licensor. Licensee may make copies 
 *  of the Software for backup purposes only.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT 
 *	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 *  FOR A PARTICULAR PURPOSE. LICENSOR IS NOT LIABLE TO LICENSEE FOR ANY DAMAGES, 
 *  INCLUDING COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL 
 *  DAMAGES, CONNECTED WITH OR RESULTING FROM THIS LICENSE AGREEMENT OR LICENSEE'S 
 *  USE OF THIS SOFTWARE.
 *
 *  @package laboratory
 *  @subpackage generic
 *  @version 2.0
 *  @copyright Medical Technology Services
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */
//require_once("{$GLOBALS['fileroot']}/vendor/tecnickcom/tcpdf/tcpdf.php");
//require_once("{$GLOBALS['fileroot']}/vendor/tecnickcom/tcpdf/fpdi/fpdi.php");
require_once("{$GLOBALS['fileroot']}/library/tcpdf/tcpdf.php");
require_once("{$GLOBALS['fileroot']}/library/tcpdf/fpdi/fpdi.php");

$client_address = '';

if (!class_exists("ReportConcat")) {
	class ReportConcat extends FPDI {
		public function Footer() {}
		public function Header() {}
	}
}

if (!class_exists("ResultReport")) {
	/**
	 * The class LabCorpResult is used to generate the lab documents for
	 * the LabCorp interface. It utilizes the TCPDF library routines to 
	 * generate the PDF documents.
	 *
	 */
	class ResultReport extends TCPDF {
		/**
		 * Overrides the default header method to produce a custom document header.
		 * @return null
		 * 
		 */
		public function Header() {
			global $message, $client_address, $lab_data;
			
			$docroot = $_SERVER['DOCUMENT_ROOT'];
			if ($docroot == '/') $docroot = $GLOBALS['wmt_docroot'];
			$this->Image($docroot.'/images/new_logo.png', 15, 10, 120);
			
			ob_start();
?>
<table style="width:100%">
	<tr><td><br/><br/></td></tr>
	<tr>
		<td style="text-align:center;font-size:20px;font-weight:bold">
			<?php echo $lab_data['name'] ?>
		</td>
	</tr>
	<tr>
		<td style="text-align:center;font-weight:bold">
			Williams Medical Technologies, Inc.
		</td>
	</tr>
</table>
<?php 
			$output = ob_get_clean(); 
			$this->writeHTMLCell(0,0,'','',$output,0,1);
			$this->ln(15);

			ob_start();
?>
<table nobr="true" style="width:100%">
	<tr>
		<td style="width:50%"><span style="font-size:1.3em;font-weight:bold">&nbsp;&nbsp;&nbsp;&nbsp;eResults</span></td>
		<td style="width:55%;text-align:right"><span style="font-size:1.3em;font-weight:bold">&nbsp;</span>Page <?php echo $this->getAliasNumPage() ?> of <?php echo $this->getAliasNbPages() ?></td>
	</tr>
</table>
<?php 
			$output = ob_get_clean(); 
			$this->writeHTMLCell(0,0,'','',$output,0,1);

			$pageNo = $this->PageNo();
			if ($pageNo > 1) { // starting on second page
				// reset header height for page 2+
				$this->SetMargins(PDF_MARGIN_LEFT, 150, PDF_MARGIN_RIGHT);
					
				ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td style="width:60%;border:1px solid black">
			<small>Patient Name</small><br/>
			<b><?php echo $message->name[0].", ".$message->name[1]." ".$message->name[2] ?></b>
		</td>
		<td style="width:20%;border:1px solid black">
			<small>Account Number</small><br/><b><?php echo $message->account_id ?></b>
		</td>
		<td style="width:20%;border:1px solid black">
			<small>Accession Number</small><br/><b><?php echo $message->lab_number; ?></b>
		</td>
	</tr>
</table>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td style="width:15%;border:1px solid black">
			<small>Patient ID</small><br/>
			<b><?php echo $message->pubpid ?></b>
		</td>
		<td style="width:15%;border:1px solid black">
			<small>Date of Birth</small><br/>
			<b><?php if ($message->dob) echo ( date('Y-m-d',strtotime($message->dob)) ) ?></b>
		</td>
		<!-- td style="width:12%;border:1px solid black">
			<small>Patient Age</small><br/>
			<b><?php if ($message->dob) echo ( floor((time() - strtotime($message->dob)) / 31556926) ) ?></b>
		</td -->
		<td style="width:15%;border:1px solid black">
			<small>Gender</small><br/>
			<b><?php echo $message->sex ?></b>
		</td>
		<td style="width:15%;border:1px solid black">
			<small>Order Number</small><br/>
			<b><?php echo $message->order_number ?></b>
		</td>
		<td style="width:20%;border:1px solid black">
			<?php $label = ($lab_data['type'] == 'radiology')? "Date/Time Ordered" : "Date/Time Collected"; ?>
			<small><?php echo $label ?></small><br/>
			<b><?php echo ($message->specimen_datetime) ? date('Y-m-d h:i A', strtotime($message->specimen_datetime)) : '' ?></b>
		</td>
		<td style="width:20%;border:1px solid black">
			<small>Date/Time Reported</small><br/>
			<b><?php echo date('Y-m-d h:i A', strtotime($message->reported_datetime)) ?></b>
		</td>
	</tr>
</table>
<?php 
				$output = ob_get_clean(); 
				$this->writeHTMLCell(0,0,'','',$output,0,1);
				$this->ln(5);
				
				ob_start();
?>
<table style="width:100%">
	<tr style="font-size:8px;font-weight:bold;">
		<td style="width:15px">&nbsp;</td>
<?php if ($lab_data['type'] == 'radiology') { ?>
		<td style="width:65%">
			RESULT DESCRIPTION
		</td>
		<td style="text-align:center;width:11%">
			REPORTED
		</td>
		<td style="text-align:center;width:9%">
			STATUS
		</td>
		<td style="text-align:center;width:11%">
			FACILITY
		</td>
<?php } else { ?>
		<td style="width:8%">
			RESULT 
		</td>
		<td style="width:25%">
			DESCRIPTION
		</td>
		<td style="width:9%">
			VALUE
		</td>
		<td style="width:13%">
			UNITS
		</td>
		<td style="width:13%">
			REFERENCE
		</td>
		<td style="text-align:center;width:11%">
			FLAG
		</td>
		<td style="text-align:center;width:9%">
			STATUS
		</td>
		<td style="text-align:center;width:11%">
			FACILITY
		</td>
<?php } ?>
		<td></td>
	</tr>
</table>
<?php
				$output = ob_get_clean(); 
				$this->writeHTMLCell(0,0,'','',$output,0,1);
				$this->ln(5);
				
			} // end page 2+ header
			
		} // end header

		
		/**
		 * Overrides the default footer method to produce a custom document footer.
		 * @return null
		 * 
		 */
		public function Footer() {
			global $message;
			
			ob_start();
?>
			<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
				<tr>
					<td style="border:1px solid black">
						<small>Accession Number</small><br/><b><?php echo $message->lab_number ?></b>
					</td>
					<td style="border:1px solid black">
						<small>Patient ID</small><br/>
						<b><?php echo ($message->pubpid) ? $message->pubpid : $message->pid ?></b>
					</td>
					<td style="border:1px solid black">
						<small>Order Number</small><br/>
						<b><?php echo $message->order_number ?></b>
					</td>
					<td style="border:1px solid black">
						<small>Account Number</small><br/>
						<b><?php echo $message->account_id ?></b>
					</td>
					<td style="border:1px solid black">
						<small>Report Status</small><br/>
						<b><?php echo $message->report_status ?></b>
					</td>
				</tr>
			</table>
<?php 
			$output = ob_get_clean(); 
			$this->writeHTMLCell(0,0,'','',$output,0,1);
					
			ob_start();
?>
			<table nobr="true" style="width:100%;padding:10px 0;font-size:1.2em">
				<tr>
					<td style="width:5%">&nbsp;</td>
					<td style="text-align:left;width:45%">
						<?php echo date('m/d/Y h:i A') ?>
					</td>
					<td style="text-align:right;width:50%">
						Page <?php echo $this->getAliasNumPage() ?> of <?php echo $this->getAliasNbPages() ?>
					</td>
				</tr>
			</table>
<?php 
			$output = ob_get_clean(); 
			$this->writeHTMLCell(0,0,'','',$output,0,1);
									
		} // end footer
	} // end Result
} // end if exists

/**
 *
 * The makeResultDocuments() creates a PDF requisition.
 *
 * 1. Create a PDF requisition document
 * 2. Store the document in the repository
 * 4. Return a reference to the document
 *
 * @access public
 * @param Request $request object
 * @return string $document PDF document as string
 * 
 */
if (!function_exists("makeResultDocument")) {
	/**
	 * The makeResultDocument function is used to generate the requisition for
	 * the laboratory interface. It utilizes the TCPDF library routines to 
	 * generate the PDF document.
	 *
	 * @param Order $order object containing original input data
	 * @param Request $request object containing prepared request data
	 * 
	 */
	function makeResultDocument(&$message, &$lab_data) {
		// get client information
		global $client_address;
		
		// fetch order detail records
		$item_list = wmtOrderItem::fetchItemList($message->order_number);
		
		// fetch patient record
		$patient = false;
		if ($message->pid) { // we should have a patient
			$patient = wmtPatient::getPidPatient($message->pid);
			if (!$message->pubpid) $message->pubpid = $patient->pubpid;
		}
		
		// if no patient found
		if (! $patient) { // no patient (or could not find)
			$patient = new wmtPatient();
			
			$patient->lname = $message->name[0];
			$patient->fname = $message->name[1];
			$patient->mname = $message->name[2];
			$patient->DOB = $message->dob;
			$patient->pubpid = ($message->pubpid)? $message->pubpid : $message->external_id;
			$patient->sex = $message->sex;
			$patient->ss = $message->ss;
			$patient->phone_home = $message->phone;
			
			if ($message->address && is_array($message->address)) {
				$patient->street = $message->address[0];
				if ($message->address[1]) $patient->street .= "<br/>".$message->address[1];
				$patient->city = $message->address[2];
				$patient->state = $message->address[3];
				$patient->postal_code = $message->address[4];
			}
		}
		
		// SPECIAL FOR CERNER (use lab pid as pubpid)
		if ($lab_data['npi'] == 'CERNER' && $message->external_pid) {
			$patient->pubpid = $message->external_pid;
		}
		
		$client_address = "Unknown Site Identifier:<br/>";
		$client_address .= ($message->facility_id)? $message->facility_id : "NONE"; // in case we can't find it
		
		if ($message->facility_id) {
			$query = "
				SELECT fa.* FROM `facility` fa, `list_options` lo
				WHERE lo.`list_id` LIKE 'Lab_Site_Identifiers' 
					AND lo.`title` = fa.`id` AND lo.`option_id` = ?";
			$facility = sqlQuery($query,array($message->facility_id));
			if ($facility['name']) {
				$client_address = $facility['name']."<br/>";
				if ($facility['street']) $client_address .= $facility['street']."<br/>";
				if ($facility['city']) $client_address .= $facility['city'].",  ";
				if ($facility['state']) $client_address .= $facility['state']."  ".$facility['postal_code']."<br/>";
				if ($facility['phone'] && $facility['phone'] != '000-000-0000') $client_address .= $facility['phone'];
			}
		}
		
		// create new PDF document
		$pdf = new ResultReport('P', 'pt', 'letter', true, 'UTF-8', false);

		// set document information
		$pdf->SetCreator('OpenEMR');
		$pdf->SetAuthor('Williams Medical Technologies, Inc.');
		$pdf->SetTitle('Result Observations - '.$message->order_number);
		$pdf->SetSubject($lab_data['name'].' Results');
		$pdf->SetKeywords($lab_data['name'].', WMT, results, observation');

		// set header and footer fonts
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set initial margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, 105, PDF_MARGIN_RIGHT); // 80
		$pdf->SetHeaderMargin(15);
		$pdf->SetFooterMargin(60);
		//$pdf->setPrintHeader(false);
		//$pdf->setPrintFooter(false);
	
		// set auto page breaks / bottom margin
		$pdf->SetAutoPageBreak(TRUE, 65);

		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		$pdf->setJPEGQuality ( 100 );

		$pdf->setLanguageArray($l);

		// set font
		$pdf->SetFont('helvetica', '', 10);

		// start page
		$pdf->AddPage();

		// result image storage
		$images = $message->images;
		
		// truncate the lab number at Cerner 
		$message->lab_number = (strlen($message->lab_number) > 18) ? substr($message->lab_number,0,15).'...' : $message->lab_number;
		
		ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td style="border:1px solid black">
			<small>Accession Number</small><br/><b><?php echo $message->lab_number; ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Patient ID</small><br/>
			<b><?php $pubpid = ($message->pubpid) ? $message->pubpid : $message->extpid; 
					echo ($pubpid) ? $pubpid : $message->pid ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Order Number</small><br/>
			<b><?php echo $message->order_number ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Account Number</small><br/>
			<b><?php echo $message->account ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Report Status</small><br/>
			<b><?php echo $message->report_status ?></b>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTMLCell(0,0,'','',$output,0,1);
		
		ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td colspan="3" width="50%" style="border:1px solid black">
			<small>Patient Name</small><br/>
			<b><?php echo $patient->lname.", ".$patient->fname." ".$patient->mname ?></b>
		</td>
		<td rowspan="3" colspan="2" width="50%">
			<small>Client Address</small><br/>
			<b><?php echo $client_address ?></b>
		</td>
	</tr>
	<tr>
		<td style="border:1px solid black">
			<small>Date of Birth</small><br/>
			<b><?php if ($patient->DOB) echo ( date('Y-m-d',strtotime($patient->DOB)) ) ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Patient Age</small><br/>
			<b><?php if ($patient->DOB) echo ( floor((time() - strtotime($patient->DOB)) / 31556926) ) ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Gender</small><br/>
			<b><?php echo $patient->sex ?></b>
		</td>
	</tr>
	<tr>
		<td style="border:1px solid black">
			<small>Alt Patient Id</small><br/>
			<b><?php echo ($pubpid) ? $patient->pid : ''; // pid displayed as pubpid above if pubpid missing ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Patient SS#</small><br/>
			<b><?php echo $patient->ss ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Patient Phone</small><br/>
			<b><?php echo $patient->phone_home ?></b>
		</td>
	</tr>
	<tr>
		<td colspan="3" style="border:1px solid black">
			<small>Patient Address</small><br/>
			<b><?php echo $patient->street ?>&nbsp;<br/>
			<?php if ($patient->city) echo $patient->city.", " ?><?php echo $patient->state ?>  <?php echo $patient->postal_code ?></b>&nbsp;<br/>
		</td>
		<td width="50%"  style="border:1px solid black">
			<small>Additional Information</small><br/><b><?php echo $message->additional_data; ?></b>
<?php 
//		$notes = 0;
//		if (count($message->notes) > 0) {
//			foreach ($message->notes AS $note) {
//				echo "<b>".$note ."</b><br/>\n";
//				if ($notes++ == 3) break;
//			}
//		}
?>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTMLCell(0,0,'','',$output,0,1);
		ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td style="border:1px solid black">
			<?php $label = ($lab_data['type'] == 'radiology' || $lab_data['type'] == 'internal')? "Date/Time Ordered" : "Date/Time Collected"; ?>
			<small><?php echo $label ?></small><br/>
			<b><?php echo ($message->specimen_datetime) ? date('Y-m-d h:i A', strtotime($message->specimen_datetime)) : '' ?></b>
		</td>
		<td style="border:1px solid black">
			<?php $label = ($lab_data['type'] == 'radiology' || $lab_data['type'] == 'internal')? "Date/Time Processed" : "Date/Time Received"; ?>
			<small><?php echo $label ?></small><br/>
			<b><?php echo ($message->received_datetime) ? date('Y-m-d h:i A', strtotime($message->received_datetime)) : '' ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Date/Time Reported</small><br/>
			<b><?php echo date('Y-m-d h:i A', strtotime($message->reported_datetime)) ?></b>
		</td>
		<td style="border:1px solid black">
			<small>Physician Name</small><br/>
			<b><?php if ($message->provider[1]) echo substr($message->provider[2], 0, 1)." ".$message->provider[1] ?></b>
		</td>
		<td style="border:1px solid black">
			<small>NPI Number</small><br/>
			<b><?php echo $message->provider[0] ?></b>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTMLCell(0,0,'','',$output,0,1);
		$pdf->ln(5);
					
		$count = 0;
		$tests = "";
		$codes = array();
		/* REMOVED... 06-02-2015
		foreach ($message->reports AS $report) {
			if ($report->service_id[0] != $last_id) {
				$last_id = $report->service_id[0];
				if ($tests) $tests .= "; ";
				$tests .= $report->service_id[1];
				$count++;
			}
		}
		REPLACE WITH FOLLOWING */
		foreach ($item_list as $order_item) {
			$report_data = wmtResult::fetchResult($order_item->procedure_order_id, $order_item->procedure_order_seq);
			if (!$report_data || $codes[$order_item->procedure_code]) continue; // no results yet
			$codes[$order_item->procedure_code] = true;
			if ($tests) $tests .= "; ";
			$tests .= $order_item->procedure_name;
			$count++;
		}
				
		ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td style="border:1px solid black">
			<small>Tests Processed  (<?php echo $count ?>)</small><br/>
			<b><?php echo $tests ?></b>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTMLCell(0,0,'','',$output,0,1);

		$note_text = '';
		if (count($message->notes) > 0) {
			foreach ($message->notes AS $note) {
				$note_text .= nl2br($note->comment);
			}
		}
		
		if ($note_text) { // only print section when there is text
			$pdf->ln(5);
			ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 5px">
	<tr>
		<td style="border:1px solid black">
			<small>Laboratory Comments</small><br/>
			<span style="font-weight:bold"><?php
			$first = true;
			foreach ($message->notes AS $note) {
				if (!$first) echo "<br/>";
				echo nl2br($note->comment);
				$first = false;
			}
			?></span>
		</td>
	</tr>
</table>
<?php 
			$output = ob_get_clean(); 
			$pdf->writeHTMLCell(0,0,'','',$output,0,1);
		} // end if lab comments
		
		$pdf->ln(5);
		ob_start();

		// loop through each ordered item
		$last_code = "FIRST";
		foreach ($item_list as $order_item) {
			$report_data = wmtResult::fetchResult($order_item->procedure_order_id, $order_item->procedure_order_seq);
			if (!$report_data) continue; // no results yet

			$reflex_data = '';
			if ($order_item->reflex_code) {
				$reflex_data = wmtResult::fetchReflex($report_data->procedure_order_id, $order_item->reflex_code, $order_item->reflex_set);
			}
?>
<table style="width:100%">
	<tr>
		<td colspan="2" style="text-align:left;font-size:1.0em;font-weight:bold">
<?php 		if ($last_code != "FIRST") echo "<br/><br/>";
			echo $order_item->procedure_name;
			if ($order_item->reflex_code) echo "<br/>&nbsp;&nbsp;&nbsp;Reflex test triggered by: ".$reflex_data->result_code."&nbsp;&nbsp;".$reflex_data->result;
//			if ($report_data->date_report) echo " [".date('Y-m-d H:i:s',strtotime($report_data->date_report))."]";
			if ($report_data->report_status == 'Rejected') echo " [REJECTED]";
?>													
		</td>
	</tr>
<?php 
			if ($report_data->report_notes) {
?>	
	<tr style="font-size:8px;font-weight:bold;">
		<td style="width:15px">&nbsp;</td>
		<td style="text-align:left;width:85%">
			LABORATORY COMMENTS
		</td>
	</tr>
	<tr style="font-family:monospace;font-size:11px" >
		<td>&nbsp;</td>
		<td style="text-align:left;width:85%">
			<?php echo $report_data->report_notes ?>
		</td>
	</tr>
	<tr><td colspan="2">&nbsp;</td></tr>
<?php
	 		}
?>
</table>
<?php 
			$last_code = $order_item->procedure_code;
			
			$specimen_list = wmtSpecimenItem::fetchItemList($report_data->procedure_report_id);
			if ($specimen_list) {
?>					
<table style="width:100%">
	<tr style="font-size:8px;font-weight:bold;">
		<td style="width:15px">&nbsp;</td>
		<td style="text-align:left;width:20%">
			SPECIMEN 
		</td>
		<td style="text-align:center;width:22%">
			SAMPLE COLLECTED
		</td>
		<td style="text-align:center;width:22%">
			SAMPLE RECEIVED
		</td>
		<td style="text-align:left;width:34%">
			ADDITIONAL INFORMATION
		</td>
		<td></td>
	</tr>
<?php 
				foreach ($specimen_list AS $specimen_data) {
					// add in details as notes if necessary
					$notes = '';
					if (count($specimen_data->detail_notes) > 0) { // need to process details
						// merge details into a single note field
						foreach ($specimen_data->detail_notes AS $detail) {
							$note = $detail->observation_id[1]; // text
							if (!$note) continue; // nothing there
							
							$obvalue = $detail->observation_value;
							if (is_array($obvalue)) $obvalue = $obvalue[0]; // save text portion
							$note .= ": " . $obvalue . " " . $detail->observation_units;
							
							if ($notes) $notes .= "<br/>\n";
							$notes .= htmlentities($note);
						}
					}
		
					// SPECIAL FOR CERNER (strip leading zeros)
					if ($lab_data['npi'] == 'CERNER') {
						$specimen_data->specimen_number = ltrim($specimen_data->specimen_number,'0');
					}
		
?>					
	<tr style="font-family:monospace;font-size:11px" >
		<td>&nbsp;</td>
		<td style="text-align:left">
			<?php echo $specimen_data->specimen_number ?>
		</td>
		<td style="text-align:center">
			<?php echo $specimen_data->collected_datetime ?>
		</td>
		<td style="text-align:center">
			<?php echo $specimen_data->received_datetime ?>
		</td>
		<td style="text-align:left">
			Type: 
			<?php echo ($specimen_data->specimen_type)? $specimen_data->specimen_type : 'UNKNOWN'; ?>
			<?php if ($specimen_data->type_modifier) echo "<br/>Modifier: $specimen_data->type_modifier"; ?>		
			<?php if ($specimen_data->specimen_additive) echo "<br/>Additive: $specimen_data->specimen_additive"; ?>		
			<?php if ($specimen_data->collection_method && $lab_data['npi'] != "CERNER") echo "<br/>Method: $specimen_data->collection_method"; ?>		
			<?php if ($specimen_data->source_site) {
				echo "<br/>Source: $specimen_data->source_site"; 
				if ($specimen_data->source_quantifier && $specimen_data->source_site != $specimen_data->source_quantifier) 
					echo "( $specimen_data->source_quantifier )"; }
			?>		
			<?php if ($specimen_data->specimen_volume) echo "<br/>Volume: $specimen_data->specimen_volume"; ?>		
			<?php if ($specimen_data->specimen_condition) echo "<br/>Condition: $specimen_data->specimen_condition"; ?>		
			<?php if ($specimen_data->specimen_rejected) echo "<br/>Rejected: $specimen_data->specimen_rejected"; ?>		
			<?php if ($notes) echo "<br/>$notes"; ?>		
		</td>	
	</tr>		
	<tr><td colspan="5">&nbsp;</td></tr>
</table>
<?php
	 			} // end specimens
			} // end if specimens
			
			$result_list = wmtResultItem::fetchItemList($report_data->procedure_report_id);
			if ($result_list) {

?>	
<table style="width:100%">
	<tr style="font-size:8px;font-weight:bold;">
		<td style="width:15px">&nbsp;</td>
<?php if ($lab_data['type'] == 'radiology') { ?>
		<td colspan="4" style="width:65%">
			RESULT DESCRIPTION
		</td>
		<td style="text-align:center;width:11%">
			REPORTED
		</td>
		<td style="text-align:center;width:9%">
			STATUS
		</td>
		<td style="text-align:center;width:11%">
			FACILITY
		</td>
<?php } else { ?>
		<td style="width:25%">
			RESULT
		</td>
		<td style="width:13%">
			VALUE
		</td>
		<td style="width:13%">
			UNITS
		</td>
		<td style="width:13%">
			REFERENCE
		</td>
		<td style="text-align:center;width:11%">
			FLAG
		</td>
		<td style="text-align:center;width:9%">
			STATUS
		</td>
		<td style="text-align:center;width:11%">
			FACILITY
		</td>
<?php } // END HEADER ?>
		<td></td>
	</tr>
<?php 
				// process each observation
				foreach ($result_list AS $result_data) {
		
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
					
					$rstat = 'P'; // prelim
					if (strtolower($result_data->result_status) == 'final') $rstat = 'F';
					if (strtolower($result_data->result_status) == 'cancel') $rstat = 'X';
					if (strtolower($result_data->result_status) == 'corrected') $rstat = 'C';
?>
	<tr style="<?php if ($abnormal) echo "color:#bb0000;font-weight:bold;"?>font-family:monospace;font-size:10px" >
		<td style="width:15px">&nbsp;</td>

<?php  				// ------------- RADIOLOGY -------------
					if ($lab_data['type'] == 'radiology') { // SPECIAL FOR RADIOLOGY 
						if ($result_data->result_data_type == 'RP') { // put LINK on same line
?>
		<td colspan="2">
			<?php echo trim($result_data->result_code) ?>
		</td>
		<td colspan="2">
			<a href="<?php echo htmlspecialchars($result_data->result) ?>" target="_blank">IMAGE LINK</a>
		</td>
<?php 				
						} else {
?>
		<td colspan="4">
			<?php echo ($result_data->result_text) ? $result_data->result_text : $result_data->result_code ?>
		</td>
<?php 
						} // end link check
?>
		<td>
			<?php echo date('Y-m-d',strtotime($result_data->date)); ?>
		</td>
		<td style="text-align:center">
			<?php echo htmlentities($rstat) ?>
		</td>
		<td style="text-align:center">
			<?php echo htmlentities($result_data->facility) ?>
		</td>
		<td></td>
<?php 
						if ($result_data->result_data_type == 'TX') { // put TEXT on next line
?>
	</tr>
	<tr style="font-family:monospace;<?php if ($abnormal) echo "font-weight:bold;color:#bb0000;font-size:10px" ?>">
		<td style="width:35px">&nbsp;</td>
		<td colspan="6" style="font-family:monospace;font-size:10px;">
			<br/><br/>
			<?php echo nl2br($result_data->result); ?>
			<br/>
		</td>
		<td></td>
<?php 
						} 
?> 
	</tr>
<?php				} else {  
						
						// ------------- LABORATORY -------------
?>

		<td <?php if ($result_data->result_data_type == 'FT') echo 'colspan="4"' ?>>
			<?php echo $result_data->result_text ?>
		</td>
<?php 
						if ($result_data->result_data_type) { // there is an observation
							if ($result_data->result_data_type == 'FT') { // special for Cerner
								// already used the columns above
							} elseif ($result_data->units) {
?>
		<td>
			<?php if ($result_data->result != ".") echo htmlentities($result_data->result) ?>
		</td>
		<td>
			<?php echo htmlentities($result_data->units) ?>
		</td>
		<td>
			<?php echo htmlentities($result_data->range) ?>
		</td>
<?php
							} elseif ($result_data->range) { 
?>
		<td colspan="2">
			<?php if ($result_data->result != ".") echo htmlentities($result_data->result) ?>
		</td>
		<td>
			<?php echo htmlentities($result_data->range) ?>
		</td>
<?php 	
			 				} else { 
?>
		<td colspan="3">
			<?php if ($result_data->result != ".") echo htmlentities($result_data->result) ?>
		</td>
<?php 	
							} 
?>
		<td style="text-align:center">
			<?php echo $abnormal ?>
		</td>
		<td style="text-align:center">
			<?php echo htmlentities($rstat) ?>
		</td>
		<td style="text-align:center">
			<?php
				$lab_code = $result_data->facility;
				if ($lab_data['npi'] == 'BBPL') $lab_code = 'BBPL';
				echo htmlentities($lab_code) ?>
		</td>
		<td></td>
	</tr>
<?php
							if ($result_data->result_data_type == 'FT' && $result_data->result) { // put comments below test line
?>
	<tr style="font-family:monospace;<?php if ($abnormal) echo "font-weight:bold;color:#bb0000;font-size:10px" ?>">
		<td>&nbsp;</td>
		<td colspan="6" style="font-family:monospace;padding-left:10px;font-size:10px;whitespace:nowrap">
			<pre><?php echo $result_data->result; ?></pre>
		</td>
	</tr>
<?php 
							} // end if FT results
							
							if ($result_data->comments) { // put comments below test line
?>
	<tr style="font-family:monospace;<?php if ($abnormal) echo "font-weight:bold;color:#bb0000;font-size:10px" ?>">
		<td colspan="2">&nbsp;</td>
		<td colspan="6" style="font-family:monospace;padding-left:10px;font-size:10px">
			<pre><?php echo $result_data->comments; ?></pre>
		</td>
	</tr>
<?php 
							} // end if comments
						} // end if obser value
						else { 
?>
		<td colspan="2">&nbsp;</td>
		<td colspan="5" style="font-family:monospace;padding-left:10px;font-size:10px">
			<pre><?php echo $result_data->comments; ?></pre>
		</td>
		<td style="font-family:monospace;text-align:center;font-size:10px">
			<?php
				$lab_code = $result_data->facility;
				if ($lab_data['npi'] == 'BBPL') $lab_code = 'BBPL';
				echo htmlentities($lab_code) ?>
		</td>
		<td></td>
	</tr>
<?php
						} // end if observ
						
						// SPECIAL FOR COVID-19 TESTING
						if ($result_data->result_code == '94500-6') {
							if ($result_data->result == 'Positive') {
?>
	<tr style="font-family:monospace;font-weight:bold;color:#bb0000;font-size:10px">
		<td colspan="2">&nbsp;</td>
		<td colspan="6" style="font-family:monospace;padding-left:10px;font-size:10px"><br/><br/>
Positive results are indicative of the presence of SARS-CoV-2 RNA;<br/>
clinical correlation with patient history and other diagnostic<br/>
information is necessary to determine patient infection status.<br/>
Positive results do not rule out bacterial infection or co-infection<br/>
with other viruses. Positive and negative predictive values of<br/>
testing are highly dependent on prevalence.<br/>
		</td>
	</tr>
<?php
							} else { 
?>
	<tr style="font-family:monospace;font-weight:bold;color:#bb0000;font-size:10px">
		<td colspan="2">&nbsp;</td>
		<td colspan="6" style="font-family:monospace;padding-left:10px;font-size:10px"><br/><br/>
Negative results do not preclude SARS-CoV-2 infection and should not<br/>
be used as the sole basis for patient management decisions. Negative<br/>
results must be combined with clinical observations, patient history,<br/>
and epidemiological information. Optimum specimen types and timing<br/>
for peak viral levels during infections caused by SARS-CoV-2 have not<br/>
been determined. Collection of multiple specimens or types of<br/>
specimens may be necessary to detect virus. Improper specimen<br/>
collection and handling, sequence variability under primers/probes,<br/>
or organism present below the limit of detection may lead to false<br/>
negative results. Positive and negative predictive values of<br/>
testing are highly dependent on prevalence. False negative test<br/>
results are more likely when prevalence is high.<br/>
		</td>
	</tr>
<?php 
							}
						}
						
					} // END NORMAL LABORATORY
				} // end result foreach
?>
</table>
<?php
			} // end if results
		} // end foreach ordered item
		
		$output = ob_get_clean(); 
//echo "<pre>".htmlspecialchars($output)."</pre>"; // DEBUGGING
		$pdf->writeHTMLCell(0,0,'','',$output,0,1);
		$pdf->ln(20);

		// do we need a facility box at all?
		if (count($message->labs) > 0) {
			ob_start();
?>
<table nobr="true" style="width:100%;border:1px solid black;padding:0 10px;font-size:0.8em">
<?php 
		// loop through all of the labs
			$first = true;
			foreach ($message->labs AS $lab) {
				if ($lab->phone) {
					$phone = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $lab->phone);
				}
	
				$director = "DIRECTOR NOT PROVIDED";
				if ($lab->director) {
					$director = "";
					if (is_array($lab_director)) {
						if ($lab->director[5]) $director .= $lab->director[5]." ";
						$director .= $lab->director[2]." "; // first
						if ($lab->director[3]) $director .= $lab->director[3]." "; // middle
						$director .= $lab->director[1]." "; // last
						if ($lab->director[4]) $director .= $lab->director[4]." "; // suffix
						if ($lab->director[0]) $director .= "<br/>NPI: ".$lab->director[0]; // identifier
					}
					else {
						$director = $lab->director;
					}
				}

				$address = "ADDRESS NOT PROVIDED";
				if ($lab->address) {
					if ($lab->address[4]) {
						if (strlen($lab->address[4] > 5)) $zip = preg_replace('~.*(\d{5})(\d{4}).*~', '$1-$2', $lab->address[4]);
						else $zip = $lab->address[4];				
					}
					$address = $lab->address[0].", ";
					if ($lab->address[1]) $address .= $lab->address[1].", ";
					$address .= $lab->address[2].", ";
					$address .= $lab->address[3]." ";
					$address .= $zip." ";
				
					if ($lab->address[5] || $lab->address[8]) {
						$address .= $lab->address[5];
						if ($lab->address[8]) $address .= " ( ".$lab->address[8]." )";
					}
				}
									
?>
	<tr nobr="true">
		<td style="width:15%">
			<b><?php echo $lab->code ?></b>
		</td>
		<td style="width:55%">
<?php 		
				echo $lab->name."<br/>";
				echo $address;
				echo "<br/>";
?>
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<?php if ($lab->phone) { ?>
				<b>For inquiries, please contact the lab at: <?php echo $phone ?></b>
				<?php } elseif ($lab->address) { ?> 
				<b>For inquiries, please contact the lab at the above address.</b>
				<?php } ?>
		</td>
		<td style="width:30%">
			Director: <?php echo $director ?>
		</td>
	</tr>
<?php
			} // end foreach lab 
?>
</table>
<?php 		
			$output = ob_get_clean();
			$pdf->writeHTMLCell(0,0,'','',$output,0,1);
		} // end of need facility box check

		// finish page
		$pdf->lastPage();

		// generate the PDF document
		$document = $pdf->Output('result.pdf','S'); // return as variable
		
		/* ************************************************************************************************* *
		 *   CAPTURE AND ATTACH IMAGES TO PDF OUTPUT FILE                                                    *
		 * ************************************************************************************************* */
		if (count($images) > 0) { // we have image attachments
			$pdfc = new ReportConcat('P', 'pt', 'letter', true, 'UTF-8', false);
				
			$pdfc->setPrintHeader(false);
			$pdfc->setPrintFooter(false);
				
			$thefile = tempnam(sys_get_temp_dir(),'PDF'); // work file
			file_put_contents($thefile, $document);

			// add generated result document
			$pagecount = $pdfc->setSourceFile($thefile);
			for ($i = 1; $i <= $pagecount; $i++) {
				$tplidx = $pdfc->ImportPage($i);
				$s = $pdfc->getTemplatesize($tplidx);
				$pdfc->AddPage('P', array($s['w'], $s['h']));
				$pdfc->useTemplate($tplidx);
			}
		
			// add embedded documents
			foreach ($images AS $image) {
				// write raw file
				$thedoc = base64_decode($image);
				$thefile = tempnam(sys_get_temp_dir(),'PDF'); // work file
				file_put_contents($thefile, $thedoc);
				
				$pagecount = $pdfc->setSourceFile($thefile);
				for ($i = 1; $i <= $pagecount; $i++) {
					$tplidx = $pdfc->ImportPage($i);
					$s = $pdfc->getTemplatesize($tplidx);
					$pdfc->AddPage('P', array($s['w'], $s['h']));
					$pdfc->useTemplate($tplidx);
				}
			}
	
			// generate merged document
			$document = $pdfc->Output('total.pdf','S'); // return as variable
		}
			
		return $document;
	} // end makeResultDocument
} // end if exists
