<?php
/**
 * @package   	WMT
 * @subpackage	CarePlan
 * @author		Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$here = dirname(__FILE__, 6);
require_once($here . "/globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;

use WMT\Objects\Patient;

// default parameters
$planid = empty($_REQUEST['planid'])? 0 : $_REQUEST['planid'];
$mode = empty($_POST['mode' ]) ? '' : $_POST['mode' ];

$title = empty($planid) ? 'Create' : 'Modify';
$title .= ' Care Plan';
$pat_name = Patient::getFullName($pid);

// process input save
if ($mode) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    $sets = "title = ?, user = ?, groupname = ?, authorized = ?, date = NOW()";
    $sqlBindArray = array($form_id, $_SESSION['authUser'], $_SESSION['authProvider'], $userauthorized);

    if ($transid) {
        array_push($sqlBindArray, $transid);
        sqlStatement("UPDATE transactions SET $sets WHERE id = ?", $sqlBindArray);
    } else {
        array_push($sqlBindArray, $pid);
        $sets .= ", pid = ?";
        $newid = sqlInsert("INSERT INTO transactions SET $sets", $sqlBindArray);
    }

    $fres = sqlStatement("SELECT * FROM layout_options " .
    "WHERE form_id = ? AND uor > 0 AND field_id != '' " .
    "ORDER BY group_id, seq", array($form_id));

    while ($frow = sqlFetchArray($fres)) {
        $data_type = $frow['data_type'];
        $field_id  = $frow['field_id'];
        $value = get_layout_form_value($frow);

        if ($transid) { // existing form
            if ($value === '') {
                $query = "DELETE FROM lbt_data WHERE " .
                "form_id = ? AND field_id = ?";
                sqlStatement($query, array($transid, $field_id));
            } else {
                $query = "REPLACE INTO lbt_data SET field_value = ?, " .
                "form_id = ?, field_id = ?";
                sqlStatement($query, array($value, $transid, $field_id));
            }
        } else { // new form
            if ($value !== '') {
                sqlStatement(
                    "INSERT INTO lbt_data " .
                    "( form_id, field_id, field_value ) VALUES ( ?, ?, ? )",
                    array($newid, $field_id, $value)
                );
            }
        }
    }

    if (!$transid) {
        $transid = $newid;
    }
}

$settings = array(
				'heading_title' => xl($title),
				'include_patient_name' => true,
				'expandable' => false,
				'expandable_files' => array(),//all file names need suffix _xpd
				'action' => "",//conceal, reveal, search, reset, link or back
				'action_title' => "",
				'action_href' => "",//only for actions - reset, link or back
				'show_help_icon' => true,
				'help_file_name' => "care_plan_help.php"
);
$oemr_ui = new OemrUI($settings);
?>
<!DOCTYPE html>
<head>
	<?php Header::setupHeader('common'); ?>

	<title><?php echo $title ?></title>
	<meta name="author" content="Ron Criswell" />
	<meta name="description" content="Care Plan Add/Edit" />
	<meta name="copyright" content="&copy;<?php echo date('Y') ?> Williams Medical Technologies, Inc.  All rights reserved." />
<?php /*
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-dt/css/jquery.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/css/buttons.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-dt/css/colReorder.dataTables.min.css" type="text/css">
*/ ?>
	<script>
		// initiation after load complete
		$(document).ready(function() {

			$('.card-title').click(function() {
				$(this).find('i').toggleClass('fa fa-plus-square fa fa-minus-square')
			});

		});

		// Process click to pop up the add window.
		function add_concern() {
//		    top.restoreSession();
		    var addTitle = '<i class="fa fa-plus" style="width:20px;" aria-hidden="true"></i> ' + <?php echo xlj("Add Health Concern"); ?>;
		    let scriptTitle = 'edit_plan_concern.php?plan=' + <?php echo $planid ?> + '&id=0&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>;
		    dlgopen(scriptTitle, '_blank', 900, 550, false, addTitle);
		}

		// Process click to pop up the edit window.
		function edit_concern(id) {
//		    top.restoreSession();
		    var editTitle = '<i class="fa fa-pencil-alt" style="width:20px;" aria-hidden="true"></i> ' + <?php echo xlj("Edit Health Concern"); ?> + ' ';
		    var scriptTitle = 'edit_plan_concern.php?plan=' + <?php echo $planid ?> + '&id=' + id + '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>;
		    dlgopen(scriptTitle, '_blank', 800, 550, false, editTitle);
		}

		function do_resize() {
			$('textarea').each(function() {
				var height = $(this).prop('scrollHeight');
				$(this).css('height',height + 3);
			});
		}
	</script>

	<style>
		h5 { font-size: 1.5rem; }
		.btn-xs { padding: .1rem .4rem; min-width: 68px; }
		.card { margin: .5rem 1rem 0 1rem; }
		.card-header { padding: .5rem .75rem; }
		.card-title { display:inline; font-size:1.1rem; }
		.card-body { padding: .5rem .75rem; }
		
		#plan_form table th {
			font-size: .6rem;
			font-weight: normal;
			text-transform: uppercase;
			vertial-align: bottom;
			white-space: nowrap;
			padding-top: .2rem;
		}

#lab_form {
	font-size: 14px;
}

.w-30 {
	width: 30%;
}
		
	</style>
