<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights reserved
 */

namespace Juggernaut\App\Model;


class NotificationModel
{

    public function getPatientTextMessages(): array
    {
        $sql = "SELECT `tmm`.`date`, `tmm`.`fromnumber`, `tmm`.`text`, CONCAT(pd.fname, ' ', pd.lname) AS name " .
        " FROM `text_message_module` tmm ";
        if (!empty($_SESSION['pid'])) {
            $sql .= " JOIN `patient_data` pd ON CONCAT('+1', REPLACE(`pd`.`phone_cell`, '-', '')) = `tmm`.`fromnumber`";
            $sql .= " WHERE `tmm`.`fromnumber` = '+1" . str_replace("-", "", $this->getPatientCell()['phone_cell'])
                 . "' ORDER BY `tmm`.`id` DESC LIMIT 25";
        } else {
            $sql .= " JOIN `patient_data` pd ON CONCAT('+1', REPLACE(`pd`.`phone_cell`, '-', '')) = `tmm`.`fromnumber`";
            $sql .= " ORDER BY `tmm`.`id` DESC LIMIT 25";
        }
        $source = sqlStatement($sql);
        $dataArray = [];
        while($row = sqlFetchArray($source)) {
            $dataArray[] = $row;
        }

        return $dataArray;
    }

    /**
     * @return array|false|null
     */
    public function getPatientCell()
    {
        $sql = "SELECT `phone_cell` FROM `patient_data` WHERE `pid` = ? ";
        return sqlQuery($sql, [$_SESSION['pid']]);
    }

    public function createMeetingId()
    {
        $newmeetingid = sqlQuery("select DOB from patient_data where pid = ?", [$_SESSION['pid']]);
        return md5($newmeetingid['DOB'] . $_SESSION['pid']);
    }

}
