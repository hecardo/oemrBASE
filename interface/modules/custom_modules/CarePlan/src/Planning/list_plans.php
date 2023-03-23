<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author		Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$here = dirname(__FILE__, 6);
require_once($here . "/globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Menu\PatientMenuRole;
use OpenEMR\OeUI\OemrUI;

use WMT\Objects\CarePlan;

$settings = array(
	'heading_title' => xl('Patient Care Plan'),
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

	<title>Care Plan</title>
	<meta name="author" content="Ron Criswell" />
	<meta name="description" content="Care Plan Maintenance" />
	<meta name="copyright" content="&copy;<?php echo date('Y') ?> Williams Medical Technologies, Inc.  All rights reserved." />

	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-dt/css/jquery.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-buttons/css/buttons.dataTables.min.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-dt/css/colReorder.dataTables.min.css" type="text/css">

	<script>
		// Called by the deleteme.php window on a successful delete.
		function imdeleted() {
			top.restoreSession();
			location.href = '../../patient_file/transaction/transactions.php';
		}
		// Process click on Delete button.
		function deleteme(transactionId) {
			top.restoreSession();
			dlgopen('../deleter.php?transaction=' + encodeURIComponent(transactionId) + '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>, '_blank', 500, 450);
			return false;
		}
	</script>
</head>

<body>
	<div id="container_div" class="<?php echo $oemr_ui->oeContainer();?> mt-3">
        <div class="row">
            <div class="col-sm-12">
                <?php require_once("$include_root/patient_file/summary/dashboard_header.php");?>
            </div>
        </div>
		<?php
			$list_id = "planning"; // to indicate nav item is active, count and give correct id
			$menuPatient = new PatientMenuRole();
			$menuPatient->displayHorizNavBarMenu();
		?>
		<div class="row mt-3">
			<div class="col-sm-12">
				<div class="btn-group">
					<a href="edit_plan.php" class="btn btn-primary btn-add" onclick="top.restoreSession()">
						<?php echo xlt('Create New Plan'); ?></a>
				</div>
			</div>
		</div>
		<br />
		<div>
			<div class="col-sm-12 text jumbotron py-4">
				<?php
				if ($plans = CarePlan::getPlansByPid($pid)) {
				?>
					<div class="table-responsive">
						<table class="table table-hover">
							<thead>
								<tr>
									<th scope="col">&nbsp;</th>
									<th scope="col"><?php echo xlt('Service'); ?></th>
									<th scope="col"><?php echo xlt('Initiated'); ?></th>
									<th scope="col"><?php echo xlt('Completed'); ?></th>
									<th scope="col"><?php echo xlt('Provider'); ?></th>
									<th scope="col"><?php echo xlt('Descripton'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ($result as $item) {
									if (!isset($item['body'])) {
										$item['body'] = '';
									}

									// Collect date
									if (!empty($item['refer_date'])) {
										// Special case for referrals, which uses refer_date stored in lbt_data table
										//  rather than date in transactions table.
										//  (note this only contains a date without a time)
										$date = oeFormatShortDate($item['refer_date']);
									} else {
										$date = oeFormatDateTime($item['date']);
									}

									$id = $item['id'];
									$edit = xl('View/Edit');
									$view = xl('Print'); //actually prints or displays ready to print
									$delete = xl('Delete');
									$title = xl($item['title']);
									?>
									<tr>
										<td>
											<div class="btn-group oe-pull-toward">
												<a href='add_transaction.php?transid=<?php echo attr_url($id); ?>&title=<?php echo attr_url($title); ?>&inmode=edit'
													onclick='top.restoreSession()'
													class='btn btn-primary btn-edit'>
													<?php echo text($edit); ?>
												</a>
												<?php if (AclMain::aclCheckCore('admin', 'super')) { ?>
													<a href='#'
														onclick='deleteme(<?php echo attr_js($id); ?>)'
														class='btn btn-danger btn-delete'>
														<?php echo text($delete); ?>
													</a>
												<?php } ?>
												<?php if ($item['title'] == 'LBTref') { ?>
													<a href='print_referral.php?transid=<?php echo attr_url($id); ?>' onclick='top.restoreSession();'
														class='btn btn-print btn-primary'>
														<?php echo text($view); ?>
													</a>
												<?php } ?>
											</div>
										</td>
										<td><?php echo getLayoutTitle('Transactions', $item['title']); ?></td>
										<td><?php echo text($date); ?></td>
										<td><?php echo text($item['user']); ?></td>
										<td><?php echo text($item['body']); ?></td>
									</tr>
									<?php
								}
								?>
							</tbody>
						</table>
					</div>
					<?php
				} else {
					?>
				<span class="text">
					<i class="fa fa-exclamation-circle oe-text-orange" aria-hidden="true"></i> <?php echo xlt('There is no Care Plan on file for this patient.'); ?>
				</span>
					<?php } ?>
			</div>
		</div>
	</div><!--end of container div-->
	<?php $oemr_ui->oeBelowContainerDiv();?>
	<script>
		var listId = '#' + <?php echo js_escape($list_id); ?>;
		$(function () {
			$(listId).addClass("active");
		});
	</script>
</body>
</html>
