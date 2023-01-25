<?php
/** ****************************************************************************************
 *	LABORATORY/LINK_PROCESS.PHP
 *
 *	Copyright (c)2022 - Medical Technology Services
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
 *  @version 3.0
 *  @copyright Medical Technology Services
 *  @author Ron Criswell <ron@MDTechSvcs.com>
 *  @uses laboratory/common.php
 * 
 ************************************************************************************** */

// Global setup
require_once("../../globals.php");
require_once($GLOBALS['srcdir']."/mdts/mdts.globals.php");

use OpenEMR\Core\Header;

use function mdts\GetSeconds;
use function mdts\FormatDate;
use function mdts\FormatTime;
use function mdts\FormatDateTime;
use function mdts\LogError;
use function mdts\LogException;

use mdts\objects\User;
use mdts\objects\Patient;
use mdts\objects\Laboratory;
use mdts\objects\Facility;
use mdts\objects\LabOrphan;
use mdts\objects\Encounter;
use mdts\objects\Form;

use mdts\classes\Options;

// Grab session data
$authid = $_SESSION['authId'];
$authuser = $_SESSION['authUser'];
$groupname = $_SESSION['authProvider'];
$authorized = $_SESSION['userauthorized'];

// Security violation
if (!$authuser) {
	mdts\LogError(E_ERROR, "Attempt to access program without authorization credentials.");
	die ();
}

$result_title = "Laboratory Results - ";
$form_title = 'Laboratory Result Link';
$form_id = $_REQUEST['id'];
$form_pid = $_REQUEST['pid'];
$process = $_REQUEST['process'];

