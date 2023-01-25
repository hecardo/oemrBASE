<?php
/** ***************************************************************************************
 *	REPORT_SAMPLE.PHP
 *
 *	Copyright (c)2018 - Williams Medical Technology, Inc.
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
 *	This application utilizes the capabilities and features of the "DataTables" library
 *	by SpryMedia Ltd (licensed as MIT Open Source).  Information https://datatables.net.
 *
 *  @package wmt
 *  @subpackage reports
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 *  @uses datatables  <SpryMedia Ltd>
 *  @see https://datatables.net
 *
 **************************************************************************************** */

use OpenEMR\Core\Header;

use function mdts\ToTime;
use function mdts\LogError;
use function mdts\LogException;

use mdts\objects\Laboratory;
use mdts\objects\Patient;
use mdts\objects\Insurance;
use mdts\objects\Encounter;
use mdts\objects\Order;
use mdts\objects\OrderItem;
use mdts\objects\User;

use mdts\classes\Options;

require_once("../../globals.php");
require_once($GLOBALS['srcdir']."/mdts/mdts.globals.php");

// Default page content
$colDefs = array();
$colVars = array();
$totals = array();

try { // catch any page processing errors

	// Define columns (name is identifier, data is db column, title is optional)
	$colVars[] = array('name'=>'pid', 'data'=>'pid', 'title'=>'Identifier');
	$colVars[] = array('name'=>'name', 'data'=>'name', 'title'=>'Patient Name');
	$colVars[] = array('name'=>'dob', 'data'=>'DOB', 'title'=>'Birthdate');
	$colVars[] = array('name'=>'sex', 'data'=>'sex', 'title'=>'Gender');
	
	// Column definitions (optional column parameters)
//	$colDefs[] = array('width'=>'25%');
//	$colDefs[] = array('orderable'=>false, 'targets'=>array(0));

	// process the ajax request	
	if ($_REQUEST['process'] == 'report') {

		// Columns
		$sColumns = array();
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
					if ($field == 'name') {
						$sOrder .= "lname $scale, fname $scale, mname $scale ";
					} else {
						$sOrder .= $sColumns[ intval($sort['column']) ]['data'] . " $scale ";
					}
				}
			}
		}
		
		// Global Search
		$sWhere = "";
		$sBinds = array();
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
			
			// Process specials
			foreach ($sFilter AS $key => $value) {
				$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
				if ($key == 'dob_start') $sWhere .= "`DOB` >= ? ";
				if ($key == 'dob_end') $sWhere .= "`DOB` <= ? ";
				$sBinds[] = $value;
			}

			if ( isset($sWhere) ) $sWhere .= ") ";
		}
		
		// Total records
		$total = sqlQuery("SELECT COUNT(`id`) AS `count` FROM `patient_data`");
		
		// Filtered records
		$filtered = sqlQuery("SELECT COUNT(`id`) AS `count` FROM `patient_data` $sWhere", $sBinds);
		
		// Build main query
		$sql = "SELECT * FROM `patient_data` ";
		$sql .= $sWhere . $sOrder . $sLimit;
		$result = sqlStatement($sql, $sBinds);
		
		// Create output
		$output = array(
				"draw" => intval($_REQUEST['draw']),
				"recordsFiltered" => $filtered['count'],
				"recordsTotal" => $total['count'],
				"data" => array()
		);
		
		// Collect results as rows
		$rows = array();
		if ($result) {
			while ($record = sqlFetchArray($result)) {
				foreach ($sColumns AS $column) {
					if ($column['data'] == "name") {
						$name = $record['lname'] . ", " . $record['fname'];
						if (!empty($record['mname'])) $name .= " " . $record['mname'];
						$row['name'] = $name;
					} else {
						$row[$column['data']] = $record[$column['data']];
					}
				}

				$output['data'][] = $row;
			}
		}

		echo json_encode( $output );
		
		return; // that's it for ajax request
	}
}
catch (Exception $e) { // fatal error processing page
	wmt\LogException($e);
	exit();
}
?><!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
	<?php Header::setupHeader(); ?>

	<title>Test Report</title>
	<meta name="author" content="Ron Criswell" />
	<meta name="description" content="Report Description" />
	<meta name="keywords" content="Report Keywords" />
	<meta name="copyright" content="&copy;<?php echo date('Y') ?> Williams Medical Technologies, Inc.  All rights reserved." />

	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-dt/css/jquery.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-Buttons/css/buttons.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-dt/css/colReorder.dataTables.min.css" type="text/css">

	<style>
		.ui-widget { font-size: 0.7em; }
		
		#container table.dataTable td {
			padding: 5px;
			line-height: 15px;
		}
		#container table.dataTable thead th {
			text-align: left;
			padding-left: 4px;
		}

		#report_inputs { float:left;padding:8px;border-radius:8px;border:solid var(--gray300) 1px;box-shadow:2px 2px 2px var(--light); }
		#report_inputs div { float:left }
		#report_buttons { float:left;margin:4px 30px; }
		
		button.dt-button { color: black !important;font-size:12px;height:23px;padding:0 1em;border-radius:5px; }
		
		.dataTables_wrapper .dataTables_processing { z-index:100 }	
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
			<span class="title" style="margin-left:10px">Referrals - Outbound Order Workflow</span>
		</header>

		<div id="content">
			<form method='post' name='theform' id='theform' action=''>
				<input type="hidden" id="process" name="process" value="report" />
	
				<div id="report_parameters" style="b:1px solid; b-radius:5px">
	
					<!-- REPORT PARAMETERS -->
					<div id="report_inputs">
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">Start Date:</div>
							<input class="form-control form-control-sm w-auto" type='date' name='start_date' id='start_date'
									value='<?php echo $start_date ?>' />
						</div>
						<div class="form-inline mr-4">
							<div class="control-label text-nowrap pr-2">End Date:</div>
							<input class="form-control form-control-sm w-auto" type='date' name='start_date' id='start_date'
									value='<?php echo $end_date ?>' />
						</div>
					</div>

					<!-- REPORT BUTTON -->
					<div id="report_buttons" style="text-align:center">
						<button type="submit" class="btn btn-primary" id="btn_report">Run Report</button>
					</div>

				</div>
			</form>
		</div>

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
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-Buttons/js/dataTables.buttons.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-Buttons/js/buttons.flash.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-Buttons/js/buttons.html5.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-Buttons/js/buttons.print.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-Buttons/js/jszip.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/pdfmake/build/pdfmake.min.js"></script>
	<script src="<?php echo $GLOBALS['assets_static_relative']; ?>/pdfmake/build/vfs_fonts.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-ui/jquery-ui.min.js"></script>

	<script>
		var colDefs = <?php echo json_encode($colDefs) ?>;
		var colVars = <?php echo json_encode($colVars) ?>;

		var firstTime = true;

		$(document).ready(function() {
			$(".datepicker").datepicker({
				dateFormat: "yy-mm-dd"
			});

			$(document).ajaxError(function(event, request, settings) {
				$('#dynamic').css('visibility', 'visible');
				$('#dynamic').html('<div>'+request.responseText+'</div>');
			});

			var buttonCommon = {};
			
			var report = $('#report').DataTable({
				"language":			{"infoFiltered": ""},
				"pageLength":		30,
				"paging":			true,
				"processing":		true,
				"autoWidth":		true,
				"serverSide":		true,
				"deferLoading":		true,
				"lengthChange":		false,
				"searching":		false,
				"scrollCollapse":	true,
				"scrollY":			"65vh",
				
				"columnDefs":		colDefs,
				"columns":			colVars,

				"dom":				"Bfrtip",

				"ajax": {
					"url": "report_sample.php",
					"data": function( params ) {
						$(':input').each(function() {
							if ($(this).val() != '') {
								sName = $(this).attr('name');
								sValue = $(this).val();
								params[sName] = sValue;
							}
						});
					}
				},

				
				"buttons": [
					{ "extend": "copyHtml5", "text": "copy" }, 
					{ "extend": "excelHtml5", "text": "excel" }, 
					{ "extend": "pdfHtml5", "text": "pdf" }, 
					{ "extend": "print", "text": "print" } 
				]
			});
			
			$('#btn_report').click(function(event) {
				event.preventDefault();
				$('#process').val('report');
				report.draw();
				$('#dynamic').css('visibility', 'visible');
			});

		}); // end of READY function
		
	</script>

</body>
</html>