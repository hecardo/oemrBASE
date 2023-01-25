<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\Header;

use OpenEMR\Common\Logging\SystemLogger;

use WMT\Classes\Tools;
use WMT\Classes\Options;

require_once("../../../../../globals.php");

// Set defaults
$form_start = date('Y-m-d', strtotime('now - 1 month'));
if (!empty($_POST['start_date']) && strtotime($_POST['start_date']) !== false) {
	$form_start = date('Y-m-d', strtotime($_POST['start_date']));
}
$form_end = date('Y-m-d', strtotime('now'));
if (!empty($_POST['end_date']) && strtotime($_POST['end_date']) !== false) {
	$form_end = date('Y-m-d', strtotime($_POST['end_date']));
}

// get remaining report parameters
$form_provider = $_REQUEST['form_provider'];
$form_facility = $_REQUEST['form_facility'];
$form_status = $_REQUEST['form_status'];
$form_processor = $_REQUEST['form_processor'];
$form_handling = $_REQUEST['form_handling'];
$form_billing = $_REQUEST['form_billing'];
$form_group = $_REQUEST['form_group'];
$form_active = $_REQUEST['form_active'];
$form_ignore = $_REQUEST['ignore'];

// run process flag
$process = $_REQUEST['process'];

// hide a result
if ($form_ignore) {
	$key = $form_ignore;
	
	if ($key) {
		sqlStatement("UPDATE `form_orphans` SET `pid` = 2 WHERE `id` = ?", array($key));
	}
	$form_ignore = '';
}

// Load lists
$bill_list = new Options('Lab_Billing');
$status_list = new Options('Lab_Form_Status');

// Limit to orders
$status_limit = '';
foreach ($status_list->list AS $item) {
	if (substr($item['title'], 0, 6) === 'Result') {
		if ($status_limit) $status_limit .= ",";
		$status_limit .= "'" .$item['option_id']. "'";
	}
}

// Default page content
$colDefs = array();
$colVars = array();
$totals = array();

