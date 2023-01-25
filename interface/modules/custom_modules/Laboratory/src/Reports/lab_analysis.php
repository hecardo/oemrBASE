<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\Header;

use WMT\Classes\Tools;
use WMT\Classes\Options;

require_once(dirname(__FILE__, 6)."/globals.php");

// is this the printable HTML-option?
$printable = $_REQUEST['print'];
$popup = $_REQUEST['popup'];

// date parameters
$from_date	= $_REQUEST['form_from_date'];
if (!$from_date || strtotime($from_date) === false) $from_date = "";
$to_date  	= $_REQUEST['form_to_date'];
if (!$to_date || strtotime($to_date) === false) $to_date = "";
$mode = $_REQUEST['mode'];
if (!$mode) $mode = 'list';
$state = ($_POST['state'])? $_POST['state'] : 'Toggle On';
$value_select = $_POST['value_code']; // what items are checkedboxed?
if (empty($value_select)) $state = 'Toggle On';

// main db-spell
//----------------------------------------
$main_spell  = "SELECT procedure_result.procedure_result_id, procedure_result.result, procedure_result.result_text,  procedure_result.result_code, procedure_result.result_data_type, procedure_result.units, procedure_result.abnormal, procedure_result.range, ";
$main_spell .= "procedure_result.date AS result_date, procedure_result.result_status, procedure_report.date_collected, procedure_report.review_status, ";
$main_spell .= "form_encounter.encounter AS encounter_id, form_encounter.date AS encounter_date, procedure_order.procedure_order_id AS order_number ";
$main_spell .= "FROM procedure_result ";
$main_spell .= "JOIN procedure_report ";
$main_spell .= "	ON procedure_result.procedure_report_id = procedure_report.procedure_report_id ";
$main_spell .= "JOIN procedure_order ";
$main_spell .= "	ON procedure_report.procedure_order_id = procedure_order.procedure_order_id ";
//$main_spell .= "JOIN procedure_providers ";
//$main_spell .= "	ON procedure_providers.ppid = procedure_order.lab_id ";
$main_spell .= "JOIN form_encounter ";
$main_spell .= "    ON form_encounter.encounter = procedure_order.encounter_id ";
$main_spell .= "WHERE procedure_result.result_code = ? "; 
$main_spell .= "AND procedure_order.patient_id = ? ";
$main_spell .= "AND procedure_result.result IS NOT NULL ";
$main_spell .= "AND procedure_result.result != '' ";
//$main_spell .= "AND ( procedure_result.result_data_type = 'NM' OR procedure_result.result_data_type = 'SN' ) ";
$main_spell .= "AND procedure_result.result REGEXP '^[0-9]*[.]{0,1}[0-9]*$' ";
$main_spell .= "AND procedure_order.date_ordered >= ? AND procedure_order.date_ordered <= ? ";
$main_spell .= "ORDER BY procedure_order.date_ordered DESC ";
//----------------------------------------

// some styles and javascripts
// ####################################################
echo "<html><head>";
?><!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
	<?php Header::setupHeader(); ?>

	<title>Orders Report</title>

<script	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/jquery.jqplot.js"></script>
<script	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.canvasTextRenderer.min.js"></script>
<script	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.canvasAxisLabelRenderer.min.js"></script>
<script	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.dateAxisRenderer.min.js"></script>
<script	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
<script	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.categoryAxisRenderer.min.js"></script>
<script src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.highlighter.min.js"></script>
<script src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.cursor.min.js"></script>

<style>
input[type='radio'] {
	margin-top: -3px;
	vertical-align: middle;
}

input[type='checkbox'] {
	vertical-align: middle;
	margin-bottom:6px;
}
.result {
	font-family: monospace; 
	text-align: center;
	border: 1px solid black;
}
.result_table {
	width: 100%;
	border: 1px solid black;
	margin-bottom:5px;
	border-collapse:collapse;
	font-size: 11px;
}
.result_title td {
	font-size: 8px; 
	font-weight: bold; 
	text-align:center;
}
.result_row {
	background-color: #fff;
	line-height: 12px;
}
#labdata,
#labdata table {
	font-size:9pt;
	font-family:helvetica;
}

