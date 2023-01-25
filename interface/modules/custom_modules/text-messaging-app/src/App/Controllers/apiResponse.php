<?php
/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\App\Controllers;

class apiResponse
{
    public static function getResponse($varRespCode)
    {
        switch ($varRespCode) {

            case '200':
                $success = TRUE;
                $response = '200';
                $responseDescription = 'The text received';
                break;

            case '400':
                $success = FALSE;
                $response = '400';
                $responseDescription = 'The URI is in correct';
                break;
            default:
                $success = TRUE;
                $response = '000';
                $responseDescription = 'Unknown application response request.';
        }

        return ['success' => $success, 'response' => $response, 'responseDescription' => $responseDescription];
    }
}
