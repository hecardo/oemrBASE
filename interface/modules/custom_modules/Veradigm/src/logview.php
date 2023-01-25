<?php


use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\Globals\GlobalSetting;


$error_log_path = $GLOBALS['OE_SITE_DIR'] . '/documents/veradigm_error';
$ccr_path = $GLOBALS['OE_SITE_DIR'] . '/ccr/';
$bat_content = '';
$bat_size = 0;
$start_date = '';


function send_file($filename, $file_size, $file_content) {
	$matches = NULL;
	preg_match('/error-\d{4}-\d{1,2}-\d{1,2}\.log/', $filename, $matches);
	if ($matches) {
		header('Accept-Charset: utf-8');
		header('Accept: text/plain, text/*');
		header('Age: 0');
		header('Cache-Control: no-cache, no-store, , private');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . $file_size);
		header('Content-Type: text/plain; charset=UTF-8');
		header('Expires: 0');
		echo $file_content;
		die;
	}
}


if (array_key_exists('start_date', $_REQUEST)) {
	$start_date = $_REQUEST['start_date'];
}

$start_date = $start_date ? substr($start_date, 0, 10) : date('Y-m-d');

$handle = opendir($error_log_path);
if ($handle) {
	while (false !== ($file = readdir($handle))) {
		if ($file != '.' && $file != '..' && 'error-' . $start_date . '.log' === $file) {
			$fd = fopen($error_log_path . '/' . $file, 'r');
			if ($fd) {
			$bat_size = filesize($error_log_path . '/' . $file);
			$bat_content = fread($fd, $bat_size);
			fclose($fd);
			}
			break;
		}
	}
	closedir($handle);
}

if (!empty($_REQUEST['filename'])) {  // Download Log File
	send_file($_REQUEST['filename'], $bat_size, $bat_content);
} elseif (!empty($_REQUEST['ccr']) && !empty($_REQUEST['pid'])) {  // Download CCR File
	$ccr_list = glob($ccr_path . $_REQUEST['pid'] . '/' . $_REQUEST['dir'] . '*.xml');
	if ($ccr_list) {
		$ccr_filename = end($ccr_list);
		$fd = fopen($ccr_path . $_REQUEST['pid'] . '/' . $ccr_filename, 'r');
		if ($fd) {
			$file_size = filesize($ccr_path . $_REQUEST['pid'] . '/' . $file);
			$file_content = fread($fd, $file_size);
			fclose($fd);
		}
	}
	send_file($_REQUEST['filename'], $file_size, $file_content);
}

?>
<!DOCTYPE html>
<html>
	<head>
		<?php html_header_show(); ?>
		<link rel="stylesheet" href="<?php echo $GLOBALS['css_header']; ?>" type="text/css">
		<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-datetimepicker/jquery.datetimepicker.css" type="text/css">
		<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-datetimepicker/jquery.datetimepicker.js"></script>

		<script type="text/javascript">
			function add_class_by_id(elem_id, class_name) {
				document.getElementById(elem_id).classList.add(class_name);
			}

			function rm_class_by_id(elem_id, class_name) {
				document.getElementById(elem_id).classList.remove(class_name);
			}

			function hide_by_id(elem_id) {
				add_class_by_id(elem_id, 'hidden');
			}

			function unhide_by_id(elem_id) {
				rm_class_by_id(elem_id, 'hidden');
			}

			function set_link() {
				hide_by_id('DownloadCCR');
				const ccr_pid = document.getElementById('ccr_pid').value;
				if (ccr_pid) {
					let ccr_dir = document.getElementById('ccr_direction').checked ? 'Uploaded' : 'Downloaded';
					document.getElementById('DownloadCCR').href = 'logview.php?ccr=1&pid=' + ccr_pid + '&dir=' + ccr_dir;
					unhide_by_id('DownloadCCR');
				}
			}
		</script>

		<style type="text/css">
			.hidden {
				appearance: none;
				cursor: default;
				display: none!important;
				height: 0;
				visibility: hidden;
				-moz-appearance: none;
				-moz-user-focus: ignore;
				-webkit-appearance: none;
			}
		</style>
	</head>
	<body class="body_top">
		<h1>Veradigm Logs</h1>
		<br/><br/>
		<div>
			<table>
				<tr>
					<td><label for="ccr_pid">Download CCR by PID: </label></td>
					<td><input id="ccr_pid" min="1" name="ccr_pid" onchange="set_link();" step="1" type="number" value=""/></td>
					<td><label for="ccr_direction">Uploaded CCR: <input id="ccr_direction" name="ccr_direction" onchange="set_link();" type="checkbox" value="U"/></label></td>
					<td><a id="DownloadCCR" class="hidden" download="" href="" rel="alternate" target="_blank" type="text/plain">Download CCR</a></td>
				</tr>
			</table>
		</div>
		<br/><br/>
		<form method="POST">
			<table>
				<tr>
					<td><label for="start_date">Date: </label></td>
					<td>
						<input type="text" size="10" name="start_date" id="start_date" value="<?php echo $start_date; ?>" title="Date of service (yyyy-mm-dd)" onkeyup="datekeyup(this, mypcc)" onblur="dateblur(this, mypcc)"/>
						<img src="<?php echo $GLOBALS['webroot']; ?>/interface/pic/show_calendar.gif" align="absbottom" width="24" height="22" id="img_begin_date" border="0" alt="[?]" style="cursor:pointer;" title="Click here to choose a date"/>
					</td>
					<td>&nbsp;<input type="submit" name="search_logs" value="Search"/></td>
				</tr>
			</table>
		</form>
<?php if ($bat_size) {
	$safe_file_name = htmlspecialchars($file, ENT_QUOTES);
?>
		<div>Download: <a download="<?php echo $safe_file_name; ?>" href="logview.php?filename=<?php echo $safe_file_name; ?>" rel="alternate" target="_blank" type="text/plain"><?php echo $safe_file_name; ?></a><br/></div>
		<br/>
		<textarea rows="32" cols="128" style="width: 100%"><?php echo htmlspecialchars($bat_content, ENT_QUOTES); ?></textarea>
<?php
	} else {
		echo 'No log file exists for the selected date: ' . $start_date;
	}
?>

	</body>
	<script type="text/javascript">
		Calendar.setup({inputField:'start_date', ifFormat:'%Y-%m-%d', button:'img_begin_date'});
	</script>
</html>