#labdata h2 {
	font-size: 1.5em;
	margin-bottom: 12px;
}
#report_parameters table table td {
	padding: 1px 3px;
}
</style>

<script>
function loadEncounter(datestr, enc) {
	if ( (window.opener) && (window.opener.setEncounter) ) {
		window.opener.setEncounter(datestr, enc, 'RTop');
		window.opener.loadFrame('enc2', 'RTop', 'patient_file/encounter/encounter_top.php?set_encounter=' + enc);
	} else {
		parent.left_nav.setEncounter(datestr, enc, 'RTop');
		parent.left_nav.loadFrame('enc2', 'RTop', 'patient_file/encounter/encounter_top.php?set_encounter=' + enc);
	}
}

(function($) {
	$(document).ready(function() {
		$("#state").click(function() {
			if ($("#state").val() == "Toggle On") { 
				$(".include").prop("checked", true).attr('checked', true);
				$("#state").val("Toggle Off");
			} else { 
				$(".include").prop("checked", false).removeAttr('checked');
				$("#state").val("Toggle On");
			}
		});

        $("tr.result_row").click(function() {
            if ( $(this).hasClass('hilite') ) {
                $(this).removeClass('hilite');
            }
            else {
            	$(this).addClass('hilite');
            }
        });

        $.jqplot.config.enablePlugins = true;
        $(".plot").click(function(event) {
            event.preventDefault();
            
            var chart = $(this).attr('chart');
            if ($("#chart_"+chart).is(':visible')) {
                $("#chart_"+chart).hide();
            }
            else {
                $("#chart_"+chart).show();
                eval("var points = data_"+chart+";");
                eval("var min = parseFloat(min_"+chart+");");
                eval("var max = parseFloat(max_"+chart+");");
                $.jqplot('chart_'+chart, [points], {
					title:						'Graphical Result Chart',
					autoscale:					true,
					axes:{
						xaxis:{
					    	renderer:			$.jqplot.DateAxisRenderer,
			                rendererOptions:{
			                   	tickRenderer:	$.jqplot.CanvasAxisTickRenderer
			                },
				            tickOptions:{
				            	fontSize:		'9px',
			    	            fontFamily:		'Tahoma',
			        	        angle:			-30,
						    	formatString:	'%Y-%m-%d'
					    	}
						},
						yaxis:{
							min:				min,
							max:				max,
							tickOptions:{
				            	formatString:	'%.2f'
				            }
						}
				  	},
					highlighter: {
				    	show: 					true,
				    	sizeAdjust: 			7.5
				    },
					cursor: {
				    	show: 					false
				    },
					series:[{lineWidth:1, markerOptions:{style:'square'}}]
				});
            }
		});

    });
})(jQuery);

var data;

</script>

	<style>
		#report_inputs { padding:3px 8px;border-radius:8px;border:solid var(--gray300) 1px;box-shadow:2px 2px 2px var(--light);margin:0 10px; }
		#report_inputs table { font-size:.8rem }
		#report_buttons { float:left;margin:4px 0; }
		#report_output { padding:10px 8px;border-radius:8px;border:solid var(--gray300) 1px;box-shadow:2px 2px 2px var(--light);margin:10px 10px; }
	</style>

</head>

<body class="body_top">
	<div id="container">

		<header>
			<!-- HEADER (if desired) -->
			<span class="title" style="margin-left:10px">Laboratory - Results Analysis Report</span>
		</header>

		<div id="content">

<?php ##############################################################################
// some patient data...
$spell  = "SELECT * ";
$spell .= "FROM patient_data ";
$spell .= "WHERE pid = ?";
//---
$myrow = sqlQuery($spell,array($pid));
$lastname = $myrow["lname"];
$firstname  = $myrow["fname"];
$DOB  = $myrow["DOB"];

