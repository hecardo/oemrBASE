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

use WMT\Classes\Tools;
use WMT\Classes\Options;

use WMT\Objects\User;
use WMT\Objects\Patient;
use WMT\Objects\Insurance;
use WMT\Objects\Encounter;

use WMT\Laboratory\Common\Processor;
use WMT\Laboratory\Common\LabOrder;
use WMT\Laboratory\Common\LabOrderItem;
use WMT\Laboratory\Common\LabResult;
use WMT\Laboratory\Common\LabResultItem;

// grab inportant stuff
$pop = $_REQUEST['pop'];
$print = $_REQUEST['print'];
$id = $_REQUEST['id'];

$form_title = 'Laboratory Order';

$save_url = $rootdir.'/forms/laboratory/save.php';
$document_url = $GLOBALS['web_root'].'/controller.php?document&retrieve&patient_id='.$pid.'&document_id=';

try {
	// default initial values
	$date_ordered = date('Y-m-d');
	if (empty($pid)) $pid = $_SESSION['pid'];
	if (empty($encounter)) $encounter = $_SESSION['encounter'];
	
	// load order (new or existing)
	$order_data = new LabOrder('laboratory', $id);
	
	if ($order_data && $order_data->id) {
		// load existing order data (override defaults)
		$pid = $order_data->pid;
		$order_data->pid = $pid;
		$encounter = $order_data->encounter;
		$lab_id = $order_data->lab_id;
		$date_ordered = Tools::FormatDate($order_data->date_ordered);
		$item_list = LabOrderItem::fetchItemList($order_data->order_number);
		$mode = ($order_data->status == 'i')? 'new' : 'update';
	} else {
		// order data defaults
		$order_data->pid = $pid;
		$order_data->patient_id = $order_data->pid;
		$order_data->encounter_id = $encounter;
		$order_data->lab_id = $lab_id;
		$order_data->date_ordered = $date_ordered;
		$order_data->status = 'i';
		$item_list = false;
	}

	if ($order_data->user == 'system') $generated = true;
	if (!$pid) {
		throw new \Exception("Missing patient identifier");
	}
	if (!$encounter) {
		throw new \Exception("Missing current encounter identifier.");
	}
	if (!$lab_id) {
		throw new \Exception("Missing laboratory processor identifier.");
	}
	 
	// find lab information
	$lab_data = new Processor($lab_id);

	// load common lists
	$status_list = new Options('Lab_Form_Status');
	$bill_list = new Options('Lab_Billing');
	$num_list = new Options('One_To_Nine');
	$spec_list = new Options('Proc_Specimen');
	$rep_list = new Options('Proc_Rep_Status');
	$res_list = new Options('Proc_Res_Status');
	$trans_list = new Options('Lab_Transport');
	$acct_list = new Options('Lab_Accounts');

	// load type specific lists
	$lab_type = $lab_data->type;
	$acct_id = $lab_data->send_fac_id;
	$fac_id = $enc_data->facility_id;
	
	$acct_list = new Options('Lab_'.$lab_type.'_Accounts');
	$label_list = new Options('Lab_Label_Printers');
	
	// find subaccount
	$accounts = [];
	$accounts[$acct_id] = 'Default Account';
	foreach ($acct_list->list as $account) {
		if ($account['notes'] == $lab_id) {
			$accounts[$account['option_id']] = $account['title'];
		}
	}

	// validate installation
	$invalid = "";
	if (!$acct_id) $invalid .= "No Sending Account Identifier\n";
	if (empty($lab_data->lab_account)) $invalid .= "No Lab Account Assigned\n";
	if (empty($lab_data->lab_appt_name)) $invalid .= "No Lab Appointment Type\n";
	if (empty($lab_data->lab_cat_name)) $invalid .= "No Lab Document Category\n";
	
	if (!$lab_data->recv_fac_id) $invalid .= "No Receiving Facility Identifier\n";
	if (!$lab_data->recv_app_id) $invalid .= "No Receiving Application Identifier\n";
	
	if ($lab_type != 'internal') {
		if (!$lab_data->login) $invalid .= "No Username\n";
		if (!$lab_data->password) $invalid .= "No Password\n";
	}
	
	if (!$lab_data->orders_path) $invalid .= "No Order Path\n";
	if (!$lab_data->results_path) $invalid .= "No Result Path\n";
	
	if (!file_exists($lab_data->lab_work_path)) $invalid .= "No Lab Work Directory\n";
	if (!file_exists($GLOBALS["srcdir"]."/wmt")) $invalid .= "Missing WMT Libraries\n";
	
	if (!extension_loaded("curl")) $invalid .= "CURL Module Not Enabled\n";
	if (!extension_loaded("xml")) $invalid .= "XML Module Not Enabled\n";
	if (!extension_loaded("sockets")) $invalid .= "SOCKETS Module Not Enabled\n";
	if (!extension_loaded("soap")) $invalid .= "SOAP Module Not Enabled\n";
	if (!extension_loaded("openssl")) $invalid .= "OPENSSL Module Not Enabled\n";

	if ($invalid) { 
?>
		<h1>Laboratory Interface Not Available</h1>
		The interface is not enabled, not properly configured, or required components are missing!!
		<br/><br/>
		For assistance with implementing this service contact:
		<br/><br/>
		<a href="http://www.williamsmedtech.com/support" target="_blank"><b>Williams Medical Technologies, Inc.</b></a>
		<br/><br/>
		<table style="border:2px solid red;padding:20px"><tr><td style="white-space:pre;color:red"><h3>DEBUG OUTPUT</h3><?php echo $invalid ?></td></tr></table>
<?php
		die(); 
	}

	// fetch other data
	$pat_data = Patient::getPid($pid);
	$enc_data = Encounter::getEncounter($encounter);
	$ins_list = Insurance::getPidInsDate($pid, $date_ordered);
}
catch (Exception $e) {
	$logger->error($e->getMessage());
	die($e->getMessage());
}


// set form status
$status = 'i'; // incomplete and pending
if ($order_data->id && $order_data->status) {
	$status = $order_data->status;
}
//if ($report_data->id && $report_data->status) {
//	$status = $report_data->status;
//}

// diagnoses favorites
$diag_list = array();

// active encounter diagnoses
$sql = "SELECT 'Active' AS title, CONCAT('ICD10:',`formatted_dx_code`) AS code, `short_desc`, `long_desc` FROM `issue_encounter` ie ";
$sql .= "LEFT JOIN `lists` ls ON ie.`list_id` = ls.`id` AND ie.`pid` = ls.`pid` AND ls.`activity` = '1' ";
$sql .= "LEFT JOIN `icd10_dx_order_code` oc ON oc.`formatted_dx_code` = SUBSTR(ls.`diagnosis` FROM 7) AND oc.`active` = '1' ";
$sql .= "WHERE ie.`pid` = ? AND ie.`encounter` = ? AND ie.`resolved` = 0 AND `short_desc` IS NOT NULL ";
$sql .= "ORDER BY oc.`short_desc`";
$result = sqlStatementNoLog($sql,array($pid,$encounter));

while ($data = sqlFetchArray($result)) {
	// create array ('tab title','icd9 code','short title','long title')
	$diag_list[] = $data;
}

// retrieve diagnosis quick list
$query = "SELECT `title`, CONCAT('ICD10:',`formatted_dx_code`) AS code, `short_desc`, `long_desc` FROM `list_options` l ";
$query .= "JOIN `icd10_dx_order_code` c ON c.`formatted_dx_code` = l.`option_id` AND c.`active` = 1 ";
$query .= "WHERE l.list_id LIKE ? ";
$query .= "ORDER BY l.`title`, l.`seq`";
$list_key = 'Lab_Diag%';
$result = sqlStatement($query, array($list_key));

while ($data = sqlFetchArray($result)) {
	// create array ('tab title','icd code','short title','long title')
	$diag_list[] = $data;
}

// procedures favorites
$test_list = array();