<?php
$arrOeUiSettings = array(
    'heading_title' => xl($title),
    'include_patient_name' => true,
    'expandable' => false,
    'expandable_files' => array(),//all file names need suffix _xpd
    'action' => "",//conceal, reveal, search, reset, link or back
    'action_title' => "",
    'action_href' => "",//only for actions - reset, link and back
    'show_help_icon' => true,
//    'help_file_name' => "add_edit_plan_help.php"
);
$oemr_ui = new OemrUI($arrOeUiSettings);
?>

</head>

<body onload="do_resize()" >
	<div id="container_div" class="<?php echo $oemr_ui->oeContainer();?> mt-3 mb-3">
		<form id='plan_form' name='plan_form' class="form form-horizontal" method='post' 
				action='edit_plan.php?transid=<?php echo attr_url($planid); ?>'>
			<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
			<input type='hidden' name='mode' value='add' />
			<input type='hidden' id='code_key' value='' />
			<div class="row">
				<div class="col-sm-12">
					<?php require_once("$include_root/patient_file/summary/dashboard_header.php");?>
				</div>
			</div>
			<div class="row mt-1">
				<div class="col-sm-12">
					<div class="btn-group">
						<button type="button" class="btn btn-primary btn-save" onclick="submitme();">Save</button>
                        <button type="button" class="btn btn-secondary btn-cancel" onclick="location.href='list_plans.php';">Cancel</button>
                    </div>
                </div>
				<div class="card col-sm-12 p-0 mt-3">
					<div class="card-header">
						<div class="card-title" data-toggle="collapse" data-target="#plan_info" style="font-size:1.1rem">
							<i class="fa fa-minus-square" id="icon_plan_info"></i> Plan Definition
						</div>
					</div>
					<div id="plan_info" class="collapse show">
						<div class="card-body">
							<div class="form-row m-0">




<table class="w-100">
									<tbody><tr>
										<th style="width:75px"></th>
										<th style="padding-left:9px">Title</th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Plan Type</th>
										<th style="width:100px;padding-left:9px">Initiated</th>
										<th style="width:90px;padding-left:9px">Modified</th>
										<th style="width:170px;padding-left:9px">Author</th>
									</tr>

										
									
																			
								<tr id="oc_10010">
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" oncinfolick="concern_edit('1')">Unlock</button>
										</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" readonly name="test_profile[]" value="Diabetes Plan of Care">
</td><td class="align-top">
<select class="form-control form-control-sm" autocomplete="off" readonly name="billing_type" id="billing_type">
		<option>Active</option>
	</select>
</td>
<td class="align-top">
<select class="form-control form-control-sm" autocomplete="off" readonly name="billing_type" id="billing_type">
		<option>Medium</option>
	</select>
</td>
<td class="align-top">
<select class="form-control form-control-sm" autocomplete="off" readonly name="billing_type" id="billing_type">
		<option>Medical</option>
	</select>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" readonly name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" readonly name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" required>
</td>
<td class="align-top">
<select class="form-control form-control-sm" autocomplete="off" readonly name="billing_type" id="billing_type">
		<option>Yvette Poinderter</option>
	</select>
</td>
</tr><tr>
<td class="align-top" colspan="7">
	<textarea class="name form-control form-control-sm" readonly name="test_text[]" style="white-space:prewrap">
Care Plans are used in many areas of healthcare with a variety of scopes. They can be as simple as a general practitioner keeping track of when their patient is next due for a tetanus immunization through to a detailed plan for an oncology patient covering diet, chemotherapy, radiation, lab work and counseling with detailed timing relationships, pre-conditions and goals. 

The scope of care plans may vary widely. Examples include:

 - Multi-disciplinary cross-organizational care plans; e.g. An oncology plan including the oncologist, home nursing staff, pharmacy and others
 - Plans to manage specific disease/condition(s) (e.g. nutritional plan for a patient post bowel resection, neurological plan post head injury, etc.)
 - Decision support generated plans following specific practice guidelines (e.g. stroke care plan, diabetes plan, falls prevention, etc.)
 - Self-maintained patient or care-giver authored plans identifying their goals and an integrated understanding of actions to be taken

This resource can be used to represent both proposed plans (for example, recommendations from a decision support engine or returned as part of a consult report) as well as active plans. The nature of the plan is communicated by the status. Some systems may need to filter CarePlans to ensure that only appropriate plans are exposed via a given user interface. 
</textarea>
</td></tr>
</tbody>
</table>


							</div>
						</div>
					</div>
                </div>

				<div class="card col-sm-12 p-0">
					<div class="card-header">
						<div class="card-title" data-toggle="collapse" data-target="#plan_concerns">
							<i class="fa fa-minus-square" id="icon_plan_concerns"></i> Health Concerns
						</div>
						<button type="button" class="btn btn-primary btn-sm btn-xs" onclick="add_concern()" style="float:right">Add Concern</button>
					</div>
					<div id="plan_concerns" class="collapse show">
						<div class="card-body">
							<div id="concern_1" class="form-row m-0 mb-3 concern">
								<table class="w-100">
									<tbody><tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Effective</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
										<th></th>
										<!-- th class="wmtHeader" style="width:300px">Order Entry Questions</th -->
									</tr>
	
									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" oncinfolick="concern_edit('1')">Unlock</button>
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="text" name="concern_status[]" id="concern_status" value="Active" readonly>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="text" name="concern_priority[]" id="concern_priority" readonly value="High" readonly>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="concern_type[]" type="text" id="concern_type" readonly value="Medical">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="date" name="concern_start[]" id="concern_start" 
												style="font-family:sans-serif;font-size:.875rem" value="<?php echo date('Y-m-d'); ?>" readonly required>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="concern_code[]" type="text" id="concern_code" readonly value="SNOMED:10010">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="concern_title[]" type="text" id="concern_title" readonly value="Diabetes militus without complication">
										</td>
										</tr>
						<tr>
										<td class="align-top" colspan="6">
											<textarea class="name form-control form-control-sm" name="concern_text[]" id="concern_text" readonly style="white-space:prewrap">