if($printable || $popup) {
	if ($printable) {
		echo "<div class='no-print' style='float:right;margin-right:6%'>";
		echo "<input type='button' onclick='window.print()' value='print' />";
		echo "</div>\n";
	}
	echo "<table class='text' style='margin-bottom:10px;margin-left:10px;width:100%'>";
	echo "<tr><td style='width:90px'>" . xlt('Patient') . ": </td><td><b>" . text($lastname) . ", " . text($firstname) . "</b></td></tr>";
	echo "<tr><td>" . xlt('Patient ID') . ": </td><td>" . text($pid) . "</td></tr>";
	echo "<tr><td>" . xlt('Date of birth') . ": </td><td>" . text($DOB) . "</td></tr>";
	echo "<tr><td>" . xlt('Print date') . ": </td><td>" . text(date('Y-m-d H:i:s')) . "</td></tr>";
	if ($from_date || $to_date)
		echo "<tr><td>" . xlt('Date range') . ": </td><td>" . text($from_date) . " - " . text($to_date) ."</td></tr>";
		
	echo "</table>";
}
#####################################################################################

if (!$printable) { ?>

			<form method='post' name='theform' id='theform' action=''>
				<input type="hidden" id="process" name="process" value="report" />
	
				<!-- REPORT PARAMETERS -->
				<div id="report_inputs" class="clearfix">
				
					<div style='font-weight:bold;float:left;margin:8px 6px'><?php echo xlt('Select the result items to be included in this report') ?>: </div>
					<div style='float:right;margin:6px'><input type='button' class='btn btn-sm btn-secondary' id='state' name='state' value='<?php echo $state ?>' /></div>

					<input type='hidden' name='popup' value='<?php echo $popup ?>'>
					
<?php 
	// What items are there for this patient?
	// ---------------------------------------
	$value_list = array();
	$tab = 0;
	
	$spell  = "
		SELECT res.result_code AS value_code, TRIM(res.result_text) AS value_text 
		FROM procedure_result res
		JOIN procedure_report rep ON res.procedure_report_id = rep.procedure_report_id
		JOIN procedure_order ord ON rep.procedure_order_id = ord.procedure_order_id 
		WHERE ord.patient_id = ? 
			AND res.result IS NOT NULL
			AND res.result != ''
			AND res.result REGEXP '^[0-9]*[.]{0,1}[0-9]*$' 
		GROUP BY value_code ORDER BY value_text 
	";
	$query  = sqlStatement($spell, array($pid));
?>
						<table id='results' class='table w-100 mt-2'>
							<tr>
								<td style='whitespace:nowrap'>
<?php 
	// Select which items to view...
	$i = 0;
	$rows = sqlNumRows($query);
	if (!$rows) {
		echo "<h3><br/><center>NO RESULT ITEMS AVAILABLE FOR THIS PATIENT</center></h3></td>";
	} else {
		$cols = round($rows/4);
		while ($myrow = sqlFetchArray($query)) {
			if (empty($myrow['value_text'])) continue;
			
			$my_key = str_replace('-','_',$myrow['value_code']);
			echo "<input class='include' type='checkbox' name='value_code[]' value=" . attr($myrow['value_code']) . " ";
			if ($value_select){
				if (in_array($myrow['value_code'], $value_select)){
					echo "checked='checked' ";
				}
			}
			echo " /> " . text($myrow['value_code']."  :  ".substr($myrow['value_text'],0,32)) . "<br />";
			$value_list[$i][value_code] = $myrow['value_code'];
			$i++;
			$tab++;
			if($tab > $cols) {
				echo "</td><td style='whitespace:nowrap'>";
				$tab=0;
			}
		}
	} 
?>
						</tr>
					</table>

					<div class='form-inline m-1'>
						<label class='control-label font-weight-bold mr-1'><?php xl('Results From','e'); ?>:</label>
						<input type='date' class='form-control form-control-sm' name='form_from_date' id="form_from_date"  
								value='<?php echo $from_date ?>'/>
			
						<label class='control-label ml-3 font-weight-bold mr-1'><?php xl('Results Thru','e'); ?>: </label>
						<input type='date' class='form-control form-control-sm' name='form_to_date' id="form_to_date"  
								value='<?php echo $to_date ?>'/>
			
						<label class='control-label font-weight-bold ml-3 mr-2'><?php xl('Select Output','e'); ?>: </label>
						<div class='form-check form-check-inline'>
							<input class='form-check-input' type='radio' name='mode' id='mode' value='list' <?php if (!$mode || $mode == 'list') echo 'checked=checked'; ?>>
							<label class='form-check-label' for='mode_list'><?php echo xlt('List') ?></label>

							<input class='form-check-input ml-2' type='radio' name='mode' id='mode' value='matrix' <?php if ($mode == 'matrix') echo 'checked=checked'; ?>>
							<label class='form-check-label' for='mode_matrix'><?php echo xlt('Matrix') ?></label>
						</div>	
							
						<button type='submit' class='ml-4 btn btn-sm btn-primary'><?php echo xlt('Submit'); ?></button>		
					</div>
				</div>
<?php 
} // end "if printable"
?>