// retrieve favorite tests
$query = "SELECT `title`, `procedure_code` AS code, `name`, `procedure_type` AS type FROM `list_options` l ";
$query .= "JOIN `procedure_type` pt ON l.`option_id` LIKE pt.`procedure_code` AND pt.`activity` = 1 ";
$query .= "AND pt.`lab_id` = ? AND pt.`procedure_type` IN('ord','pro') ";
$query .= "WHERE l.`list_id` LIKE ? ";
$query .= "ORDER BY l.`title`, l.`seq`";
$list_key = 'Lab_'.$lab_type.'_Test%';
$result = sqlStatement($query, array($lab_id, $list_key));

while ($data = sqlFetchArray($result)) {
	// create array ('tab title','test code','name','type')
	$test_list[] = $data;
}

?>
<!DOCTYPE html>
<html>
<head>
	<?php Header::setupHeader(); ?>
	<title><?php echo $form_title; ?></title>

<?php /* ?>
	<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
	<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/library/wmt/wmt.default.css" media="screen" />
	<!-- link rel="stylesheet" type="text/css" href="http://code.jquery.com/ui/1.10.0/themes/base/jquery-ui.css" media="screen" / -->
		
	<script><?php include_once("{$GLOBALS['srcdir']}/restoreSession.php"); ?></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery-1.7.2.min.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery-ui-1.10.0.custom.min.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/common.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4_patch.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/dialog.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/overlib_mini.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/textformat.js"></script>
	<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/wmt/wmtstandard.js"></script>
<?php */ ?>
		
	<script>
		var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

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
//				var form_id = $('#id').val();
//				location.href = "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/view.php?id=" + form_id;
				window.parent.closeTab(window.name, true); 
			});
			
			$('#process_print').click(function(){
				var docid = $('#doc_req_id').val();
				printPDF(docid);
			});
			
			$('.card-header').click(function() {
				$(this).find('i').toggleClass('fa fa-plus-square fa fa-minus-square')
			});

