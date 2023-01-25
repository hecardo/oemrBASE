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

use WMT\Classes\Options;

$here = dirname(__FILE__, 6);
require_once($here . "/globals.php");

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
$form_priority = $_REQUEST['form_priority'];
$form_billing = $_REQUEST['form_billing'];
$form_group = $_REQUEST['form_group'];

// run process flag
$process = $_REQUEST['process'];

// Load lists
$bill_list = new Options('Lab_Billing');
$status_list = new Options('Lab_Form_Status');

// Limit to orders
$status_limit = '';
foreach ($status_list->list AS $item) {
	if (substr($item['title'], 0, 5) === 'Order') {
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
	$colVars[] = array('name'=>'ordered', 'data'=>'ordered', 'title'=>'Ordered');
	$colVars[] = array('name'=>'order_number', 'data'=>'order_number', 'title'=>'Requisition');
	$colVars[] = array('name'=>'provider', 'data'=>'provider', 'title'=>'Provider');
	$colVars[] = array('name'=>'patient', 'data'=>'patient', 'title'=>'Patient');
	$colVars[] = array('name'=>'pid', 'data'=>'pid', 'title'=>'PID');
	$colVars[] = array('name'=>'pubpid', 'data'=>'pubpid', 'title'=>'PID');
	$colVars[] = array('name'=>'insurance', 'data'=>'insurance', 'title'=>'Insurance');
	$colVars[] = array('name'=>'billing', 'data'=>'billing', 'title'=>'Billing');
	$colVars[] = array('name'=>'lab_name', 'data'=>'lab_name', 'title'=>'Processor');
	$colVars[] = array('name'=>'status', 'data'=>'status', 'title'=>'Status');
	
	$colVars[] = array('name'=>'action', 'data'=>'action', 'title'=>'Form Link');
	
	// Column definitions (optional column parameters)
	$colDefs[] = array('orderable'=>false, 'visible'=>false, 'targets'=>array(0,5));
	//	$colDefs[] = array('width'=>'25%');

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
						case 'name':
							$sOrder .= "lname $scale, fname $scale, mname $scale ";
							break;
						case 'provider':
							$sOrder .= "doc_lname $scale, doc_fname $scale, doc_mname $scale ";
							break;
						case 'ordered':
							$sOrder .= "date_ordered $scale ";
							break;
						case 'billing':
							$sOrder .= "billing_type $scale ";
							break;
						default:
							$sOrder .= $sColumns[ intval($sort['column']) ]['data'] . " $scale ";
							break;
					}
				}
			}
		}
		
		// Global Search
		$sWhere = "WHERE (fo.`id` IS NOT NULL ";
		$sWhere .= "AND ( DATE(po.`date_ordered`) >= ? AND DATE(po.`date_ordered`) <= ? ) ";
		$sWhere .= "AND fo.`status` IN (" .$status_limit. ") ";
		$sBinds = array($form_start, $form_end);
		
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
			$sWhere .= "po.`provider_id` = ? ";
			$sBinds[] = $form_provider;
		}
		if ($form_status) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "fo.`status` LIKE ? ";
			$sBinds[] = $form_status;
		}
		if ($form_processor) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "po.`lab_id` = ? ";
			$sBinds[] = $form_processor;
		}
		if ($form_priority) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "po.`order_priority` LIKE ? ";
			$sBinds[] = $form_priority;
		}
		if ($form_billing) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "fo.`billing_type` LIKE ? ";
			$sBinds[] = $form_billing;
		}
		
		if ( isset($sWhere) ) $sWhere .= ") ";
		
		// Build source string
		$sFrom = "
			FROM `form_laboratory` fo
			LEFT JOIN `forms` f ON fo.`id` = f.`form_id` AND f.`formdir` LIKE 'laboratory'
			LEFT JOIN `form_encounter` fe ON fe.`encounter` = f.`encounter`
			LEFT JOIN `procedure_order` po ON po.`procedure_order_id` = fo.`order_number`
			LEFT JOIN `users` u ON u.`id` = po.`provider_id`
			LEFT JOIN `patient_data` pd ON pd.`pid` = fo.`pid`
			LEFT JOIN `procedure_providers` pp ON fo.`lab_id` = pp.`ppid`
			LEFT JOIN `insurance_data` ip ON fo.`ins_primary` = ip.`id`
			LEFT JOIN `insurance_companies` ic ON ip.`provider` = ic.`id`
		";
		
		// Total records
		$query = "SELECT COUNT(*) AS 'count' " . $sFrom . $sWhere;
		$totals = reset( sqlQuery($query, $sBinds) );

		// Build main query
		$query = "
			SELECT u.`fname` AS doc_fname, u.`mname` AS doc_mname, u.`lname` AS doc_lname, pp.`name` AS 'lab_name',
				fo.`status`, fo.`order_number`, po.`billing_type`, po.`order_priority`, fo.`facility_id`,
				po.`provider_id`, po.`date_ordered`, po.`lab_id`, pd.`pid`, po.`provider_id`, fo.`id`,
				pd.`fname` AS pat_fname, pd.`lname` AS pat_lname, pd.`mname` AS pat_mname, pd.`pubpid`, 
				ic.`name` AS 'insurance' 
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
						case 'provider':
							if ($record['provider_id']) {
								$element = $record['doc_fname'] .' ';
								if ($record['doc_mname']) $element .= $record['doc_mname'] .' ';
								$element .= $record['doc_lname'];
							} else {
								$element = 'Not Provided';
							}
							break;
						case 'ordered':
							$time = strtotime($record['date_ordered']);
							$element = date('Y-m-d', $time);
							break;
						case 'patient':
							$element = $record['pat_fname'] .' ';
							if ($record['pat_mname']) $element .= $record['pat_mname'] .' ';
							$element .= $record['pat_lname'];
							break;
						case 'billing':
							$element = $bill_list->getItem($record['billing_type']);
							break;
						case 'status':
							$element = $status_list->getItem($record['status']);
							break;
						case 'insurance':
							if ($record['insurance']) $element = $record['insurance'];
							else $element = 'No Insurance';
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

	<title>Orders Report</title>
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
			<span class="title" style="margin-left:10px">Laboratory - Pending Orders Report</span>
		</header>

		<div id="content">
			<form method='post' name='theform' id='theform' action=''>
				<input type="hidden" id="process" name="process" value="report" />
	
				<div id="report_parameters">
	
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
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Order Status:</div>
							<select class="form-control form-control-sm w-auto" name='form_status' id='form_status'>
								<option value=''>-- All --</option>
