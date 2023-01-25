<?php 
/**
 * Bootstrap custom module for WMT Laboratory module.
 *
 * @package   wmt\laboratory
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c) 2023 Medical Technology Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory;

use OpenEMR\Core\Header;

require_once("../../../globals.php");

// Retrieve Module ID
$mod_data = sqlQuery("SELECT * FROM `modules` WHERE `mod_directory` LIKE ?", ["Laboratory"]);
$mod_id = $mod_data['mod_id'];
if (empty($mod_id) || $mod_data['mod_active'] != 1) return;

// Retrieve Module Parameters
$lab_account = '';
$lab_psc_only = '';
$lab_orphan_pid = '';
$lab_pick_ins = '';
$lab_sms_notify = '';
$lab_draw_bill = '';
$lab_work_path = $GLOBALS['OE_SITE_DIR'] . '/labs';

$result = sqlStatement("SELECT * FROM `module_configuration` WHERE `module_id` = ?", [$mod_id]);
while ($row = sqlFetchArray($result)) {
	switch ($row['field_name']) {
		case 'lab_work_path': 
			$lab_work_path = $row['field_value'];
			break;
		case 'lab_account':
			$lab_account = $row['field_value'];
			break;
		case 'lab_psc_only':
			$lab_psc_only = $row['field_value'];
			break;
		case 'lab_orphan_pid':
			$lab_orphan_pid = $row['field_value'];
			break;
		case 'lab_appt_name':
			$lab_appt_name = $row['field_value'];
			break;
		case 'lab_cat_name':
			$lab_cat_name = $row['field_value'];
			break;
		case 'lab_pick_ins':
			$lab_pick_ins = $row['field_value'];
			break;
		case 'lab_draw_bill':
			$lab_draw_bill = $row['field_value'];
			break;
		case 'lab_sms_notify':
			$lab_sms_notify = $row['field_value'];
			break;
	}
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt("Custom Laboratory"); ?></title>
    <?php echo Header::setupHeader(); ?>
    <script>
		function saveConfig(frm_id, mod_id) {
			$.ajax({
				type: 'POST',
				url: "../../zend_modules/public/Installer/saveConfig",
				data: $('#' + frm_id + mod_id).serialize(),
				success: function(data){
					var msg = 'Configuration saved successfully';
					$('#target' + data.modeId).html(msg + ' ....').show().fadeOut(4000);
				}
			});
		}
    </script>
</head>
<body>
	<div class="container-fluid" style="font-size:14px">
		<form name="configform" id="configform<?php echo $mod_id ?>">
			<table style="width:100%;margin:20px 0px;">
				<tr>
					<td style="white-space:nowrap">
						Laboratory Master Account:
					</td>
					<td style="width:100%">
						<input style="margin-left:10px;width:30%" name="lab_account" type="text" id="lab_account" value="<?php echo $lab_account ?>">
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Laboratory Work Directory:
					</td>
					<td style="width:100%">
						<input style="margin-left:10px;width:80%" name="lab_work_path" type="text" id="lab_work_path" value="<?php echo $lab_work_path ?>">
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						PSC Only Lab Orders:
					</td>
					<td style="width:100%">
						<select style="margin-left:10px;width:auto" name="lab_psc_only" id="lab_psc_only">
							<option value="N" <?php if ($lab_psc_only == 'N') echo "selected"; ?>>No</option>
							<option value="Y" <?php if ($lab_psc_only == 'Y') echo "selected"; ?>>Yes</option>
						</select>
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Allow Insurance Selection:
					</td>
					<td style="width:100%">
						<select style="margin-left:10px;width:auto" name="lab_pick_ins" id="lab_pick_ins">
							<option value="N" <?php if ($lab_pick_ins == 'N') echo "selected"; ?>>No</option>
							<option value="Y" <?php if ($lab_pic_ins == 'Y') echo "selected"; ?>>Yes</option>
						</select>
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Send Result Notification:
					</td>
					<td style="width:100%">
						<select style="margin-left:10px;width:auto" name="lab_sms_notify" id="lab_sms_notify">
							<option value="N" <?php if ($lab_sms_notify == 'N') echo "selected"; ?>>No</option>
							<option value="Y" <?php if ($lab_sms_notify == 'Y') echo "selected"; ?>>Yes</option>
						</select>
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Automatic Draw Fee Insert:
					</td>
					<td style="width:100%">
						<select style="margin-left:10px;width:auto" name="lab_draw_bill" id="lab_draw_bill">
							<option value="N" <?php if ($lab_draw_bill == 'N') echo "selected"; ?>>No</option>
							<option value="Y" <?php if ($lab_draw_bill == 'Y') echo "selected"; ?>>Yes</option>
						</select>
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Lab Orphan PID:
					</td>
					<td style="width:100%">
						<input style="margin-left:10px;width:10%" name="lab_orphan_pid" type="text" id="lab_orphan_pid" value="<?php echo $lab_orphan_pid ?>">
					</td>
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Lab Appointment Type:
					</td>
					<td style="width:100%">
						<input style="margin-left:10px;width:40%" name="lab_appt_name" type="text" id="lab_appt_name" value="<?php echo $lab_appt_name ?>">
					</td>
					</td>
				</tr>
				<tr>
					<td style="white-space:nowrap">
						Lab Document Category:
					</td>
					<td style="width:100%">
						<input style="margin-left:10px;width:40%" name="lab_cat_name" type="text" id="lab_cat_name" value="<?php echo $lab_cat_name ?>">
					</td>
					</td>
				</tr>
			</table>
			<input type="hidden" name="module_id" value="<?php echo $mod_id ?>">
			<button type="button" class="btn btn-primary btn-sm" onclick="saveConfig('configform','<?php echo $mod_id ?>');">Save</button>
			<br/><span id="target<?php echo $mod_id ?>" style="color: #996600"></span>
		</form>
	</div>
</body>
</html>