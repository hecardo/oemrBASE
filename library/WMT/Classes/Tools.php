<?php
/**
 * @package   WMT
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/**
 * All new classes are defined in the WMT namespace
 */
namespace WMT\Classes;

/** 
 * Provides general utility functions.
 */
class Tools {

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
	public static function SecurityCheck($realm, $section, $result=null) {
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
	public static function GetSeconds($date) {
		if ($date == '') return FALSE;
		if ($date == 0) return FALSE;
		if ($date == '000-00-00 00:00:00') return FALSE;
			
		$time = strtotime($date);
		if ($time === FALSE) return FALSE;
		if ($time == 0) return FALSE;
		if (!$time) return FALSE;
			
		return $time;
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
	public static function FormatDate($date, $format='Y-m-d') {
		$seconds = Self::GetSeconds($date);
		if (strpos($date, '0000-00-00') !== false || !$seconds) return '';
			
		$date = date($format, $seconds);
			
		return $date;
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
	public static function FormatTime($date, $format='H:i') {
		$seconds = Self::GetSeconds($date);
		if (strpos($date, '0000-00-00') !== false || !$seconds) return '';
			
		$time = date($format, $seconds);
			
		return $time;
	}
	
	/**
	 * This public static functionformats dates to eliminate OpenEMR 0000-00-00 00:00:00 formats
	 * and verifies that the format is valid for strtotime conversions.
	 *
	 * @version 1.1.0
	 * @since 2022-03-30
	 * @author Ron Criswell <ron.criswell@MDTechSvcs.com>
	 *
	 * @param 	String $date consisting of a date/time string
	 * @return	String formatted time value
	 */
	public static function FormatDateTime($date, $format='Y-m-d H:i:s') {
		$seconds = Self::GetSeconds($date);
		if (strpos($date, '0000-00-00') !== false || !$seconds) return '';
			
		$datetime = date($format, $seconds);
			
		return $datetime;
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
	public static function LogError($level, $message) {
			
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
	public static function LogException($e) {
			
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