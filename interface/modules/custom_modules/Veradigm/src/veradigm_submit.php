<?php

declare(strict_types=1);


namespace OpenEMR\Modules\Veradigm;


/** Alias for a `GET` specific SOAP-API `submitRequest()` */
function submitSoapGetRequest(string $api_url, string $payload = '', array $params = [], array $headers = [], array $userOptions = [])
{
	return submitRequest('GET', $api_url, true, $params, $headers, $userOptions, $payload);
}


/** Alias for a `POST` specific SOAP-API `submitRequest()` */
function submitSoapPostRequest(string $api_url, string $payload = '', array $params = [], array $headers = [], array $userOptions = []) {
	return submitRequest('POST', $api_url, true, $params, $headers, $userOptions, $payload);
}


/** @brief Execute Curl request
@param $method
@param string $url
@param array $params
@param array $headers
@param array $userOptions
@return mixed
@throws Exception
*/
function submitRequest(string $method, string $api_url, bool $is_soap = false, array $params = [], array $headers = [], array $userOptions = [], string $payload = '') {
	$method = strtoupper($method);
	$api_url = trim($api_url);
	// Setup headers
	$curl_headers = [];
	if ($is_soap) {
		$curl_headers[] = 'Content-type: text/xml';
	} elseif (!in_array('Content-type: application/x-www-form-urlencoded', $headers)) {
		$curl_headers[] = 'Accept: application/json';
		$curl_headers[] = 'Content-type: application/json; charset=utf-8';
	}
	array_merge($curl_headers, $headers);
	// Set options
	$options = [
		CURLOPT_CRLF => false,
		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HEADER => false,
		CURLOPT_HTTPHEADER => $curl_headers,
		CURLOPT_MAXREDIRS => 8,
		CURLOPT_NOPROGRESS => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYHOST => 1,
		CURLOPT_SSL_VERIFYPEER => 1,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_URL => $api_url,
		CURLOPT_USERAGENT => 'PostmanRuntime/8.0.7',
	];
	switch ($method) {
		case 'DELETE':
			$options[CURLOPT_HTTPGET] = false;
			$options[CURLOPT_POST] = false;
			if ($params) {
				$options[CURLOPT_POSTFIELDS] = $params;
			}
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;
		case 'GET':
			$options[CURLOPT_HTTPGET] = true;
			if ($params) {
				$api_url .= '?' . http_build_query($params);
			}
			break;
		case 'POST':
			$options[CURLOPT_POST] = true;
			if ($params) {
				$options[CURLOPT_POSTFIELDS] = $params;
			} elseif ($payload) {
				$options[CURLOPT_POSTFIELDS] = $payload;
			}
			break;
		case 'PUT':
			$options[CURLOPT_HTTPGET] = false;
			$options[CURLOPT_POST] = false;
			if ($params) {
				$options[CURLOPT_POSTFIELDS] = $params;
			}
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			break;
		default:
			return [];
	}
	array_merge($options, $userOptions);
	$curl_api = curl_init();
	curl_setopt_array($curl_api, $options);
	// Submit response
	$response = curl_exec($curl_api);
	// Check response & provide output and results
	$err_num = curl_errno($curl_api);
	$err_msg = '';
	if ($err_num) {
		$err_msg = curl_error($curl_api);
	}
	$curl_info = curl_getinfo($curl_api);
	curl_close($curl_api);
	if ($err_msg) {
		error_log('<pre class="veradigm_error">' . str_replace("\n", '<br/>', "An error occurred in veradigm::submitRequest() while performing a $method request on $api_url<br/><br/>Request failed<br/><br/>Error ($err_num) = {$err_msg}<br/><br/>Response = " . print_r($response, true) . '<br/><br/>Info = ' . print_r($curl_info, true) . '<br/><br/>' . htmlspecialchars(print_r($payload, true), ENT_HTML5 | ENT_QUOTES)) . '</pre>');
		return ['ERROR'];
	}
	if ($response === false || $response === null) {
		error_log('<pre class="veradigm_error">' . str_replace("\n", '<br/>', "An error occurred in veradigm::submitRequest() while retrieving the response for a $method on $api_url<br/><br/>Request failed<br/><br/>Error ($err_num) = {$err_msg}<br/><br/>Response = " . print_r($response, true) . '<br/><br/>Info = ' . print_r($curl_info, true) . '<br/><br/>' . htmlspecialchars(print_r($payload, true), ENT_HTML5 | ENT_QUOTES)) . '</pre>');
		return ['ERROR'];
	}
	if ($is_soap) {  // SOAP APIs
		$output = (string)$response;
	} else {  // Restful APIs
		$output = json_decode((string)$response, true);
	}
	if (!$output) {
		error_log('<pre class="veradigm_error">' . str_replace("\n", '<br/>', "An error occurred in veradigm::submitRequest() while performing a $method on $api_url<br/><br/>Request failed<br/><br/>Error ($err_num) = {$err_msg}<br/><br/>Response = " . print_r($response, true) . '<br/><br/>Info = ' . print_r($curl_info, true) . '<br/><br/>' . htmlspecialchars(print_r($payload, true), ENT_HTML5 | ENT_QUOTES)) . '</pre>');
		return ['ERROR'];
	}
	if ($is_soap) {  // SOAP APIs
		return [$output];
	}
	return (array)$output;
}