<?php 
// print results of patient's items
//-------------------------------------------
$nothing = true;
$start = ($from_date) ? $from_date.' 00:00:00' : '1961-01-01 00:00:00';
$finish = ($to_date) ? $to_date.' 23:59:59' : date('Y-m-d H:i:s');

// are some Items selected?
if ($value_select){

	echo "<div id='report_output'>";  // start of output section
	
	// print in List-Mode
	if($mode=='list'){

		// process each observation
		foreach($value_select as $this_value){
			$results = array();
			$value_count = 0;
			$norm_top = 0;
			$norm_bot = 0;
			$value_array = array(); // reset local array
			$date_array  = array();//  reset local array
			$this_key = str_replace('-','_',$this_value);

			// get data from db
			$spell  = $main_spell;
			$query  = sqlStatement($spell,array($this_value,$pid,$start,$finish));
			while($myrow = sqlFetchArray($query)){
				$nothing = false;
				if ($last_code != $this_value) {
?>
					<div id="chart_<?php echo $this_key ?>" style="margin:10px 30px 30px 30px;height:200px; width:90%;display:none"></div>
					
					<table class="result_table">
					<tr class="result_title bg-light" >
							<!-- td style="width: 30%;text-align:left">RESULT DESCRIPTION<a href="#chart_<?php echo $this_key ?>" id="plot_<?php echo $this_key ?>" class="ml-3 plot" chart="<?php echo $this_key ?>">[CHART]</a></td -->
							<td style="width: 30%;text-align:left">RESULT DESCRIPTION</td>
							<td style="width: 10%">VALUE</td>
							<td style="width: 10%">UNITS</td>
							<td style="width: 10%">REFERENCE</td>
							<td style="width: 12%">FLAG</td>
							<td style="width: 12%">REPORTED</td>
							<td style="width: 8%">STATUS</td>
							<td style="width: 10%">ENCOUNTER</td>
						</tr>
<?php }
				$last_code = $this_value;
				$norms = explode("-",$myrow['normal']);
				$abnormal = $myrow['abnormal']; // in case they sneak in a new status
				if ($abnormal == 'H') $abnormal = 'High';
				if ($abnormal == 'L') $abnormal = 'Low';
				if ($abnormal == 'HH') $abnormal = 'Alert High';
				if ($abnormal == 'LL') $abnormal = 'Alert Low';
				if ($abnormal == '>') $abnormal = 'Panic High';
				if ($abnormal == '<') $abnormal = 'Panic Low';
				if ($abnormal == 'A') $abnormal = 'Abnormal';
				if ($abnormal == 'AA') $abnormal = 'Critical';
				if ($abnormal == 'S') $abnormal = 'Susceptible';
				if ($abnormal == 'R') $abnormal = 'Resistant';
				if ($abnormal == 'I') $abnormal = 'Intermediate';
				if ($abnormal == 'NEG') $abnormal = 'Negative';
				if ($abnormal == 'POS') $abnormal = 'Positive';
?>
						<tr class="result_row" style="font-weight:bold;<?php if ($abnormal) echo 'color:#bb0000' ?>">
							<td class="result" style="text-align: left"><?php echo $myrow['result_code'] ?>
								- <?php echo substr($myrow['result_text'],0,32) ?>
							</td>
<?php 
				if ($myrow['result_data_type']) { // there is an observation
					if ($myrow['result'] == ".") $myrow['result'] = '';
					$results[] = array('date'=>$myrow['result_date'],'value'=>$myrow['result']);
?>
							<td class="result"><?php echo htmlentities($myrow['result'])?>
							</td>
							<td class="result"><?php echo htmlentities($myrow['units']) ?>
							</td>
							<td class="result"><?php echo htmlentities($myrow['range']) ?>
							</td>
							<td class="result"><?php echo $abnormal ?>
							</td>
							<td class="result"><?php echo (strtotime($myrow['result_date']))? date('Y-m-d',strtotime($myrow['result_date'])): '' ?>
							</td>
							<td class="result"><?php echo htmlentities($myrow['result_status']) ?>
							</td>
							<td class="result">
<?php if (!$printable) { 
	    	$link_ref="$rootdir/forms/form_".$row['type']."/update.php?id=".$myrow['form_id']."&pid=".$pid."&enc=".$myrow['encounter_id']."&pop=1";
?>

								<!-- a href="<?php echo $link_ref; ?>" target="_blank" class="link_submit" 
									onclick="top.restoreSession()">Result Form - <?php echo $myrow['order_number']; ?></a>&nbsp; -->
						    	<!-- a href="#" onclick="parent.left_nav.loadFrame2('ens1', 'RBot', 'patient_file/encounter/encounter_top.php?set_encounter=<?php echo attr($myrow['encounter_id']) ?>')" -->
						    	<!-- a href="#" onclick="window.opener.setEncounter('<?php echo date('Y-m-d',strtotime($myrow['encounter_date']))?>','<?php echo $myrow['encounter_id'] ?>', '');window.opener.loadCurrentEncounterFromTitle();" -->
						    	<a href="#" onclick="loadEncounter('<?php echo date('Y-m-d',strtotime($myrow['encounter_date']))?>','<?php echo $myrow['encounter_id'] ?>');">
						    	<!-- a href="<?php echo $web_root; ?>/interface/patient_file/encounter/encounter_top.php?set_encounter=<?php echo attr($myrow['encounter_id']) ?>" target="_blank" -->
			<?php echo htmlentities($myrow['encounter_id']) ?></a>
<?php } else { ?>
			<?php echo htmlentities($myrow['encounter_id']) ?>
<?php } ?>
							</td>
						</tr>
<?php
				} // end if observ
			} // end result while
?>
					</table>
					<script>
<?php 
			$min = 0;
			$max = 0;
			echo "var data_".$this_key." = [];\n";
			if (count($results) < 2) {
//					echo "$('#plot_".$this_key."').hide();";
			} else {
				foreach ($results AS $item) {
					if ($item['value'] < $min) $min = $item['value'];
					if ($item['value'] > $max) $max = $item['value'];
					echo "data_".$this_key.".push(['".$item['date']."','".$item['value']."']);\n";
				}
			}
			if (is_numeric($norms[0]) && is_numeric($norms[1])) {
				if ($min > $norms[0]) $min = $norms[0];
				if ($max < $norms[1]) $max = $norms[1];
			}
			echo "var min_".$this_key." = ".$min.";\n";
			echo "var max_".$this_key." = ".$max.";\n";
?>
					</script>
<?php 
			} // end foreach selected value
		} // end if mode = list

		//##########################################################################################################################
		if ($mode=='matrix'){

			$value_matrix = array();
			$datelist = array();
			$i = 0;

			// get all data of patient's items
			foreach($value_select as $this_value){

				$spell  = $main_spell;
				$query  = sqlStatement($spell,array($this_value,$pid,$start,$finish));

				while($myrow = sqlFetchArray($query)){
					$value_matrix[$i]['result_id'] 			= $myrow['procedure_result_id'];
					$value_matrix[$i]['result_code'] 		= $myrow['result_code'];
					$value_matrix[$i]['result_text'] 		= $myrow['result_text'];
					$value_matrix[$i]['result'] 			= $myrow['result'];
					$value_matrix[$i]['units'] 				= $myrow['units'];
					$value_matrix[$i]['range'] 				= $myrow['range'];
					$value_matrix[$i]['abnormal'] 			= $myrow['abnormal'];
					$value_matrix[$i]['review_status'] 		= $myrow['review_status'];
					$value_matrix[$i]['encounter_id'] 		= $myrow['encounter_id'];
					$value_matrix[$i]['date_collected'] 	= $myrow['date_collected'];
					$value_matrix[$i]['result_date'] 		= $myrow['result_date'];
					$datelist[]								= $myrow['date_collected'];
					$i++;
				}
			}

			// get unique datetime
			$datelist = array_unique($datelist);

			// sort datetime DESC
			rsort($datelist);

			// sort item-data
			foreach($value_matrix as $key => $row) {
				$result_code[$key] = $row['result_code'];
				$result_date[$key] = $row['result_date'];
			}
			array_multisort(array_map('strtolower',$result_code), SORT_ASC, $result_date, SORT_DESC, $value_matrix);

			$cellcount = count($datelist);
			$itemcount = count($value_matrix);
			
			$nothing = false;

?>
				<div style="width:100%;overflow:auto">
				<table class="result_table" style="width:100%">
					<tr class="result_title bg-light" >
						<td style="width: 30%;min-width:250px">RESULT NAME</td>
						<td style="width: 10%">UNITS</td>
						<td style="width: 10%">REFERENCE</td>
<?php 
			foreach($datelist as $this_date){
				echo "<td style='width:50px;max-width:50px;text-align:center'>" . date('Y-m-d',strtotime($this_date))."<br/>".date('h:i A',strtotime($this_date)) . "</td>\n";
			}
			if ($cellcount < 10) {
				$width = round((10-$cellcount)*50);
				echo "<td style='width:".$width."px'>&nbsp;</td>\n";
			}
?>
					</tr>
				
<?php
			$nextval = 0;
			$lastcode = 'FIRST';
			while ($itemcount > $nextval) {
				$myrow = $value_matrix[$nextval++];
				$lastcode = $myrow['result_code'];
?>
					<tr class="result_row">
						<td class="result" style="text-align: left"><?php echo $myrow['result_code'] ?>
							- <?php echo $myrow['result_text'] ?>
						</td>
						<td class="result">
							<?php echo htmlentities($myrow['units']) ?>
						</td>
						<td class="result" style="border-right: 3px solid grey">
							<?php echo htmlentities($myrow['range']) ?>
						</td>
<?php 
				$nextdate = 0;
				while ($nextdate < $cellcount) {
?>
						<td class='result'style="font-weight:bold;<?php if ($myrow['abnormal']) echo 'color:#bb0000' ?>">
<?php 
					if ($myrow['date_collected'] == $datelist[$nextdate++]) { 					
						if ($myrow['result'] != ".") echo htmlentities($myrow['result']);
						if ($nextdate < $cellcount) $myrow = $value_matrix[$nextval++];
					}
					echo "</td>\n";
				}
				if ($cellcount < 10) echo "<td style='border:1px solid black;background-color: #fff'>&nbsp;</td>\n";
				echo "</tr>";
			} // end next item
			echo "</table></div>";
		}// end if mode = matrix

		if(!$printable){
			if(!$nothing && $mode == 'list'){
				echo "</form><p>";
				echo "<form method='post' action='' target='_blank' onsubmit='return top.restoreSession()'>";
				echo "<input type='hidden' name='print' value='print'>";
				echo "<input type='hidden' name='mode' value='". attr($mode) . "'>";
				echo "<input type='hidden' name='form_from_date' value='". attr($from_date) . "'>";
				echo "<input type='hidden' name='form_to_date' value='". attr($to_date) . "'>";
				foreach($_POST['value_code'] as $this_valuecode) {
					echo "<input type='hidden' name='value_code[]' value='". attr($this_valuecode) . "'>";
				}
				echo "<button type='submit' class='btn btn-sm btn-secondary'>" . xla('Print View') . "</button>";
				echo "</form>";
			}
			if ($value_select && $nothing) {
				echo "<p>" . xlt('No qualifying records') . ".</p>";
			}
	
		} else {
			echo "<p>" . xlt('End of report') . ".</p>";
		}
	}	

	if (!$printable) { ?>
		<script language='JavaScript'>
			<?php if ($alertmsg) { echo " alert('$alertmsg');\n"; } ?>
		</script>
<?php 
	} ?>
	</div>
</body>
</html>