<?php if ($status != 'i') { // disable if not incomplete ?> 
			$("#order_entry :input").attr("disabled", true);
			$("#order_review :input").attr("disabled", true);
			$("#order_submit :input").attr("disabled", true);
			$(".nolock").attr("disabled", false);
<?php } ?>
				
		}); // end ready setup
		
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

		// clear codes
		function clearCodes() {
			$("#code_areas input:checked").each(function() {
				$(this).prop('checked',false);
			});
		}

		// assign codes
		function addCodes() {
			var count = 0;
			$("#code_areas .active input:checked").each(function() {
				success = addCodeRow($(this).attr('code'), $(this).attr('desc'));
				if (success) count++;
			});
			clearCodes();
		}

		// add new code row
		function addCodeRow(code,text) {
			$('#codeEmptyRow').remove();

			var key = code.replace('.','_');
			key = key.replace('ICD9:','');
			key = key.replace('ICD10:','');
			if ($('#dc_'+key).length) {
				alert("Code "+code+" has already been added.");
				return false;
			}

			if ($('#code_table tr').length > 10) {
				alert("Maximum number of diagnosis codes exceeded.");
				return false;
			}
			
			var newRow = "<tr id='dc_"+key+"'>";
			newRow += "<td class='pr-2'><button type='button' class='btn btn-sm btn-primary' onclick=\"removeCodeRow('dc_"+key+"')\" />remove</button></td>\n";
			newRow += "<td class='pr-2'><input class='code form-control form-control-sm' name='dx_code[]' readonly value='"+code+"'/></td>\n";
			newRow += "<td><input class='name form-control form-control-sm' name='dx_text[]' readonly value='"+text+"'/></td>\n";
			newRow += "</tr>\n";

			$('#code_table').append(newRow);

			return true;
		}

		// remove code row
		function removeCodeRow(id) {
			$('#'+id).remove();
			// there is always the header and the "empty" row
			if ($('#code_table tr').length == 1) {
				$('#code_table').append('<tr id="CodeEmptyRow"><td colspan="3"><b>NO DIAGNOSIS CODES SELECTED</b></td></tr>');
			}
		}

		// search for the provided icd code
		function searchCodes() {
			var output = '';
			var code = $('#search_code').val();
			if ( code == '' ) { 
				alert('You must enter a diagnosis search code or name.');
				return;
			}

			// set correct tab
			$('#diag_search').html('<div style="display:flex;justify-content:center"><div class="spinner-border mt-5"></div></div>');
			$('#code_tabs .active').removeClass('active');
			$('#code_areas .active').removeClass('active');
			$('#diag_tab_search').addClass('active');	
			$('#diag_search').addClass('active show');	

			// retrieve the diagnosis array
			$.ajax ({
				type: "POST",
				url: "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/labs_ajax.php",
				dataType: "json",
				data: {
					type: 'code',
					code: code
				},
				success: function(data) {
					$.each(data, function(key, val) {
						var code = val.code.replace('ICD10:','');
						var id = code.replace('.','_');
						output += "<div class='form-check'>";
						output += "<input class='form-check-input' type='checkbox' id='diag_"+id+"' code='"+code+"' desc='"+val.short_desc+"' >";
						output += "<label class='form-check-label' for='diag_"+id+"'><b>"+code+"</b> - "+val.short_desc+"</label></div>\n";
					});
				},
				async:   false
				
			});

			if (output == '') {
				output = '<div class="alert show text-center p-4 mt-4 text-danger">NO CODES FOUND</div>';
			}
			
			$('#diag_search').html(output);
			$('#search_code').val('');
		}

		// clear tests
		function clearTests() {
			$("#test_areas input:checked").each(function() {
				$(this).prop('checked',false);
			});
		}

		// assign tests
		function addTests() {
			var count = 0;
			$("#test_areas .active input:checked").each(function() {
				success = addTestRow($(this).attr('code'), $(this).attr('pro'), $(this).attr('desc'));
				if (success) count++;
			});
			clearTests();
		}

		// add new test row
		function addTestRow(code,type,text) {
			$('#testEmptyRow').remove();

			var key = code.replace('.','_');
			if ($('#oc_'+key).length) {
				alert("Test "+code+" has already been added.");
				return false;
			}

			if ($('#order_table tr').length > 35) {
				alert("Maximum number of tests exceeded.");
				return false;
			}

			// retrieve test details
			var data = testDetails(code);
			var type = data.type; // json data from ajax
			var unit = data.unit; // json data from ajax
			var state = data.state; // json data from ajax
			var name = data.name; // json data from ajax
			var profile = data.profile; // json data fron ajax
			var aoe = data.aoe; // json data from ajax

			var success = true;
			$('.component').each(function() {
				if ($(this).attr('unit') == code && success) {
					alert("Test "+code+" has already been added as profile component.");
					success = false;
				} 					
			});
			if (!success) return false;
			var newRow = "<tr id='oc_"+key+"'>";
			newRow += "<td class='pr-2 align-top'><button type='button' class='btn btn-sm btn-primary' onclick=\"removeTestRow('oc_"+key+"')\" />remove</button>\n";
			newRow += "<button type='button' class='btn btn-sm btn-info' onclick=\"testOverview('"+code+"')\" />details</button></td>\n";
			if (type == 'pro') newRow += "<td class='pr-2 align-top'><input class='test form-control form-control-sm font-weight-bold text-danger' name='test_code[]' type='text' readonly value='"+code+"'/><input type='hidden' name='test_profile[]' value='pro' /></td>\n";
			if (type == 'ord') newRow += "<td class='pr-2 align-top'><input class='test form-control form-control-sm' name='test_code[]' type='text' readonly value='"+code+"'/><input type='hidden' name='test_profile[]' value='ord' /></td>\n";
			newRow += "<td class='align-top' colspan='2'><input class='name form-control form-control-sm' name='test_text[]' readonly value='"+text+"'/>\n";

			// add profile tests if necessary
			success = true;
			for (var key in profile) {
				var obj = profile[key];

				$('.component').each(function() {
					if ($(this).attr('unit') == obj.component) {
						alert("Component of test "+code+" has already been added.");
						success = false;
					} 					
				});
					
				if (obj.description)  newRow += "<input class='form-control form-control-sm component w-100' readonly unit='"+obj.component+"' value='"+obj.component+" - "+obj.description+"'/>\n";
				
				// add component AOE questions if necessary
				var aoe2 = obj.aoe;
				for (var key2 in aoe2) {
					var obj2 = aoe2[key2];
				   
					var test_code = obj2.code;
					var test_unit = obj2.unit;
					var question = obj2.question.replace(':','');
					if (obj2.description) question = obj2.description.replace(':',''); // use longer if available
					var prompt = obj2.prompt;
					if (test_code) {
						newRow += '<input type="hidden" name="aoe'+key+'_label[]" value="'+question+'" />'+"\n";
						newRow += "<input type='hidden' name='aoe"+key+"_code[]' value='"+test_code+"' />\n";
				   		newRow += "<input type='hidden' name='aoe"+key+"_unit[]' value='"+test_unit+"' />\n";
				   		newRow += "<div style='margin-top:5px;text-align:right'>" + question + ": <input name='aoe"+key+"_text[]' title='" + test_code + ": " + prompt + "' class='wmtFullInput aoe' value='' style='width:300px' /></div>\n";
					}	
				}
			}

			if (!success) return false;
			
			// add order AOE questions if necessary
			for (var key in aoe) {
				var obj = aoe[key];
			   
				var test_code = obj.code;
				var question = obj.question.replace(':','');
				if (obj.description) question = obj.description.replace(':',''); // use longer if available
				var prompt = obj.prompt;
				if (test_code) {
					newRow += '<input type="hidden" name="aoe'+key+'_label[]" value="'+question+'" />'+"\n";
					newRow += "<input type='hidden' name='aoe"+key+"_code[]' value='"+test_code+"' />\n";
					newRow += "<div style='margin-top:5px;text-align:right'>" + question + ": <input name='aoe"+key+"_text[]' title='" + prompt + "' class='wmtFullInput aoe' value='' style='width:300px' /></div>\n";
				}	
			}

			newRow += "</td></tr>\n"; // finish up order row
			
			$('#order_table').append(newRow);

			return true;
		}

		// remove test row
		function removeTestRow(id) {
			$('#'+id).remove();
			// there is always the header and the "empty" row
			if ($('#order_table tr').length == 1) {
				$('#specimen_transport').val('');
				$('#transport_name').val('');
				$('#order_table').append('<tr id="TestEmptyRow"><td colspan="3"><b>NO PROFILES / TESTS SELECTED</b></td></tr>');
			}
		}

		// search for the provided test code
		function searchTests() {
			var output = '';
			var code = $('#search_test').val();
			if (code == '') { 
				alert('You must enter a procedure search code or name.');
				return;
			}

			// set correct tab
			$('#test_search').html('<div style="display:flex;justify-content:center"><div class="spinner-border mt-5"></div></div>');
			$('#test_tabs .active').removeClass('active');
			$('#test_areas .active').removeClass('active');
			$('#test_tab_search').addClass('active');	
			$('#test_search').addClass('active show');	

			// retrieve the diagnosis array
			$.ajax ({
				type: "POST",
				url: "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/labs_ajax.php",
				dataType: "json",
				data: {
					type: 'test',
					code: code,
					lab_id: '<?php echo $lab_id ?>'
				},
				success: function(data) {
					// data = array('id','code','type','title','description','provider');
					$.each(data, function(key, val) {
						var id = val.code.replace('.','_');
						var text = val.description;
						if (!text) text = val.title;
						output += "<div class='form-check'>";
						output += "<input class='form-check-input' type='checkbox' id='test_"+id+"' code='"+val.code+"' desc='"+text+"' prof='"+val.type+"' /> ";
						output += "<label class='form-check-label' for='test_"+id+"'>";
						if (val.type == 'pro') {
							output += "<span class='font-weight-bold text-danger'>"+val.code+"</span>";
						} else { 	
							output += "<span class='font-weight-bold'>"+val.code+"</span>";
						}
						output += " - "+text+"</label></div>\n";
					});
				},
				async:   false
			});

			if (output == '') {
				output = '<div class="alert show text-center p-4 mt-4 text-danger">NO CODES FOUND</div>';
			}
			
			$('#test_search').html(output);
			$('#search_test').val('');
		}

		// search for the provided test code
		function testDetails(code) {
			var output = '';
			
			// retrieve the test details
			$.ajax ({
				type: "POST",
				url: "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/labs_ajax.php",
				dataType: "json",
				data: {
					type: 'details',
					code: code,
					lab_id: '<?php echo $lab_id ?>'
				},
				success: function(data) {
					output = data; // process later
				},
				async:   false
			});

			return output;
		}

		// print document
		function printPDF(id) {
			location.href = "<?php echo $document_url ?>" + id;
			return;
		} 
		
		// print labels
		function printLabels() {
			var printer = $('#label_printer').val();
			var count = $('#label_count').val();
			var order = $('#order_number').val();
			
			// retrieve the label
			$.ajax ({
				type: "POST",
				url: "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/labs_ajax.php",
				dataType: "text",
				data: {
					type: 'label',
					printer: printer,
					count: count,
					order: order,
					patient: "<?php echo $pat_data->lname; ?>, <?php echo $pat_data->fname; ?> <?php echo $pat_data->mname; ?>",
					pid: "<?php echo $pat_data->pid  ?>",
					lab: "<?php echo $lab_id ?>",
					account: "<?php echo $acct_id ?>"
				},
				success: function(data) {
					if (printer == 'file') {
						window.open(data, "_blank");
					} else {
						alert(data);
					}
				}
			});

		}

		// save current form
		function saveClicked() {
            top.restoreSession();
			var resp = true;
<?php if ($status == 'i') { // has not been submitted yet ?>
			resp = confirm("Your order will be saved but will NOT be submitted.\n\nClick 'OK' to save and exit.");
<?php } ?>
			if (resp) {
				$('form').submit();;
			}
		}

		// cancel current form
		function cancelClicked() {
            top.restoreSession();
			resp = confirm("Your changes have not been saved and will be discarded.\n\nClick 'OK' to exit without saving.");
			if (resp) {
<?php if ($pop) { ?>
				window.parent.dlgclose(); // in modal
<?php } else { ?>
				window.parent.closeTab(window.name, true); // in tab, do parent refresh
<?php } ?>
			}
		}

		// display test overview pop up
		function testOverview(code) {
			$('#details_modal').modal('show');
			
			$.ajax ({
				type: "POST",
				url: "<?php echo $GLOBALS['webroot'] ?>/interface/forms/laboratory/labs_ajax.php",				
				dataType: "html",
				data: {
					type: 'overview',
					code: code
				},
				success: function(data) {
					$('#details_output').html(data);
				}
			});

			return;
		}

		// validate data and submit form
		function submitClicked() {
            top.restoreSession();

			// minimum validation
			notice = '';
			$('.aoe').each(function() {
				if (!$(this).val()) notice = "\n- All order questions must be answered."; 
			});
			if ($('.code').length < 1) notice += "\n- At least one diagnosis code required.";
			if ($('.test').length < 1) notice += "\n- At least one profile / test code required.";
			if (!$('#order_psc').is(':checked')) {
				if ($('#collector_id').val() == '') notice += "\n- Specimen collected by is required.";
				if ($('#date_collected').val() == '') notice += "\n- Specimen collection date is required.";
				if ($('#time_collected').val() == '') notice += "\n- Specimen collection time is required.";
			}
			if ($('#provider_id').val() == '' || $('#provider_id').val() == '_blank') notice += "\n- An ordering physician is required.";
			if ($('#billing_type').val() == '') notice += "\n- A billing type must be specified.";

			if (notice) {
				notice = "PLEASE CORRECT THE FOLLOWING:\n" + notice;
				alert(notice);
				return;
			}

			$('#mode').val('process'); // flag doing submit
			$('#process_modal').modal('show');
			
			$.ajax ({
				type: "POST",
				url: "<?php echo $save_url ?>",
				data: $("form").serialize(),
				success: function(data) {
					$('#process_output').html(data);

					$('#process_close').prop('disabled',false);
					var docid = $(data).find('#order_req_id').val();
					if (docid) {
						$('#process_print').prop('disabled',false);
						$('#doc_req_id').val(docid);
					}
				}
			});
		}