try { // catch any page processing errors

	// Define columns (name is identifier, data is db column, title is optional)
	$colVars[] = array('name'=>'id', 'data'=>'id', 'title'=>'Id');
	$colVars[] = array('name'=>'docid', 'data'=>'docid', 'title'=>'Result PDF');
	$colVars[] = array('name'=>'ordered', 'data'=>'ordered', 'title'=>'Ordered');
	$colVars[] = array('name'=>'reported', 'data'=>'reported', 'title'=>'Reported');
	$colVars[] = array('name'=>'lab_name', 'data'=>'lab_name', 'title'=>'Processor');
	$colVars[] = array('name'=>'control_id', 'data'=>'control_id', 'title'=>'Requisition');
	$colVars[] = array('name'=>'doc_npi', 'data'=>'doc_npi', 'title'=>'Provider NPI');
	$colVars[] = array('name'=>'provider', 'data'=>'provider', 'title'=>'Provider');
	$colVars[] = array('name'=>'patient', 'data'=>'patient', 'title'=>'Patient');
	$colVars[] = array('name'=>'pat_DOB', 'data'=>'pat_DOB', 'title'=>'DOB');
	$colVars[] = array('name'=>'pat_sex', 'data'=>'pat_sex', 'title'=>'Sex');
	$colVars[] = array('name'=>'active', 'data'=>'active', 'title'=>'Active');
	
	$colVars[] = array('name'=>'action', 'data'=>'action', 'title'=>'Actions');
	
	// Column definitions (optional column parameters)
	$colDefs[] = array('orderable'=>false, 'visible'=>false, 'targets'=>array(0,1));
	$colDefs[] = array('orderable'=>false, 'targets'=>array(11,12));
	
	// process the ajax request
	if ($process == 'report' || $process == 'excel') {

		// Columns
		$sColumns = $colVars; // default to everything
		if ( isset($_REQUEST['columns']) ) $sColumns = $_REQUEST['columns'];

		// Paging
		$sLimit = "";
		if ( isset($_REQUEST['start']) && $_REQUEST['length'] != '-1' ) {
			$sLimit = "LIMIT ".intval($_REQUEST['start']).", ".intval($_REQUEST['length']);
		}

		// Ordering
		$sOrder = "";
		if ( isset( $_REQUEST['order'] ) ) {
			foreach ( $_REQUEST['order'] AS $sort ) {
				if ( isset($sort['column']) ) {
					$sOrder .= ( empty($sOrder) ) ? "ORDER BY " : ", ";
					
					$field = $sColumns[ intval($sort['column']) ]['data'];
					$scale = ($sort['dir'] == 'asc') ? "ASC " : "DESC ";
					
					switch ($field) {
						case 'id':
							$sOrder .= "fo.id $scale ";
							break;
						case 'patient':
							$sOrder .= "pat_lname $scale, pat_fname $scale, pat_mname $scale ";
							break;
						case 'provider':
							$sOrder .= "doc_lname $scale, doc_fname $scale, doc_mname $scale ";
							break;
						case 'ordered':
							$sOrder .= "date_ordered $scale ";
							break;
						case 'reported':
							$sOrder .= "report_datetime $scale ";
							break;
						default:
							$sOrder .= $sColumns[ intval($sort['column']) ]['data'] . " $scale ";
							break;
					}
				}
			}
		}
		
		// Global Search
		$sWhere = "WHERE (fo.`pid` < '3' ";
		
		if ( isset($_REQUEST['search']) && $_REQUEST['search']['value'] != "" ) {
			foreach ( $sColumns AS $column ) {
				if ( $column['searchable'] == "true" ) {
					$sWhere .= ( empty($sWhere) ) ? "WHERE ( `" : "AND `";
					$sWhere .= $column['data'] . "` LIKE ? ";
					$sBinds[] = "%" . $_REQUEST['search']['value'] . "%";
				}
			}
			if ( isset($sWhere) ) $sWhere .= ") ";
		}
		
		// Filters
		$sFilter = array();
		if ( isset($_REQUEST['filter']) ) {
			
			// Collect the filters
			foreach ( $_REQUEST['filter'] AS $key => $value) {
				if ( isset($key) && isset($value) )
					$sFilter[$key] = $value;
			}
			
			// Apply to columns
			foreach ( $sColumns AS $column ) {
				if ( isset($sFilter[$column['name']]) ) {
					$sWhere .= ( empty($sWhere) ) ? "WHERE ( `" : "AND `";
					$sWhere .= $column['data'] . "` LIKE ? ";
					$sBinds[] = $sFilter[$column['name']];
					unset($sFilter[$column['name']]); // remove from list
				}
			}
		}

		// Process specials
		if ($form_facility) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "fo.`facility_id` = ? ";
			$sBinds[] = $form_facility;
		}
		if ($form_provider) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "u.`id` = ? ";
			$sBinds[] = $form_provider;
		}
		if ($form_processor) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "pp.`ppid` = ? ";
			$sBinds[] = $form_processor;
		}
		if (!$form_active) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "fo.`pid` = '1' ";
		}
		
		if ( isset($sWhere) ) $sWhere .= ") ";
		
		// Build source string
		$sFrom = "
			FROM `form_orphans` fo
			LEFT JOIN `procedure_providers` pp ON fo.`lab_id` = pp.`ppid`
			LEFT JOIN `users` u ON u.`npi` LIKE fo.`doc_npi` AND u.`lname` LIKE fo.`doc_lname`
		";
		
		// Total records
		$query = "SELECT COUNT(*) AS 'count' " . $sFrom . $sWhere;
		$totals = reset( sqlQuery($query, $sBinds) );

		// Build main query
		$query = "
			SELECT fo.id, fo.pid, fo.pat_lname, fo.pat_mname, fo.pat_fname, fo.pat_DOB, fo.pat_sex, fo.order_number, 
				fo.date_ordered, fo.doc_lname, fo.doc_fname, fo.doc_mname, fo.doc_npi, fo.facility_id, 
				pp.name AS lab_name, fo.control_id, fo.report_datetime, fo.result_doc_id AS docid
		";
		
		$query .= $sFrom . $sWhere . $sOrder . $sLimit;
		$result = sqlStatement($query, $sBinds);
		
		// Collect results as rows
		$rows = array();
		if ($result) {
			while ($record = sqlFetchArray($result)) {
				$row = array();
				foreach ($sColumns AS $column) {
					$element = $record[$column['data']];
					switch ($column['name']) {
						case 'patient':
							$element = $record['pat_fname'] .' ';
							if ($record['pat_mname']) $element .= $record['pat_mname'] .' ';
							$element .= $record['pat_lname'];
							break;
						case 'pat_DOB':
							$element = Tools::FormatDate($record['pat_DOB']);
							break;
						case 'pat_sex':
							$element = 'UNKNOWN';
							if (strtoupper($record['pat_sex']) == 'M') $element = 'Male';
							if (strtoupper($record['pat_sex']) == 'F') $element = 'Female';
							break;
						case 'provider':
							$element = $record['doc_fname'] .' ';
							if ($record['doc_mname']) $element .= $record['doc_mname'] .' ';
							$element .= $record['doc_lname'];
							break;
						case 'ordered':
							$element = Tools::FormatDate($record['date_ordered']);
							break;
						case 'reported':
							$element = Tools::FormatDate($record['report_datetime']);
							break;
						case 'active':
							$element = ($record['pid'] == '1')? 'Active' : 'Inactive';
							break;
					}
					$row[$column['data']] = $element;
				}

				// Save the output row
				if ($row) $rows[] = $row;
			}
		}
		
		// Limit output
		$filtered = count($rows);
		$start = intval($_REQUEST['start']);
		$length = intval($_REQUEST['length']);
		$draw = intval($_REQUEST['draw']);

		if (!$draw) $draw = 1;
		if (!$length) $length = $filtered;
		$limited = array_slice( $rows, $start, $length );

		// Create output
		$output = array(
			"draw" => $draw,
			"recordsTotal" => $totals['count'],
			"recordsFiltered" => $filtered,
			"data" => $limited
		);
			
		echo json_encode( $output );
		
		return; // that's it for ajax request
	}
}
catch (Exception $e) { // fatal error processing page
	$logger = new SystemLogger();
	$msg = $e->getMessage();
	$logger->error($msg);
	die($msg);
}

