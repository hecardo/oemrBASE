<?php
/** **************************************************************************
 *	mdts.globals.php
 *
 *	Copyright (c)2019 - Medical Technology Services <MDTechSvcs.com>
 *
 *	This program is free software: you can redistribute it and/or modify it under the 
 *  terms of the GNU General Public License as published by the Free Software Foundation, 
 *  either version 3 of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT ANY
 *	WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
 *  PARTICULAR PURPOSE. DISTRIBUTOR IS NOT LIABLE TO USER FOR ANY DAMAGES, INCLUDING 
 *  COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL DAMAGES, 
 *  CONNECTED WITH OR RESULTING FROM THIS AGREEMENT OR USE OF THIS SOFTWARE.
 *
 *	See the GNU General Public License <http://www.gnu.org/licenses/> for more details.
 *
 *  @package mdts
 *  @subpackage utilities
 *  @version 1.0.0
 *  @copyright Medical Technology Services
 *  @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 *
 ******************************************************************************************** */

/**
 * All new functions are defined in the mdts namespace
 */
namespace mdts;

/**
 * Auto class loader function for all MDTS applications. The class name passed to the
 * function must contain a "prefix" of "mdts" which identifies the class as an MDTS
 * class, and a "name" which can be one or more segments separated by a "\". The last
 * segment must be the class name and must start with a capital letter. The "prefix" 
 * will map to the "mdts" library, intermediate segments will define the directories
 * below the root library, and the final class name will be used to create a .php 
 * file reference: "mdts\levens\name.class.php". The default MDTS class library is
 * the "~/library/mdts/" directory.
 *
 * Default subdirectories are...
 * 
 * MODULE - reusable component used in forms
 * OBJECT - data object access functions
 * CLASS  - support for processing functions
 * 
 * @version 1.0.0
 * @since 2019-08-08
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $class consisting of "prefix\names\file"
 * @throws 	Exception class file not found
 * 			Exception class not loadable
 * 
 */
if (!function_exists('mdts\ClassLoader')) {
	function ClassLoader($class) {
		$parts = explode('\\', $class); // break into components
		if (reset($parts) != 'mdts') return; // not a mdts reference

		// build directory path
		$path = $GLOBALS['srcdir'];
		foreach ($parts AS $part) {
			$path .= "/$part";
		}
		
		// format class file name
		$file = "$path.class.php";
		
		// validate file
		if (!file_exists($file)) {
			throw new \Exception("Class [$class] not found in MDTS library");
		}

		// load file
		require_once($file);
		if (!class_exists($class)) {
			throw new \Exception("Class [$class] could not be loaded");
		}				
	}

	// Make sure the class loader funtion is on the spl_autoload queue
	$splList = spl_autoload_functions();
	if (!$splList || !$splList['mdts\ClassLoader']) {
		spl_autoload_register('mdts\ClassLoader');
	}
};

/**
 * This function retrieves the security level for the currently signed in user
 * and compares it to the minimum level required. If user is authorized, their
 * user level is returned; otherwise, no acl level is provided.
 * 
 * @version 1.0.0
 * @since 2017-01-01
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $class consisting of "prefix\name"
 */
if (!function_exists('mdts\SecurityCheck')) {
	function SecurityCheck($realm, $section, $result=null) {
		$user_acl = false;

		// User authentication
		if (!$_SESSION['authUser'] || $_SESSION['authUser'] == '') {
			LogError("FATAL", "Missing user credentials");
			die ("FATAL ERROR: missing user credentials, please log in again!!");
		}
		
		// Permission verification
		$user_acl = acl_check($realm, $section, $result);

		// Failed authentication
		if (!$user_acl) {
			$key = $realm;
			if ($section) $key .= " : " . $section;
			if ($result) $key .= " : " . $result;
			LogError("WARNING", "User [" .$_SESSION['authUser']. "] denied access to [" .$key. "]");
		}
		
		// Return result
		return $user_acl;
	}
}

/**
 * This function scrubs dates to eliminate OpenEMR 0000-00-00 00:00:00 formats
 * and verifies that the format is valid for strtotime conversions.
 * 
 * @version 1.1.0
 * @since 2022-03-30
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $date consisting of a date/time string
 * @return	Integer $time jullean time numeric value
 */