<?php /* ----------------------------?>
 			function printClicked() {
 	 			// do save before print
				var f = document.forms[0];
				$('#print').val('1'); // flag doing print
				var prnwin = window.open('','print','width=735px,height=575px,status=no,scrollbars=yes');
				prnwin.focus();
				$('#<?php echo $form_name ?>').attr('target','print');
				restoreSession();
				f.submit();
 			}

 			function messageClicked() {
 	 			var url = "../../main/messages/add_edit_message.php?mode=addnew&reply_to=<?php echo $order_data->pid ?>&document_id=<?php echo $order_data->result_doc_id ?>";
				var prnwin = window.open(url,'message','width=735px,height=575px,status=no,scrollbars=yes');
 			}


			// print labels
			function printLabels(item) {
				var f = document.forms[0];
				var fl = document.forms[item];
				var printer = fl.labeler.value;
				if ( printer == '' ) { 
					alert('Unable to determine default label printer.\nPlease select a label printer.');
					return;
				}

				var count = fl.count.value;
				var order = f.order_number.value;
				var patient = "<?php echo $pat_data->lname; ?>, <?php echo $pat_data->fname; ?> <?php echo $pat_data->mname; ?>";
				var pid = "<?php echo $pat_data->pid  ?>";
				
				// retrieve the label
				$.ajax ({
					type: "POST",
					url: "<?php echo $GLOBALS['webroot'] ?>/library/wmt/quest/QuestAjax.php",
					dataType: "text",
					data: {
						type: 'label',
						printer: printer,
						count: count,
						order: order,
						patient: patient,
						pid: pid,
						siteid: '<?php echo $siteid ?>'
					},
					success: function(data) {
						if (printer == 'file') {
							window.open(data,"_blank");
						}
						else {
							alert(data);
						}
					},
					async:   false
				});

			}

			
<?php ------------------------ OLD ------------------------- */ ?>

	</script>
	
<style>
#lab_form .nav-tabs .nav-link {
	background-color: var(--light);
	border: none;
	border-bottom: 1px solid black;
	border-radius: 0;
	height: 26px;
	padding: 2px 10px;
	margin-bottom: 0;
	color: var(--dark);
	font-size: 14px;
}

#lab_form .nav-tabs .nav-link.active {
	background-color: var(--cyan);
	color: var(--white);
}

#lab_form legend {
	font-size: 1rem !important;
	margin-bottom: 0 !important;
}

#lab_form table th {
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

</head>

<body class="body_top">
	<div class="container">
		<div class="page-header col-md-12">
			<h2><?php echo $form_title; ?> for <?php echo $pat_data->format_name; ?></h2>
		</div>
		<div id="sections" class="col-12">
			<form id="lab_form" class="form form-horizontal" method="post" action="<?php echo $save_url ?>">
				<input type="hidden" name="csrf_token_form" value="<?php //echo attr(CsrfUtils::collectCsrfToken()); ?>" />
				<input type='hidden' id='mode' name='mode' value='<?php echo attr($mode); ?>' />
				<input type='hidden' name='pop' value='<?php echo attr($pop) ?>' />
				<input type='hidden' id='id' name='id' value='<?php echo attr($id) ?>' />
				<input type='hidden' name='pid' value='<?php echo attr($pid) ?>' />
				<input type='hidden' name='lab_id' value='<?php echo attr($lab_id) ?>' />
				<input type='hidden' name='encounter' value='<?php echo attr($encounter) ?>' />
				<input type='hidden' name='facility_id' value='<?php echo attr($enc_data->facility_id) ?>' />
				<input type='hidden' id='doc_req_id' value='<?php echo attr($order_data->order_req_id) ?>' />

				<!-- ORDER ENTRY -->
				<div class="card mb-1">
					<div class="card-header pl-2" data-toggle="collapse" data-target="#order_entry" style="font-size:1.1rem">
						<i class="fa  <?php echo (in_array($order_data->status,['i']))? 'fa-minus-square' : 'fa-plus-square'; ?>" id="icon_order_entry"></i> <?php echo xlt('Order Entry'); ?>
					</div>
					<div id="order_entry" class="collapse <?php if (in_array($order_data->status,['i'])) echo 'show'; ?>">
						<div class="card-body p-1">
							<div class="form-row m-0">

								<!-- LEFT SIDE -->
								<div class="col-6">
									<div class="border" style="background-color:var(--gray300)">
										<div class="p-1">
											<div class="form-row pl-2">
												<label>CLINICAL DIAGNOSIS CODES</label>
											</div>
											<div class="form-row">
												<div class="col-4">
													<button type="button" class="btn btn-primary btn-sm" onclick="addCodes()">add selected</button>
												</div>
												<div class="col-8">
													<button type="button" class="btn btn-primary btn-sm float-right ml-1" onclick="searchCodes()">search</button>
													<input class="form-control-sm float-right w-50" type="text" id="search_code" />
												</div>
											</div>
										</div>
										<div class="p-0">
											<div class="form-row m-0 p-0">
												<div class="col-3 p-0" style="min-height:200px">
													<div id="code_tabs" class="nav nav-tabs" role="tablist" aria-orientation="vertical">
<?php 
$title = 'Search';
echo '<a class="nav-item nav-link w-100 active" id="diag_tab_search" href="#diag_search" data-toggle="tab" role="tab" area-selected="false">'.$title.'</a>';
foreach ($diag_list as $data) {
	if ($data['title'] != $title) {
		$title = $data['title']; // new tab
		$link = strtolower(str_replace(' ', '_', $title));
		echo '<a class="nav-item nav-link w-100" id="diag_tab_'.$link.'" href="#diag_'.$link.'" data-toggle="tab" role="tab" area-selected="false">'.$title.'</a>';
	}
}
?>
													</div>
												</div>
												<div class="col-9 tab-content bg-white overflow-auto" id="code_areas" style="height:250px;max-height:250px">
<?php 
$title = 'Search';
echo '<div class="tab-pane fade show active" id="diag_search" role="tabpanel">';
echo '<div class="alert show text-center p-4 mt-4">Select profile at left or<br/>search using search box at top.</div>';
foreach ($diag_list as $data) {
	if ($data['title'] != $title) {
		if ($title) echo "</div>\n"; // end previous section
		$title = $data['title']; // new section
		$link = strtolower(str_replace(' ', '_', $title));
		echo '<div class="tab-pane fade" id="diag_'.$link.'" role="tabpanel">';
	}
	$text = ($data['notes']) ? $data['notes'] : $data['short_desc'];
	$code = str_replace('ICD10:', '', $data['code']);
	$id = str_replace('.', '_', $code);

	echo "<div class='form-check'>";
	echo "<input class='form-check-input' type='checkbox' id='diag_".$id."' code='".$data['code']."' desc='".htmlspecialchars($text)."' >";
	echo "<label class='form-check-label' for='diag_".$id."'><b>".$code."</b> - ".$text."</label></div>\n";
}
echo "</div></div>\n";
?>
											</div>									
										</div>									
									</div>
								</div>

								<!-- RIGHT SIDE -->
								<div class="col-6 pl-0">
									<div class="border" style="background-color:var(--gray300)">
										<div class="p-1">
											<div class="form-row pl-2">
												<label>LABORATORY TEST CODES</label>
											</div>
											<div class="form-row">
												<div class="col-4">
													<button type="button" class="btn btn-primary btn-sm" onclick="addTests()">add selected</button>
												</div>
												<div class="col-8">
													<button type="button" class="btn btn-primary btn-sm float-right ml-1" onclick="searchTests()">search</button>
													<input class="form-control-sm float-right w-50" id="search_test" type="text"/>
												</div>
											</div>
										</div>
										<div class="p-0">
											<div id="test_tabs" class="form-row m-0 p-0">
												<div class="col-3 p-0" style="min-height:200px">
													<div class="nav nav-tabs" role="tablist" aria-orientation="vertical">
														<a class="nav-item nav-link active w-100" id="test_tab_search" href="#test_search" data-toggle="tab" role="tab" area-selected="true">Search</a>
