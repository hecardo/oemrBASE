<?php

declare(strict_types=1);


namespace OpenEMR\Modules\Veradigm;

use OpenEMR\Modules\Veradigm\GlobalConfig;
use OpenEMR\Utils\CommonQueries;
use OpenEMR\Utils\RxNorm;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;


function isFacilityInVeradigm($site_id): bool {
	$user_data = sqlQuery('SELECT `add2veradigm`, `veradigmSiteUUID` FROM `facility` WHERE (`id` = ?) LIMIT 1;', [$site_id]);
	return $user_data['add2veradigm'] == '1' && !empty($user_data['veradigmSiteUUID']);
}


function isPatientInVeradigm($patient_id): bool {
	$user_data = sqlQuery('SELECT `add2veradigm`, `p_uuid` FROM `patient_data` WHERE (`pid` = ?) LIMIT 1;', [$patient_id]);
	return $user_data['add2veradigm'] == '1' && $user_data['p_uuid'];
}


function isUserInVeradigm($user_id): bool {
	if (!$user_id && !empty($_SESSION['authId'])) {
		$user_id = $_SESSION['authId'];
	}
	$user_data = sqlQuery('SELECT `add2veradigm`, `veradigm_user_role`, `veradigmUserUUID` FROM `users` WHERE (`id` = ?) LIMIT 1;', [$user_id]);
	return $user_data['add2veradigm'] == '1' && $user_data['veradigmUserUUID'] && $user_data['veradigm_user_role'];
}


/** Save a Facility's Veradigm info */
function storeFacilityVeradigmInfo($site_id, $site_uuid) {
	sqlQuery('UPDATE `facility` SET `add2veradigm` = 1, `veradigmSiteUUID` = ? WHERE (`id` = ?) LIMIT 1;', [$site_uuid, $site_id]);
}


/** Save a Patient's Veradigm info */
function storePatientVeradigmInfo($patient_id, $patient_uuid) {
	sqlQuery('UPDATE `patient_data` SET `add2veradigm` = 1, `p_uuid` = ? WHERE (`pid` = ?) LIMIT 1', [$patient_uuid, $patient_id]);
}


/** Save the Veradigm UUID of a user */
function storeUserVeradigmInfo($user_id, $user_uuid) {
	sqlQuery('UPDATE `users` SET `add2veradigm` = 1, `veradigmUserUUID` = ? WHERE (`id` = ?) LIMIT 1', [$user_uuid, $user_id]);
}


/** Class used to control the Veradigm interface. */
class veradigmAPI {

	private static $site_prod_login = 'https://eprescribe.allscripts.com/Login.aspx';
	private static $site_test_login = 'https://eprescribeqa.allscripts.com/Login.aspx';
	private static $api_prod_sso = 'https://eprescribe.allscripts.com/SAML/SSO.aspx';
	private static $api_test_sso = 'https://eprescribeqa.allscripts.com/SAML/SSO.aspx';
	private static $api_prod_reg = 'https://erxnowregistration.allscripts.com/erxnowregws/eRxNowRegSrv.asmx';
	private static $api_test_reg = 'https://eprescriberegistrationqa.allscripts.com/regws/erxnowregsrv.asmx';
	private static $api_prod_partner = 'https://eprescribe.allscripts.com/PartnerServices/PartnerSvc.asmx';
	private static $api_test_partner = 'https://eprescribeqa.allscripts.com/PartnerServices/PartnerSvc.asmx';

	private static $mode_lockdown = 'PatientLockDownMode';
	private static $mode_patient_context = 'PatientContext';
	private static $mode_sso = 'StandardSSO';
	private static $mode_task = 'TaskMode';
	private static $mode_utility = 'UtilityMode';

	private $globalsConfig;

	private $password = '';
	private $objKey = '';
	private $key_file = '';
	private $cert = null;

	public $error_log_path = '';
	public $allow_logging = false;
	public $extra_logging = false;
	public $save_ccr = false;

	public $site_login = '';
	public $api_sso = '';
	public $api_reg = '';
	public $api_partner = '';
	public $api_xml_domain = '';

	public $timezone = '';
	public $allergies = null;
	public $partner_id = '';
	public $username = '';
	public $mrn_suffix = '';
	public $version_num = '20.1.3.174';

	public $patient_id = 0;
	public $facility_uuid = '';
	public $patient = null;
	public $rxnorm = null;

