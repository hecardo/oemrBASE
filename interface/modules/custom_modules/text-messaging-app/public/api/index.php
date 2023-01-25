<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, Access-Control-Allow-Headers, Authorizations, X-Requested-With');

$ignoreAuth = true;
// Set $sessionAllowWrite to true to prevent session concurrency issues during authorization related code
$sessionAllowWrite = true;

require_once __DIR__ . "/../../../../../globals.php";
require_once __DIR__ . '/../../vendor/autoload.php';

use Juggernaut\App\Controllers\StoreTexts;
use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Common\Logging\EventAuditLogger;
use Juggernaut\App\Controllers\apiResponse;

$key = new CryptoGen();
if (!defined('CONST_INCLUDE_KEY')) {define('CONST_INCLUDE_KEY', $key->decryptStandard($GLOBALS['response_key']));}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$uri = explode('/', $uri);
$json = file_get_contents('php://input');

if ($uri[7] === 'reply') {
    $res = apiResponse::getResponse('200');
    $messageData = json_decode($json, true);
    $saveDate = new StoreTexts();
    if ($uri[8] === 'default') {
        $db = '';
    } else {
        $db = $uri[8];
    }
    $saveDate->saveText($messageData, $db);

    echo json_encode($res);
} else {
    $res = apiResponse::getResponse('400');
    echo json_encode($res);
}

EventAuditLogger::instance()->newEvent('text', '', '', 1, "Inbound Text received");