<?php 
$title = 'Search';
foreach ($test_list as $data) {
	if ($data['title'] != $title) {
		$title = $data['title']; // new tab
		$link = strtolower(str_replace(' ', '_', $title));
		echo '<a class="nav-item nav-link w-100" href="#test_'.$link.'" data-toggle="tab" role="tab" area-selected="false">'.$title.'</a>';
	}
}
?>
													</div>
												</div>
												<div class="col-9 tab-content bg-white overflow-auto" id="test_areas" style="height:250px;max-height:250px">
<?php 
$title = 'Search';
echo '<div class="tab-pane fade show active" id="test_search" role="tabpanel">';
echo '<div class="alert show text-center p-4 mt-4">Select profile at left or<br/>search using search box at top.</div>';
foreach ($test_list as $data) {
	if ($data['title'] != $title) {
		if ($title) echo "</div>\n"; // end previous section
		$title = $data['title']; // new section
		$link = strtolower(str_replace(' ', '_', $title));
		echo '<div class="tab-pane fade" id="test_'.$link.'" role="tabpanel">';
	}
	$text = ($data['notes']) ? $data['notes'] : $data['name'];
	$code = $data['code'];
	$id = str_replace('.', '_', $code);
	echo "<div class='form-check'>";
	echo "<input class='form-check-input' type='checkbox' id='test_".$id."' code='".$code."' desc='".htmlspecialchars($text)."' prof='".$data['type']."' /> ";
	echo "<label class='form-check-label' for='test_".$id."'>";
	if ($data['type'] == 'pro') {
		echo "<span class='font-weight-bold text-danger'>".$code."</span>";
	} else { 	
		echo "<span class='font-weight-bold'>".$code."</span>";
	}
	echo " - ".$text."</label></div>\n";
}
echo "</div></div>\n";
?>
											</div>									
										</div>									
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				

				<!-- ORDER REVIEW -->
				<div class="card mb-1">
					<div class="card-header pl-2" data-toggle="collapse" data-target="#order_review" style="font-size:1.1rem">
						<i class="fa  <?php echo (in_array($order_data->status,['i','s']))? 'fa-minus-square' : 'fa-plus-square'; ?>" id="icon_order_review"></i> <?php echo xlt('Lab Order Review'); ?>
					</div>
					<div id="order_review" class="collapse <?php if (in_array($order_data->status,['i','s'])) echo 'show'; ?>">
						<div class="card-body p-2">
							<fieldset class="border p-2 bg-white">
								<legend class="w-auto">Diagnosis Codes</legend>
								<table id="code_table" class="w-100">
									<tr>
										<th style="width:150px">Action</th>
										<th style="width:150px">Diagnosis</th>
										<th style="width:auto">Description</th>
									</tr>

<?php 
// load the existing diagnosis codes
$newRow = '';
$diag_array = array();
if ($order_data->order_diagnosis)
	$diag_array = explode("|", $order_data->order_diagnosis); // code & text

foreach ($diag_array AS $diag) {
	list($code,$text) = explode("^", $diag);
	if (empty($code)) continue;
	if (strpos($code,":") !== false)	
		list($dx_type,$code) = explode(":", $code);

	$code = trim($code);
	if (!$dx_type) $dx_type = 'ICD10';
	$key = str_replace('.', '_', $code);
	$code = $dx_type.":".$code;
	
	// add new row
	$newRow .= "<tr id='dc_".$key."'>";
	$newRow .= "<td class='pr-2'><button type='button' class='btn btn-sm btn-primary' onclick=\"removeCodeRow('dc_".$key."')\" />remove</button></td>\n";
	$newRow .= "<td class='pr-2'><input class='code form-control form-control-sm' name='dx_code[]' readonly value='".$code."'/></td>\n";
	$newRow .= "<td><input class='name form-control form-control-sm' name='dx_text[]' readonly value='".$text."'/></td>\n";
	$newRow .= "</tr>\n";
}

// anything found
if ($newRow) {
	echo $newRow;
}
else { // create empty row
?>
									<tr id="codeEmptyRow">
										<td colspan="3" class="font-weight-bold">
											NO DIAGNOSIS CODES SELECTED
										</td>
									</tr>
<?php } ?>
								</table>
							</fieldset>

<?php 
// create unique identifier for order number
if ($order_data->order_number) {
	$ordnum = $order_data->order_number;
}
else {
	$ordnum = $GLOBALS['adodb']['db']->GenID('order_seq');
	
	// duplicate checking
	$dupchk = sqlQuery("SELECT `procedure_order_id` AS id FROM `procedure_order` WHERE `procedure_order_id` = ?",array($ordnum));
	while($dupchk['id']) {
		$ordnum = $GLOBALS['adodb']['db']->GenID('order_seq');
		$dupchk = sqlQuery("SELECT `procedure_order_id` AS id FROM `procedure_order` WHERE `procedure_order_id` = ?",array($ordnum));
	} 
}
?>
							<fieldset class="border p-2 bg-white">
								<legend class="w-auto">Order Requisition - <?php echo $ordnum ?></legend>
								<input type="hidden" id="order_number" name="order_number" value="<?php echo $ordnum ?>" />

								<table id="lab_table">
									<tr>
										<th>LABORATORY PROCESSOR</th>
									</tr><tr>
										<td class="font-weight-bold">
											<input type="hidden" name="lab_id" value="<?php echo $lab_id ?>" />
											<?php echo strtoupper($lab_data->name) ?>
										</td>
									</tr>
								</table>
									
								<hr style="border-color:#f0f0f0;margin:.5rem 0;" />
									
<?php if ($ins_list[0]->ins_type_code == 2) { // medicare ?>
								<div class="form-check">
									<input type='checkbox' class="form-check-input" id='order_abn' name='order_abn' 
										value="1" <?php if ($order_data->order_abn || (!$viewmode && $GLOBALS['wmt_lab_psc'])) echo "checked" ?> />
									<label class="font-weight-bold form-check-label">ABN (Advanced Beneficiary Notice) Signed or Not Required</label>
								</div>
<?php } ?>
								<div class="form-check mb-3">
									<input type='checkbox' class="form-check-input" id='order_psc' name='order_psc' 
										value="1" <?php if ($order_data->order_psc || (!$viewmode && $GLOBALS['wmt_lab_psc'])) echo "checked" ?> />
									<label class="font-weight-bold form-check-label">Specimen Not Collected [ PSC Hold Order ]</label>
								</div>
								
								<div id="sample_data" style="<?php if ($order_data->order_psc || (!$viewmode && $GLOBALS['wmt_lab_psc'])) echo "display:none" ?>">
<?php if ($lab_type == 'quest') { ?>
									<div class="form-inline pb-1" >
										<div class="control-label pr-2">Transport Method:</div>
										<input type='hidden' name='specimen_transport' id='specimen_transport' value="<?php echo $order_data->specimen_transport ?>" />
										<input class="form-control form-control-sm" type='text' id='transport_name' readonly 
											value="<?php echo $trans_list->getItem($order_data->specimen_transport); ?>" />
									</div>
<?php } else { ?>
									<input type='hidden' name='specimen_transport' id='specimen_transport' value="" />
<?php } ?>
									<div class="form-inline pb-1" >
										<div class="control-label text-nowrap pr-2">Collected By:</div>
										<select class="form-control form-control-sm form-select w-auto" name='collector_id' id='collector_id' autocomplete="off" >
											<?php User::showIdOptions($order_data->collector_id, '-- select --'); ?>
										</select>
										<div class="control-label text-nowrap pl-4 pr-2">Collection Date:</div>
										<input class="form-control form-control-sm w-auto" type='date' name='date_collected' id='date_collected' 
											value='<?php echo Tools::FormatDate($order_data->date_collected); ?>' />
										<div class="control-label text-nowrap pl-4 pr-2">Collection Time:</div>
										<input class="form-control form-control-sm w-auto" type='time' name='time_collected' id='time_collected' 
											value='<?php echo Tools::FormatTime($order_data->date_collected); ?>' />
									</div>
								</div>
								
								<div id="psc_data" style="<?php if (!$order_data->order_psc && !$GLOBALS['wmt_lab_psc']) echo "display:none" ?>">
									<div class="form-inline pb-1">
										<div class="control-label text-nowrap pr-2">Scheduled/Anticipated Date:</div>
										<input class="form-control form-control-sm w-auto" type='date' name='date_pending' id='date_pending' 
											value='<?php echo Tools::FormatDate($order_data->date_pending); ?>' />
									</div>
								</div>

								<hr style="border-color: #f0f0f0" />
									
								<table id="order_table" class="w-100">
									<tr>
										<th style="width:150px">Actions</th>
										<th style="width:150px">Profile / Test</th>
										<th>General Description</th>
										<!-- th class="wmtHeader" style="width:300px">Order Entry Questions</th -->
									</tr>

