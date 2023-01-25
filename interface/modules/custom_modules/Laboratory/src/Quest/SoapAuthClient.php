<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Quest;

use SoapClient;

class SoapAuthClient extends SoapClient {
	/**
	 * Since the PHP SOAP package does not support basic authentication
	 * this class downloads the WDSL file using the cURL package and
	 * creates a local copy of the wsdl on the server.
	 * 
	 * Make sure you provide the following additional parameter in the
	 * $options Array: wsdl_local_copy => true
	 */

	function SoapAuthClient($wsdl, $options) {
		if (isset($options['wsdl_local_copy']) &&
				isset($options['login']) &&
				isset($options['password']) &&
				isset($options['wsdl_path'])) {
			 
			$file = "/" . $options['wsdl_local_copy'].'.xml'; 
			
			$path = $options['wsdl_path'];
			if (!file_exists($path)) {
				if (!mkdir($path,0700)) {
					throw new Exception('Unable to create directory for WSDL file ('.$path.')');
				}
			}

			$path .= "/wsdl"; // subdirectory
			if (!file_exists($path)) {
				if (!mkdir($path,0700)) {
					throw new Exception('Unable to create subdirectory for WSDL file ('.$path.')');
				}
			}
				
			if (($fp = fopen($path.$file, "w+")) == false) {
				throw new Exception('Could not create local WSDL file ('.$path.$file.')');
			}
				 
			$ch = curl_init();
			$creds = ($options['login'].':'.$options['password']);
			curl_setopt($ch, CURLOPT_URL, $wsdl);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_USERPWD, $creds);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_FILE, $fp);
				
			// testing only!!
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

			if (($xml = curl_exec($ch)) === false) {
				curl_close($ch);
				fclose($fp);
				unlink($path.$file);
				 
				$ch = curl_init();
				$creds = ($options['login'].':'.$options['password']);
				curl_setopt($ch, CURLOPT_URL, $wsdl);
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($ch, CURLOPT_USERPWD, $creds);
				curl_setopt($ch, CURLOPT_TIMEOUT, 15);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				
				if (($xml = curl_exec($ch)) === false) {
					$error = curl_error($ch);
					curl_close($ch);
					fclose($fp);
					unlink($path.$file);
				}
				 
				throw new Exception($error);
			}
				 
			curl_close($ch);
			fclose($fp);
			$wsdl = "file:///".$path.$file;
		}

		unset($options['wsdl_local_copy']);
		unset($options['wsdl_force_local_copy']);
		 
//		echo "\n" . $wsdl;
		parent::__construct($wsdl, $options);
	}
}
?>