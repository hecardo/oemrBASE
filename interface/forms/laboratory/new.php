<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\Header;
use OpenEMR\Common\Logging\SystemLogger;

use WMT\Laboratory\Common\Processor;

// Global setup
require_once("../../globals.php");

// Establish log handler
$logger = new SystemLogger();

// Grab session data
$authid = $_SESSION['authId'];
$authuser = $_SESSION['authUser'];
$groupname = $_SESSION['authProvider'];
$authorized = $_SESSION['userauthorized'];

// Security violation
if (!$authuser) {
	$logger->error("Attempt to access program without authorization credentials.");
	die("Attempt to access program without authorization credentials.");
}

// initialization
$lab_type = ($_REQUEST['formname'])? $_REQUEST['formname'] : false;
$lab_id = ($_REQUEST['labid'])? $_REQUEST['labid'] : false;

$lab_data = null;
if ($lab_id) {
	// retrieve specific lab
	$lab_data = new Processor($lab_id);
	if (empty($lab_data->ppid)) {
		$message = "No data for laboratory id [" .$lab_id. "]";
		$logger->error($message);
		die($message);
	} 
} else {
	// retrieve lab list
	$lab_list = Processor::fetchLabs(); // returns array or objects
	if (count($lab_list) < 1) {
		$message = "Laboratory providers have not been created.";
		$logger->error($message);
		die($message);
	} elseif (count($lab_list) == 1) { // skip selection if only one laboratory
		$lab_data = reset($lab_list);  // retrieve first element
		$lab_id = $lab_data->ppid;
	}
}

// single lab identified
if ($lab_id) {
	$mode = 'new';
	include("common.php");
	exit;
}

?>
<!DOCTYPE html>
<html>
<head>
	<?php Header::setupHeader(); ?>
	<title>Laboratory Order</title>

	<script>
		function saveClicked() {
			var labid = $('#labid').val();
			if (parseInt(labid) > 0) {
				location.href="<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/new.php?labid=" + labid;
				exit();
			}
			alert('You must select a laboratory processor before continuing.');
		}

		function cancelClicked() {
			top.restoreSession();
			parent.closeTab(window.name, false);
		}

		</script>
</head>

	<div class="container">
		<div class="page-header col-md-12">
			<input type=hidden name='mode' value='<?php echo ($viewmode)? 'update' : 'new'; ?>' />
			<input type=hidden name='id' value='<?php echo $order_data->id ?>' />
			<h2>Laboratory Order</h2>
		</div>
		<div id="sections" class="col-12">
			<form id="lab_form" class="form form-horizontal" method="post" action="">
				<input type="hidden" name="csrf_token_form" value="<?php //echo attr(CsrfUtils::collectCsrfToken()); ?>" />

				<!-- LABORATORY PROCESSOR SELECT -->
				<div id="order_entry" class="card mb-1">
					<div class="card-header pl-2" style="font-size:1.1rem"><?php echo xlt('Laboratory Processor'); ?></div>

					<div id="order_submit" class="collapse show">
						<div class="card-body p-3">
							<div class="control-label text-nowrap w-25">Select Laboratory Processor:</div>
							<select class="form-control form-control-sm form-select w-25" name='labid' id='labid'>
								<option value=''>-- select --</option>
<?php 
	foreach ($lab_list as $lab) {
		echo "<option value='" . $lab->ppid . "'>" .$lab->name. "</option>";
  	}
?>
							</select>
							<div class="btn-group mt-3" role="group">
								<button type="button" class="btn btn-success btn-save" name="btn_save" id="bn_save" onclick="saveClicked()">Continue</button>
								<button type="button" class="btn btn-secondary btn-cancel" onclick="cancelClicked()">Cancel/Exit</button>
							</div>
						</div>
					</div>
					
				</div>
			</div>
			
		</form>
	</body>

</html>
