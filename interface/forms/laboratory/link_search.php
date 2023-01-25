<?php
/** *************************************************************************************
 *	LABORATORY/LINK_SEARCH.PHP
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
use function mdts\LogError;
use function mdts\LogException;

use mdts\objects\User;
use mdts\objects\Patient;
use mdts\objects\LabOrphan;

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

// Process initial ajax request
$process = $_REQUEST['process'];
if ($process == 'report') {
	// Retrieve filter data		
	$form_id = $_REQUEST['id'];
	$form_lname = $_REQUEST['lname'];
	$form_fname = $_REQUEST['fname'];
	$form_sex = $_REQUEST['sex'];
	$form_ss = $_REQUEST['ss'];
	$form_dob = $_REQUEST['dob'];
} else {
	// Retrieve orphan order
	$form_id = $_REQUEST['id'];
	$orphan_data = new LabOrphan($form_id);
	$form_lname = $orphan_data->pat_lname;
	$form_fname = $orphan_data->pat_fname;
	$form_sex = $orphan_data->pat_sex;
	$form_ss = $orphan_data->pat_ss;
	$form_dob = $orphan_data->pat_DOB;
}

$info_msg = "";

// Default page content
$colDefs = array();
$colVars = array();
$totals = array();

try { // catch any page processing errors
	// Define columns (name is identifier, data is db column, title is optional)
	$colVars[] = array('name'=>'pid', 'data'=>'pid', 'title'=>'PID');
	$colVars[] = array('name'=>'lname', 'data'=>'lname', 'title'=>'Last Name');
	$colVars[] = array('name'=>'fname', 'data'=>'fname', 'title'=>'First Name');
	$colVars[] = array('name'=>'sex', 'data'=>'sex', 'title'=>'Gender');
	$colVars[] = array('name'=>'ss', 'data'=>'ss', 'title'=>'Social Security');
	$colVars[] = array('name'=>'DOB', 'data'=>'DOB', 'title'=>'Date of Birth');
	$colVars[] = array('name'=>'pubpid', 'data'=>'pubpid', 'title'=>'PID');
	
	$colVars[] = array('name'=>'action', 'data'=>'action', 'title'=>'Action');
	
	// Column definitions (optional column parameters)
	$colDefs[] = array('orderable'=>false, 'visible'=>false, 'targets'=>array(0));
	$colDefs[] = array('orderable'=>false, 'targets'=>array(7));

	// rocess report ajax request
	if ($process == 'report' || $process == 'initial') {

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
					
					$sOrder .= $sColumns[ intval($sort['column']) ]['data'] . " $scale ";
				}
			}
		}
		
		// Global Search
		$sWhere = '';
		
		if ( isset($_REQUEST['search']) && $_REQUEST['search']['value'] != "" ) {
			foreach ( $sColumns AS $column ) {
				if ( $column['searchable'] == "true" ) {
					$sWhere .= ( empty($sWhere) ) ? "WHERE ( `" : "AND `";
					$sWhere .= $column['data'] . "` LIKE ? ";
					$sBinds[] = "%" . $_REQUEST['search']['value'] . "%";
				}
			}
			if ( !empty($sWhere) ) $sWhere .= ") ";
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
		if ($form_fname) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "`fname` LIKE ? ";
			$sBinds[] = $form_fname;
		}
		
		if ($form_lname) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "`lname` LIKE ? ";
			$sBinds[] = $form_lname;
		}
		
		if ($form_sex) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "`sex` LIKE ? ";
			$sBinds[] = $form_sex;
		}
		
		if ($form_ss) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "`ss` LIKE ? ";
			$sBinds[] = $form_ss;
		}
		
		if ($form_dob) {
			$sWhere .= ( empty($sWhere) ) ? "WHERE ( " : "AND ";
			$sWhere .= "DATE(`DOB`) = ? ";
			$sBinds[] = $form_dob;
		}
		
		if ( !empty($sWhere) ) $sWhere .= ") ";
		
		// Build source string
		$sFrom = "
			FROM `patient_data`
		";
				
		// Total records
		$query = "SELECT COUNT(*) AS 'count' " . $sFrom . $sWhere;
		$totals = reset( sqlQuery($query, $sBinds) );

		// Build main query
		$query = "
			SELECT `pid`, `fname`, `mname`, `lname`, `sex`, `DOB`, `ss`, `pubpid` 
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
						case 'status':
							$element = ($record['pid'] == '999999999')? 'Active' : 'Inactive';
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
	wmt\LogException($e);
	exit();
}
?>
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
	<?php Header::setupHeader(); ?>

	<title>Orphan Patient Search</title>
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
			width: 99%;
		}
		#container table.dataTable td {
			padding: 5px;
			line-height: 15px;
		}
		#container table.dataTable thead th {
			text-align: left;
			padding-left: 4px;
		}

		#container table.dataTable thead tr.filters th {
			border-bottom: none !important;
			padding-bottom: 0 !important;
		}
		
		button.dt-button { color: black !important;font-size:12px;height:23px;padding:0 1em;border-radius:5px; }
		
		.dataTables_wrapper .dataTables_processing { z-index:100;top:84px;margin-top:0;padding:0;color:var(--primary);font-weight:bold; }	
//		.dataTable_wrapper div.container { width:100% }
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
			<span class="title" style="margin-left:10px">Laboratory - Orphan Patient Search</span>
		</header>

		<div id="content">
			<form method='post' name='theform' id='theform' action=''>
				<input type="hidden" id="process" name="process" value="initial" />
				<input type="hidden" id="id" name="id" value="<?php echo $form_id ?>" />
			</form>

			<div id="dynamic" style="visibility:hidden">

				<!-- GENERATED OUTPUT -->
				<table class="display" id="report">
					<thead>
						<tr class='filters'></tr>
					</thead>
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
//				"fixedHeader":		true,
				"destroy":			true,
				"pageLength":		25,
				"paging":			true,
				"processing":		true,
				"autoWidth":		true,
				"serverSide":		true,
//				"deferLoading":		true,
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
					"url": "link_search.php",
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

			    "buttons": []
				
			});

		    // Add filters
			var sex_filter = '<th><select class="form-control form-control-sm bg-white" name="sex">';
			sex_filter += '<option value="Male" <?php if ($form_sex == 'Male') echo 'selected' ?>>Male</option>';					
			sex_filter += '<option value="Female" <?php if ($form_sex == 'Female') echo 'selected' ?>>Female</option>';
			sex_filter += '</select></th>';
		    $('.filters').append('<th><input type="text" class="form-control form-control-sm" name="lname" value="<?php echo $form_lname ?>"/></th>');
		    $('.filters').append('<th><input type="text" class="form-control form-control-sm" name="fname" value="<?php echo $form_fname ?>"/></th>');
		    $('.filters').append(sex_filter);
		    $('.filters').append('<th><input type="text" class="form-control form-control-sm" name="ss" value="<?php echo $form_ss ?>"/></th>');
		    $('.filters').append('<th><input type="date" class="form-control form-control-sm" name="dob" value="<?php echo $form_dob ?>"/></th>');
			$('.filters').append('<th></th>');
			$('.filters').append('<th><button type="submit" class="btn btn-primary btn-sm" id="btn_report">Refresh</button></th>');

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

			refreshReport();
			
		}); // end of READY function

		function makeButtons(data, type, row, meta) {
			var actions = "<button type='button' class='btn btn-secondary btn-sm mr-1' onclick='linkOrder(" +row.pid+ ")'>link</button>\n";
			return actions;
		}
		
		function linkOrder(pid) {
			event.preventDefault();
			var id = $('#id').val();
			if (id == '' || typeof(id) == 'undefined') {
				alert ("Missing order identification, please contact support!!");
			} else if (pid == '' || typeof(pid) == 'undefined') {
				alert ("Missing patient identification, please contact support!!");
			} else {
				location.href="link_process.php?id="+id+"&pid=" + pid;
			}
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




