	public function __construct($globals = null, $pid = null) {
		global $GLOBALS;
		if (!isset($_SESSION['authUserID'])) {
			error_log('User attempted to access Veradigm features.');
			die('Login to access Veradigm features.');
		}
		$this->globalsConfig = new GlobalConfig($GLOBALS);
		if (!$this->globalsConfig->isConfigured()) {
			error_log('Veradigm is not configured.');
			die('Veradigm is not configured.');
		}
		$this->error_log_path = $this->globalsConfig->getGlobalSetting('OE_SITE_DIR') . '/documents/veradigm_error';
		$this->rxnorm = new RxNorm::RxNorm();
		$this->allow_logging = $this->globalsConfig->loggingEnabled();
		$this->extra_logging = $this->globalsConfig->extraLoggingEnabled();
		$this->timezone = $this->globalsConfig->getDefaultFacilityTimezone();
		$this->partner_id = $this->globalsConfig->getPartnerID();
		$this->username = $this->globalsConfig->getPartnerUsername();
		$this->password = $this->globalsConfig->getPartnerPswd();
		$this->objKey = null;
		$this->key_file = $this->globalsConfig->getGlobalSetting('key_file');
		$this->crt_file = $this->globalsConfig->getGlobalSetting('crt_file');
		$this->cert = file_get_contents($this->crt_file);
		if (!is_dir($this->error_log_path)) {
			if (!mkdir($this->error_log_path, 0777, true)) {
				error_log('Failed to create the Veradigm logs directory "' . $this->error_log_path . '".');
				die('Failed to create the Veradigm logs directory "' . $this->error_log_path . '".');
			}
		}
		if ($this->globalsConfig->isTestMode()) {
			$this->site_login = self::$site_test_login;
			$this->api_sso = self::$api_test_sso;
			$this->api_reg = self::$api_test_reg;
			$this->api_partner = self::$api_test_partner;
			$this->mrn_suffix = '-Test-Sandbox';
		} else {
			$this->site_login = self::$site_prod_login;
			$this->api_sso = self::$api_prod_sso;
			$this->api_reg = self::$api_prod_reg;
			$this->api_partner = self::$api_prod_partner;
			$this->mrn_suffix = '';
		}
		$this->api_xml_domain = 'eprescribe.allscripts.com';
		if ($pid) {
			$this->setPatientId($pid);
		}
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		}
	}

	public function save_ccr_file($ccr, $direction) {
		if (!$this->globalsConfig->saveCCREnabled() || !$ccr || !$direction) {
			return;
		}
		$storage_path = $this->globalsConfig->getGlobalSetting('OE_SITE_DIR') . '/ccr/' . $this->patient_id . '/';
		if (!is_dir($storage_path)) {
			if (!mkdir($storage_path, 0770, true)) {
				error_log('Failed to create CCR directory "' . $storage_path . '"');
				return;
			}
		}
		$filename = $direction . '-' . date('YmdHis') . '.xml';
		$ccr_file = fopen($storage_path . $filename, 'w');
		if (!fwrite($ccr_file, $ccr)) {
			error_log('Failed to save CCR "' . $storage_path . $filename . '" for PID ' . $this->patient_id);
		}
		fclose($ccr_file);
	}

	public function isAuthorized(): bool {
		if (empty($_SESSION['authId'])) {
			error_log('User attempted to access Veradigm features.');
			die('Login to access Veradigm features.');
		}
		$user = CommonQueries::getUserById($_SESSION['authId']);
		if (!$user || !isset($user['active']) || $user['active'] != '1') {
			return false;
		} else {
			if ($this->globalsConfig->hasGlobalSiteLicense()) {
				$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
			} else {
				$this->facility_uuid = CommonQueries::getUserFacility($user['id'])['veradigmSiteUUID'];
			}
		}
		if ($user['username'] === 'admin') {
			return true;
		}
		if (empty($user['veradigm_user_role'])) {
			return false;
		}
		if (!empty($_SESSION['authUser'])) {
			return true;
		}
		return false;
	}

	public function setPatientId($patient_id = 0) {
		if ($this->patient_id && $this->patient) {  // Patient already set
			return true;
		}
		$this->patient = CommonQueries::getPatientById($patient_id);
		if (!$this->patient) {
			$this->patient_id = 0;
			$this->patient = null;
			return false;
		}
		if (!$this->patient_id) {
			$this->patient_id = $this->patient['pid'];
		}
		if ($this->patient) {
			return true;
		}
		$this->patient_id = 0;
		$this->patient = null;
		return false;
	}

	public function facilityCheck($site_id): array {
		if (!$this->facility_uuid && !$site_id) {
			return ['success' => false, 'error' => 'No Facility set!'];
		}
		if (!$this->facility_uuid || !isFacilityInVeradigm($site_id)) {
			$this->facility_uuid = $this->saveSite($site_id);
			if (!$this->facility_uuid) {
				return ['success' => false, 'error' => 'ERROR (createSavePatientXml): The facility (' . $site_id . ') this user belongs to was improperly configured!'];
			}
		}
		return [];
	}

	public function submitPostPartner($xml_string, $action = '') {
		if (empty($_SESSION['authUser']) || empty($_SESSION['authUserID']) || !empty($_SESSION['authId'])) {
			error_log('User attempted to access Veradigm features.');
			die('Login to access Veradigm features.');
		}
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log("REQUEST (Partner):\n" . $xml_string);
		}
		$response = submitSoapPostRequest($this->api_partner, $xml_string);
		if ($response == ['ERROR']) {
			return null;
		}
		$_msg_prefix = 'RESPONSE: ';
		if ($action) {
			$_msg_prefix = $action . ' ' . $_msg_prefix;
		}
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log($_msg_prefix . print_r($response, true));
		}
		return $response;
	}

	public function submitPostRegistration($xml_string, $action = '') {
		if (empty($_SESSION['authUser']) || empty($_SESSION['authUserID']) || empty($_SESSION['authId'])) {
			error_log('User attempted to access Veradigm features.');
			die('Login to access Veradigm features.');
		}
		$this->veradigm_error_log("REQUEST (Registration):\n" . $xml_string);
		$response = submitSoapPostRequest($this->api_reg, $xml_string);
		if ($response == ['ERROR']) {
			return null;
		}
		$_msg_prefix = 'RESPONSE: ';
		if ($action) {
			$_msg_prefix = $action . ' ' . $_msg_prefix;
		}
		$this->veradigm_error_log($_msg_prefix . print_r($response, true));
		return $response;
	}

	public function signXml($xml_string) {
		$doc = new DOMDocument();
		$doc->loadXML($xml_string);
		$objDSig = new XMLSecurityDSig();
		$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
		$objDSig->addReference(
			$doc,
			XMLSecurityDSig::SHA256,
			['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
			['force_uri' => true]
			);
		if (!$this->objKey) {
			$this->objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
			$this->objKey->passphrase = $this->password;
			$this->objKey->loadKey($this->key_file, true);
		}
		$objDSig->sign($this->objKey);
		$objDSig->add509Cert($this->cert);
		$objDSig->appendSignature($doc->documentElement);
		$xmlString = $doc->saveXML();
		$b64Xml = base64_encode($xmlString);
		unset($xmlString);
		unset($objDSig);
		unset($doc);
		$this->veradigm_error_log($b64Xml);
		return $b64Xml;
	}

	public function check4Errors() {
		if (empty($_SESSION['authUser']) || empty($_SESSION['authUserID']) || empty($_SESSION['authId'])) {
			error_log('User attempted to access Veradigm features.');
			die('Login to access Veradigm features.');
		}
		if (!$this->patient_id || !$this->patient) {
			return ['No patient has been selected!'];
		}
		return $this->checkPatient();
	}

	public function checkPatient() {
		if (!$this->patient_id || !$this->patient) {
			return ['No patient has been selected!'];
		}
		$output = [];
		if (empty($this->patient['p_uuid'])) {
			$output[] = 'The patient record is missing a UUID (the record is likely corrupted)';
		}
		if (empty($this->patient['fname'])) {
			$output[] = 'The patient data is missing the first name';
		}
		if (empty($this->patient['lname'])) {
			$output[] = 'The patient data is missing the last name';
		}
		if (empty($this->patient['date_of_birth'])) {
			$output[] = 'The patient data is missing the date of birth';
		}
		if (empty($this->patient['metric_height'])) {
			$output[] = 'The patient has no height on record';
		}
		if (empty($this->patient['metric_weight'])) {
			$output[] = 'The patient has no weight on record';
		}
		return $output;
	}

	public function checkResp4Errors($data, $extra_info = null) {
		if (!$data) {
			return null;
		}
		if (gettype($data) === 'array') {
			$err_msg = $data[0];
		} else {
			$err_msg = $data;
		}
		if (mb_strpos($err_msg, " desc='Success' ")) {
			return $data;
		}
		if (mb_strpos($err_msg, 'Fatal Error') || mb_strpos($err_msg, 'Failed ') === 0 || mb_strpos(mb_strtolower($err_msg), 'error') === 0 || mb_strpos($err_msg, 'Unauthorized ') === 0) {
			$err_msg = htmlspecialchars(print_r($data, true), ENT_HTML5 | ENT_QUOTES) . '<br/><br/>' . htmlspecialchars(print_r($extra_info, true), ENT_HTML5 | ENT_QUOTES);
			$this->veradigm_error_log('VERADIGM ERROR: ' . str_replace('<br/>', "\n", $err_msg));
		}
		return $data;
	}

	public function isErrorInResp($data): bool {
		if (!$data) {
			return true;
		}
		if (gettype($data) === 'array') {
			$data = $data[0];
		}
		if (mb_strpos($data, " desc='Success' ")) {
			return false;
		}
		if (mb_strpos($data, 'Fatal Error') || mb_strpos($data, 'No ') === 0 || mb_strpos($data, 'Failed ') === 0 || mb_strpos(mb_strtolower($data), 'error') === 0 || mb_strpos($data, 'Unauthorized ') === 0) {
			$this->veradigm_error_log('Curl Error: ' . $data);
			return true;
		}
		return false;
	}

	public function veradigm_error_log($message, $extra_info = '') {
		if (!$this->allow_logging) {
			return;
		}
		$f = fopen($this->error_log_path . '/error-' . date('Y-m-d') . '.log', 'a');
		if (is_array($message)) {
			$message = json_encode($message);
		}
		if ($extra_info) {
			$message = $extra_info . ' ' . $message;
		}
		$header = date('Y-m-d H:i:s') . ' (User: ' . $_SESSION['authUser'] . ' {' . $_SESSION['authId'] . '}) ==========> ';
		fwrite($f, $header . ' ' . $message . "\n\n");
		fclose($f);
	}

	/* Create SOAP-XML */

	public function createSsoXml($mode = 'StandardSSO') {
		$user = CommonQueries::getUserById($_SESSION['authUserID']);
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		} else {
			$this->facility_uuid = CommonQueries::getUserFacility($user['id'])['veradigmSiteUUID'];
		}
		$issue_instant = date('Y-m-d\TH:i:s.000\Z', strtotime('-2 minutes'));
		$not_on_nor_after = date('Y-m-d\TH:i:s.000\Z', strtotime('+2 minutes'));
		// $patient_guid = '<saml:Attribute AttributeName="patient-guid" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue /><saml:AttributeValue /><saml:AttributeValue>00000000-0000-0000-0000-000000000000</saml:AttributeValue></saml:Attribute>';
		$patient_guid = '<saml:Attribute AttributeName="patient-guid" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue>00000000-0000-0000-0000-000000000000</saml:AttributeValue></saml:Attribute>';
		if ($mode === 'PatientLockDownMode' || $mode === 'PatientContext') {
			$this->setPatientId();
			// $patient_guid = '<saml:Attribute AttributeName="patient-guid" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue /><saml:AttributeValue /><saml:AttributeValue>' . $this->patient['p_uuid'] . '</saml:AttributeValue></saml:Attribute>';
			$patient_guid = '<saml:Attribute AttributeName="patient-guid" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue>' . $this->patient['p_uuid'] . '</saml:AttributeValue></saml:Attribute>';
		}
		$raw_saml = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"><saml:Assertion MajorVersion="1" MinorVersion="1" AssertionID="_c2d1398e-70f7-4103-85ff-77a29b498425" Issuer="' . $this->globalsConfig->getIssuerName() . '.com" IssueInstant="2021-03-02T18:11:24.812Z" xmlns:saml="urn:oasis:names:tc:SAML:1.0:assertion"><saml:Conditions NotBefore="' . $issue_instant . '" NotOnOrAfter="' . $not_on_nor_after . '" /><saml:AuthenticationStatement AuthenticationMethod="urn:oasis:names:tc:SAML:1.0:am:password" AuthenticationInstant="' . $issue_instant . '"><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject></saml:AuthenticationStatement><saml:AttributeStatement><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject><saml:Attribute AttributeName="version" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue>' . $this->version_num . '</saml:AttributeValue></saml:Attribute></saml:AttributeStatement><saml:AttributeStatement><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject><saml:Attribute AttributeName="operation" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue>16</saml:AttributeValue></saml:Attribute></saml:AttributeStatement><saml:AttributeStatement><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject><saml:Attribute AttributeName="reserved" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue>' . $this->partner_id . '</saml:AttributeValue></saml:Attribute></saml:AttributeStatement><saml:AttributeStatement><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject>' . $patient_guid . '</saml:AttributeStatement><saml:AttributeStatement><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject><saml:Attribute AttributeName="reserved" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue /></saml:Attribute></saml:AttributeStatement><saml:AttributeStatement><saml:Subject><saml:NameIdentifier Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" NameQualifier="urn:' . $this->globalsConfig->getIssuerName() . '.com">' . $user['veradigmUserUUID'] . '</saml:NameIdentifier><saml:SubjectConfirmation><saml:ConfirmationMethod>urn:oasis:names:tc:SAML:1.0:cm:bearer</saml:ConfirmationMethod></saml:SubjectConfirmation></saml:Subject><saml:Attribute AttributeName="SSOMode" AttributeNamespace="' . $this->globalsConfig->getIssuerName() . '.com"><saml:AttributeValue>' . $mode . '</saml:AttributeValue></saml:Attribute></saml:AttributeStatement></saml:Assertion><samlp:Status><samlp:StatusCode Value="samlp:Success" /></samlp:Status></samlp:Response>';
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log('RAW SAML for ' . $mode . ":\n" . $raw_saml);
		}
		return $this->signXml($raw_saml);
	}

	public function createAddSiteXml($site) {
		$b64Xml = base64_encode('<site-profile><partner-id>' . $this->partner_id . '</partner-id><site-name>' . mb_substr($site['name'], 0, 30) . '</site-name><site-address>' . mb_substr($site['street'], 0, 35) . '</site-address><site-address2></site-address2><site-city>' . mb_substr($site['city'], 0, 30) . '</site-city><site-state>' . $site['state'] . '</site-state><site-zip>' . $site['postal_code'] . '</site-zip><site-phone>' . preg_replace('/[^0-9]/u', '', $site['phone']) . '</site-phone><site-fax>' . preg_replace('/[^0-9]/u', '', $site['fax']) . '</site-fax><site-time-zone>' . $this->timezone . '</site-time-zone></site-profile>');
		return '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Header><AuthHeader xmlns="https://stscripts.allscripts.com/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap:Header><soap:Body><AddSite xmlns="https://stscripts.allscripts.com/"><SiteProfile>' . $b64Xml . '</SiteProfile></AddSite></soap:Body></soap:Envelope>';
	}

	public function createUpdateSiteXml($site) {
		$b64Xml = base64_encode('<site-profile><partner-id>' . $this->partner_id . '</partner-id><license-id>' . $site['veradigmSiteUUID'] . '</license-id><site-name>' . mb_substr($site['name'], 0, 30) . '</site-name><site-address>' . mb_substr($site['street'], 0, 35) . '</site-address><site-address2></site-address2><site-city>' . mb_substr($site['city'], 0, 30) . '</site-city><site-state>' . $site['state'] . '</site-state><site-zip>' . $site['postal_code'] . '</site-zip><site-phone>' . preg_replace('/[^0-9]/u', '', $site['phone']) . '</site-phone><site-fax>' . preg_replace('/[^0-9]/u', '', $site['fax']) . '</site-fax><site-time-zone>' . $this->timezone . '</site-time-zone></site-profile>');
		return '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Header><AuthHeader xmlns="https://stscripts.allscripts.com/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap:Header><soap:Body><UpdSite xmlns="https://stscripts.allscripts.com/"><SiteProfile>' . $b64Xml . '</SiteProfile></UpdSite></soap:Body></soap:Envelope>';
	}

	public function createAddUserXml($user_id) {
		$user = CommonQueries::getUserById($user_id);
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		} else {
			$this->facility_uuid = CommonQueries::getUserFacility($user['id'])['veradigmSiteUUID'];
		}
		$is_staff = mb_strtolower($user['veradigm_user_role']) === 'erxstaff' ? true : false;
		$is_admin = CommonQueries::isUserAdmin($user_id) ? 'Y' : 'N';
		$is_doctor = mb_strtolower($user['veradigm_user_role']) === 'erxdoctor' ? 'Y' : 'N';
		if ($user && empty($user['veradigm_user_role'])) {
			return ['success' => true];
		}
		if (!$user || ($is_doctor === 'Y' && !$user['federaldrugid'])) {
			return ['success' => false, 'error' => 'No user found or missing Federal Drug ID!'];
		}
		$result = $this->facilityCheck($user['facility_id']);
		if ($result && gettype($result) === 'array') {
			return $result;
		}
		$prescribe_on_behalf_rights = mb_strtolower($user['veradigm_user_role']) === 'erxprescribersomereview' ? 'Y' : 'N';
		$prescribe_on_behalf_limited_rights = mb_strtolower($user['veradigm_user_role']) === 'erxprescriberfullreview' ? 'Y' : 'N';
		$prescribe_on_behalf_super_rights = mb_strtolower($user['veradigm_user_role']) === 'erxprescribernoreview' ? 'Y' : 'N';
		$physician_assistant = mb_strtolower($user['veradigm_user_role']) === 'erxmidlevelwosupervision' ? 'Y' : 'N';
		$physician_assistant_supervised = mb_strtolower($user['veradigm_user_role']) === 'erxmidlevelwsupervision' ? 'Y' : 'N';
		$npi = !empty($user['npi']) ? '<npi>' . $user['npi'] . '</npi>' : '';
		$email_address = mb_strpos($user['email'], '@') === 0 ? $user['username'] . $user['email'] : $user['email'];
		$title = !empty($user['title']) ? '<title>' . $user['title'] . '</title>' : '<title></title>';
		$specialty = !empty($user['specialty']) ? '<specialty-cd>' . $user['specialty'] . '</specialty-cd>' : '';
		$suffix = !empty($user['suffix']) ? '<suffix>' . $user['suffix'] . '</suffix>' : '';
		$state_license = '';
		if (!$is_staff && (!empty($user['state_license_number']) || !empty($user['license_issue_state']) || !empty($user['state_expire_date']))) {
			$state_license .= '<state-licenses><state-license>';
			if (!empty($user['state_license_number'])) {
				$state_license .= '<state-license-number>' . $user['state_license_number'] . '</state-license-number>';
			}
			if (!empty($user['license_issue_state'])) {
				$state_license .= '<issuing-state>' . $user['license_issue_state'] . '</issuing-state>';
			}
			if (!empty($user['state_expire_date'])) {
				$state_license .= '<state-license-expire-date>' . $user['state_expire_date'] . '</state-license-expire-date>';
			}
			$state_license .= '</state-license></state-licenses>';
		}
		if ($is_admin && !$npi) {
			$dea_info = '';
		} else {
			$dea_info = $user['federaldrugid'] ? '<dea>' . $user['federaldrugid'] . '</dea>' : '';
			$dea_info .= $user['dea_expire_date'] ? '<dea-expire-date>' . $user['dea_expire_date'] . '</dea-expire-date>' : '';
			$dea_info .= $user['dea_schedule_2'] === 'Y' ? '<dea-schedule-2>' . $user['dea_schedule_2'] . '</dea-schedule-2>' : '';
			$dea_info .= $user['dea_schedule_3'] === 'Y' ? '<dea-schedule-3>' . $user['dea_schedule_3'] . '</dea-schedule-3>' : '';
			$dea_info .= $user['dea_schedule_4'] === 'Y' ? '<dea-schedule-4>' . $user['dea_schedule_4'] . '</dea-schedule-4>' : '';
			$dea_info .= $user['dea_schedule_5'] === 'Y' ? '<dea-schedule-5>' . $user['dea_schedule_5'] . '</dea-schedule-5>' : '';
		}
		$b64Xml = base64_encode('<user-profile><partner-id>' . $this->partner_id . '</partner-id><license-id>' . $this->facility_uuid . '</license-id><user-name>' . $user['username'] . '</user-name>' . $title . '<last-name>' . $user['lname'] . '</last-name><first-name>' . $user['fname'] . '</first-name><mi>' . mb_substr($user['mname'], 0, 1) . '</mi>' . $suffix . $specialty . '<email>' . $email_address . '</email>' . $npi . $dea_info . $state_license . '<admin-rights>' . $is_admin . '</admin-rights><provider-rights>' . $is_doctor . '</provider-rights><prescribe-on-behalf-rights>' . $prescribe_on_behalf_rights . '</prescribe-on-behalf-rights><prescribe-on-behalf-limited-rights>' . $prescribe_on_behalf_limited_rights . '</prescribe-on-behalf-limited-rights><prescribe-on-behalf-super-rights>' . $prescribe_on_behalf_super_rights . '</prescribe-on-behalf-super-rights><physicianassistant>' . $physician_assistant . '</physicianassistant><physicianassistantsupervised>' . $physician_assistant_supervised . '</physicianassistantsupervised></user-profile>');
		return '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Header><AuthHeader xmlns="https://stscripts.allscripts.com/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap:Header><soap:Body><AddUser xmlns="https://stscripts.allscripts.com/"><UserProfile>' . $b64Xml . '</UserProfile></AddUser></soap:Body></soap:Envelope>';
	}

	public function createUpdateUserXml($user_id) {
		$user = CommonQueries::getUserById($user_id);
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		} else {
			$this->facility_uuid = CommonQueries::getUserFacility($user['id'])['veradigmSiteUUID'];
		}
		$is_admin = CommonQueries::isUserAdmin($user_id) ? 'Y' : 'N';
		$is_staff = mb_strtolower($user['veradigm_user_role']) === 'erxstaff' ? true : false;
		$is_doctor = mb_strtolower($user['veradigm_user_role']) === 'erxdoctor' ? 'Y' : 'N';
		if (!$user || ($is_doctor === 'Y' && !$user['federaldrugid'])) {
			return ['success' => false, 'error' => 'No user found or missing Federal Drug ID!'];
		}
		$result = $this->facilityCheck($user['facility_id']);
		if ($result && gettype($result) === 'array') {
			return $result;
		}
		$prescribe_on_behalf_rights = mb_strtolower($user['veradigm_user_role']) === 'erxprescribersomereview' ? 'Y' : 'N';
		$prescribe_on_behalf_limited_rights = mb_strtolower($user['veradigm_user_role']) === 'erxprescriberfullreview' ? 'Y' : 'N';
		$prescribe_on_behalf_super_rights = mb_strtolower($user['veradigm_user_role']) === 'erxprescribernoreview' ? 'Y' : 'N';
		$physician_assistant = mb_strtolower($user['veradigm_user_role']) === 'erxmidlevelwosupervision' ? 'Y' : 'N';
		$physician_assistant_supervised = mb_strtolower($user['veradigm_user_role']) === 'erxmidlevelwsupervision' ? 'Y' : 'N';
		$email_address = mb_strpos($user['email'], '@') === 0 ? $user['username'] . $user['email'] : $user['email'];
		$title = isset($user['title']) && $user['title'] ? '<title>' . $user['title'] . '</title>' : '<title></title>';
		$specialty = isset($user['specialty']) && $user['specialty'] ? '<specialty-cd>' . $user['specialty'] . '</specialty-cd>' : '';
		$suffix = isset($user['suffix']) && $user['suffix'] ? '<suffix>' . $user['suffix'] . '</suffix>' : '';
		$npi = isset($user['npi']) && $user['npi'] ? '<npi>' . $user['npi'] . '</npi>' : '';
		$state_license = '';
		if (!$is_staff && ((isset($user['state_license_number']) && $user['state_license_number']) || (isset($user['license_issue_state']) && $user['license_issue_state']) || (isset($user['state_expire_date']) && $user['state_expire_date']))) {
			$state_license .= '<state-licenses><state-license>';
			if (isset($user['state_license_number']) && $user['state_license_number']) {
				$state_license .= '<state-license-number>' . $user['state_license_number'] . '</state-license-number>';
			}
			if (isset($user['license_issue_state']) && $user['license_issue_state']) {
				$state_license .= '<issuing-state>' . $user['license_issue_state'] . '</issuing-state>';
			}
			if (isset($user['state_expire_date']) && $user['state_expire_date']) {
				$state_license .= '<state-license-expire-date>' . $user['state_expire_date'] . '</state-license-expire-date>';
			}
			$state_license .= '</state-license></state-licenses>';
		}
		if ($is_admin && !$npi) {
			$dea_info = '';
		} else {
			$dea_info = $user['federaldrugid'] ? '<dea>' . $user['federaldrugid'] . '</dea>' : '';
			$dea_info .= $user['dea_expire_date'] ? '<dea-expire-date>' . $user['dea_expire_date'] . '</dea-expire-date>' : '';
			$dea_info .= $user['dea_schedule_2'] === 'Y' ? '<dea-schedule-2>' . $user['dea_schedule_2'] . '</dea-schedule-2>' : '';
			$dea_info .= $user['dea_schedule_3'] === 'Y' ? '<dea-schedule-3>' . $user['dea_schedule_3'] . '</dea-schedule-3>' : '';
			$dea_info .= $user['dea_schedule_4'] === 'Y' ? '<dea-schedule-4>' . $user['dea_schedule_4'] . '</dea-schedule-4>' : '';
			$dea_info .= $user['dea_schedule_5'] === 'Y' ? '<dea-schedule-5>' . $user['dea_schedule_5'] . '</dea-schedule-5>' : '';
		}
		$b64Xml = base64_encode('<user-profile><partner-id>' . $this->partner_id . '</partner-id><user-guid>' . $user['veradigmUserUUID'] . '</user-guid><license-id>' . $this->facility_uuid . '</license-id><user-name>' . $user['username'] . '</user-name>' . $title . '<last-name>' . $user['lname'] . '</last-name><first-name>' . $user['fname'] . '</first-name><mi>' . mb_substr($user['mname'], 0, 1) . '</mi>' . $suffix . $specialty . '<email>' . $email_address . '</email>' . $npi . $dea_info . $state_license . '<admin-rights>' . $is_admin . '</admin-rights><provider-rights>' . $is_doctor . '</provider-rights><prescribe-on-behalf-rights>' . $prescribe_on_behalf_rights . '</prescribe-on-behalf-rights><prescribe-on-behalf-limited-rights>' . $prescribe_on_behalf_limited_rights . '</prescribe-on-behalf-limited-rights><prescribe-on-behalf-super-rights>' . $prescribe_on_behalf_super_rights . '</prescribe-on-behalf-super-rights><physicianassistant>' . $physician_assistant . '</physicianassistant><physicianassistantsupervised>' . $physician_assistant_supervised . '</physicianassistantsupervised></user-profile>');
		return '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Header><AuthHeader xmlns="https://stscripts.allscripts.com/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap:Header><soap:Body><UpdUser xmlns="https://stscripts.allscripts.com/"><UserProfile>' . $b64Xml . '</UserProfile></UpdUser></soap:Body></soap:Envelope>';
	}

	public function createPatientCcrXml() {
		$ccr = '<CCR><![CDATA[<ContinuityOfCareRecord xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="urn:astm-org:CCR"><CCRDocumentObjectID>1.3.6.1.4.1.22812.1.00000000000000000001.</CCRDocumentObjectID><PreferredLanguage><Code><CodingSystem>ISO</CodingSystem><Value>eng</Value></Code></PreferredLanguage><Body>';
		// Allergies
		$allergies = CommonQueries::GetAllergyList($this->patient_id);
		$allergies_array = [];
		if ($allergies) {
			$ccr .= '<Alerts>';
			foreach ($allergies as $allergy) {
				if (!isset($allergy['date']) || $allergy['subtype'] === 'Veradigm') {
					continue;
				}
				if (strlen($allergy['title']) < 3 || in_array($allergy['title'], $allergies_array)) {
					continue;
				}
				if (strtolower($allergy['title']) === 'no known drug allergies' || strtolower($allergy['title']) === 'nk' || strtolower($allergy['title']) === 'nka') {
					$allergen = 'No Known Allergies';
				}
				$rxnorm_code = $this->rxnorm->name2rxnorm($allergy['title']);
				if (!$rxnorm_code) {
					continue;
				}
				array_push($allergies_array, $allergy['title']);
				$ccr .= '<Alert><Type><Text>Allergy</Text></Type><Status><Text>Active</Text></Status><DateTime><Type><Text>Start Date</Text></Type><ExactDateTime>' . str_replace(' ', 'T', $allergy['date']) . 'Z</ExactDateTime></DateTime><Agent><Products><Product><Type><Text>Medication</Text></Type><Description><Code><Value>' . $rxnorm_code . '</Value><CodingSystem>RxNorm</CodingSystem></Code></Description></Product></Products></Agent>';
				$ccr .= '</Alert>';
			}
			$ccr .= '</Alerts>';
		}
		// Medications
		$medications = CommonQueries::getRecentMedications($this->patient_id);
		$med_ccr = '';
		$medications_array = [];
		if ($medications) {
			$med_ccr .= '<Medications>';
			foreach ($medications as $medication) {
				if (empty($medication['rxnorm_drugcode'])) {
					$rxnorm_code = $this->rxnorm->name2rxnorm($medication['drug']);
					if (!$rxnorm_code) {
						continue;
					}
					$medication['rxnorm_drugcode'] = $rxnorm_code;
				}
				if (in_array($medication['rxnorm_drugcode'], $medications_array)) {
					continue;
				}
				array_push($medications_array, $medication['rxnorm_drugcode']);
				$refill = $medication['refills'] ? $medication['refills'] : 0;
				$med_size = $medication['size'] ? $medication['size'] : 0;
				$med_ccr .= '<Medication><CCRDataObjectID/><Type><Text>Medication</Text></Type><FulfillmentInstructions><Instruction><Text/></Instruction></FulfillmentInstructions><DateTime><Type><Text>Prescription Date</Text></Type><ExactDateTime>' . $medication['date_added'] . 'T01:00:00 -05:00</ExactDateTime></DateTime><DateTime><Type><Text>Prescription Sent Date</Text></Type><ExactDateTime>' . $medication['date_added'] . 'T01:00:00 -05:00</ExactDateTime></DateTime><DateTime><Type><Text>Expiration Date</Text></Type><ExactDateTime>' . $medication['date_added'] . 'T01:00:00 -05:00</ExactDateTime></DateTime><IDs><ID>DB581A44-0D50-4A58-90B9-330024CED475</ID><Source><Actor><ActorID>Partner</ActorID></Actor></Source></IDs>';
				$med_ccr .= '<Description><Text>' . $medication['drug'] . '</Text></Description><Status><Text>New</Text></Status><Product><ProductName><Text>' . $medication['drug'] . '</Text><Code><Value>' . $medication['rxnorm_drugcode'] . '</Value><CodingSystem>RxNorm</CodingSystem></Code><Code><Value /><CodingSystem>Transmission Method</CodingSystem></Code></ProductName><Strength><Value>' . $med_size . '</Value><Units>1</Units></Strength><Form><Text>tablet</Text></Form></Product><Directions><Direction><Description><Text>Dose Unknown</Text></Description><Dose><Value>Dose Unknown</Value><Units><Unit/></Units></Dose><Route><Text/></Route></Direction></Directions><PatientInstructions><Instruction/></PatientInstructions><Refills><Refill><Number>' . $refill . '</Number><Status/></Refill></Refills></Medication>';
			}
			$med_ccr .= '</Medications>';
		}
		if ($med_ccr && $med_ccr !== '<Medications></Medications>') {
			$ccr .= $med_ccr;
		}
		// Diagnosis
		$med_ccr = '';
		$problems = CommonQueries::GetMedicalProblemList($this->patient_id);
		if ($problems) {
			$med_ccr .= '<Problems>';
			foreach ($problems as $problem) {
				if (!$problem['diagnosis']) {
					continue;
				}
				$icd10 = explode(':', $problem['diagnosis']);
				if (!$icd10[0] || !$icd10[1]) {
					continue;
				}
				// Skip any ICD9
				if ($icd10[0] == 'ICD9') {
					continue;
				}
				// Any inactive problems do not need to be sent
				if ($problem['enddate'] !== '0000-00-00' && $problem['enddate'] != '' ) {
					continue;
				}
				if ($problem['begdate'] === '0000-00-00') {
					continue;
				}
				$med_ccr .= '<Problem><Status><Text>Active</Text></Status><Description><Code><CodingSystem>' . $icd10[0] . '</CodingSystem><Value>' . $icd10[1] . '</Value></Code></Description><DateTime><Type><Text>Start Date</Text></Type><ExactDateTime>' . $problem['begdate'] . 'T00:00:00Z</ExactDateTime></DateTime>
				<DateTime><Type><Text>Resolved Date</Text></Type><ExactDateTime>' . $problem['begdate'] . 'T00:00:00Z</ExactDateTime></DateTime>';
				$med_ccr .= '</Problem>';
			}
			$med_ccr .= '</Problems>';
		}
		if ($med_ccr && $med_ccr !== '<Problems></Problems>') {
			$ccr .= $med_ccr;
		}
		if (!$allergies && !$medications && !$problems) {
			return '';
		}
		return $ccr . '</Body><Actors><Actor></Actor></Actors></ContinuityOfCareRecord>]]></CCR>';
	}

	public function createSavePatientXml() {
		$user = CommonQueries::getUserById($_SESSION['authUserID']);
		if (!$user) {
			return ['success' => false, 'error' => 'No user found!'];
		}
		if ($user && empty($user['veradigm_user_role'])) {
			return ['success' => true];
		}
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		} else {
			$this->facility_uuid = CommonQueries::getUserFacility($_SESSION['authUserID'])['veradigmSiteUUID'];
		}
		$is_doctor = mb_strtolower($user['veradigm_user_role']) === 'erxdoctor' ? 'Y' : 'N';
		if (!$user || ($is_doctor === 'Y' && !$user['federaldrugid'])) {
			return ['success' => false, 'error' => 'No user found or missing Federal Drug ID!'];
		}
		$result = $this->facilityCheck($user['facility_id']);
		if ($result && gettype($result) === 'array') {
			return $result;
		}
		$dob = DateTime::createFromFormat('m/d/Y', $this->patient['date_of_birth'])->format('Y-m-d');
		$mid_initial = $this->patient['minitial'] ? '<MiddleInitial>' . $this->patient['minitial'] . '</MiddleInitial>' : '';
		$address2 = false ? '<Address2></Address2>' : '';
		$weight = $this->patient['metric_weight'] ? '<Weight>' . $this->patient['metric_weight'] . '</Weight>' : '<Weight>0.0</Weight>';
		$height = $this->patient['metric_height'] ? '<Height>' . $this->patient['metric_height'] . '</Height>' : '<Height>0.0</Height>';
		$phone = '<Phone>' . preg_replace('/[^0-9]/u', '', $this->patient['phone']) . '</Phone>';
		$language = '<preferredLanguage>' . $this->patient['language'] . '</preferredLanguage>';
		$gender = $this->patient['sex'] ? mb_strtoupper(mb_substr($this->patient['sex'], 0, 1)) : 'U';
		$ccr = $this->createPatientCcrXml();
		return '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Header><AuthHeader xmlns="https://' . $this->api_xml_domain . '/partnerservices/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap12:Header><soap12:Body><SavePatient xmlns="https://' . $this->api_xml_domain . '/partnerservices/"><patient><PatientID><Value>' . $this->patient['pid'] . $this->mrn_suffix . '</Value><Type>MRN</Type><LicenseID>' . $this->facility_uuid . '</LicenseID></PatientID><LicenseID>' . $this->facility_uuid . '</LicenseID><MRN>' . $this->patient['pid'] . $this->mrn_suffix . '</MRN><FirstName>' . mb_substr($this->patient['fname'], 0, 35) . '</FirstName><LastName>' . mb_substr($this->patient['lname'], 0, 35) . '</LastName>' . $mid_initial . '<Address1>' . $this->patient['street'] . '</Address1>' . $address2 . '<City>' . $this->patient['city'] . '</City><State>' . $this->patient['state'] . '</State><ZIPCode>' . $this->patient['postal_code'] . '</ZIPCode>' . $phone . '<Gender>' . $gender . '</Gender><MedHistReq>Y</MedHistReq><DateOfBirth>' . $dob . '</DateOfBirth><Email>' . mb_substr($this->patient['email'], 0, 100) . '</Email>' . $ccr . '<Status>ACTIVE</Status>' . $weight . $height . $language . '<IsHealthPlanDisclosable>Y</IsHealthPlanDisclosable></patient></SavePatient></soap12:Body></soap12:Envelope>';
	}

	public function createUpdatePatientXml() {
		$user = CommonQueries::getUserById($_SESSION['authUserID']);
		if (!$user) {
			return ['success' => false, 'error' => 'No user found!'];
		}
		if ($user && empty($user['veradigm_user_role'])) {
			return ['success' => true];
		}
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		} else {
			$this->facility_uuid = CommonQueries::getUserFacility($user['id'])['veradigmSiteUUID'];
		}
		$is_doctor = mb_strtolower($user['veradigm_user_role']) === 'erxdoctor' ? 'Y' : 'N';
		if (!$user || ($is_doctor === 'Y' && !$user['federaldrugid'])) {
			return ['success' => false, 'error' => 'No user found or missing Federal Drug ID!'];
		}
		$result = $this->facilityCheck($user['facility_id']);
		if ($result && gettype($result) === 'array') {
			return $result;
		}
		$date = DateTime::createFromFormat('m/d/Y', $this->patient['date_of_birth']);
		$dob = $date->format('Y-m-d');
		$mid_initial = $this->patient['minitial'] ? '<MiddleInitial>' . $this->patient['minitial'] . '</MiddleInitial>' : '';
		$address2 = false ? '<Address2></Address2>' : '';
		$weight = $this->patient['metric_weight'] ? '<Weight>' . $this->patient['metric_weight'] . '</Weight>' : '<Weight>0.0</Weight>';
		$height = $this->patient['metric_height'] ? '<Height>' . $this->patient['metric_height'] . '</Height>' : '<Height>0.0</Height>';
		$phone = '<Phone>' . preg_replace('/[^0-9]/u', '', $this->patient['phone']) . '</Phone>';
		$language = '<preferredLanguage>' . $this->patient['language'] . '</preferredLanguage>';
		$gender = $this->patient['sex'] ? mb_strtoupper(mb_substr($this->patient['sex'], 0, 1)) : 'U';
		$ccr = $this->createPatientCcrXml();
		$this->save_ccr_file($ccr, 'Uploaded');
		return '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Header><AuthHeader xmlns="https://' . $this->api_xml_domain . '/partnerservices/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap12:Header><soap12:Body><SavePatient xmlns="https://' . $this->api_xml_domain . '/partnerservices/"><patient><PatientID><Value>' . $this->patient['p_uuid'] . '</Value><Type>GUID</Type><LicenseID>' . $this->facility_uuid . '</LicenseID></PatientID><LicenseID>' . $this->facility_uuid . '</LicenseID><PatientGUID>' . $this->patient['p_uuid'] . '</PatientGUID><MRN>' . $this->patient['pid'] . $this->mrn_suffix . '</MRN><FirstName>' . mb_substr($this->patient['fname'], 0, 35) . '</FirstName><LastName>' . mb_substr($this->patient['lname'], 0, 35) . '</LastName>' . $mid_initial . '<Address1>' . $this->patient['street'] . '</Address1>' . $address2 . '<City>' . $this->patient['city'] . '</City><State>' . $this->patient['state'] . '</State><ZIPCode>' . $this->patient['postal_code'] . '</ZIPCode>' . $phone . '<Gender>' . $gender . '</Gender><MedHistReq>Y</MedHistReq><DateOfBirth>' . $dob . '</DateOfBirth><Email>' . mb_substr($this->patient['email'], 0, 100) . '</Email>' . $ccr . '<Status>ACTIVE</Status>' . $weight . $height . $language . '<IsHealthPlanDisclosable>Y</IsHealthPlanDisclosable></patient></SavePatient></soap12:Body></soap12:Envelope>';
	}

	public function createGetPatientXml() {
		if ($this->globalsConfig->hasGlobalSiteLicense()) {
			$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
		} else {
			$this->facility_uuid = CommonQueries::getUserFacility($_SESSION['authUserID'])['veradigmSiteUUID'];
		}
		return '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Header><AuthHeader xmlns="https://' . $this->api_xml_domain . '/partnerservices/"><PartnerID>' . $this->partner_id . '</PartnerID><UserName>' . $this->username . '</UserName><Password>' . $this->password . '</Password></AuthHeader></soap12:Header><soap12:Body><GetPatient xmlns="https://' . $this->api_xml_domain . '/partnerservices/"><patientID><Value>' . $this->patient['p_uuid'] . '</Value><Type>GUID</Type></patientID><LicenseID>' . $this->facility_uuid . '</LicenseID></GetPatient></soap12:Body></soap12:Envelope>';
	}

	/* Submit SOAP-XML */

	public function submitAddSite($site) {
		$xml_str = $this->createAddSiteXml($site);
		if (!$xml_str) {
			$this->veradigm_error_log('Failed to create the AddSite XML!');
			return ['Failed to create the AddSite XML!'];
		}
		return $this->submitPostRegistration($xml_str, 'ADD SITE');
	}

	public function submitUpdateSite($site) {
		$xml_str = $this->createUpdateSiteXml($site);
		if (!$xml_str) {
			$this->veradigm_error_log('Failed to create the UpdateSite XML!');
			return ['Failed to create the UpdateSite XML!'];
		}
		return $this->submitPostRegistration($xml_str, 'UPDATE SITE');
	}

	public function saveSite($site) {
		if (!$site) {
			$facility = CommonQueries::getUserFacility($_SESSION['authId']);
			$site = $facility['site_id'];
			if ($this->globalsConfig->hasGlobalSiteLicense()) {
				$this->facility_uuid = $this->globalsConfig->getGlobalSiteLicense();
			} else {
				$this->facility_uuid = $facility['veradigmSiteUUID'];
			}
		}
		if (gettype($site) === 'integer' || is_string($site)) {
			$site = CommonQueries::getFacilityById($site);
			$this->veradigm_error_log("Facility/Site info:\n" . print_r($site, true) . "\n\n" . print_r($_POST, true) . "\n");
			if (!$site) {
				$this->checkResp4Errors(['No Site specified!']);
				return '';
			}
		}
		if ($site['add2veradigm'] && $site['veradigmSiteUUID']) {
			$data = $this->checkResp4Errors($this->submitUpdateSite($site));
			if (!$data) {
				return null;
			}
		} else {
			$data = $this->checkResp4Errors($this->submitAddSite($site));
			if (!$data) {
				return null;
			}
			$matches = [];
			if (gettype($data) === 'array') {
				$tmp_data = $data[0];
			} else {
				$tmp_data = $data;
			}
			preg_match('/&lt;license-id&gt;([A-Z0-9\-]+)&lt;\/license-id&gt;/u', $tmp_data, $matches, PREG_UNMATCHED_AS_NULL);
			if (!$matches || empty($matches[0])) {
				$this->veradigm_error_log("Failed to obtain the site UUID!\nResponse:\n" . $tmp_data);
				$this->checkResp4Errors(['Failed to obtain the site UUID!'], $data);
				return '';
			}
			$site_uuid = str_replace('&lt;/license-id&gt;', '', str_replace('&lt;license-id&gt;', '', $matches[0]));
			if (!$site_uuid) {
				$this->veradigm_error_log("Failed to obtain the site UUID!\nResponse:\n" . $tmp_data);
				$this->checkResp4Errors(['Failed to obtain the site UUID!']);
				return '';
			}
			if ($this->globalsConfig->hasGlobalSiteLicense()) {
				storeFacilityVeradigmInfo($site['id'], $this->globalsConfig->getGlobalSiteLicense());
			} else {
				storeFacilityVeradigmInfo($site['id'], $site_uuid);
			}
		}
		$facility_uuid = CommonQueries::getFacilityById($user['facility_id'])['veradigmSiteUUID'];
		$this->facility_uuid = $facility_uuid;
		return $facility_uuid;
	}

	public function submitAddUser($user_id) {
		if (!$user_id) {
			error_log('Failed to create the AddUser XML (no user ID given)!');
			return ['Failed to create the AddUser XML (no user ID given)!'];
		}
		$xml_str = $this->createAddUserXml($user_id);
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log('REQUEST (ADD USER ' . $user_id . "):\n" . $xml_str);
		}
		if (is_array($xml_str) && !$xml_str['success']) {
			return $xml_str['error'];
		} elseif (is_array($xml_str) && $xml_str['success']) {
			return true;
		} elseif (!$xml_str) {
			$this->veradigm_error_log('Failed to create the AddUser XML for "' . $user_id . '"!');
			return ['Failed to create the AddUser XML!'];
		}
		return $this->submitPostRegistration($xml_str, 'ADD USER');
	}

	public function submitUpdateUser($user_id) {
		if (!$user_id) {
			error_log('Failed to create the UpdateUser XML (no user ID given)!');
			return ['Failed to create the UpdateUser XML (no user ID given)!'];
		}
		$xml_str = $this->createUpdateUserXml($user_id);
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log('REQUEST (UPDATE USER' . $user_id . "):\n" . $xml_str);
		}
		if (is_array($xml_str) && !$xml_str['success']) {
			return $xml_str['error'];
		} elseif (!$xml_str) {
			$this->veradigm_error_log('Failed to create the UpdateUser XML for "' . $user_id . '"!');
			return ['Failed to create the UpdateUser XML!'];
		}
		return $this->submitPostRegistration($xml_str, 'UPDATE USER');
	}

	/** Save a User to Veradigm by passing in the user ID */
	public function saveUser($user_id) {
		if (!$user_id) {
			error_log('No user ID given for adding/updating the user to Veradigm!');
			return ['No user ID given for adding/updating the user!'];
		}
		if (isUserInVeradigm($user_id)) {
			return $this->checkResp4Errors($this->submitUpdateUser($user_id));
		}
		$data = $this->checkResp4Errors($this->submitAddUser($user_id));
		if (!$data) {
			return null;
		}
		$matches = [];
		if (is_array($data)) {
			$tmp_data = $data[0];
		} else {
			$tmp_data = $data;
		}
		preg_match('/<user-guid>([A-Z0-9\-]+)<\/user-guid>/u', $tmp_data, $matches, PREG_UNMATCHED_AS_NULL);
		if (!$matches || !isset($matches[0]) || !$matches[0]) {
			preg_match('/&lt;user-guid&gt;([A-Z0-9\-]+)&lt;\/user-guid&gt;/u', $tmp_data, $matches, PREG_UNMATCHED_AS_NULL);
			if (!$matches || !isset($matches[0]) || !$matches[0]) {
				$err_type = 'Failed to obtain the user UUID!';
				if (mb_strpos($tmp_data, 'User already exists.')) {
					$err_type = 'Failed to obtain the user UUID (User already exists)!';
				}
				$this->veradigm_error_log($err_type . "\nFAILED ADD USER RESPONSE:\n" . $tmp_data);
				return $this->checkResp4Errors(['Failed to obtain the user UUID!'], $data);
			}
		}
		$user_uuid = str_replace('&lt;/user-guid&gt;', '', str_replace('&lt;user-guid&gt;', '', str_replace('</user-guid>', '', str_replace('<user-guid>', '', $matches[0]))));
		if (!$user_uuid) {
			$err_type = 'Failed to obtain the user UUID!';
			if (mb_strpos($tmp_data, 'User already exists.')) {
				$err_type = 'Failed to obtain the user UUID (User already exists)!';
			}
			$this->veradigm_error_log($err_type . "\nFAILED ADD USER RESPONSE:\n" . $tmp_data);
			return $this->checkResp4Errors(['Failed to obtain the user UUID!']);
		}
		$this->veradigm_error_log("ADD USER RESPONSE:\n" . $tmp_data);
		if ($user_uuid) {
			storeUserVeradigmInfo($user_id, $user_uuid);
		}
		return $data;
	}

	/** Save a Patient to Veradigm by passing in the Patient ID */
	public function submitSavePatient($patient_id = 0) {
		if (!$this->setPatientId($patient_id)) {
			error_log('No patient was specified (submitSavePatient)!');
			return ['No patient was specified!'];
		}
		$xml_str = $this->createSavePatientXml();
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log('REQUEST (ADD PATIENT' . $patient_id . "):\n" . $xml_str);
		}
		if (is_array($xml_str) && !$xml_str['success']) {
			return $xml_str['error'];
		} elseif (!$xml_str) {
			$this->veradigm_error_log('Failed to create the SavePatient XML for updating the patient "' . $patient_id . '"!');
			return ['Failed to create the SavePatient XML!'];
		}
		return $this->submitPostPartner($xml_str, 'ADD PATIENT');
	}

	/** Update a Patient to Veradigm by passing in the Patient ID */
	public function submitUpdatePatient($patient_id = 0) {
		if (!$this->setPatientId($patient_id)) {
			error_log('No patient was specified (submitUpdatePatient)!');
			return ['No patient was specified!'];
		}
		$xml_str = $this->createUpdatePatientXml();
		if ($this->globalsConfig->extraLoggingEnabled()) {
			$this->veradigm_error_log('REQUEST (UPDATE PATIENT' . $patient_id . "):\n" . $xml_str);
		}
		if (is_array($xml_str) && !$xml_str['success']) {
			return $xml_str['error'];
		} elseif (!$xml_str) {
			$this->veradigm_error_log('Failed to create the SavePatient XML for updating the patient "' . $patient_id . '"!');
			return ['Failed to create the SavePatient XML for updating the patient!'];
		}
		return $this->submitPostPartner($xml_str, 'UPDATE PATIENT');
	}

	/** Save a Patient to Veradigm by passing in the Patient ID */
	public function savePatient($patient_id = 0) {
		if (!$this->setPatientId($patient_id)) {
			return $this->checkResp4Errors(['No patient was specified (savePatient)!']);
		}
		if (!isUserInVeradigm($_SESSION['authUserID'])) {
			if (!empty($_SESSION['authUserID'])) {
				$this->saveUser($_SESSION['authUserID']);
			} elseif ($this->patient['providerID']) {
				$this->saveUser($this->patient['providerID']);
			} else {
				return null;
			}
		}
		if (!isUserInVeradigm($_SESSION['authUserID'])) {
			return null;
		}
		if ($this->patient['add2veradigm']) {
			$resp = $this->submitUpdatePatient($this->patient_id);
			if (!$resp) {
				return null;
			}
			$this->manageCCR($resp[0]);
			return $this->checkResp4Errors($resp);
		}
		$data = $this->checkResp4Errors($this->submitSavePatient($this->patient_id));
		if (!$data) {
			return null;
		}
		$matches = [];
		if (is_array($data)) {
			$tmp_data = $data[0];
		} else {
			$tmp_data = $data;
		}
		preg_match('/Patient\.([A-Za-z0-9\-]+)\s*<\/ActorID>/u', $tmp_data, $matches, PREG_UNMATCHED_AS_NULL);
		if (!$matches || empty($matches[0])) {
			preg_match('/Patient\.([A-Za-z0-9\-]+)\s*&lt;\/ActorID&gt;/u', $tmp_data, $matches, PREG_UNMATCHED_AS_NULL);
			if (!$matches || !isset($matches[0]) || !$matches[0]) {
				$this->veradigm_error_log('Failed to obtain the patient UUID (PID: ' . $patient_id . ")!\nResponse:\n" . $tmp_data);
				return $this->checkResp4Errors(['Failed to obtain the patient UUID!'], $data);
			}
		}
		$patient_uuid = trim(str_replace('Patient.', '', str_replace('&lt;ActorID&gt;', '', str_replace('&lt;/ActorID&gt;', '', str_replace('</ActorID>', '', trim(str_replace('<ActorID>', '', $matches[0])))))));
		if (!$patient_uuid) {
			$this->veradigm_error_log('Failed to obtain the patient UUID (PID: ' . $patient_id . ")!\nResponse:\n" . $tmp_data);
			return $this->checkResp4Errors(['Failed to obtain the patient UUID!']);
		}
		storePatientVeradigmInfo($this->patient_id, $patient_uuid);
		$this->manageCCR($tmp_data);
		return $data;
	}

	public function getPatient($patient_id = 0) {
		if (!$this->setPatientId($patient_id)) {
			return $this->checkResp4Errors(['No patient was specified (getPatient)!']);
		}
		$xml_str = $this->createGetPatientXml();
		if (!$xml_str) {
			$this->veradigm_error_log('Failed to create the GetPatient XML!');
			return ['Failed to create the GetPatient XML!'];
		}
		$resp = $this->submitPostPartner($xml_str);
		if (!$resp) {
			return null;
		}
		$this->manageCCR($resp[0]);
		return $resp;
	}

	public function manageCCR(string $resp) {
		$this->save_ccr_file($resp, 'Downloaded');
		if (!$resp) {
			return;
		}
		$ccr_array = $this->parseCCR($resp);
		if (!$ccr_array) {
			return;
		}
		// Allergy
		$allergies_array = [];
		$this->allergies = CommonQueries::GetAllergyList($this->patient_id);
		foreach ($this->allergies as $allergy) {
			if (strlen($allergy['title']) < 3 || in_array($allergy['title'], $allergies_array)) {
				continue;
			}
			array_push($allergies_array, $allergy['title']);
		}
		$allergies_array = array_unique($allergies_array);
		// Medications
		$medications = CommonQueries::getRecentMedications($this->patient_id);
		$medications_array = [];
		foreach ($medications as $medication) {
			if (in_array(trim($medication['drug']), $medications_array)) {
				continue;
			}
			array_push($medications_array, trim($medication['drug']));
		}
		$medications_array = array_unique($medications_array);
		// Problems
		$problems = CommonQueries::GetMedicalProblemList($this->patient_id);
		$problems_array = [];
		foreach ($problems as $problem) {
			$icd10 = explode(':', $problem['diagnosis']);
			if ($icd10[0] === 'ICD9' || in_array($problem['diagnosis'], $problems_array)) {
				continue;
			}
			// Any inactive problems do not need to be sent
			if ($problem['enddate'] !== '0000-00-00' && $problem['enddate'] != '' ) {
				continue;
			}
			array_push($problems_array, $problem['diagnosis']);
		}
		$problems_array = array_unique($problems_array);
		$this->saveCCR($ccr_array, $allergies_array, $medications_array, $problems_array);
	}

	public function parseCCR(string $ccr): array {
		if (!$ccr) {
			return [];
		}
		$ccr_array = [];
		$problems = [];
		$allergies = [];
		$medications = [];
		$ccr = str_replace(' ;', ';', str_replace("\t", '', str_replace('  ', ' ', str_replace('   ', ' ', str_replace('    ', ' ', str_replace("\r", '', str_replace("\n", '', $ccr)))))));
		preg_match('/&lt;Problems&gt;(.+)&lt;\/Problems&gt;/u', $ccr, $problems, PREG_UNMATCHED_AS_NULL);
		preg_match('/&lt;Alerts&gt;(.+)&lt;\/Alerts&gt;/u', $ccr, $allergies, PREG_UNMATCHED_AS_NULL);
		preg_match('/&lt;Medications&gt;(.+)&lt;\/Medications&gt;/u', $ccr, $medications, PREG_UNMATCHED_AS_NULL);
		// Get Diagnosed Issues
		if ($problems && isset($problems[0]) && $problems[0]) {
			$problems_array = explode('&lt;/Problem&gt;', $problems[0]);
			$ccr_array['problems'] = [];
			foreach ($problems_array as $problem) {
				$diagnosis = [];
				preg_match('/&lt;Description&gt;(.+)&lt;\/Description&gt;/u', $problem, $diagnosis, PREG_UNMATCHED_AS_NULL);
				if (!$diagnosis || !isset($diagnosis[0])) {
					continue;
				}
				$diagnosis_name = [];
				$diagnosis_code = [];
				$diagnosis_system = [];
				preg_match('/&lt;Text&gt;([^;]+)&lt;\/Text&gt;/u', $diagnosis[0], $diagnosis_name, PREG_UNMATCHED_AS_NULL);
				preg_match('/&lt;Value&gt;([^;]+)&lt;\/Value&gt;/u', $diagnosis[0], $diagnosis_code, PREG_UNMATCHED_AS_NULL);
				preg_match('/&lt;CodingSystem&gt;([^;]+)&lt;\/CodingSystem&gt;/u', $diagnosis[0], $diagnosis_system, PREG_UNMATCHED_AS_NULL);
				if (isset($diagnosis_name[0]) && isset($diagnosis_code[0])) {
					$diagnosis_name = trim(str_replace('&lt;/Text&gt;', '', str_replace('&lt;Text&gt;', '', $diagnosis_name[0])));
					$diagnosis_code = trim(str_replace('&lt;/Value&gt;', '', str_replace('&lt;Value&gt;', '', $diagnosis_code[0])));
					$diagnosis_system = trim(str_replace('&lt;/CodingSystem&gt;', '', str_replace('&lt;CodingSystem&gt;', '', $diagnosis_system[0])));
					array_push($ccr_array['problems'], ['name' => $diagnosis_name, 'code' => $diagnosis_code, 'code_system' => $diagnosis_system]);
				}
			}
		}
		// Get Allergies
		if ($allergies && isset($allergies[0]) && $allergies[0]) {
			$allergies_array = explode('&lt;/Alert&gt;', $allergies[0]);
			$ccr_array['allergies'] = [];
			$ccr_array['allergies_obj'] = [];
			$ccr_array['allergies_eie'] = [];
			$ccr_array['allergies_inactive'] = [];
			foreach ($allergies_array as $problem) {
				$allergen = [];
				preg_match('/&lt;Description&gt;([\s]*)&lt;Text&gt;([^;]+)&lt;\/Text&gt;([\s]*)&lt;\/Description&gt;/u', $problem, $allergen, PREG_UNMATCHED_AS_NULL);
				if (!$allergen || !isset($allergen[0])) {
					continue;
				}
				// Medi-Span
				$allergen_medispan = [];
				preg_match('/&lt;Code&gt;([\s]*)&lt;Value&gt;([0-9]+)&lt;\/Value&gt;([\s]*)&lt;CodingSystem&gt;Medispan PAR ID&lt;\/CodingSystem&gt;([\s]*)&lt;\/Code&gt;/u', $problem, $allergen_medispan, PREG_UNMATCHED_AS_NULL);
				if ($allergen_medispan && isset($allergen_medispan[0])) {
					$allergen_medispan = trim($allergen_medispan[1]);
				} else {
					$allergen_medispan = null;
				}
				// RxNorm
				$allergen_rxnorm = [];
				preg_match('/&lt;Code&gt;([\s]*)&lt;Value&gt;([0-9]+)&lt;\/Value&gt;([\s]*)&lt;CodingSystem&gt;RxNorm&lt;\/CodingSystem&gt;([\s]*)&lt;\/Code&gt;/u', $problem, $allergen_rxnorm, PREG_UNMATCHED_AS_NULL);
				if ($allergen_rxnorm && isset($allergen_rxnorm[0])) {
					$allergen_rxnorm = trim($allergen_rxnorm[1]);
				} else {
					$allergen_rxnorm = null;
				}
				// Allergy Name
				$allergen = trim(str_replace('&lt;/Text&gt;', '', str_replace('&lt;Text&gt;', '', str_replace("\r", '', str_replace("\n", '', str_replace('&lt;/Description&gt;', '', str_replace('&lt;Description&gt;', '', $allergen[0])))))));
				if (strtolower($allergen) === 'no known drug allergies' || strtolower($allergen) === 'nk' || strtolower($allergen) === 'nka') {
					$allergen = 'No Known Allergies';
				}
				if (strlen($allergen) < 3) {
					continue;
				}
				array_push($ccr_array['allergies'], $allergen);
				if (strpos($problem, 'Entered in error') > 0) {
					array_push($ccr_array['allergies_eie'], $allergen);
				}
				if (!strpos($problem, '&lt;Text&gt;Active&lt;/Text&gt;')) {
					array_push($ccr_array['allergies_inactive'], $allergen);
				}
				$ccr_array['allergies_obj'] = ['name'];
				array_push($ccr_array['allergies_obj'], ['name' => trim($allergen), 'medispan' => $allergen_medispan, 'rxnorm' => $allergen_rxnorm]);
			}
			$ccr_array['allergies'] = array_unique($ccr_array['allergies']);
			$ccr_array['allergies_obj'] = array_unique($ccr_array['allergies_obj']);
			$ccr_array['allergies_eie'] = array_unique($ccr_array['allergies_eie']);
			$ccr_array['allergies_inactive'] = array_unique($ccr_array['allergies_inactive']);
		}
		// Get Medications
		if ($medications && isset($medications[0]) && $medications[0]) {
			$medications_array = explode('&lt;/Medication&gt;', $medications[0]);
			$ccr_array['medications'] = [];
			$med_list = [];
			$rxnorm_list = [];
			foreach ($medications_array as $medication) {
				$product_data = [];
				$medication = str_replace('  ', ' ', str_replace("\r", '', str_replace("\n", '', $medication)));
				if (strpos($medication, '&lt;Status&gt;&lt;Text&gt;Discontinued&lt;/Text&gt;&lt;/Status&gt;') !== false) {
					continue;
				} elseif (strpos($medication, '&lt;Status&gt;&lt;Text&gt;Completed&lt;/Text&gt;&lt;/Status&gt;') !== false) {
					continue;
				}
				preg_match('/&lt;Product&gt;(.+)&lt;\/Product&gt;/u', $medication, $product_data, PREG_UNMATCHED_AS_NULL);
				if (!$product_data || !isset($product_data[1])) {
					continue;
				}
				$product_data = $product_data[1];
				$product_name = [];
				preg_match('/&lt;ProductName&gt;(.+)&lt;\/ProductName&gt;/u', $product_data, $product_name, PREG_UNMATCHED_AS_NULL);
				if (!$product_name || !isset($product_name[1])) {
					continue;
				}
				$product_name = $product_name[1];
				$product_instruction = [];
				preg_match('/&lt;Direction&gt;[\s*]&lt;Description&gt;(.+)&lt;\/Description&gt;/u', $medication, $product_instruction, PREG_UNMATCHED_AS_NULL);
				$product_instruction = str_replace('  ', ' ', $product_instruction[1]);
				$uuid_data = [];
				$med_uuid = null;
				preg_match('/&lt;CCRDataObjectID&gt;([A-Fa-f0-9\-]+)&lt;\/CCRDataObjectID&gt;/u', $medication, $uuid_data, PREG_UNMATCHED_AS_NULL);
				if ($uuid_data && isset($uuid_data[1])) {
					$med_uuid = trim($uuid_data[1]);
				}
				// Medication Name
				$medication_name = [];
				$product_name = trim(str_replace(' TAB ', ' ' , str_replace(' CAP ; ', ' ' , str_replace(' CAP&lt;', '&lt;', $product_name))));
				preg_match('/&lt;Text&gt;([^;&]+)&lt;\/Text&gt;/u', $product_name, $medication_name, PREG_UNMATCHED_AS_NULL);
				if (isset($medication_name[1])) {
					$medication_name = trim(str_replace('DoseUnknown', '', str_replace('Dose Unknown', '', str_replace('Dose Unknown ;', '', $medication_name[1]))));
				}
				if (!$medication_name || in_array(strtolower($medication_name), $med_list) || $medication_name === 'Dose Unknown') {
					continue;
				}
				$medication_name2 = [];
				preg_match('(.+)&lt;\/Text&gt;(.+)/u', $medication_name, $medication_name2, PREG_UNMATCHED_AS_NULL);
				if (isset($medication_name2[1])) {
					$medication_name = $medication_name2[1];
				}
				//$medication_name2 = [];
				$med_dose = [];
				//if (!strpos($medication_name, '/')) {
				//	preg_match('/[\s]*(.+)[\s]*([0-9\-\.]+)[\s]*([MGmg]+)[\s]*(.*)[\s]*/u', $medication_name2, $med_dose, PREG_UNMATCHED_AS_NULL);
				//}
				/*$medication_name = $medication_name2[1];
				if ($med_dose && isset($med_dose[1])) {
					$med_dose = trim($med_dose[1]);
					$medication_name = trim(str_replace('  ', ' ', str_replace($med_dose, '', $medication_name)));
					$med_dose = strtolower($med_dose);
				}*/
				// Dose
				$temp_dose = [];
				$medication_dose = [];
				$medication_dose_unit = [];
				if (!$med_dose) {
					preg_match('/&lt;Strength&gt;(.+)&lt;\/Strength&gt;/u', $product_data, $medication_dose, PREG_UNMATCHED_AS_NULL);
					if (isset($medication_dose[1])) {
						preg_match('/&lt;Unit&gt;([^;&]+)&lt;\/Unit&gt;/u', $medication_dose[1], $medication_dose_unit, PREG_UNMATCHED_AS_NULL);
						preg_match('/&lt;Value&gt;([^;&]+)&lt;\/Value&gt;/u', $medication_dose[1], $temp_dose, PREG_UNMATCHED_AS_NULL);
						if (isset($temp_dose[1])) {
							$medication_dose = trim($temp_dose[1]);
						}
					}
					if (isset($medication_dose[1])) {
						$medication_dose = trim($medication_dose[1]);
					}
					if (isset($medication_dose_unit[1])) {
						$medication_dose_unit = trim($medication_dose_unit[1]);
					} else {
						$medication_dose_unit = null;
					}
				} else {
					$medication_dose = str_replace('mg', '', str_replace(' mg', '', $med_dose));
					$medication_dose_unit = 'mg';
				}
				if (!$medication_dose) {
					$medication_dose = null;
				}
				if (!$medication_dose_unit) {
					$medication_dose_unit = null;
				}
				// Codes
				$medication_rxnorm = [];
				preg_match('/&lt;Value&gt;([0-9]+)&lt;\/Value&gt;[\s*]&lt;CodingSystem&gt;RxNorm&lt;\/CodingSystem&gt;/u', $product_data, $medication_rxnorm, PREG_UNMATCHED_AS_NULL);
				if (isset($medication_rxnorm[1])) {
					$medication_rxnorm = trim($medication_rxnorm[1]);
				}
				if (!$medication_rxnorm) {
					preg_match('/&lt;CodingSystem&gt;RxNorm&lt;\/CodingSystem&gt;[\s*]&lt;Value&gt;([0-9]+)&lt;\/Value&gt;/u', $product_data, $medication_rxnorm, PREG_UNMATCHED_AS_NULL);
					if (isset($medication_rxnorm[1])) {
						$medication_rxnorm = trim($medication_rxnorm[1]);
					}
				}
				if (!$medication_rxnorm) {
					$medication_rxnorm = null;
				}
				if ($medication_rxnorm && in_array($medication_rxnorm, $rxnorm_list)) {
					continue;
				}
				array_push($rxnorm_list, $medication_rxnorm);
				// Refills
				$medication_refills = [];
				preg_match('/&lt;Refill&gt;[\s*]&lt;Number&gt;([0-9]+)&lt;\/Number&gt;[\s*]&lt;\/Refill&gt;/u', $medication, $medication_refills, PREG_UNMATCHED_AS_NULL);
				if (!$medication_refills || !isset($medication_refills[1])) {
					$medication_refills = null;
				} else {
					$medication_refills = $medication_refills[1];
				}
				// Instructions
				$medication_instructions = [];
				preg_match('/&lt;Text&gt;(.+)&lt;\/Text&gt;/u', $product_instruction, $medication_instructions, PREG_UNMATCHED_AS_NULL);
				if (!$medication_instructions || !isset($medication_instructions[1])) {
					$medication_instructions = null;
				} else {
					$medication_instructions = trim($medication_instructions[1], " \n\r\t\v\x00;");
				}
				// End
				array_push($med_list, strtolower($medication_name));
				array_push($ccr_array['medications'], ['name' => $medication_name, 'dose' => $medication_dose, 'unit' => $medication_dose_unit, 'rxnorm' => $medication_rxnorm, 'instructions' => $medication_instructions, 'refills' => $medication_refills, 'uuid' => $med_uuid]);
			}
		}
		return $ccr_array;
	}

	public function saveCCR(array $ccr_data, array $allergies_array, array $medications_array, array $problems_array) {
		/* if (isset($ccr_data['problems'])) {
			$ccr_data['problems'] = array_unique($ccr_data['problems']);
			foreach ($ccr_data['problems'] as $problem) {
				if (!in_array('ICD10:' . $problem['code'], $problems_array)) {
					$this->veradigm_error_log('Got a problem back we do not have: ' . $problem['code']);
				}
			}
		} */
		if (isset($ccr_data['allergies'])) {
			$ccr_data['allergies'] = array_unique($ccr_data['allergies']);
			$ccr_data['allergies_obj'] = array_unique($ccr_data['allergies_obj']);
			foreach ($ccr_data['allergies_obj'] as $allergy) {
				if (strtolower($allergy['name']) === 'no known drug allergies') {
					$allergy['name'] = 'No Known Allergies';
				}
				if (strlen($allergy['name']) <= 1) {
					continue;
				}
				if (!in_array($allergy['name'], $allergies_array) && !in_array($allergy['name'], $ccr_data['allergies_eie']) && !in_array($allergy['name'], $ccr_data['allergies_inactive'])) {
					CommonQueries::AddAllergy($this->patient_id, date('Y-m-d'), $allergy['name'], 'Veradigm', $allergy['rxnorm'], $allergy['medispan']);
				} elseif (in_array($allergy['name'], $ccr_data['allergies_inactive'])) {
					foreach ($this->allergies as $allergy_obj) {
						if ($allergy_obj['title'] === $allergy['name']) {
							CommonQueries::UpdateAllergy($this->patient_id, $allergy_obj['id'], $allergy_obj['comments'], $allergy_obj['begdate'], $allergy_obj['title'], $allergy_obj['reaction'], $allergy_obj['outcome'], '0', 'now', 'Veradigm', $allergy['rxnorm'], $allergy['medispan']);
						}
					}
				} else {
					CommonQueries::UpdateAllergy($this->patient_id, $allergy_obj['id'], $allergy_obj['comments'], $allergy_obj['begdate'], $allergy_obj['title'], $allergy_obj['reaction'], $allergy_obj['outcome'], '1', $allergy_obj['enddate'], 'Veradigm', $allergy['rxnorm'], $allergy['medispan']);
				}
			}
		}
		if (isset($ccr_data['medications'])) {
			CommonQueries::upsertPatientMedications($ccr_data['medications'], $this->patient_id, $this->patient['providerID'], 'Veradigm');
		}
	}
}
