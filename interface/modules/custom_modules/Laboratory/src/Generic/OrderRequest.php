<?php 
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Generic;

use Mpdf\Mpdf;
use \Document;

use WMT\Objects\Patient;
use WMT\Objects\Insurance;
use WMT\Classes\Options;

/**
 * The class OrderRequest is used to generate the lab documents for
 * the laboratory interface. It utilizes the mPFD library routines to 
 * generate the PDF documents.
 */
class OrderRequest 
{
	public function Header() {
		$order_data = $this->order_data;
		$pat_data = $this->pat_data;
		
		$pageNo = $this->PageNo();
		if ($pageNo > 1) { // starting on second page
			$acct = $order_data->request_account;
			$date = 'PSC HOLD';
			if ($order_data->date_ordered > 0)
				$date = date('m/d/Y',strtotime($order_data->date_ordered));
			$pubpid = $order_data->pid;
			if ($order_data->pubpid != $order_data->pid) $pubpid .= " ( ".$order_data->pid." )";
			$pat = $pat_data->lname . ", ";
			$pat .= $pat_data->fname . " ";
			$pat .= $pat_data->mname;
			
			$header = <<<EOD
<table style="width:80%;border:3px solid black">
	<tr>
		<td style="font-weight:bold;text-align:right">
			Account #:
		</td>
		<td style="text-align:left">
			&nbsp;$acct
		</td>
		<td style="font-weight:bold;text-align:right">
			Patient Name:
		</td>
		<td style="text-align:left">
			&nbsp;$pat
		</td>
	</tr>
	<tr>
		<td style="font-weight:bold;text-align:right">
			Requisition #:
		</td>
		<td style="text-align:left">
			&nbsp;$order_data->reqno
		</td>
		<td style="font-weight:bold;text-align:right">
			Patient ID:
		</td>
		<td style="text-align:left">
			&nbsp;$pubpid
		</td>
	</tr>
	<tr>
		<td style="font-weight:bold;text-align:right">
			Specimen Date:
		</td>
		<td style="text-align:left">
			&nbsp;$date
		</td>
		<td style="font-weight:bold;text-align:right">
			Page:
		</td>
		<td style="text-align:left">
EOD;
				//$header .= "&nbsp;". $this->getAliasNumPage() ." of ". $this->getAliasNbPages();
				$header .= <<<EOD
		</td>
	</tr>
</table>
EOD;
			// add the header to the document
			$this->writeHTMLCell(0,0,120,40,$header,0,1,0,1,'C');
		} // end if second page
	} // end header

	public function Footer() {
		$order_data = $this->order_data;
		$pat_data = $this->pat_data;
		$bar_data = $this->bar_data;
		
		$pageNo = $this->PageNo();
		$pageHeight = $this->getPageHeight();
		$pageY = $pageHeight - 90;
		if ($pageNo == 1 && $bar_data) { // first page only
			
			// set style for barcode
			$style = array(
					'border' => false,
					'padding' => 0,
					'vpadding' => 10,
					'hpadding' => 0,
					'fgcolor' => array(0,0,0),
					'bgcolor' => false, // array(255,255,255)
					'position' => 'R', // right margin
					'module_width' => 1, // width of a single module in points
					'module_height' => 1 // height of a single module in points
			);
				
			// print the barcode	
			if ($this->lab_npi == 'BBPL')
				$this->write2DBarcode($bar_data, 'PDF417', 0, $pageY, 150, 0, $style, 'N');

		} // end if first page
	} // end footer