?><!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
	<?php Header::setupHeader(); ?>

	<title>Orphan Results Report</title>
	<meta name="author" content="Ron Criswell" />
	<meta name="description" content="Report Description" />
	<meta name="keywords" content="Report Keywords" />
	<meta name="copyright" content="&copy;<?php echo date('Y') ?> Williams Medical Technologies, Inc.  All rights reserved." />

	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-dt/css/jquery.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/css/buttons.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-dt/css/colReorder.dataTables.min.css" type="text/css">

	<style>
		.ui-widget { font-size: 0.7em; }
		
		#container table.dataTable {
			font-size: 14px;
		}
		#container table.dataTable td {
			padding: 5px;
			line-height: 15px;
		}
		#container table.dataTable thead th {
			text-align: left;
			padding-left: 4px;
		}

		#report_inputs { float:left;padding:3px 8px;border-radius:8px;border:solid var(--gray300) 1px;box-shadow:2px 2px 2px var(--light);max-width:85%;margin-right:20px; }
		#report_inputs div { float:left;margin: 2px 10px 2px 0; }
		#report_buttons { float:left;margin:4px 0; }
		
		button.dt-button { color: black !important;font-size:12px;height:23px;padding:0 1em;border-radius:5px; }
		
		.dataTables_wrapper .dataTables_processing { z-index:100;margin-top:0;padding:0;color:var(--primary);font-weight:bold; }	
		.dataTable_wrapper div.container { width:100% }
	</style>
	
	<!-- Set system variables in local scope -->
	<script>
		var currentMonth = "<?php echo ltrim(date('m'), '0') ?>";
		var currentYear = "<?php echo date('Y') ?>";
		var errorMessage = "<?php echo $errorMessage ?>";
		
		var alertMessage = "<?php echo $alertMessage ?>";
		if (alertMessage != "") alert(alertMessage);
	</script>

