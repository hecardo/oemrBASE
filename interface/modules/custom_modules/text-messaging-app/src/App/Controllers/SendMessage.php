<?php

/**
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

namespace Juggernaut\App\Controllers;

use OpenEMR\Common\Crypto\CryptoGen;


class SendMessage
{

    public static function outBoundMessage(int $phone, string $message) : string
    {
        $key = self::getKey();
        $webhook = self::buildWebHookUrl();  //return path for inbound messages

        if (empty($key)) {
            return 'Please enter a valid key in the globals';
        }
        $ch = curl_init('https://textbelt.com/text');
        $data = array(
            'phone' => $phone,
            'message' => $message,
            'replyWebhookUrl' => $webhook,
            'key' => $key,
        );

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * @return false|string
     */
    private static function getKey()
    {
        $key = new CryptoGen();
        return $key->decryptStandard($GLOBALS['texting_enables']);
    }

    public static function buildWebHookUrl()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $http = "https://";
        } else {
            $http = "http://";
        }
        return $http . $_SERVER['HTTP_HOST'] . $GLOBALS['webroot'] .
            '/interface/modules/custom_modules/text-messaging-app/public/api/reply/' . $_SESSION['site_id'];
    }

}