A Health Concern Act is used to track non-optimal physical or psychological situations drawing the patient to the healthcare system. These may be from the perspective of the care team or from the perspective of the patient.When the underlying condition is of concern (i.e., as long as the condition, whether active or resolved, is of ongoing concern and interest), the statusCode is 'active'. Only when the underlying condition is no longer of concern is the statusCode set to 'completed'. The effectiveTime reflects the time that the underlying condition was felt to be a concern; it may or may not correspond to the effectiveTime of the condition (e.g., even five years later, a prior heart attack may remain a concern). Health concerns require intervention(s) to increase the likelihood of achieving the goals of care for the patient and they specify the condition oriented reasons for creating the plan. </textarea>
										</td>
									</tr>

<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
RELATED DATA REFERENCES:
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Diagnosis">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="ICD10:X00.0">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Diabetes militus without complication">
</td></tr>
<tr><td class="align-middle pr-1" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Results">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="LOINC:249.2">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="A1c 7.2mg">
</td></tr>
<tr><td class="align-middle pr-1" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Vitals">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:339488321">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Weight 239lb">
</td></tr>
</tbody></table>
							</div>
							
							<!--  NEW CONCERN ENTRY -->
							<div id="concern_2" class="form-row m-0 mb-3 concern">
								<table class="w-100">
									<tbody><tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Effective</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>
																			
									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
										
										<td class="align-top">
										<select class="form-control form-control-sm" autocomplete="off" name="concern_status[]" id="concern_2_status">
												<option>Active</option>
											</select>
										</td>
										<td class="align-top">
										<select class="form-control form-control-sm" autocomplete="off" name="concern_priority[]" id="concern_2_priority">
												<option>Medium</option>
											</select>
										</td>
										<td class="align-top">
										<select class="form-control form-control-sm" autocomplete="off" name="concern_type[]" id="concern_2_type">
												<option>Medical</option>
											</select>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="date" name="concern_start[]" id="concern_2_start" 
												style="font-family:sans-serif;font-size:.875rem" value="<?php echo date('Y-m-d'); ?>" required>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" codetype="SNOMED-CT" name="concern_code[]" 
												id="concern_2_code" type="text" value="" onclick="get_related('SNOMED-CT','concern_2')">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="concern_title[]" id="concern_2_title" type="text" value="">
										</td>
									</tr><tr>
										<td class="align-top" colspan="6">
											<textarea class="name form-control form-control-sm" name="concern_text[]" id="concern_2_text" style="white-space:prewrap"></textarea>
										</td>
									</tr>
								</tbody></table>
							</div>




						</div>
					</div>
                </div>

				<div class="card col-sm-12 p-0">
					<div class="card-header">
						<div class="card-title" data-toggle="collapse" data-target="#plan_actions">
							<i class="fa fa-minus-square" id="icon_plan_actions"></i> Planned Interventions
						</div>
						<button type="button" class="btn btn-primary btn-sm btn-xs" onclick="add_action()" style="float:right">Add Intervention</button>
					</div>
					<div id="plan_actions" class="collapse show">
						<div class="card-body">

							<div id="action_1" class="form-row m-0 action">
								<table class="w-100">
									<tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Target</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>

									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" oncinfolick="concern_edit('1')">Unlock</button>
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Active" readonly>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Medium" readonly>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Medication">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:10010">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="1000mg Metformin">
</td>
</tr><tr>
<td class="align-top" colspan="6">
	<textarea class="name form-control form-control-sm" name="test_text[]" readonly style="white-space:prewrap">
Interventions are actions taken to maximize the prospects of the goals of care for the patient, including the removal of barriers to success. Interventions can be planned, ordered, historical, etc.

