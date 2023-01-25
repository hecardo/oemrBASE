<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\Logging\SystemLogger;

// Global setup
require_once("../../globals.php");

// Establish log handler
$logger = new SystemLogger();

// Grab session data
$authid = $_SESSION['authId'];
$authuser = $_SESSION['authUser'];
$groupname = $_SESSION['authProvider'];
$authorized = $_SESSION['userauthorized'];

// Security violation
if (!$authuser) {
	$logger->error("Attempt to access program without authorization credentials.");
	die("Attempt to access program without authorization credentials.");
}

include("common.php");

exit();
?>