<?php 
	// Build a drop-down list of form status.
	$query = "SELECT `option_id`, `title` FROM `list_options` WHERE `list_id` = 'Lab_Form_Status' AND `title` LIKE 'Order%' ORDER BY `seq`";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$statid = $row['option_id'];
		echo "    <option value='$statid'";
		if ($statid == $form_status) echo " selected";
		echo ">" . $row['title'] . "\n";
	}
?>
							</select>
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Order Start:</div>
							<input class="form-control form-control-sm w-auto" type='date' name='start_date' id='start_date'
									value='<?php echo $form_start ?>' />
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Order End:</div>
							<input class="form-control form-control-sm w-auto" type='date' name='end_date' id='end_date'
									value='<?php echo $form_end ?>' />
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Priority:</div>
							<select class="form-control form-control-sm w-auto" name='form_priority' id='form_priority'>
								<option value=''>-- All --</option>
<?php 
	// Build a drop-down list of form handling.
	$query = "SELECT `option_id`, `title` FROM `list_options` WHERE `list_id` = 'Lab_Priority' ORDER BY `seq`";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$priority = $row['option_id'];
		echo "    <option value='$priority'";
		if ($priority == $form_priority) echo " selected";
		echo ">" . $row['title'] . "\n";
	}
?>
							</select>
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Billing:</div>
							<select class="form-control form-control-sm w-auto" name='form_billing' id='form_billing'>
								<option value=''>-- All --</option>
<?php 
	// Build a drop-down list of form billing.
	$query = "SELECT `option_id`, `title` FROM `list_options` WHERE `list_id` = 'Lab_Billing' ORDER BY `seq`";
	$res = sqlStatement($query);
	
	while ($row = sqlFetchArray($res)) {
		$billing = $row['option_id'];
		echo "    <option value='$billing'";
		if ($billing == $form_billing) echo " selected";
		echo ">" . $row['title'] . "\n";
	}
?>
							</select>
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
					"url": "lab_orders.php",
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
			$code = "<a href='#' onclick='editOrder(" +row.id+ ")'>Order Form - ";
			$code += row.order_number;
			$code += "</a>";
			return $code;
		}
		
		function editOrder(id) {
			event.preventDefault();
			event.stopPropagation();
	        var title = <?php echo xlj('Laboratory Order'); ?>;
	        
	        dlgopen('', 'lab_order', 1200, 900, '', '', {
	            allowResize: true,
	            allowDrag: true,
	            dialogId: '',
	            type: 'iframe',
	            url: '<?php echo $webroot ?>/interface/forms/laboratory/view.php?pop=1&id=' +id
	        });
			return false;
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

		