<?php 
// load the existing requisition codes
$newRow = '';
foreach ($item_list as $order_item) { // $item = array of objects
	if (!$order_item->procedure_code) continue;
	$code = $order_item->procedure_code;
	$key = str_replace('.', '_', $code);

	// generate test row
	$newRow .= "<tr id='oc_".$key."'>";
	$newRow .= "<td class='pr-2 align-top'><button type='button' class='btn btn-sm btn-primary' onclick=\"removeTestRow('oc_".$key."')\" />remove</button>\n";
	$newRow .= "<button type='button' class='btn btn-sm btn-info' onclick=\"testOverview('".$code."')\" />details</button></td>\n";
	if ($order_item->procedure_type == 'pro') {
		$newRow .= "<td class='pr-2 align-top'><input class='test form-control form-control-sm font-weight-bold text-danger' name='test_code[]' type='text' readonly value='".$code."'/><input type='hidden' name='test_profile[]' value='pro' /></td>\n";
	} else {
		$newRow .= "<td class='pr-2 align-top'><input class='test form-control form-control-sm' name='test_code[]' type='text' readonly value='".$code."'/><input type='hidden' name='test_profile[]' value='ord' /></td>\n";
	}
	$newRow .= "<td class='align-top' colspan='2'><input class='name form-control form-control-sm' name='test_text[]' readonly value='".$order_item->procedure_name."'/>\n";
	
	// retrieve all component test if profile
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
				$newRow .= "<input class='form-control form-control-sm component w-100' readonly unit='".$profile['component']."' value='COMPONENT: ".$description."'/>\n";
			}
				
			// add component AOE questions if necessary
			$result2 = sqlStatement("SELECT aoe.`procedure_code` AS code, aoe.`question_code`, aoe.`question_text`, aoe.`tips`, `answer` FROM `procedure_answers` ans ".
				"LEFT JOIN `procedure_questions` aoe ON aoe.`question_code` = ans.`question_code` ".
				"WHERE aoe.`lab_id` = ? AND ans.`procedure_order_id` = ? AND ans.`procedure_order_seq` = ? AND aoe.`activity` = 1 ORDER BY ans.`answer_seq`",
					array($lab_id, $order_item->procedure_order_id, $order_item->procedure_order_seq));
	
			while ($aoe2 = sqlFetchArray($result2)) {
				$question = str_replace(':','',$aoe2['question_text']);
				if ($aoe2['analyte_cd']) {
					$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_label[]' value='".$question."' />\n";
					$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_code[]' value='".$aoe2['question_code']."' />\n";
					$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_unit[]' value='".$aoe2['unit_cd']."' />\n";
					$newRow .= "<div style='margin-top:5px;text-align:right'>".$question.": <input name='aoe".$aoe2['code']."_text[]' title='".$aoe2['result_filter']."' class='wmtFullInput aoe' value='".$test["aoe{$aoe_count}_text"]."' style='width:300px' /></div>\n";
					$aoe_count++;
				}
			}
		}
	}

	// add AOE questions if necessary
	$result = sqlStatement("SELECT aoe.`procedure_code` AS code, aoe.`question_code`, aoe.`question_text`, aoe.`tips`, ans.`answer` FROM `procedure_questions` aoe ".
		"LEFT JOIN `procedure_answers` ans ON aoe.`question_code` = ans.`question_code` AND ans.`procedure_order_id` = ? AND ans.`procedure_order_seq` = ? ".
		"WHERE aoe.`lab_id` = ? AND aoe.`procedure_code` = ? AND aoe.`activity` = 1 ORDER BY aoe.`seq`, ans.`answer_seq`",
			array($order_item->procedure_order_id, $order_item->procedure_order_seq, $lab_id, $order_item->procedure_code));
			
	$aoe_count = 0;
	while ($aoe = sqlFetchArray($result)) {
		$question = str_replace(':','',$aoe['question_text']);
		if ($aoe['code']) {
			$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_label[]' value='".$question."' />\n";
			$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_code[]' value='".$aoe['question_code']."' />\n";
			$newRow .= "<div style='margin-top:5px;text-align:right'>".$question.": <input name='aoe".$aoe['code']."_text[]' title='".$aoe['tips']."' class='wmtFullInput aoe' value='".$aoe['answer']."' style='width:300px' /></div>\n";
			$aoe_count++;
		}
	}
	
	$newRow .= "</td></tr>\n"; // finish up order row

}

// anything found
if ($newRow) {
	echo $newRow;
}
else { // create empty row
?>
										
									<tr id="testEmptyRow">
										<td colspan="3">
											<b>NO PROFILES / TESTS SELECTED</b>
										</td>
									</tr>
<?php } ?>
																			
								</table>
									
								<hr style="border-color: #f0f0f0" />

								<label class="control-label mb-0" for="order_notes">Order Comments:</label>
								<div style="color:red;font-size:10px;float:right;margin-top:5px">[ Sent to lab and printed on requisition ]</div>
								<textarea name="clinical_hx" id="clinical_hx" class="form-control mb-2" rows="1"><?php echo htmlspecialchars($order_data->clinical_hx) ?></textarea>
	
								<label class="control-label mb-0" for="order_notes">Patient Notes and Instructions:</label>
								<div style="color:red;font-size:10px;float:right;margin-top:5px">[ Information provided to patient ]</div>
								<textarea name="patient_instructions" id="patient_instructions" class="form-control" rows="1"><?php echo htmlspecialchars($order_data->patient_instructions) ?></textarea>
											
							</fieldset>

						</div>				
					</div>
				</div>
				
				
				<!-- ORDER SUBMISSION -->
				<div class="card mb-1">
					<div class="card-header pl-2" data-toggle="collapse" data-target="#order_submit" style="font-size:1.1rem">
						<i class="fa  <?php echo (in_array($order_data->status,['i','s']))? 'fa-minus-square' : 'fa-plus-square'; ?>" id="icon_order_submit"></i> <?php echo xlt('Order Submission'); ?>
					</div>
					<div id="order_submit" class="collapse <?php if (in_array($order_data->status,['i','s'])) echo 'show'; ?>">
						<div class="card-body p-1">
							<div class="form-row m-0">

								<!-- LEFT SIDE -->
								<div class="col-6 pl-2 pt-3">
									<div class="form-inline flex-nowrap pb-1">
										<div class="control-label text-nowrap w-15">Ordered:</div>
										<div class="w-30">
											<input class="form-control form-control-sm w-auto" type='date' name='date_ordered' id='date_ordered'
													value='<?php echo Tools::FormatDate($order_data->date_ordered) ?>' />
										</div>
										<div class="control-label text-nowrap pl-2 w-15">Provider:</div>
										<select class="form-control form-control-sm w-auto" name='provider_id' id='provider_id' autocomplete="off">
											<?php User::showProvOpts($order_data->provider_id, '-- select --'); ?> 
										</select>
									</div>
									<div class="form-inline flex-nowrap pb-1">
										<div class="control-label text-nowrap w-15">Processed:</div>
										<div class="w-30">
											<input class="form-control form-control-sm w-auto" type='date' name='date_transmitted' id='date_transmitted' readonly
												value='<?php echo Tools::FormatDate($order_data->date_transmitted) ?>' />
										</div>
										<div class="control-label text-nowrap pl-2 w-15">Status:</div>
										<input class="form-control form-control-sm w-auto" type='text' id='order_status' readonly
											value="<?php $status_list->showItem($status) ?>" />
									</div>
									<div class="form-inline flex-nowrap pb-1">
										<div class="control-label text-nowrap w-15">Billing Type:</div>
										<div class="w-30">
											<select class="form-control form-control-sm w-auto" autocomplete="off" name='billing_type' id='billing_type'>
												<option value=''>-- select --</option>

