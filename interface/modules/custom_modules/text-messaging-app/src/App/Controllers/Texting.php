<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All rights reserved
 */

namespace Juggernaut\App\Controllers;


use Juggernaut\App\Model\NotificationModel;

class Texting extends SendMessage
{

    public static function bulk(): void
    {
        echo "<title>Texting Results</title>";
        $numbers = $_POST['pnumbers'];
        if (!str_contains($numbers, ",")) {
            die(xlt('Please use a comma separated list'));
        }
        $messagesbody = $_POST['message'];
        $individuals = explode(",", $numbers);
        foreach ($individuals as $individual) {
            if(empty($individual)) {
                continue; //The plan on using it for single messages to patients
            }
            $individual = str_replace("-", "", $individual);
            $response = parent::outBoundMessage($individual, $messagesbody);
            $results = json_decode($response, true);

            echo self::messageResultsDisplay($results);
        }
    }

    //one way message
    public function sendTelehealthMessage()
    {
        $data = new NotificationModel();
        $patientNumber = $data->getPatientCell();
        if (!empty($patientNumber)) {
            $patientNumber = str_replace('-', '', $patientNumber['phone_cell']);
            $outboundMessage = self::telehealthMessageBody() .
                self::getTextFacilityInfo()['name'] . ' ' .
                self::meetingLink();
            $balance = self::balanceDue();
        if ($balance > 0) {
            $outboundMessage .= self::balanceMessage() . " $" . $balance;
        }
            $response = parent::outBoundMessage((int)$patientNumber, $outboundMessage);
            $results = json_decode($response, true);

            echo self::messageResultsDisplay($results) . ' <br>' . $patientNumber ;
        }
    }

    public function individualPatient(): string
    {
        return parent::outBoundMessage((int)$_POST['phone'], $_POST['messageoutbound']);
    }

    private function telehealthMessageBody()
    {
        return xlt("By clicking the link below, you are consenting to the telehealth service that is being provided. ") .
            xlt(" Please call office at ") . self::getTextFacilityInfo()['phone'] . ". \n ";
    }

    private function meetingLink()
    {
        $data = new NotificationModel();
        return "https://" .
            $_SERVER['SERVER_NAME'] .
            "/interface/jitsi/jitsi.php?room=" .
            $data->createMeetingId() . "&pid=" . $_SESSION['pid'];
    }

    private function getTextFacilityInfo()
    {
        return sqlQuery("select `name`, `phone` from `facility` where `id` = 3");
    }

    private static function messageResultsDisplay($results): string
    {
        if ($results['success'] === true) {
            return " Successful, message ID " . $results['textId'] .
                " <br>Remaining message " . $results['quotaRemaining'] . " <br>Alert support when this get to 20";
        } else {
            return " Message failed " . $results['error'];
        }
    }

    private function balanceDue()
    {
        require_once dirname(__FILE__, 8) . "/library/patient.inc";
        return get_patient_balance_excluding($_SESSION['pid']);
    }

    private function balanceMessage(): string
    {
        return xlt(" There is a balance due on your account. Please log into the patient portal and remit payment");
    }
}