Interventions include actions that may be ongoing (e.g., maintenance medications that the patient is taking, or monitoring the patient's health status or the status of an intervention).

Instructions are nested within interventions and may include self-care instructions. Instructions are information or directions to the patient and other providers including how to care for the individual's condition, what to do at home, when to call for help, any additional appointments, testing, and changes to the medication list or medication instructions, clinical guidelines and a summary of best practice.

Instructions are information or directions to the patient. Use the Instructions Section when instructions are included as part of a document that is not a Care Plan. Use the Interventions Section, containing the Intervention Act containing the Instruction entry, when instructions are part of a structured care plan.
</textarea>
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
ACTIVITIES COMPLETED:
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Medication">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="RXNORM:120">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="1000mg Metformin B.I.D.">
</td></tr>
</tbody></table>
							</div>
							
							<div id="action_2" class="form-row m-0 action">
								<table class="w-100 mt-3">
									<tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Target</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>
																			
									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" oncinfolick="concern_edit('1')">Unlock</button>
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Active" readonly>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Medium" readonly>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Laboratory">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:44993">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Laboratory Testing">
</td>
</tr><tr>
<td class="align-top" colspan="6">
	<textarea class="name form-control form-control-sm" name="test_text[]" readonly style="white-space:prewrap">
Run standard metabolic laboratory tests to determine functional efficency of patient.
</textarea>
</td></tr>
</tbody></table>

							</div>

							<div id="action_3" class="form-row m-0 action">
								<table class="w-100 mt-3">
									<tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Target</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>
									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" oncinfolick="concern_edit('1')">Unlock</button>
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Active" readonly>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Medium" readonly>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Counseling">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
												value="<?php echo date('Y-m-d'); ?>" readonly required>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:493">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Behavioral Counseling">
										</td>
									</tr><tr>
										<td class="align-top" colspan="6">
											<textarea class="name form-control form-control-sm" name="test_text[]" readonly style="white-space:prewrap">
Provide behavioral modification counseling to patient.
										</textarea>
										</td>
									</tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
ACTIVITIES COMPLETED:
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Counseling">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="2022-01-01" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:12345">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="30 Minute Session">
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Counseling">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="2022-02-02" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:12345">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="30 Minute Session">
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Counseling">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="2022-03-03" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:12345">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="30 Minute Session">
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Counseling">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="2022-04-04" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:12345">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="30 Minute Session">
</td></tr>
</table>
							</div>

							<!--  NEW CONCERN ENTRY -->
							<div id="action_4" class="form-row m-0 mb-3 action">
								<table class="w-100">
									<tbody><tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Target</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>
																			
									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
										<td class="align-top">
											<select class="form-control form-control-sm" autocomplete="off" name="action_status[]" id="action_4_status">
												<option>Active</option>
											</select>
										</td>
										<td class="align-top">
											<select class="form-control form-control-sm" autocomplete="off" name="action_priority[]" id="action_4_priority">
												<option>Medium</option>
											</select>
										</td>
										<td class="align-top">
											<select class="form-control form-control-sm" autocomplete="off" name="action_type[]" id="action_4_type">
												<option>Medical</option>
											</select>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="date" name="action_target[]" id="action_4_start" 
												style="font-family:sans-serif;font-size:.875rem" value="<?php echo date('Y-m-d'); ?>" required>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" codetype="SNOMED-CT" name="action_code[]" 
												id="action_4_code" type="text" value="" onclick="get_related('SNOMED-CT','action_4')">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="action_title[]" id="action_4_title" type="text" value="">
										</td>
									</tr><tr>
										<td class="align-top" colspan="6">
											<textarea class="name form-control form-control-sm" name="action_text[]" id="action_4_text" style="white-space:prewrap"></textarea>
										</td>
									</tr>
								</table>
							</div>



						</div>
					</div>
                </div>

				<div class="card col-sm-12 p-0">
					<div class="card-header">
						<div class="card-title" data-toggle="collapse" data-target="#plan_goals">
							<i class="fa fa-minus-square" id="icon_plan_goals"></i> Treatment Goals
						</div>
						<button type="button" class="btn btn-primary btn-sm btn-xs" onclick="add_action()" style="float:right">Add Goal</button>
					</div>
					<div id="plan_goals" class="collapse show">
						<div class="card-body">
							<div class="form-row m-0">

								<table class="w-100">
									<tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Target</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>

										
									
																			
									<tr id="oc_10010">
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" oncinfolick="concern_edit('1')">Unlock</button>
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Active" readonly>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="text" name="test_profile[]" value="Medium" readonly>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Medication">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="SNOMED:39994">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Well Controlled Diabetes">
</td>
</tr><tr>
<td class="align-top" colspan="6">
	<textarea class="name form-control form-control-sm" name="test_text[]" readonly style="white-space:prewrap">
A goal is a defined outcome or condition to be achieved in the process of patient care. Goals include patient-defined over-arching goals (e.g., alleviation of health concerns, desired/intended positive outcomes from interventions, longevity, function, symptom management, comfort) and health concern-specific or intervention-specific goals to achieve desired outcomes.
</textarea>
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
RECENT OBSERVATIONS:
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Laboratory">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="LOINC:884.2">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="A1c: 7.9">
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Laboratory">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="2022-11-05" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="LOINC:884.2">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="A1c: 7.1 Improving">
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Laboratory">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="<?php echo date('Y-m-d'); ?>" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="LOINC:884.2">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="A1c: 8.2 Rein 2 weeks">
</td></tr>
<tr><td class="align-middle pr-2" colspan="3" style="text-align:right;font-size:.6rem;">
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="Laboratory">
</td>
<td class="align-top">
	<input class="form-control form-control-sm" type="date" name="test_profile[]" style="font-family:sans-serif;font-size:.875rem"
		value="2023-02-21" readonly required>
</td>
<td class="align-top">
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="LOINC:884.2">
</td>
</td><td>
	<input class="form-control form-control-sm" name="test_code[]" type="text" readonly="" value="A1c: 6.6 GOOD">
</td></tr>
</tbody></table>


							</div>


							<!--  NEW GOAL ENTRY -->
							<div id="goal_4" class="form-row m-0 mb-3 goal">
								<table class="w-100">
									<tbody><tr>
										<th style="width:75px"></th>
										<th style="width:100px;padding-left:9px">Status</th>
										<th style="width:100px;padding-left:9px">Priority</th>
										<th style="width:100px;padding-left:9px">Type</th>
										<th style="width:90px;padding-left:9px">Target</th>
										<th style="width:170px;padding-left:9px">Coding</th>
										<th style="padding-left:9px">Description</th>										
									</tr>
																			
									<tr>
										<td rowspan="2" class="pr-2 align-top">
											<button type="button" class="btn btn-sm btn-xs btn-primary mb-1" onclick="concern_edit('1')">Linking</button>
											<button type="button" class="btn btn-sm btn-xs btn-danger" onclick="removeTestRow('oc_10010')">Remove</button>
										</td>
										<td class="align-top">
											<select class="form-control form-control-sm" autocomplete="off" name="goal_status[]" id="goal_4_status">
												<option>Active</option>
											</select>
										</td>
										<td class="align-top">
											<select class="form-control form-control-sm" autocomplete="off" name="goal_priority[]" id="goal_4_priority">
												<option>Medium</option>
											</select>
										</td>
										<td class="align-top">
											<select class="form-control form-control-sm" autocomplete="off" name="goal_type[]" id="goal_4_type">
												<option>Medical</option>
											</select>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" type="date" name="goal_target[]" id="goal_4_start" 
												style="font-family:sans-serif;font-size:.875rem" value="<?php echo date('Y-m-d'); ?>" required>
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" codetype="SNOMED-CT" name="goal_code[]" 
												id="goal_4_code" type="text" value="" onclick="get_related('SNOMED-CT','goal_4')">
										</td>
										<td class="align-top">
											<input class="form-control form-control-sm" name="goal_title[]" id="goal_4_title" type="text" value="">
										</td>
									</tr><tr>
										<td></td>
										<td class="align-top" colspan="6">
											<textarea class="name form-control form-control-sm" name="goal_text[]" id="goal_4_text" style="white-space:prewrap"></textarea>
										</td>
									</tr>
								</table>
							</div>




						</div>
					</div>
                </div>
            </div>

		<form>
<?php 
/*



<html>
<head>

<title><?php echo xlt('Add/Edit Patient Transaction'); ?></title>

<?php Header::setupHeader(['common','datetime-picker','select2']); ?>

<?php include_once("{$GLOBALS['srcdir']}/options.js.php"); ?>

<script>
$(function () {
  if(window.tabbify){
    tabbify();
  }
  if (window.checkSkipConditions) {
    checkSkipConditions();
  }
});

var mypcc = <?php echo js_escape($GLOBALS['phone_country_code']); ?>;

$(function () {
  $("#send_sum_flag").click(function() {
    if ( $('#send_sum_flag').prop('checked') ) {
      // Enable the send_sum_elec_flag checkbox
      $("#send_sum_elec_flag").removeAttr("disabled");
      $("#send_sum_amc_confirmed").removeAttr("disabled");
    }
    else {
      //Disable the send_sum_elec_flag checkbox (also uncheck it if applicable)
      $("#send_sum_elec_flag").attr("disabled", true);
      $("#send_sum_elec_flag").prop("checked", false);
      $("#send_sum_amc_confirmed").attr("disabled", true);
      $("#send_sum_amc_confirmed").prop("checked", false);
    }
  });

  $(".select-dropdown").select2({
    theme: "bootstrap4",
    <?php require($GLOBALS['srcdir'] . '/js/xl/select2.js.php'); ?>
  });
  if (typeof error !== 'undefined') {
    if (error) {
        alertMsg(error);
    }
  }

  $('.datepicker').datetimepicker({
    <?php $datetimepicker_timepicker = false; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = true; ?>
    <?php $datetimepicker_minDate = false; ?>
    <?php $datetimepicker_maxDate = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  $('.datetimepicker').datetimepicker({
    <?php $datetimepicker_timepicker = true; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = true; ?>
    <?php $datetimepicker_minDate = false; ?>
    <?php $datetimepicker_maxDate = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  $('.datepicker-past').datetimepicker({
    <?php $datetimepicker_timepicker = false; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = true; ?>
    <?php $datetimepicker_minDate = false; ?>
    <?php $datetimepicker_maxDate = '+1970/01/01'; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  $('.datetimepicker-past').datetimepicker({
    <?php $datetimepicker_timepicker = true; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = true; ?>
    <?php $datetimepicker_minDate = false; ?>
    <?php $datetimepicker_maxDate = '+1970/01/01'; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  $('.datepicker-future').datetimepicker({
    <?php $datetimepicker_timepicker = false; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = true; ?>
    <?php $datetimepicker_minDate = '-1970/01/01'; ?>
    <?php $datetimepicker_maxDate = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  $('.datetimepicker-future').datetimepicker({
    <?php $datetimepicker_timepicker = true; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = true; ?>
    <?php $datetimepicker_minDate = '-1970/01/01'; ?>
    <?php $datetimepicker_maxDate = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
});

function titleChanged() {
 var sel = document.forms[0].title;
 // Layouts must not interfere with each other. Reload the document in Add mode.
 top.restoreSession();
 location.href = 'add_transaction.php?title=' + encodeURIComponent(sel.value);
 return true;
}

function divclick(cb, divid) {
 var divstyle = document.getElementById(divid).style;
 if (cb.checked) {
  divstyle.display = 'block';
 } else {
  divstyle.display = 'none';
 }
 return true;
}

// The ID of the input element to receive a found code.
var current_sel_name = '';

// This is for callback by the find-code popup.
// Appends to or erases the current list of related codes.
function set_related(codetype, code, selector, codedesc) {
 var frc = document.forms[0][current_sel_name];
 var s = frc.value;
 if (code) {
  if (s.length > 0) s += ';';
  s += codetype + ':' + code;
 } else {
  s = '';
 }
 frc.value = s;
}

// This invokes the find-code popup.
function sel_related(e) {
    current_sel_name = e.name;
    dlgopen('../encounter/find_code_popup.php<?php
    if ($GLOBALS['ippf_specific']) {
        echo '?codetype=REF';
    } ?>', '_blank', 500, 400);
}

// Process click on $view link.
function deleteme() {
// onclick='return deleteme()'
 dlgopen('../deleter.php?transaction=' + <?php echo js_url($transid); ?> + '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>, '_blank', 500, 450);
 return false;
}

// Called by the deleteme.php window on a successful delete.
function imdeleted() {
 top.restoreSession();
 location.href = 'transaction/transactions.php';
}

// Compute the length of a string without leading and trailing spaces.
function trimlen(s) {
 var i = 0;
 var j = s.length - 1;
 for (; i <= j && s.charAt(i) == ' '; ++i);
 for (; i <= j && s.charAt(j) == ' '; --j);
 if (i > j) return 0;
 return j + 1 - i;
}

// Validation logic for form submission.
function validate(f) {
 var errCount = 0;
 var errMsgs = new Array();

    <?php generate_layout_validation($form_id); ?>

 var msg = "";
 msg += <?php echo xlj('The following fields are required'); ?> + ":\n\n";
 for ( var i = 0; i < errMsgs.length; i++ ) {
    msg += errMsgs[i] + "\n";
 }
 msg += "\n" + <?php echo xlj('Please fill them in before continuing.'); ?>;

 if ( errMsgs.length > 0 ) {
    alert(msg);
 }

 return errMsgs.length < 1;
}

function submitme() {
 var f = document.forms['new_transaction'];
 if (validate(f)) {
  top.restoreSession();
  f.submit();
 }
}

<?php if (function_exists($form_id . '_javascript')) {
    call_user_func($form_id . '_javascript');
} ?>

</script>

<style>
.form-control {
    width: auto;
    display: inline;
    height: auto;
}
div.tab {
    height: auto;
    width: auto;
}
</style>
<?php
$arrOeUiSettings = array(
    'heading_title' => xl('Add/Edit Patient Transaction'),
    'include_patient_name' => true,
    'expandable' => false,
    'expandable_files' => array(),//all file names need suffix _xpd
    'action' => "back",//conceal, reveal, search, reset, link or back
    'action_title' => "",
    'action_href' => "transactions.php",//only for actions - reset, link and back
    'show_help_icon' => true,
    'help_file_name' => "add_edit_transactions_dashboard_help.php"
);
$oemr_ui = new OemrUI($arrOeUiSettings);
?>

</head>
<body onload="<?php echo $body_onload_code; ?>" >
    <div id="container_div" class="<?php echo $oemr_ui->oeContainer();?> mt-3">
        <form name='new_transaction' method='post' action='add_transaction.php?transid=<?php echo attr_url($transid); ?>' onsubmit='return validate(this)'>
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
            <input type='hidden' name='mode' value='add' />
            <div class="row">
                <div class="col-sm-12">
                    <?php require_once("$include_root/patient_file/summary/dashboard_header.php"); ?>
                </div>
                <br />
                <br />
                <div class="col-sm-12">
                    <div class="btn-group">
                        <a href="#" class="btn btn-primary btn-save" onclick="submitme();">
                            <?php echo xlt('Save'); ?>
                        </a>
                        <a href="transactions.php" class="btn btn-secondary btn-cancel" onclick="top.restoreSession()">
                            <?php echo xlt('Cancel'); ?>
                        </a>
                    </div>
                </div>
                <div class="col-sm-12 mt-3">
                    <fieldset>
                        <legend><?php echo xlt('Select Transaction Type'); ?></legend>
                        <div class="forms col-sm-7">
                            <label class="control-label" for="title"><?php echo xlt('Transaction Type'); ?>:</label>
                            <?php
                            $ttres = sqlStatement("SELECT grp_form_id, grp_title " .
                              "FROM layout_group_properties WHERE " .
                              "grp_form_id LIKE 'LBT%' AND grp_group_id = '' ORDER BY grp_seq, grp_title");
                            echo "<select name='title' id='title' class='form-control' onchange='titleChanged()'>\n";
                            while ($ttrow = sqlFetchArray($ttres)) {
                                $thisid = $ttrow['grp_form_id'];
                                echo "<option value='" . attr($thisid) . "'";
                                if ($thisid == $form_id) {
                                    echo ' selected';
                                }
                                echo ">" . text($ttrow['grp_title']) . "</option>\n";
                            }
                            echo "</select>\n";
                            ?>
                        </div>
                        <div class="forms col-sm-5">
                            <?php
                            if ($GLOBALS['enable_amc_prompting'] && 'LBTref' == $form_id) { ?>
                                <div class='oe-pull-away' style='margin-right:25px;border-style:solid;border-width:1px;'>
                                    <div style='margin:5px 5px 5px 5px;'>

                                        <?php // Display the send records checkboxes (AMC prompting)
                                            $itemAMC = amcCollect("send_sum_amc", $pid, 'transactions', $transid);
                                            $itemAMC_elec = amcCollect("send_sum_elec_amc", $pid, 'transactions', $transid);
                                            $itemAMC_confirmed = amcCollect("send_sum_amc_confirmed", $pid, 'transactions', $transid);
                                        ?>

                                        <?php if (!(empty($itemAMC))) { ?>
                                            <input type="checkbox" id="send_sum_flag" name="send_sum_flag" checked>
                                        <?php } else { ?>
                                            <input type="checkbox" id="send_sum_flag" name="send_sum_flag">
                                        <?php } ?>

                                        <span class="text"><?php echo xlt('Sent Summary of Care?') ?></span><br />

                                        <?php if (!(empty($itemAMC)) && !(empty($itemAMC_elec))) { ?>
                                            &nbsp;&nbsp;<input type="checkbox" id="send_sum_elec_flag" name="send_sum_elec_flag" checked>
                                        <?php } elseif (!(empty($itemAMC))) { ?>
                                            &nbsp;&nbsp;<input type="checkbox" id="send_sum_elec_flag" name="send_sum_elec_flag">
                                        <?php } else { ?>
                                            &nbsp;&nbsp;<input type="checkbox" id="send_sum_elec_flag" name="send_sum_elec_flag" disabled>
                                        <?php } ?>
                                        <span class="text"><?php echo xlt('Sent Summary of Care Electronically?') ?><br />

                                        <?php if (!(empty($itemAMC)) && !(empty($itemAMC_confirmed))) { ?>
                                            &nbsp;&nbsp;<input type="checkbox" id="send_sum_amc_confirmed" name="send_sum_amc_confirmed" checked>
                                        <?php } elseif (!(empty($itemAMC))) { ?>
                                            &nbsp;&nbsp;<input type="checkbox" id="send_sum_amc_confirmed" name="send_sum_amc_confirmed">
                                        <?php } else { ?>
                                            &nbsp;&nbsp;<input type="checkbox" id="send_sum_amc_confirmed" name="send_sum_amc_confirmed" disabled>
                                        <?php } ?>
                                        <span class="text"><?php echo xlt('Confirmed Recipient Received Summary of Care?') ?>
                                    </div>
                                </div>
                                <?php
                            } ?>
                        </div>
                    </fieldset>
                </div>
            </div>

            <div id='referdiv'>
                <div id="DEM">
                    <ul class="tabNav">
                        <?php
                        $fres = sqlStatement("SELECT * FROM layout_options " .
                          "WHERE form_id = ? AND uor > 0 " .
                          "ORDER BY group_id, seq", array($form_id));
                        $last_group = '';

                        while ($frow = sqlFetchArray($fres)) {
                            $this_group = $frow['group_id'];
                            // Handle a data category (group) change.
                            if (strcmp($this_group, $last_group) != 0) {
                                $group_seq  = substr($this_group, 0, 1);
                                $group_name = $grparr[$this_group]['grp_title'];
                                $last_group = $this_group;
                                if ($group_seq == 1) {
                                    echo "<li class='current'>";
                                } else {
                                    echo "<li class=''>";
                                }
                                echo "<a href='#' id='div_" . attr($group_seq) . "'>" .
                                text(xl_layout_label($group_name)) . "</a></li>";
                            }
                        }
                        ?>
                    </ul>
                    <div class="tabContainer">
                        <?php
                        $fres = sqlStatement("SELECT * FROM layout_options " .
                          "WHERE form_id = ? AND uor > 0 " .
                          "ORDER BY group_id, seq", array($form_id));

                        $last_group = '';
                        $cell_count = 0;
                        $item_count = 0;
                        $display_style = 'block';
                        $condition_str = '';

                        while ($frow = sqlFetchArray($fres)) {
                            $this_group = $frow['group_id'];
                            $titlecols  = $frow['titlecols'];
                            $datacols   = $frow['datacols'];
                            $data_type  = $frow['data_type'];
                            $field_id   = $frow['field_id'];
                            $list_id    = $frow['list_id'];

                            // Accumulate action conditions into a JSON expression for the browser side.
                            accumActionConditions($frow, $condition_str);

                            $currvalue  = '';
                            if (isset($trow[$field_id])) {
                                $currvalue = $trow[$field_id];
                            }

                            // Handle special-case default values.
                            if (!$currvalue && !$transid && $form_id == 'LBTref') {
                                if ($field_id == 'refer_date') {
                                    $currvalue = date('Y-m-d');
                                } elseif ($field_id == 'body' && $transid > 0) {
                                     $tmp = sqlQuery("SELECT reason FROM form_encounter WHERE " .
                                      "pid = ? ORDER BY date DESC LIMIT 1", array($pid));
                                    if (!empty($tmp)) {
                                        $currvalue = $tmp['reason'];
                                    }
                                }
                            }

                            // Handle a data category (group) change.
                            if (strcmp($this_group, $last_group) != 0) {
                                end_group();
                                $group_seq  = substr($this_group, 0, 1);
                                $group_name = $grparr[$this_group]['grp_title'];
                                $last_group = $this_group;
                                if ($group_seq == 1) {
                                    echo "<div class='tab current' id='div_" . attr($group_seq) . "'>";
                                } else {
                                    echo "<div class='tab' id='div_" . attr($group_seq) . "'>";
                                }

                                echo " <table border='0' cellpadding='0'>\n";
                                $display_style = 'none';
                            }

                            // Handle starting of a new row.
                            if (($titlecols > 0 && $cell_count >= $CPR) || $cell_count == 0) {
                                end_row();
                                echo " <tr>";
                            }

                            if ($item_count == 0 && $titlecols == 0) {
                                $titlecols = 1;
                            }

                            // Handle starting of a new label cell.
                            if ($titlecols > 0) {
                                end_cell();
                                echo "<td width='70' valign='top' colspan='" . attr($titlecols) . "'";
                                echo ($frow['uor'] == 2) ? " class='required'" : " class='bold'";
                                if ($cell_count == 2) {
                                    echo " style='padding-left:10pt'";
                                }

                                // This ID is used by action conditions.
                                echo " id='label_id_" . attr($field_id) . "'";
                                echo ">";
                                $cell_count += $titlecols;
                            }

                            ++$item_count;

                            echo "<b>";

                            // Modified 6-09 by BM - Translate if applicable
                            if ($frow['title']) {
                                echo (text(xl_layout_label($frow['title'])) . ":");
                            } else {
                                echo "&nbsp;";
                            }

                             echo "</b>";

                            // Handle starting of a new data cell.
                            if ($datacols > 0) {
                                end_cell();
                                echo "<td valign='top' colspan='" . attr($datacols) . "' class='text'";
                                // This ID is used by action conditions.
                                echo " id='value_id_" . attr($field_id) . "'";
                                if ($cell_count > 0) {
                                    echo " style='padding-left:5pt'";
                                }

                                echo ">";
                                $cell_count += $datacols;
                            }

                            ++$item_count;
                            generate_form_field($frow, $currvalue);
                            echo "</div>";
                        }

                        end_group();
                        ?>
                    </div><!-- end of tabContainer div -->
                </div><!-- end of DEM div -->
            </div><!-- end of referdiv -->
        </form>

        <!-- include support for the list-add selectbox feature -->
        <?php require $GLOBALS['fileroot'] . "/library/options_listadd.inc.php"; ?>
    </div> <!--end of container div-->
    <?php $oemr_ui->oeBelowContainerDiv();?>
</body>

<script>

// Array of action conditions for the checkSkipConditions() function.
var skipArray = [
<?php echo $condition_str; ?>
];

<?php echo $date_init; ?>
// titleChanged();
<?php
if (function_exists($form_id . '_javascript_onload')) {
    call_user_func($form_id . '_javascript_onload');
}
?>

</script>
*/ ?>

        </form>

    </div> <!--end of container div-->
    <?php $oemr_ui->oeBelowContainerDiv();?>

<script>
	// define ajax error handler
	$(function() {
		$.ajaxSetup({
			error: function(jqXHR, exception) {
				if (jqXHR.status === 0) {
					alert('Not connected to network.');
				} else if (jqXHR.status == 404) {
					alert('Requested page not found. [404]');
				} else if (jqXHR.status == 500) {
					alert('Internal Server Error [500].');
				} else if (exception === 'parsererror') {
					alert('Requested JSON parse failed.');
				} else if (exception === 'timeout') {
					alert('Time out error.');
				} else if (exception === 'abort') {
					alert('Ajax request aborted.');
				} else {
					alert('Uncaught Error.\n' + jqXHR.responseText);
				}
			}
		});
	
		return false;
	});

	function get_related(type, key) {
		$('#code_key').val(key);
//	    window.top.restoreSession();
	    dlgopen("<?php echo $_GLOBALS['webroot']; ?>/interface/patient_file/encounter/find_code_popup.php?default=" +type, '_blank', 700, 400);
	}

	function set_related(type, code, key, title) {
		var key = $('#code_key').val();

	    if (type !== '') {
			code = type +':'+ code;
	    } else {
			code = '';
	    }

	    $('#'+key+'_code').val(code);
	    $('#'+key+'_title').val(title);
	}



	//jqury-ui tooltip
	$(function () {
		$('.icon-tooltip i').attr({"title": <?php echo xlj('Click to see more information'); ?>, "data-toggle": "tooltip", "data-placement": "bottom"}).tooltip({
			show: {
				delay: 700,
				duration: 0
			}
		});
		$('#enter-details-tooltip').attr({"title": <?php echo xlj('Additional help to fill out this form is available by hovering over labels of each box and clicking on the dark blue help ? icon that is revealed. On mobile devices tap once on the label to reveal the help icon and tap on the icon to show the help section'); ?>, "data-toggle": "tooltip", "data-placement": "bottom"}).tooltip();
	});

	// initiation after load complete
	$(document).ready(function() {
		$("#search_code").keyup(function(event){
			if(event.keyCode == 13) {
				searchCodes();
			}
		});

		$("#search_test").keyup(function(event){
			if(event.keyCode == 13) {
				searchTests();
			}
		});

		$("#order_psc").change(function(){
			if ($(this).prop("checked")) {
				$("#sample_data").hide();
				$("#psc_data").show();
			} else {
				$("#sample_data").show();
				$("#psc_data").hide();
			}
		});
			
		$("#work_flag").change(function(){
			$("#work_data").hide();
			
			if ($(this).attr("checked")) {
				$("#work_data").show();
			}
		});

		$('#process_close').click(function(){
//			var form_id = $('#id').val();
//			location.href = "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/view.php?id=" + form_id;
			window.parent.closeTab(window.name, true); 
		});
		
		$('#process_print').click(function(){
			var docid = $('#doc_req_id').val();
			printPDF(docid);
		});
		
			
	}); // end ready setup
	
</script>
</body>
</html>
