<?php


namespace OpenEMR\Modules\Veradigm;

use OpenEMR\Common\Http\HttpRestRequest;
use OpenEMR\Common\Http\HttpRestRouteHandler;
use OpenEMR\Modules\Veradigm\GlobalConfig;
use OpenEMR\RestControllers\RestControllerHelper;
use OpenEMR\Utils\CommonQueries;
use Particle\Validator\Validator;
use Psr\Http\Message\ResponseInterface;
use RestConfig;


/** Restful API Controller */
class Veradigm_RestController {

	/** Validate a patient ID */
	public function validate_pid($pid) {
		$validator = new Validator();
		$validator->required('pid')->numeric();
		return $validator->validate($pid);
	}

	/** Validate a facility ID */
	public function validate_fid($fid) {
		$validator = new Validator();
		$validator->required('fid')->numeric();
		return $validator->validate($fid);
	}

	/** Validate a user ID */
	public function validate_uid($uid) {
		$validator = new Validator();
		$validator->required('uid')->numeric();
		return $validator->validate($uid);
	}

	/** Sync patient data between Veradigm and ICE */
	public function sync_patient($pid) {
		if (!isUserInVeradigm($_SESSION['authId'])) {
			return RestControllerHelper::responseHandler('The user does not have Veradigm access', null, 400);
		}
		$validationResult = $this->validate_pid($pid);
		$validationHandlerResult = RestControllerHelper::validationHandler($validationResult);
		if (is_array($validationHandlerResult)) {
			return $validationHandlerResult;
		}
		$veradigmAPI = new veradigmAPI($GLOBALS, $pid);
		if (!$veradigmAPI->globalsConfig->isConfigured()) {
			return RestControllerHelper::responseHandler('The Veradigm plugin is disabled.', null, 403);
		}
		$veradigmAPI->savePatient($pid);
		return RestControllerHelper::responseHandler('Success', null, 200);
	}

	/** Submit facility data to Veradigm */
	public function send_facility($fid) {
		$validationResult = $this->validate_fid($fid);
		$validationHandlerResult = RestControllerHelper::validationHandler($validationResult);
		if (is_array($validationHandlerResult)) {
			return $validationHandlerResult;
		}
		if (isFacilityInVeradigm($fid)) {
			return RestControllerHelper::responseHandler('Success', null, 200);
		}
		$veradigmAPI = new veradigmAPI($GLOBALS);
		if (!$veradigmAPI->globalsConfig->isConfigured()) {
			return RestControllerHelper::responseHandler('The Veradigm plugin is disabled.', null, 403);
		}
		$veradigmAPI->saveSite($fid);
		return RestControllerHelper::responseHandler('Success', null, 200);
	}

	/** Submit user data to Veradigm */
	public function send_user($uid) {
		$validationResult = $this->validate_fid($uid);
		$validationHandlerResult = RestControllerHelper::validationHandler($validationResult);
		if (is_array($validationHandlerResult)) {
			return $validationHandlerResult;
		}
		$veradigmAPI = new veradigmAPI($GLOBALS);
		if (!$veradigmAPI->globalsConfig->isConfigured()) {
			return RestControllerHelper::responseHandler('The Veradigm plugin is disabled.', null, 403);
		}
		$fid = CommonQueries::getUserFacility($uid)['site_id'];
		if (isFacilityInVeradigm($fid)) {
			$veradigmAPI->saveSite($fid);
		}
		$veradigmAPI->saveUser($uid);
		return RestControllerHelper::responseHandler('Success', null, 200);
	}

	/** Access the Veradigm interface */
	public function enter_veradigm_interface(string $mode) {
		if (!$mode || !in_array($mode, ['PatientContext', 'PatientLockDownMode', 'StandardSSO', 'TaskMode', 'UtilityMode'])) {
			return RestControllerHelper::responseHandler('Invalid mode.', null, 400);
		}
		$veradigmAPI = new veradigmAPI($GLOBALS);
		if (!$veradigmAPI->globalsConfig->isConfigured()) {
			return RestControllerHelper::responseHandler('The Veradigm plugin is disabled.', null, 403);
		}
		if (!isUserInVeradigm($_SESSION['authId'])) {
			return RestControllerHelper::responseHandler('The user does not have Veradigm access', null, 400);
		}
		$b64Xml = $veradigmAPI->createSsoXml($mode)
		$pid = empty($_SESSION['pid']) ? 0 : $_SESSION['pid'];
		return RestControllerHelper::responseHandler('Success', ['mode' => $mode, 'pid' => $pid, 'redirect_url' => $veradigmAPI->api_sso, 'saml' => $b64Xml, 'script' => $this->getAssetPath() . 'js/veradigm.js', 'uid' => $_SESSION['authId']], 200);
	}

	/** Test if the user has the Veradigm interface buttons (an HTTP status code of `200` means true, otherwise false) */
	public function has_veradigm_buttons() {
		$veradigmAPI = new veradigmAPI($GLOBALS);
		if (!$veradigmAPI->globalsConfig->isConfigured()) {
			return RestControllerHelper::responseHandler('The Veradigm plugin is disabled.', null, 403);
		}
		if (!isUserInVeradigm($_SESSION['authId'])) {
			return RestControllerHelper::responseHandler('The user does not have Veradigm access', null, 400);
		}
		return RestControllerHelper::responseHandler('Success', null, 200);
	}
}