<?php 
	$bill_option = "";
	if (($order_data->ins_primary && $order_data->ins_primary != 'No Insurance') || 
			($order_data->ins_secondary && $order_data->ins_secondary != 'No Insurance') || 
					$ins_list[0]->company_name || $ins_list[1]->company_name) { // insurance available
		$bill_option .= "<option value='T'";
		if ($order_data->billing_type == 'T') $bill_option .= " selected";
		$bill_option .= ">Third Party</option>\n";
	}
	$bill_option .= "<option value='P'";
	if ($order_data->billing_type == 'P') $bill_option .= " selected";
	$bill_option .= ">Patient Bill</option>\n";
	$bill_option .= "<option value='C'";
	if ($order_data->billing_type == 'C') $bill_option .= " selected";
	$bill_option .= ">Client Bill</option>\n";
	
	echo $bill_option;	
?>
											</select>
										</div>
										<div class="control-label text-nowrap pl-2 w-15">Account:</div>
										<select class="form-control form-control-sm w-auto" autocomplete="off" name='account_facility' id='account_facility'>
<?php 
	foreach ($accounts AS $acct_id => $acct_name) {
		echo "<option value='$acct_id' ";
		if ($acct_id == $order_data->account) echo "selected";
		echo ">$acct_name</option>";
	}
?>
										</select>
									</div>
								</div>

								<!-- RIGHT SIDE -->
								<div class="form-group col-6 pr-2">
									<label class="control-label mb-0" for="order_notes">Internal Order Notes:</label>
									<div style="color:red;font-size:10px;float:right;margin-top:5px">[ Available to internal staff only ]</div>
									<textarea name="order_notes" id="order_notes" class="form-control" rows="3"><?php echo htmlspecialchars($order_data->order_notes) ?></textarea>
								</div>
				
							</div>
						</div>
					</div>
				</div>
				
<?php 
if ($order_data->status != 'i' && $order_data->status != 's' && $order_data->status != 'p' ) { // skip until we have a result
?>
				<!-- LAB RESULTS -->
				<div class="card mb-1">
					<div class="card-header pl-2" data-toggle="collapse" data-target="#order_results" style="font-size:1.1rem">
						<i class="fa fa-minus-square" id="icon_order_result"></i> <?php echo xlt('Reported Results'); ?>
					</div>
					<div id="order_results" class="collapse show">
						<div class="card-body p-2">
							<fieldset class="border p-2 bg-white w-100">
								<legend class="w-auto">Report Summary</legend>
									
<?php 
	if (GetSeconds($order_data->report_datetime)) { // results available
		if ($order_data->control_id) {
?>
								<div class="form-inline pb-1">
									<div class="control-label text-nowrap pr-2" style="width:130px">Accession Number:</div>
									<input type="text" class="form-control form-control-sm w-auto"  readonly value="<?php echo $order_data->control_id ?>" />
								</div>
<?php 
		}
?>
								<div class="form-inline pb-1" >
									<div class="control-label pr-2" style="width:130px">Ordered Date: </div>
									<input class="form-control form-control-sm w-auto" type='date' readonly value='<?php echo Tools::FormatDate($order_data->date_transmitted); ?>' />
									<div class="control-label pl-4 pr-2 text-right" style="width:100px">Time: </div>
									<input class="form-control form-control-sm w-auto" type='time' readonly value='<?php echo Tools::FormatTime($order_data->date_transmitted); ?>' />
								</div>
								<div class="form-inline pb-1" >
									<div class="control-label pr-2" style="width:130px">Collection Date: </div>
									<input class="form-control form-control-sm w-auto" type='date' readonly value='<?php echo Tools::FormatDate($order_data->date_collected); ?>' />
									<div class="control-label pl-4 pr-2 text-right" style="width:100px">Time: </div>
									<input class="form-control form-control-sm w-auto" type='time' readonly value='<?php echo Tools::FormatTime($order_data->date_collected); ?>' />
								</div>
								<div class="form-inline pb-1" >
									<div class="control-label pr-2" style="width:130px">Reported Date: </div>
									<input class="form-control form-control-sm w-auto" type='date' readonly value='<?php echo Tools::FormatDate($order_data->report_datetime); ?>' />
									<div class="control-label pl-4 pr-2 text-right" style="width:100px">Time: </div>
									<input class="form-control form-control-sm w-auto" type='time' readonly value='<?php echo Tools::FormatTime($order_data->report_datetime); ?>' />
									<div class="control-label pl-4 pr-2 text-right" style="width:100px">Status: </div>
									<input class="form-control form-control-sm w-auto" type='text' readonly value='<?php $status_list->showItem($order_data->status); ?>' />
								</div>
<?php 
		if ($order_data->lab_notes) { 
?>
								<div class="form-inline pb-1" >
									<div class="control-label pr-2">Lab Comments: </div>
									<textarea class="wmtInput" style="width:100%" readonly rows=2><?php echo $order_data->lab_notes ?></textarea>
								</div>
<?php 
		} 
?>
							</fieldset>
									
							<fieldset class="border p-2 bg-white w-100">
								<legend class="w-auto">Result Details</legend>
								
<?php
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
											<th scope="col">
												RESULT
											</th>
											<th scope="col">
												DESCRIPTION
											</th>
											<th scope="col">
												VALUE
											</th>
											<th scope="col">
												REFERENCE
											</th>
											<th scope="col">
												FLAG
											</th>
											<th scope="col">
												STATUS
											</th>
											<th scope="col">
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
											<td>
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
											<td colspan="6" style="padding-left:120px;text-align:left;font-family:monospace;">
												<?php echo nl2br($result_data->comments); ?>
											</td>
										</tr>
<?php 
					} // end if comments
				} else { // end if obser value
?>
											<td colspan="6" style="padding-left:120px;text-align:left;font-family:monospace">
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
		} // end foreach ordered item
?>
									</tbody>
								</table>
<?php 								
		// do we need a facility box at all?
		if (count($facility_list) > 0) {
?>
								<div class="control-label font-weight-bold">
									PROCESSING FACILITIES
								</div>			

								<table class="table table-sm w-100">
									<thead>
										<tr>
											<th scope="col">
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
											<th></th>
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
											<td>
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

	} else { // end if results
?>
								<table>
									<tr><td style="font-weight:bold">NO RESULTS HAVE BEEN RECEIVED</td></tr>
								</table>
<?php 
	} // end if results 
?>
							</fieldset>

						</div>
					</div>
				</div>
				

				<!-- RESULTS REVIEW -->
				<div class="card mb-1">
					<div class="card-header pl-2" data-toggle="collapse" data-target="#order_complete" style="font-size:1.1rem">
						<i class="fa fa-minus-square" id="icon_order_complete"></i> <?php echo xlt('Review Information'); ?>
					</div>
					<div id="order_complete" class="collapse show">
						<div class="card-body p-1">
							<div class="form-row m-0">

								<!-- LEFT SIDE -->
								<div class="col-6 pl-2">
									<div class="form-inline pb-1">
										<div class="control-label pr-2 w-25">Results Received:</div>
										<input class="form-control form-control-sm w-auto" type='date' name='resulted_date' id='date_resulted' readonly
												value='<?php echo Tools::FormatDate($order_data->received_datetime) ?>' />
									</div>
									<div class="form-inline pb-1">
										<div class="control-label text-nowrap pr-2 w-25">Reviewed By:</div>