	/**
	 * The makeOrderDocument function is used to generate the requisition for
	 * the LabCorp interface. It utilizes the mPDF library routines to 
	 * generate the PDF document.
	 *
	 * @param Order $order object containing original input data
	 * @param Request $request object containing prepared request data
	 * 
	 */
	public static function makeOrderDocument(&$order_data,&$test_list,&$aoe_list) {
		$pat_data = Patient::getPidPatient($order_data->pid);
		$lab_data = sqlQuery("SELECT * FROM procedure_providers WHERE ppid = ?",array($order_data->lab_id));
		
		// retrieve insurance information
		$ins_primary = new Insurance($order_data->ins_primary);
		$ins_secondary = new Insurance($order_data->ins_secondary);
		
		if ($lab_data['npi'] == 'BIOREF') {
			if ($ins_primary) {
				$ins = sqlQuery("SELECT lab_identifier FROM insurance_companies WHERE id = ?",array($ins_primary->provider));
				$ins_primary->cms_id = $ins['lab_identifier'];
			}
			if ($ins_secondary) {
				$ins = sqlQuery("SELECT lab_identifier FROM insurance_companies WHERE id = ?",array($ins_secondary->provider));
				$ins_secondary->cms_id = $ins['lab_identifier'];
			}
		}
		
		// retrieve facility
		if ($order_data->facility_id)
			$facility = sqlQuery("SELECT * FROM facility WHERE id = $order_data->facility_id LIMIT 1");

		// retrieve physician
		if ($order_data->provider_id)
			$provider = sqlQuery("SELECT * FROM users WHERE id = $order_data->provider_id LIMIT 1");
		
		// create new PDF document
		$config = [
			'mode' 				=> 'utf-8',
			'orientation' 		=> 'P',
			'default_font_size'	=> '10px',
			'default_font' 		=> 'dejavusans',
			'pagenumPrefix' 	=> 'Page ',
			'nbpgPrefix' 		=> ' of ',
			'nbpgSuffix' 		=> ''
		];
		$pdf = new Mpdf($config);
		$pdf->text_input_as_HTML = true;
		
		// set document information
		$pdf->SetCreator('OpenEMR');
		$pdf->SetAuthor('Williams Medical Technologies, Inc.');
		$pdf->SetTitle($lab_data['name'].' Order #'.$order_data->request_account."-".$order_data->order_number);
		
		// set default styles
		$style = <<<EOD
.pdf_title {font-size:18px;font-weight:bold;}
.pdf_subtitle {font-size:14px;font-weight:bold;}
.pdf_section {font-size:9px;font-weight:bold;background-color:#dcdcdc;border:1px solid black;padding:2px 4px;}
.pdf_label {font-size:10px;font-weight:normal;text-align:right;width:100px;vertical-align:top;padding-left:4px;}
.pdf_data {font-size:10px;font-weight:bold;text-align:left;vertical-align:top;padding-left:4px;}
.pdf_border {border:1px solid black;}
.barcode {padding:1.5mm;margin:0;vertical-align:top;color:#000000;}
.barcodecell {text-align:center;vertical-align:middle;padding:0;}
table {width:100%;border-collapse:collapse;}
EOD;
		$pdf->WriteHTML($style,1);

		//		$pdf->SetHTMLHeader();
		//		$pdf->SetHTMLFooter();
		
		// start page
		$pdf->AddPage();

		// set additional page margins
		//$pdf->SetMargins(30, 80, 30, true);
		
		$head_width = '100%';
		$barcode = '';
		if ($lab_data['npi'] == 'BBPL') {
			// assemble bar code
			$barcode = "~" . $order_data->request_account;
			$barcode .= "|";
			$barcode .= $order_data->order_number;
			$barcode .= "|";
			$barcode .= $pat_data->lname . "," . $pat_data->fname;
			if ($pat_data->mname) $barcode .= " " . $pat_data->mname;
			$barcode .= "|";
			$barcode .= ($order_data->request_billing == 'C')? 'C' : 'P'; // only clinic or patient/third-party
			$barcode .= "|";
			$barcode .= substr($pat_data->sex, 0, 1);
			$barcode .= "|";
			$barcode .= (strtotime($pat_data->DOB) !== false)? date('m/d/Y',strtotime($pat_data->DOB)): '';
			$barcode .= "|";
			$barcode .= (strtotime($order_data->date_collected) !== false)? date('m/d/Y',strtotime($order_data->date_collected)) : '';
			$barcode .= "|";
			$barcode .= (strtotime($order_data->date_collected) !== false && !$order_data->order_psc)? date('h:i',strtotime($order_data->date_collected)) : '';
			$barcode .= "|";
			$barcode .= $pat_data->pid;
			$barcode .= "||";
			$barcode .= $provider['lname'] . "," . $provider['fname'];
			if ($provider['mname']) $barcode .= " " . $provider['mname'];
			$barcode .= "|";
			$barcode .= $order_data->clinical_hx;
			$barcode .= "||";
			
			// all ordered items
			$tests = false;
			foreach ($test_list AS $test_data) {
				if ($tests) $tests .= ",";
				$tests .= trim($test_data['code']);
			}
			if ($tests) $barcode .= "@" . $tests;
			
			$barcode .= "|||\r";

		}
		
		if ( $lab_data['npi'] == '1194769497') {
			// assemble bar code
			$barcode = '<div class="barcodecell"><barcode type="C39" class="barcode" code="';
			$barcode .= $order_data->request_account;
			$barcode .= "-";
			$barcode .= $order_data->order_number;
			$barcode .= '"/></div>';

			$pdf->writeHTML($barcode,2);
		}
		
		ob_start(); 
?>
<table style="width:100%;text-align:center;">
	<tr>
		<td class="pdf_title">
			<?php echo $lab_data['name'] ?>
		</td>
	</tr>
	<tr>
		<td class="pdf_subtitle" style="font-size:10px">
			Williams Medical Technologies, Inc.
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		$pdf->ln(10);
		
		$label = 'eORDER';
		if ($order_data->order_psc) $label = "PSC HOLD";
		if ($lab_data['protocol'] == 'INT') $label = 'INTERNAL';

		if (strtoupper($lab_data['npi']) == 'PATHGROUP') $label .= ' - PM';
		if (strtoupper($lab_data['npi']) == 'BIOREF') {
			$label = $lab_data['send_fac_id'].',';
			$label .= $order_data->order_number;
			$label .= strtoupper( substr($pat_data->lname, 0, 1) );
			$label .= strtoupper( substr($pat_data->fname, 0, 1) );
		}
		
		if ($order_data->request_handling == 'stat') {
			$label .= ' - STAT';
		}				
		ob_start();
?>
<table style="width:100%">
	<tr>
		<td class="pdf_subtitle" style="width:50%;text-align:left;"><?php echo $label ?></td>
		<td class="pdf_subtitle" style="text-align:right;font-size:10px;">{PAGENO}{nbpg}</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		ob_start();
?>
<table>
	<tr>
		<td colspan="2" class="pdf_section">ACCOUNT INFORMATION:</td>
	</tr>
	<tr>
		<td class="pdf_border" style="width:50%">
			<table style="width:100%">
<?php 
	$order_data->reqno = $order_data->order_number;
	if ($lab_data['npi'] == '1194769497' || $lab_data['npi'] == 'SHIEL') {
		$order_data->reqno = $order_data->account_facility."-".$order_data->order_number;
		if ($lab_data['npi'] != 'SHIEL') {
?>
				<tr>
					<td class="pdf_label">Network #:</td>
					<td class="pdf_data">CPLOIRO2</td>
				</tr>
<?php 
		}
	} elseif ($order_data->account_facility) {
?>
				<tr>
					<td class="pdf_label">Account #:</td>
					<td class="pdf_data"><?php echo $order_data->account_facility ?></td>
				</tr>
<?php 
	} // end CPL checking 
?>
				<tr>
					<td class="pdf_label">Requisition #:</td>
					<td class="pdf_data"><?php echo $order_data->reqno ?></td>
				</tr>
			</table>
		</td>
		<td class="pdf_border" style="width:50%">
			<table style="width:100%">
<?php if ($lab_data['npi'] == '1194769497') { ?>
				<tr>
					<td class="pdf_label">Account:</td>
					<td class="pdf_data"><?php echo $order_data->account ?></td>
				</tr>
<?php } // end of CPL special
	if (! $order_data->order_psc) {
		$coll_date = date('m/d/Y',strtotime($order_data->date_collected));
		$coll_time = date('h:i A',strtotime($order_data->date_collected));
?>
		 
				<tr>
					<td class="pdf_label">Collection Date:</td>
					<td class="pdf_data"><?php echo $coll_date ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Collection Time:</td>
					<td class="pdf_data"><?php echo $coll_time ?></td>
				</tr>
<?php 
	}
	
	if ($order_data->copy_acct || $order_data->copy_fax || $order_data->copy_pat) {
		$copies = '';
		if ($order_data->copy_pat) {
			$copies = '<tr><td class="pdf_label">Courtesy Copy:</td>';
			$copies .= '<td class="pdf_data">Patient</td></tr>'; 
		}
		
		if ($order_data->copy_acct) {
			$copies .= '<tr><td class="pdf_label">Copy Account:</td>';
			$copies .= '<td class="pdf_data">'.$order_data->copy_acct; 
			if ($order_data->copy_acctname) $copies .= "<br/>". $order_data->copy_acctname;
			$copies .= "</td></tr>\n";
		}
	
			if ($order_data->copy_fax) {
			$copies .= '<tr><td class="pdf_label">Send Fax:</td>';
			$copies .= '<td class="pdf_data">'.substr($order_data->copy_fax, 0, 3) . '-' . substr($order_data->copy_fax, 3, 3) . '-' . substr($order_data->copy_fax, 6);; 
			if ($order_data->copy_faxname) $copies .= "<br/>". $order_data->copy_faxname;
			$copies .= "</td></tr>\n";
		}
		if ($copies) echo $copies;
	} // end copy to
?>
			</table>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		$pdf->ln(3);
		
		ob_start();
?>
<table>
	<tr>
		<td class="pdf_section" style="width:50%">CLIENT / ORDERING SITE INFORMATION:</td>
		<td class="pdf_section" style="width:50%">ORDERING PHYSICIAN:</td>
	</tr>
	<tr>
		<td class="pdf_border">
			<table style="width:100%">
				<tr>
					<td class="pdf_label">Account Name:</td>
					<td class="pdf_data"><?php echo $facility['name'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Client Address:</td>
					<td class="pdf_data"><?php echo $facility['street'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($facility['city'])? $facility['city'].", ": "" ?><?php echo $facility['state'] ?>  <?php echo $facility['postal_code'] ?></td>
				</tr>
<?php if ($facility['phone']) { ?>
				<tr>
					<td class="pdf_label">Phone:</td>
					<td class="pdf_data"><?php echo $facility['phone'] ?></td>
				</tr>
<?php } ?>
			</table>
		</td>
		<td class="pdf_border">
			<table style="width:100%">
				<tr>
					<td class="pdf_label">Physician Name:</td>
					<td class="pdf_data"><?php echo $provider['lname'] ?>, <?php echo $provider['fname'] ?> <?php echo $provider['mname'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">NPI:</td>
					<td class="pdf_data"><?php echo $provider['npi'] ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>		
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		$pdf->ln(3);
		
		$self_guarantor = false;
		if ($pat_data->fname == $pat_data->guarantor_fname &&
				$pat_data->lname == $pat_data->guarantor_lname)
			$self_guarantor = true;
		
		ob_start();
?>
<table nobr="true" style="margin-bottom:5px">
	<tr>
		<td class="pdf_section" colspan="2">
			PATIENT <?php if ($self_guarantor && $order_data->request_billing != 'C') echo "/ GUARANTOR "?>INFORMATION:
		</td>
	</tr>
	<tr>
		<td class="pdf_border" style="width:50%">
			<table>
				<tr>
					<td class="pdf_label">Patient Name:</td>
					<td class="pdf_data"><?php echo $pat_data->lname ?>, <?php echo $pat_data->fname ?> <?php echo $pat_data->mname ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Patient Address:</td>
					<td class="pdf_data"><?php echo $pat_data->street ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($pat_data->city)? $pat_data->city.", ": "" ?><?php echo $pat_data->state ?> <?php echo $pat_data->postal_code ?></td>
				</tr>
<?php if ($pat_data->phone_home) { ?>
				<tr>
					<td class="pdf_label">Phone:</td>
					<td class="pdf_data"><?php echo $pat_data->phone_home ?></td>
				</tr>
<?php } ?>
<?php if ($self_guarantor && $order_data->request_billing != 'C') { ?>
				<tr>
					<td class="pdf_label">Guarantor:</td>
					<td class="pdf_data"><?php echo ($order_data->work_flag)? "Work Comp": "Self" ?></td>
				</tr>
<?php } ?>
			</table>
		</td>
		<td class="pdf_border" style="width:50%">
			<table>
				<tr>
					<td class="pdf_label">Patient ID:</td>
					<td class="pdf_data">
						<?php echo ($pat_data->pubpid)? $pat_data->pubpid: $pat_data->pid ?>
					</td>
				</tr>
				<tr>
					<td class="pdf_label">Gender:</td>
					<td class="pdf_data"><?php echo $pat_data->sex ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Date of Birth:</td>
					<td class="pdf_data">
						<?php echo ($pat_data->DOB)? date('m/d/Y',strtotime($pat_data->DOB)): '' ?>
						<?php //echo ($order_data->pat_age)? ' ( '.$order_data->pat_age.' years )': '' ?>
					</td>
				</tr>
<?php if ($pat_data->race) { ?>				
				<tr>
					<td class="pdf_label">Race:</td>
					<td class="pdf_data">
						<?php echo Options::getListItem('Race',$pat_data->race) ?>
						<?php echo ($pat_data->ethnicity)? ' ('.Options::getListItem('Ethnicity',$pat_data->ethnicity).')': '' ?>
					</td>
				</tr>
<?php } ?>
<?php if ($pat_data->pid != $pat_data->pubpid) { ?>
				<tr>
					<td class="pdf_label">Alt Patient ID:</td>
					<td class="pdf_data">
						<?php echo $pat_data->pid ?>
					</td>
				</tr>
<?php } ?>
			</table>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		$pdf->ln(5);
		
		ob_start();
		
?>
<table>
	<tr>
		<td class="pdf_subtitle">Order Information</td>
	</tr>
</table>
<?php 
		$adtl_done = false; // done additional data section
		if (count($test_list) < 5) { // one section only
			$adtl_done = true;
?>
<table>
	<tr>
		<td class="pdf_section" style="width:50%">
			<table>
				<tr>
					<td style="width:68px;padding:2px;font-weight:bold;">TEST</td>
					<td style="padding:2px;font-weight:bold;">DESCRIPTION&nbsp;&nbsp;&nbsp;&nbsp;(total:<?php echo count($test_list) ?>)</td>
				</tr>
			</table>
		</td>
		<td class="pdf_section" style="width:50%">ADDITIONAL INFORMATION:</td>
	</tr>
	<tr>
		<td class="pdf_border" style="width:50%">
			<table>
<?php 
			foreach ($test_list AS $test_data) {
?>
				<tr>
					<td class="pdf_data" style="padding:2px;width:68px"><?php echo $test_data['code'] ?></td>
					<td class="pdf_data" style="padding:2px;"><?php echo htmlspecialchars(substr($test_data['name'],0,33)) ?></td>
				</tr>
<?php 
			} // end foreach test
?>			
			</table>
		</td>
		<td class="pdf_border">
			<table>
<?php if ($order_data->specimen_fasting) { ?>
				<tr>
					<td class="pdf_data" colspan=2 width="100%">[fasting] <?php echo $order_data->specimen_fasting; ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->pat_height > 0) { ?>
				<tr>
					<td class="pdf_data" colspan=2 width="100%">[height] <?php echo $order_data->pat_height ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->pat_weight > 0) { ?>
				<tr>
					<td class="pdf_data" colspan=2 width="100%">[weight] <?php echo $order_data->pat_weight ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->specimen_volume) { ?>
				<tr>
					<td class="pdf_data" colspan=2 width="100%">[volume] <?php echo $order_data->specimen_volume ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->specimen_source) { ?>
				<tr>
					<td class="pdf_data" colspan=2 width="100%">[source] <?php  echo $order_data->specimen_source ?></td>
				</tr>
<?php } 
 
	if (is_array($aoe_list)) {
		foreach($aoe_list AS $aoe_data) {
			if ($aoe_data['answer'] && $aoe_data['answer'] != '_blank') {
?>
				<tr>
					<td class="pdf_data" colspan=2 width="100%">[<?php echo $aoe_data['procedure_code'] ?>] <?php echo $aoe_data['question_text'] ?>: &nbsp; <?php echo $aoe_data['answer'] ?></td>
				</tr>
<?php 
			}
		} 
	}
?>
				<tr><td>&nbsp;</td></tr>
			</table>
		</td>
<?php 
		} else { // two sections
			$half = round(count($test_list) / 2);
?>
<table>
	<tr>
		<td class="pdf_section" style="width:50%">
			<table>
				<tr>
					<td style="width:68px;padding:2px;font-weight:bold;">TEST</td>
					<td style="padding:2px;font-weight:bold;">DESCRIPTION&nbsp;&nbsp;&nbsp;&nbsp;(total:<?php echo count($test_list) ?>)</td>
				</tr>
			</table>
		</td>
		<td class="pdf_section" style="width:50%">
			<table>
				<tr>
					<td style="width:68px;padding:2px;font-weight:bold;">TEST</td>
					<td style="padding:2px;font-weight:bold;">DESCRIPTION</td>
				</tr>
			</table>
		</td>
	</tr>

	<tr style="padding-top:5px">
<?php 
			$test = 99;
			foreach ($test_list AS $test_data) {
				if ($test > $half) {
					if ($test != 99) {
?>
			</table>
		</td>
<?php 
					} // end if first split
?>
		<td class="pdf_border" style="width:50%;vertical-align:top;">
			<table style="width:100%">
<?php 
					$test = 0;
				} // end new column
				$test++;
?>
				<tr>
					<td class="pdf_data" style="width:68px"><?php echo $test_data['code'] ?></td>
					<td class="pdf_data" style="width:330px"><?php echo htmlspecialchars(substr($test_data['name'],0,33)) ?></td>
				</tr>
<?php 
			} // end foreach test
?>			
			</table>
		</td>
<?php 
		} // end section selection
?>
	</tr>
</table>		
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		$pdf->ln(3);
		
		ob_start();
		$do_section = false;
		if ($adtl_done && ($order_data->clinical_hx || $order_data->patient_instructions) ) { // do we need this section?
			$do_section = true;
			$sec_title = '';
			if ($order_data->clinical_hx) $sec_title = 'ORDER COMMENTS';
			if ($order_data->patient_instructions) {
				if ($sec_title) $sec_title .= " / ";
				$sec_title .= "PATIENT INSTRUCTIONS";
			}
?>
<table nobr="true">
	<tr>
		<td class="pdf_section">ORDER COMMENTS:</td>
	</tr>
	<tr>
		<td class="pdf_border">
			<table>
<?php if ($order_data->clinical_hx) { ?>
				<tr>
					<td class="pdf_data">
						<span style="font-weight:normal">Clinical:</span>&nbsp:<?php echo $order_data->clinical_hx ?>
					</td>
				</tr>
<?php } ?>
<?php if ($order_data->patient_instructions) { ?>
				<tr>
					<td class="pdf_data">
						<span style="font-weight:normal">Patient:</span>&nbsp:<?php echo $order_data->patient_instructions ?>
					</td>
				</tr>
<?php } ?>
			</table>
		</td>
	</tr>
</table>		
<?php 
		} // end if
		
		if (!$adtl_done) { // need this section
			$do_section = true;
?>
<table nobr="true" style="width:100%;border:1px solid black;border-collapse:collapse">
	<tr>
		<td class="pdf_section" style="width:50%;">ORDER INFORMATION:</td>
		<td class="pdf_section" style="width:50%;">AOE RESPONSES:</td>
	</tr>
	<tr>
		<td class="pdf_border">
<?php if ($order_data->clinical_hx || $order_data->patient_instructions) { ?>
			<table>
<?php if ($order_data->clinical_hx) { ?>
				<tr>
					<td class="pdf_data">
						<span style="font-weight:normal">Clinical:</span>&nbsp;:<?php echo $order_data->clinical_hx ?>
					</td>
				</tr>
<?php } ?>
<?php if ($order_data->patient_instructions) { ?>
				<tr>
					<td class="pdf_data">
						<span style="font-weight:normal">Patient:</span>&nbsp;:<?php echo $order_data->patient_instructions ?>
					</td>
				</tr>
<?php } ?>
			</table>
<?php } ?>
		</td>
		<td class="pdf_border">
			<table style="width:100%">
<?php if ($order_data->specimen_fasting) { ?>
				<tr>
					<td class="pdf_label">Patient Fasting:</td>
					<td class="pdf_data"><?php echo $order_data->specimen_fasting; ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->pat_height > 0) { ?>
				<tr>
					<td class="pdf_label">Height (in):</td>
					<td class="pdf_data"><?php echo $order_data->pat_height ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->pat_weight > 0) { ?>
				<tr>
					<td class="pdf_label">Weight (lbs):</td>
					<td class="pdf_data"><?php echo $order_data->pat_weight ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->specimen_volume) { ?>
				<tr>
					<td class="pdf_label">Volume (mls):</td>
					<td class="pdf_data"><?php echo $order_data->specimen_volume ?></td>
				</tr>
<?php } ?>
<?php if ($order_data->specimen_source) { ?>
				<tr>
					<td class="pdf_label">Sample Source:</td>
					<td class="pdf_data"><?php echo $order_data->specimen_source ?></td>
				</tr>
<?php } 
 
	if (is_array($aoe_list)) {
		foreach($aoe_list AS $aoe_data) {
?>
				<tr>
					<td colspan=2 class="pdf_data">[<?php echo $aoe_data['procedure_code'] ?>] <?php echo $aoe_data['question_text'] ?>: &nbsp; <?php echo $aoe_data['answer'] ?></td>
				</tr>
<?php 
		} 
	}
?>
			</table>
		</td>
	</tr>
</table>		
<?php
		} // end if section needed 

		$output = ob_get_clean(); // clean buffer regardless
		if ($do_section) { 
			$pdf->writeHTML($output,2);
			$pdf->ln(3);
		}
			
		ob_start();
?>
<table nobr="true">
	<tr>
		<td class="pdf_section" colspan="6" >DIAGNOSIS CODES:</td>
	</tr>
	<tr>
<?php 
		$diag_array = explode("|",$order_data->order_diagnosis); // code & text
		if (!is_array($diag_array)) $diag_array = array();
	
		for ($i = 0; $i < 5;) {
			$diag = $diag_array[$i++];
			list($dx_code,$dx_text) = explode("^",$diag);
			$dx_code = str_replace('ICD9:', '', $dx_code);
			$dx_code = str_replace('ICD10:', '', $dx_code);
?>
		<td class="pdf_data pdf_border" style="padding-left:4px;width:20%"><?php echo $dx_code ?></td>
<?php 
		} // end for loop

		if ($diag_array[$i]) {
			echo "</tr><tr>";
			for ($i = 5; $i < 10;) {
				$diag = $diag_array[$i++];
				list($dx_code,$dx_text) = explode("^",$diag);
				$dx_code = str_replace('ICD9:', '', $dx_code);
				$dx_code = str_replace('ICD10:', '', $dx_code);
?>
		<td class="pdf_data pdf_border" style="padding-left:4px;width:20%"><?php echo $dx_code ?></td>
<?php 
			} // end for loop
		} // end if
?>
	</tr>
</table>
<?php 		
		$output = ob_get_clean(); // clean buffer regardless
		$pdf->writeHTML($output,2);
		$pdf->ln(5);
		
		$bill_list = 'Lab_Billing';
//		if ($lab_data['npi'] == '1194769497') $bill_list = 'Lab_CPL_Billing';
		$bill_type = Options::getListItem($bill_list, $order_data->billing_type);
		if (empty($bill_type)) $bill_type = 'UNKNOWN';
		
		ob_start();
?>
<table style="width:100%">
	<tr>
		<td colspan="2" class="pdf_subtitle">Billing Information</td>
	</tr>
	<tr>
		<td class="pdf_section" style="width:50%">BILLING TYPE:</td>
		<td class="pdf_section" style="width:50%">INSURANCE CODE:</td>
	</tr>
	<tr>
		<td class="pdf_border pdf_data"><?php echo $bill_type ?></td>
		<td class="pdf_border pdf_data">
<?php 
		if ( $order_data->request_billing == 'T' ||
				($lab_data['npi'] == '1194769497' && $order_data->billing_type != 'A' && $order_data->billing_type != 'P') ){
			if ($order_data->work_insurance) {
				echo Options::getListItem('Workers_Insurance',$order_data->work_insurance);
			}
			else { 
				echo ($ins_primary->cms_id) ? $ins_primary->cms_id : $ins_primary->id;
			}
		} else {
			echo "Not Available";
		}
?>
		</td>
	</tr>
</table>		
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
		$pdf->ln(3);
	
		if (!$self_guarantor && $order_data->request_billing != 'C') { // only needed when not patient
			ob_start();
			$gname = '';
			$gstreet = '';
			$gaddr = '';
			
			$relation = 'Unknown'; // default to other
			if ($pat_data->guarantor_relation)
				$relation = Options::getListItem('Relationship',$pat_data->guarantor_relation);
			
			if ($pat_data->guarantor_lname) {
				$gname = $pat_data->guarantor_lname .", ". $pat_data->guarantor_fname ." ". $pat_data->guarantor_mname;
			}
			else { // self
				$relation = 'Self'; 
				$gname = $pat_data->lname .", ". $pat_data->fname ." ". $pat_data->mname;
			}
							
			if ($pat_data->guarantor_city) {
				$gstreet = $pat_data->guarantor_street;
				$gaddr = $pat_data->guarantor_city .", ". $pat_data->guarantor_state ." ". $pat_data->guarantor_zip;
			} 
			else { // self
				$gstreet = $pat_data->street;
				$gaddr = $pat_data->city .", ". $pat_data->state ." ". $pat_data->postal_code;
			}
				
?>
<table nobr="true">
	<tr>
		<td colspan="2" class="pdf_section">RESPONSIBLE PARTY / GUARANTOR INFORMATION:</td>
	</tr>
	<tr>
		<td style="width:50%" class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Guarantor:</td>
					<td class="pdf_data"><?php echo $gname ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Address:</td>
					<td class="pdf_data"><?php echo $gstreet ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo $gaddr ?></td>
				</tr>
			</table>
		</td>
		<td style="width:50%" class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Relationship:</td>
					<td class="pdf_data"><?php echo $relation ?></td>
				</tr>
				<tr>
					<td class="pdf_label"></td>
					<td class="pdf_data"></td>
				</tr>
			</table>
		</td>
	</tr>
</table>		
<?php 
			$output = ob_get_clean(); 
			$pdf->writeHTML($output,2);
			$pdf->ln(3);
		} // end self guaranteed
		
		if ($order_data->order_abn_signed || $order_data->work_flag ) {
			ob_start();
?>
<table nobr="true" class="pdf_border">
	<tr>
		<td class="pdf_label">ABN Signed: </td>
		<td class="pdf_data"><?php echo ($order_data->order_abn_signed)? Options::getListItem('LabCorp_Yes_No',$order_data->order_abn_signed): '' ?></td>
		<td class="pdf_label">Worker's Comp: </td>
		<td class="pdf_data"><?php echo ($order_data->work_flag)? Options::getListItem('Order_Yes_No',$order_data->work_flag): '' ?></td>
		<td class="pdf_label">Date of Injury: </td>
		<td class="pdf_data"><?php echo ($order_data->work_flag)? date('m/d/Y',strtotime($order_data->work_date)): '' ?></td>
	</tr>
</table>		
<?php 
			$output = ob_get_clean(); 
			$pdf->writeHTML($output,2);
			$pdf->ln(3);
		} // end extra bar
		
		if ( $order_data->request_billing == 'T' ||
				($lab_data['npi'] == '1194769497' && $order_data->request_billing != 'A' && $order_data->request_billing != 'P') ) {
			// third-party so need insurance
			ob_start();
		
			if ($order_data->work_flag) { // workers comp insurance
				$ins_work = Insurance::getCompany($order_data->work_insurance);
?>
<table nobr="true" class="pdf_border">
	<tr>
		<td style="width:50%" class="pdf_section">WORKERS COMP INSURANCE:</td>
		<td style="width:50%" class="pdf_section">INSURED EMPLOYEE:</td>
	</tr>
	<tr>
		<td class="pdf_border">
			<table style="width:100%">
				<tr>
					<td class="pdf_label">Insurance Code:</td>
					<td class="pdf_data"><?php echo $ins_work['cms_id'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Company Name:</td>
					<td class="pdf_data"><?php echo $ins_work['company_name'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Ins Address:</td>
					<td class="pdf_data"><?php echo $ins_work['line1'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_work['city'])? $ins_work['city'].', ': '' ?><?php echo $ins_work['state'] ?> <?php echo $ins_work['zip'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Case Number:</td>
					<td class="pdf_data"><?php echo $order_data->work_case ?></td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
				</tr>
			</table>
		</td>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Insured Name:</td>
					<td class="pdf_data"><?php echo $pat_data->lname ?>, <?php echo $pat_data->fname ?> <?php echo $pat_data->mname ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Insured Address:</td>
					<td class="pdf_data"><?php echo $pat_data->street ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($pat_data->city)? $pat_data->city.', ': '' ?><?php echo $pat_data->state ?> <?php echo $pat_data->postal_code ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Employer:</td>
					<td class="pdf_data"><?php echo $order_data->work_employer ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>		
<?php 
				$output = ob_get_clean(); 
				$pdf->writeHTML($output,2);
				$pdf->ln(15);
			} // end workers comp insurance
			elseif ($order_data->ins_primary && $order_data->ins_secondary) { // two insurance plans
?>
<table nobr="true">
	<tr>
		<td style="width:50%" class="pdf_section">PRIMARY INSURANCE:</td>
		<td style="width:50%" class="pdf_section">SECONDARY INSURANCE:</td>
	</tr>
	<tr>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Company Name:</td>
					<td class="pdf_data"><?php echo $ins_primary->company_name ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Ins Address:</td>
					<td class="pdf_data"><?php echo $ins_primary->line1 ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_primary->city)? $ins_primary->city.', ': '' ?><?php echo $ins_primary->state ?> <?php echo $ins_primary->zip ?><?php if ($ins_primary->plus_four) echo "-".$ins_primary->plus_four ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Policy Number:</td>
					<td class="pdf_data"><?php echo $ins_primary->policy_number ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Group Number:</td>
					<td class="pdf_data"><?php echo $ins_primary->group_number ?></td>
				</tr>
			</table>
		</td>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Insurance Code:</td>
					<td class="pdf_data"><?php echo ($ins_secondary->cms_id) ? $ins_secondary->cms_id : $ins_secondary->id; ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Company Name:</td>
					<td class="pdf_data"><?php echo $ins_secondary->company_name ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Ins Address:</td>
					<td class="pdf_data"><?php echo $ins_secondary->line1 ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_secondary->city)? $ins_secondary->city.', ': '' ?><?php echo $ins_secondary->state ?> <?php if ($ins_secondary->plus_four) echo "-".$secondary['plus_four'] ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Policy Number:</td>
					<td class="pdf_data"><?php echo $ins_secondary->policy_number ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Group Number:</td>
					<td class="pdf_data"><?php echo $ins_secondary->group_number ?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td class="pdf_section">PRIMARY POLICY HOLDER / INSURED:</td>
		<td class="pdf_section">SECONDARY POLICY HOLDER / INSURED:</td>
	</tr>
	<tr>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Insured Name:</td>
					<td class="pdf_data"><?php echo $ins_primary->subscriber_lname ?>, <?php echo $ins_primary->subscriber_fname ?> <?php echo $ins_primary->subscriber_mname ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Insured Address:</td>
					<td class="pdf_data"><?php echo $ins_primary->subscriber_street ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_primary->subscriber_city)? $ins_primary->subscriber_city.', ': '' ?><?php echo $ins_primary->subscriber_state ?> <?php echo $ins_primary->subscriber_postal_code ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Relationship:</td>
					<td class="pdf_data"><?php echo ($ins_primary->subscriber_relationship)? Options::getListItem('sub_relation',$ins_primary->subscriber_relationship) : "Other" ?></td>
				</tr>
			</table>
		</td>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Insured Name:</td>
					<td class="pdf_data"><?php echo $ins_secondary->subscriber_lname ?>, <?php echo $ins_secondary->subscriber_fname ?> <?php echo $ins_secondary->subscriber_mname ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Insured Address:</td>
					<td class="pdf_data"><?php echo $ins_secondary->subscriber_street ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_secondary->subscriber_city)? $ins_secondary->subscriber_city.', ': '' ?><?php echo $ins_secondary->subscriber_state ?> <?php echo $ins_secondary->subscriber_postal_code ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Relationship:</td>
					<td class="pdf_data"><?php echo ($ins_secondary->subscriber_relationship)? Options::getListItem('sub_relation',$ins_secondary->subscriber_relationship) : "Other"  ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>		
<?php 
				$output = ob_get_clean(); 
				$pdf->writeHTML($output,2);
				$pdf->ln(15);
			} 
			elseif ($order_data->ins_primary) { // only one insurance plan
?>
<table nobr="true">
	<tr>
		<td style="width:50%" class="pdf_section">&nbsp;PRIMARY INSURANCE:</td>
		<td style="width:50%" class="pdf_section">&nbsp;PRIMARY POLICY HOLDER / INSURED:</td>
	</tr>
	<tr>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Insurance Code:</td>
					<td class="pdf_data"><?php echo $ins_primary->cms_id ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Company Name:</td>
					<td class="pdf_data"><?php echo $ins_primary->company_name ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Ins Address:</td>
					<td class="pdf_data"><?php echo $ins_primary->line1 ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_primary->city)? $ins_primary->city.', ': '' ?><?php echo $ins_primary->state ?> <?php echo $ins_primary->zip ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Policy Number:</td>
					<td class="pdf_data"><?php echo $ins_primary->policy_number ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Group Number:</td>
					<td class="pdf_data"><?php echo $ins_primary->group_number ?></td>
				</tr>
			</table>
		</td>
		<td class="pdf_border">
			<table>
				<tr>
					<td class="pdf_label">Insured Name:</td>
					<td class="pdf_data"><?php echo $ins_primary->subscriber_lname ?>, <?php echo $ins_primary->subscriber_fname ?> <?php echo $ins_primary->subscriber_mname ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Insured Address:</td>
					<td class="pdf_data"><?php echo $ins_primary->subscriber_street ?></td>
				</tr>
				<tr>
					<td class="pdf_label">City, State Zip:</td>
					<td class="pdf_data"><?php echo ($ins_primary->subscriber_city)? $ins_primary->subscriber_city.', ': '' ?><?php echo $ins_primary->subscriber_state ?> <?php echo $ins_primary->subscriber_postal_code ?></td>
				</tr>
				<tr>
					<td class="pdf_label">Relationship:</td>
					<td class="pdf_data"><?php echo ($ins_primary->subscriber_relationship)? Options::getListItem('sub_relation',$ins_primary->subscriber_relationship) : "Other"  ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>		
<?php 
				$output = ob_get_clean(); 
				$pdf->writeHTML($output,2);
				$pdf->ln(15);
			} // end single insurance
		} // end if insurance
		
/*		ob_start();
?>
<table nobr="true" style="width:100%;font-size:0.7em">
	<tr>
		<td colspan="2"><span style="font-size:1.3em;font-weight:bold">Authorization</span> - Please sign and date</td>
	</tr><tr>
		<td colspan="2">
			I hereby authorize the release of medical information related to the services described hereon and authorize payment directly to <?php echo $lab_data['name'] ?>.
			I agree to assume responsibility for payment of charges for laboratory services that are not covered by my healthcare insurer.
		</td>
	</tr><tr>
		<td><br/></td>
	</tr><tr>
		<td>
			<table style="width:100%">
				<tr><td colspan="3">&nbsp;</td></tr>
				<tr><td colspan="3">&nbsp;</td></tr>
				<tr>
					<td style="width:400px;border-top:1px solid black">Patient Signature</td>
					<td style="width:40px"></td>
					<td style="width:100px;border-top:1px solid black">Date</td>
				</tr>
				<tr><td colspan="3">&nbsp;</td></tr>
				<tr><td colspan="3">&nbsp;</td></tr>
				<tr><td colspan="3">&nbsp;</td></tr>
				<tr>
					<td style="width:400px;border-top:1px solid black">Physician Signature</td>
					<td style="width:40px"></td>
					<td style="width:100px;border-top:1px solid black">Date</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php 
		$output = ob_get_clean(); 
		$pdf->writeHTML($output,2);
*/

//		$TEST = true;
//		if ($TEST) {
//			$pdf->Output('label.pdf', 'I'); // force display download
//		}
//		else {
//			$document = $pdf->Output('requisition.pdf','S'); // return as variable
			
//			$CMDLINE = "lpr -P $printer ";
//			$pipe = popen("$CMDLINE" , 'w' );
//			if (!$pipe) {
//				echo "Label printing failed...";
//			}
//			else {
//				fputs($pipe, $label);
//				pclose($pipe);
//				echo "Labels printing at $printer ...";
//			}
//		}

		$document = $pdf->Output('order'.$order_data->order_number.'.pdf','S'); // return as variable
		return $document;

	} // end makeOrderDocument
	
} // end class