</head>

<body class="body_top">
	<div id="container">

		<header>
			<!-- HEADER (if desired) -->
			<span class="title" style="margin-left:10px">Laboratory - Orphan Results Report</span>
		</header>

		<div id="content">
			<form method='post' name='theform' id='theform' action=''>
				<input type="hidden" id="process" name="process" value="report" />
				<input type="hidden" id="ignore" name="ignore" value="" />
	
				<div id="report_parameters" style="b:1px solid; b-radius:5px">
	
					<!-- REPORT PARAMETERS -->
					<div id="report_inputs">
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Facility:</div>
							<select class="form-control form-control-sm w-auto" name='form_facility' id='form_facility'>
								<option value=''>-- All Facilities --</option>
<?php 
	// Build a drop-down list of facilities.
	$query = "SELECT `id`, `name` FROM `facility` ORDER BY `name`";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$facid = $row['id'];
		echo "    <option value='$facid'";
		if ($facid == $form_facility)
			echo " selected";
		echo ">" . $row['name'] . "\n";
	}
?>
							</select>
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Provider:</div>
							<select class="form-control form-control-sm w-auto" name='form_provider' id='form_provider'>
								<option value=''>-- All Providers --</option>
<?php 
	// Build a drop-down list of providers.
	$query = "SELECT `id`, `username`, `lname`, `fname`, `mname` FROM `users` ";
	$query .= "WHERE `id` IN (SELECT DISTINCT(`provider_id`) FROM `procedure_order`) ";
	$query .= "ORDER BY `lname`, `fname` ";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$provid = $row['id'];
		echo "    <option value='$provid'";
		if ($provid == $form_provider) echo " selected";
		echo ">" . $row['fname'] ." ". $row['mname'] ." ". $row['lname'] ."\n";
	}
?>
							</select>
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Processor:</div>
							<select class="form-control form-control-sm w-auto" name='form_processor' id='form_processor'>
								<option value=''>-- All --</option>
<?php 
	// Build a drop-down list of processor names.
	$query = "SELECT `ppid`, `name` FROM `procedure_providers` ";
	$query .= "WHERE `type` NOT LIKE 'quick%' ORDER BY `name`";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$ppid = $row['ppid'];
		echo "    <option value='$ppid'";
		if ($ppid == $form_processor) echo " selected";
		echo ">" . $row['name'] . "\n";
	}