<?php if ($order_data->reviewed_id) {
	$rrow = sqlQuery("SELECT * FROM `users` WHERE `id` = ?", array($order_data->reviewed_id));
	$reviewer = ($rrow['lname'])? $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'] : "Reviewer Missing!!";
?>
											<input type="hidden" name='reviewed_id' value="<?php echo $order_data->reviewed_id ?>" />
											<input type="text" class="form-control form-control-sm w-auto" readonly value="<?php echo $reviewer ?>"/>
<?php } else { ?>
											<select class="form-control form-control-sm form-select w-auto" name='reviewed_id' id='reviewed_id'
													onchange="$('#date_reviewed').val('<?php echo date('Y-m-d') ?>')" autocomplete="off">
												<?php User::showProvOpts($order_data->reviewed_id, '-- select --'); ?>
											</select>
<?php } ?>
									</div>
									<div class="form-inline pb-1">
										<div class="control-label text-nowrap pr-2 w-25">Reviewed Date:</div>
										<input class="form-control form-control-sm w-auto" type='date' name='reviewed_date' id='date_reviewed'
											value='<?php echo Tools::FormatDate($order_data->reviewed_datetime) ?>' />
									</div>
									<label class="control-label mb-0" for="order_notes">Internal Review Notes:</label>
									<div style="color:red;font-size:10px;float:right;margin-top:5px">[ Available to internal staff only ]</div>
									<textarea name="review_notes" id="review_notes" class="form-control" rows="3"><?php echo htmlspecialchars($order_data->review_notes) ?></textarea>
								</div>

								<!-- RIGHT SIDE -->
								<div class="col-6 pr-2">
									<div class="form-inline pb-1">
										<div class="control-label text-nowrap pr-2 w-25">Notified By:</div>
<?php if ($order_data->notified_id) {
	$rrow= sqlQuery("SELECT * FROM users WHERE id = ?",array($order_data->notified_id));
	$notifier = ($rrow['lname'])? $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'] : "Notifier Missing!!";
?>
										<input type="hidden" name='notified_id' value="<?php echo $order_data->notified_id ?>" />
										<input type="text" class="form-control form-control-sm" readonly value="<?php echo $notifier; ?>"/>
<?php } else { ?>
										<select class="form-control form-control-sm form-select" name='notified_id' id='notified_id'
												onchange="$('#date_notified').val('<?php echo date('Y-m-d'); ?>')" autocomplete="off">
											<?php User::showIdOptions($order_data->notified_id, '-- select --'); ?>
										</select>
<?php } ?>
									</div>
									<div class="form-inline pb-1">
										<div class="control-label text-nowrap pr-2 w-25">Notification Date:</div>
										<input class="form-control form-control-sm w-auto" type='date' name='notified_date' id='date_notified'
											value='<?php Tools::FormatDate($order_data->notified_datetime) ?>' />
									</div>
									<div class="form-inline pb-1">
										<div class="control-label text-nowrap pr-2 w-25">Person Contacted:</div>
										<input type='text' class="form-control form-control-sm" id='notified_person' name='notified_person'
											value="<?php echo $order_data->notified_person ?>" />
									</div>
<?php if ($GLOBALS['wmt::portal_enable'] == 'true' && $pat_data->allow_patient_portal == 'YES') {?>										
									<div class="form-inline pb-1">
										<div class="pr-2 form-check-label">Release lab results to patient portal:</div>
										<input type='checkbox' class="form-check-input" id='portal_flag' name='portal_flag' 
											value="1" <?php if ($order_data->portal_flag) echo 'checked' ?> />
									</div>
<?php } ?>
									<label class="control-label mb-0" for="order_notes">Patient Summary:</label>
									<div style="color:red;font-size:10px;float:right;margin-top:5px">[ Information provided to patient ]</div>
									<textarea name="patient_notes" id="patient_notes" class="form-control" rows="3"><?php echo htmlspecialchars($order_data->patient_notes) ?></textarea>
								</div>
							</div>
			
						</div>
					</div>
				</div>

			</div>
			
<?php } // end results ready ?>

			<!-- FOOTER BUTTONS -->
			<div class="form-row form-group clearfix">
				<div class="float-left position-override mt-2 ml-4">
					<div class="btn-group" role="group">
						<button type="button" class="btn btn-success btn-save" id="btn_save" onclick="saveClicked()">Save</button>
<?php if ($order_data->status == 'i') { ?>
						<button type="button" class="btn btn-primary btn-transmit" id="btn-xmit" onclick="submitClicked()">Submit</button>
<?php } ?>
						<button type="button" class="btn btn-secondary btn-print" data-toggle="modal" data-target="#labels_modal" name="btn_labels">Labels</button>
<?php if ($order_data->order_abn_id) { ?>
						<button type="button" class="btn btn-secondary btn-print" name="btn_abn" onclick="printPDF('<?php echo $order_data->order_abn_id ?>')">ABN Print</button>
<?php } if ($order_data->order_req_id) { ?>
						<button type="button" class="btn btn-secondary btn-print" name="btn_req" onclick="printPDF('<?php echo $order_data->order_req_id ?>')">Order Print</button>
<?php } if ($order_data->result_doc_id) { ?>
						<button type="button" class="btn btn-secondary btn-print" name="btn_doc" onclick="printPDF('<?php echo $order_data->result_doc_id ?>')">Result Print</button>
						<button type="button" class="btn btn-secondary btn-mail" name="btn_msg" >Send Message</button>
<?php } ?>
						<button type="button" class="btn btn-secondary btn-cancel" onclick="cancelClicked()">Cancel/Exit</button>
						
						
						
					</div>
					<span class="wait fa fa-cog fa-spin fa-2x ml-2 d-none"></span>
				</div>
			</form>
				
		</div>  <!-- end sections -->
	</div> <!-- end container -->

	<!-- LABELS MODAL -->
	<div class="modal fade" id="labels_modal" tabindex="-1" role="dialog" aria-labelledby="labels_modal_title" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="labels_modal_title">Print Specimen Labels</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
	      		<div class="modal-body">
					<div class="col-auto form-group mb-1">
						<label class="control-label mb-0" for="label_printer">Label Printer:</label>
						<select class="form-control form-control-sm form-select w-auto" id="label_printer" name="label_printer" autocomplete="off" >
							<?php $label_list->showOptions($_SERVER['REMOTE_ADDR'], '-- select --'); ?>
						</select>
					</div>      		
					<div class="col-auto form-group mb-1">
						<label class="control-label mb-0" for="label_printer">Number of Labels:</label>
						<select class="form-control form-control-sm form-select w-auto" id="label_count" name="label_count" autocomplete="off" >
							<?php $num_list->showOptions(false); ?>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" onclick="printLabels()">Print</button>
				</div>
			</div>
		</div>
	</div>
		
	<!-- DETAILS MODAL -->
	<div class="modal fade" id="details_modal" tabindex="-1" role="dialog" 
			data-backdrop="static" aria-labelledby="details_modal_title" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document" style="max-width:600px">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="details_modal_title">Test Details</h5>
				</div>
	      		<div id="details_output" class="modal-body overflow-auto">
	      			<div style="display:flex;justify-content:center"><div class="spinner-border mt-5"></div></div>
				</div>
				<div class="modal-footer">
					<button id="details_close" type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
		
	<!-- PROCESS MODAL -->
	<div class="modal fade" id="process_modal" tabindex="-1" role="dialog" 
			data-backdrop="static" aria-labelledby="process_modal_title" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="process_modal_title">Transmit Requisition</h5>
				</div>
	      		<div id="process_output" class="modal-body overflow-auto">
	      			<div style="display:flex;justify-content:center"><div class="spinner-border mt-5"></div></div>
				</div>
				<div class="modal-footer">
					<button id="process_print" type="button" class="btn btn-secondary" disabled>Order Print</button>
					<button id="process_close" type="button" class="btn btn-secondary" data-dismiss="modal" disabled>Close</button>
				</div>
			</div>
		</div>
	</div>
		
</body>
</html>