// special pnote insert function
function labPnote($pid, $newtext, $assigned_to = '', $datetime = '') {
	if ($pid && $pid != '1') return; // do not generate messages without a pid
	
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

try {
	// get orphan record
	$orphan_data = new LabOrphan($form_id);
	$form_id = $orphan_data->id; // verifies that we found record
	$order_number = $orphan_data->order_number;
	if (empty($form_id)) {
		throw new Exception("Orphan record not found id ($form_id).");
	}
	
	// verify orphan record
	if (empty($order_number)) {
		throw new Exception("Orphan record missing order number.");
	}

	// get patient record
	$pat_data = Patient::getPid($form_pid);
	$pid = $pat_data->pid; // verifies that we found record
	if (empty($pid)) {
		throw new Exception("Patient record not found pid ($form_pid).");
	}

	// get laboratory processor
	$lab_data = new Laboratory($orphan_data->lab_id);
	$lab_type = $lab_data->type; // quest, labcorp, etc
	$lab_id = $lab_data->ppid;
	if (empty($lab_id)) {
		throw new Exception("Laboratory processor record not found ppid ($lab_id).");
	}
	
	// get appointment category
	$result = sqlQuery("SELECT `option_id` FROM `list_options` WHERE `list_id` LIKE 'Lab_Category'");
	$pc_catid = $result['option_id'];
	if (empty($pc_catid)) {
		throw new Exception("Laboratory appointment category list entry missing.");
	}
	
	// validate result provider
	$provider_username = '';
	$provider_id = $pat_data->providerID;   // default for patient
	$doc_npi = $orphan_data->doc_npi;  // returned from lab
	if (!empty($doc_npi)) {
		$provider_data = User::fetchUserNPI($doc_npi);
		if ($provider_data->id) {
			$provider_username = $provider_data->username;
			$provider_id = $provider_data->id;
		}
	}

	// validate facility
	$facility_id = '';
	if ($lab_type && $orphan_data->account) { // from result record
		$acct_list = new Options('Lab_'.$lab_type.'_Accounts');
		foreach ($acct_list->list AS $item) {
			if ($message->account == $item['title']) {
				$facility_id = $item['option_id']; // OpenEMR facility id
			}
		}
	} 
	if ($provider_id && empty($facility_id)) {
		$facility_id = $provider_data->facility_id;
	}
	if ($facility_id) { // facility found
		$facility_data = new Facility($facility_id);
		$facility_name = $facility_data->name;
	}

	// validate the respository directory
	$repository = $GLOBALS['oer_config']['documents']['repository'];
	$file_path = $repository . preg_replace("/[^A-Za-z0-9]/","_",$form_pid) . "/";
	if (!file_exists($file_path)) {
		if (!mkdir($file_path,0700)) {
			throw new Exception("The system was unable to create the directory for this patient, '" . $file_path . "'.\n");
		}
	}
	
	if ($process) { // doing the work
		// check that there are documents
		if ($orphan_data->result_doc_id) { // only continue if there is a document
			//move document to new patient
			$doc = new Document($orphan_data->result_doc_id);
			$file = $doc->get_url_file(); // name of document
	
			$docnum = 1;
			$docName = $orphan_data->order_number . "_RESULT";
			$file = $docName .'_'. date('yj');
			while (file_exists($file_path.$file)) { // don't overlay duplicate file names
				$file = $docName .'_'. date('yj') ."_". $docnum++;
			}
		
			if (rename($doc->get_url_filepath(), $file_path.$file)) {
				$doc->url = "file://" .$file_path.$file;
				$doc->set_foreign_id($pid);
				$doc->persist();
			} else {
				throw new Exception("The system was unable to move the document to the new patient, '" . $file_path . "'.\n");
			}
		}
		
		// build dummy encounter for this patient/result
		$enc_data = new Encounter();
			
		$enc_data->pid = $pid;
		$enc_data->user = 'SYSTEM';
		$enc_data->grouname = 'Default';
		$enc_data->authorized = 1;
		$enc_data->provider_id = $provider_id;
		$enc_data->facility_id = $facility_id;
		$enc_data->billing_facility = $facility_id;
		$enc_data->pc_catid = $pc_catid;
		$enc_data->date = FormatDateTime($odate);
		$enc_data->form_title = 'Laboratory Encounter';
		$enc_data->form_name = 'encounter';
		$enc_data->reason = 'GENERATED ENCOUNTER FOR '.strtoupper($lab_data->name).' RESULT';
		$enc_data->sensitivity = 'normal';		
	
		$enc_data->store();
		$encounter = $enc_data->encounter;
			
		// generate a new order
		$order_data = new Form('laboratory');
			
		// add tag
		$order_data->request_notes = "ORDER GENERATED FROM UNSOLICITED RESULT (PATIENT LINKED)";
			
		// move orphan data to order
		foreach ($orphan_data AS $key => $value) {
			if (in_array($key, ['id','form_name','form_table','form_title'])) continue;
			$order_data->$key = $value;  // transfer data
		}
		
		// override orphan data as needed
		$order_data->pid = $pid;
		$order_data->patient_id = $pid;
		$order_data->doc_npi = $provider_npi;
		$order_data->provider_id = $provider_id;
		$order_data->facility_id = $facility_id;
		$order_data->encounter_id = $encounter;
		$order_data->encounter = $encounter;
		$order_data->status = 'z'; // final
		
		// save order/orphan record
		$order_id = $order_data->store();
		
		// done with orphan
		$orphan_data->activity = 0;
		$orphan_data->pid = $pid;
		$orphan_data->store();
	
		// send them a message
		if ($provider_username) {
			$link_ref = "../../forms/laboratory/view.php?pop=1&id=".$order_id."&pid=".$pid."&enc=".$encounter;
	  			
			$note = "\n\n";
			$note .= $lab_data->name." orphan results linked to patient '".$pat_fname." ".$pat_lname."' (pid: ".$pid.") order number '".$message->order_number."'. ";
			$note .= "To review these results click on the following link: ";
	  		$note .= "<a href='". $link_ref ."' target='_blank' class='link_submit' onclick='top.restoreSession()'>". $lab_name ." Results - ". $message->order_number ." (". $message->contol_id .")</a>\n\n";
			labPnote($pid, $note, $provider_username);
		}
	} // end processing
	
} catch(Exception $e) {
	LogException($e);
	die();
}

?>
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>

	<?php Header::setupHeader(); ?>
	<title>Link Orphan Result</title>

</head>

<body class="body_top">
	<div id="container">

		<header>
			<!-- HEADER (if desired) -->
			<span class="title" style="margin-left:10px">Laboratory - Link Orphan Result</span>


		</header>

		<div style="margin:30px 60px">
			<form method='post' id='linkProcess' action=''>
				<input type="hidden" name="id" value="<?php echo $form_id ?>" />
				<input type="hidden" name="pid" value="<?php echo $form_pid ?>" />
				<input type="hidden" name="process" value="1" />

<?php if (!$process) { ?>
				<h1>Result Linkage...</h1>
				Please confirm linking result # <?php echo $order_number ?> (<?php echo $form_id ?>) to <?php echo $pat_data->format_name ?> (<?php echo $pat_data->pubpid ?>).
				<br/><br/>
				A new encounter will be created for this patient and the result documents and result information<br/>
				will be transferred to this patient. Click [Continue] to complete the transfer or [Cancel] to close this<br/>
				window without making any changes.
				<br/><br/><br/><br/><br/>
				<center>
					<button type="submit" class="btn btn-primary">Save Changes</button>
					<button type="button" class="btn btn-secondary" onclick="window.parent.dlgclose()">Cancel & Exit</button>
				</center>
<?php } else { ?>
				<h1>Result Linked...</h1>
				Result # <?php echo $order_number ?> (<?php echo $form_id ?>) linked to <?php echo $pat_data->format_name ?> (<?php echo $pat_data->pid?>).
				<br/><br/>
				Encounter #<?php echo $encounter ?> has been created for this patient and the result documents and result information<br/>
				have been successfully transferred. Click [Close] to close this window.
				<br/><br/><br/><br/><br/>
				<center>
					<button type="button" class="btn btn-secondary" onclick="window.parent.dlgclose()">Close Window</button>
				</center>
<?php } ?>
			</form>
		</div>
	</div>
	
</body>
</html>