?>
							</select>
						</div>
						<div class="input-group form-inline w-auto">
							<div class="form-inline mr-4">
								<div class="control-label text-nowrap pr-2">Result Start:</div>
								<input class="form-control form-control-sm w-auto" type='date' name='start_date' id='start_date'
										value='<?php echo $form_start ?>' />
							</div>
							<div class="form-inline mr-4">
								<div class="control-label text-nowrap pr-2">Result End:</div>
								<input class="form-control form-control-sm w-auto" type='date' name='end_date' id='end_date'
										value='<?php echo $form_end ?>' />
							</div>
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Include Hidden:</div>
							<input type='checkbox' class="form-check-input" id='form_active' name='form_active' 
										value="1" <?php if ($form_detail) echo "checked" ?> />
						</div>

					</div>

					<!-- REPORT BUTTON -->
					<div id="report_buttons" style="text-align:center">
						<button type="submit" class="btn btn-primary" id="btn_report">Run Report</button>
					</div>

				</div>
			</form>

			<div id="dynamic" style="visibility:hidden">

				<!-- GENERATED OUTPUT -->
				<table style="width:100%" class="display" id="report">
					<thead>  </thead>
					<tbody>  </tbody>
					<tfoot>  </tfoot>
				</table>

			</div>
		</div>

		<footer>
			<!-- FOOTER (if desired) -->
		</footer>

	</div> <!-- end of #container -->

	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net/js/jquery.dataTables.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder/js/dataTables.colReorder.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/js/buttons.flash.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/js/buttons.html5.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/js/buttons.print.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/js/jszip.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/pdfmake/build/pdfmake.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/pdfmake/build/vfs_fonts.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-ui/jquery-ui.min.js"></script>

	<script>
		var colVars = <?php echo json_encode($colVars) ?>;
		var colDefs = <?php echo json_encode($colDefs) ?>;
		colDefs.push( {targets: <?php echo count($colVars) -1 ?>, render: makeButtons} );
		
		$(document).ready(function() {
			$(document).ajaxError(function(event, request, settings) {
				$('#dynamic').css('visibility', 'visible');
				$('#dynamic').html('<div>'+request.responseText+'</div>');
			});

			var buttonCommon = {};

			var report = $('#report').DataTable({
				"language":			{"infoFiltered": ""},
				"destroy":			true,
				"pageLength":		25,
				"paging":			true,
				"processing":		true,
				"autoWidth":		true,
				"serverSide":		true,
				"deferLoading":		true,
				"lengthChange":		false,
				"searching":		false,
				"scrollCollapse":	true,
				"stateSave":		true,
				"":					[], // prevent initial sort

				"columns":			colVars,
				"columnDefs":		colDefs,

				"dom":				"Bfrtip",

				"ajax": {
					"type": "POST",
					"url": "lab_orphans.php",
					"data": function( params ) {
						$(':input:not(:checkbox):not(:button)').each(function() {
							if ($(this).val() != '') {
								sName = $(this).attr('name');
								sValue = $(this).val();
								params[sName] = sValue;
							}
						});
						$(':input:checked').each(function() {
							if ($(this).val() != '') {
								sName = $(this).attr('name');
								sValue = $(this).val();
								params[sName] = sValue;
							}
						});
					}
				},
				"buttons": [
					{
						"text": "download",
						"action": function ( e, dt, node, config ) {
							$('#process').val('excel');
							$('#theform').submit();
						}
					}
				]
			});

			$('#btn_report').click(function(event) {
				event.preventDefault();
				$('#process').val('report');
				$('#report_processing').css('visibility', 'visible');
				report.draw();
			});

			report.on('draw', function() {
				$('#report_processing').css('visibility', 'hidden');
				$('#dynamic').css('visibility', 'visible');
			})

		}); // end of READY function

		function makeButtons(data, type, row, meta) {
			$actions = "<div class='form-group form-inline text-nowrap' style='min-width:140px'>";
			if (row.docid != null) {
				$actions += "<button type='button' class='btn btn-secondary btn-sm mr-1' onclick='viewOrder(" +row.pid+ "," +row.docid+ ")'>view</button>\n";
			}
			$actions += "<button type='button' class='btn btn-secondary btn-sm mr-1' onclick='linkOrder(" +row.id+ ")'>link</button>\n";
			$actions += "<button type='button' class='btn btn-secondary btn-sm' onclick='hideOrder(" +row.id+ ")'>hide</button>\n";
			$actions += "</div>";
			
			return $actions;
		}
		
		function viewOrder(pid, docid) {
			event.preventDefault();
			location.href="<?php echo $web_root; ?>/controller.php?document&retrieve&patient_id="+pid+"&document_id=" + docid;
			return false;
		}

		function hideOrder(id) {
			$('#ignore').val(id);
			$('#process').val('');
			$('#theform').submit();
		}

		function linkOrder(id) {
			event.preventDefault();
			event.stopPropagation();
	        var title = <?php echo xlj('Link Orphan'); ?>;
		        
	        dlgopen('', 'link_orphan', 900, 700, '', '', {
	            allowResize: true,
	            allowDrag: true,
	            dialogId: '',
	            type: 'iframe',
	            onClosed: 'refreshReport',
	            url: '<?php echo $web_root; ?>/interface/forms/laboratory/link_search.php?id=' +id
	        });

		}

		function refreshReport() {
			$('#process').val('report');
			$('#btn_report').trigger('click');
		}

		function refreshPage() {
			$('#process').val('');
			$('#theform').submit();
		}			

		</script>

</body>
</html>