if (!function_exists('mdts\GetSeconds')) {
	function GetSeconds($date) {
		if ($date == '') return FALSE;
		if ($date == 0) return FALSE;
		if ($date == '000-00-00 00:00:00') return FALSE;
		
		$time = strtotime($date);
		if ($time === FALSE) return FALSE;
		if ($time == 0) return FALSE;
		if (!$time) return FALSE;
		
		return $time;
	}
}

/**
 * This function formats dates to eliminate OpenEMR 0000-00-00 00:00:00 formats
 * and verifies that the format is valid for strtotime conversions.
 * 
 * @version 1.1.0
 * @since 2022-03-30
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $date consisting of a date/time string
 * @return	String formatted date value
 */
if (!function_exists('mdts\FormatDate')) {
	function FormatDate($date, $format='Y-m-d') {
		$seconds = GetSeconds($date);
		if (!$seconds) return '';
		
		$date = date($format, $seconds);
		
		return $date;
	}
}

/**
 * This function formats dates to eliminate OpenEMR 0000-00-00 00:00:00 formats
 * and verifies that the format is valid for strtotime conversions.
 * 
 * @version 1.1.0
 * @since 2022-03-30
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $date consisting of a date/time string
 * @return	String formatted time value
 */
if (!function_exists('mdts\FormatTime')) {
	function FormatTime($date, $format='H:i') {
		$seconds = GetSeconds($date);
		if (!$seconds) return '';
		
		$time = date($format, $seconds);
		
		return $time;
	}
}

/**
 * This function formats dates to eliminate OpenEMR 0000-00-00 00:00:00 formats
 * and verifies that the format is valid for strtotime conversions.
 * 
 * @version 1.1.0
 * @since 2022-03-30
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $date consisting of a date/time string
 * @return	String formatted time value
 */
if (!function_exists('mdts\FormatDateTime')) {
	function FormatDateTime($date, $format='Y-m-d H:i:s') {
		$seconds = GetSeconds($date);
		if (!$seconds) return '';
		
		$datetime = date($format, $seconds);
		
		return $datetime;
	}
}

/**
 * This function generates an expection to obtain a trace and prints error
 * informaiton to the screen and the stderr log.
 * 
 * @version 1.0.0
 * @since 2017-01-01
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * @param 	String $level - the severity of the error
 * @param	Sting $message - the error message
 */
if (!function_exists('mdts\LogError')) {
	function LogError($level, $message) {

		// Define array of exception labels 
		$exceptions = [
		        E_ERROR => "E_ERROR",
		        E_WARNING => "E_WARNING",
		        E_PARSE => "E_PARSE",
		        E_NOTICE => "E_NOTICE",
		        E_CORE_ERROR => "E_CORE_ERROR",
		        E_CORE_WARNING => "E_CORE_WARNING",
		        E_COMPILE_ERROR => "E_COMPILE_ERROR",
		        E_COMPILE_WARNING => "E_COMPILE_WARNING",
		        E_USER_ERROR => "E_USER_ERROR",
		        E_USER_WARNING => "E_USER_WARNING",
		        E_USER_NOTICE => "E_USER_NOTICE",
		        E_STRICT => "E_STRICT",
		        E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
		        E_DEPRECATED => "E_DEPRECATED",
		        E_USER_DEPRECATED => "E_USER_DEPRECATED",
		        E_ALL => "E_ALL"
		];

		// Generate stardard exception construct
		try {
			throw new \Exception($message);
		}
		catch (\Exception $e) {
			// Print the appropriate output
			echo xl($exceptions[$level]) . ": " . xl($message);
				
			// Log output to error file
			$error = $exceptions[$level] . ": " . $message . "\n";
			$error .= $e->getTraceAsString();
			error_log($error);
		}
		
	}
}

/**
 * This function reports the error caught by an exception thrown in the
 * main code to the stderr log file and the screen.
 *
 * @version 1.0.0
 * @since 2017-01-01
 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 *
 * @param 	Exception $e 
 */
if (!function_exists('mdts\LogException')) {
	function LogException($e) {

		// Print the appropriate output
		$error = "EXCEPTION: " . $e->getMessage();
		echo $error;

		// Log output to error file
		$error .= "\n";
		$error .= $e->getTraceAsString();
		error_log($error);

	}
}

?>