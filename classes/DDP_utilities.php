<?php

namespace Stanford\DDP;
/** @var \Stanford\DDP\DDP $module **/

use \REDCap;

function findIRBNumber($pid) {
    // Find the IRB number for this project
    // Check to make sure pid is an int
    $query = "select project_irb_number from redcap_projects where project_id = " . intval($pid);
    $q = db_query($query);
    $results = db_fetch_row($q);
    if (is_null($results) or empty($results)) {
        return null;
    } else {
        return $results[0];
    }
}

function checkIRBValidity($irb_num, $pid)
{
    $tokenMgnt = \ExternalModules\ExternalModules::getModuleInstance('irb_lookup');
    return $tokenMgnt->isIRBValid($irb_num, $pid);
}


function checkPrivacyReport($irb_num) {

    global $module;

    // There are 2 Redcap projects that hold Privacy approval: pid = 9883 for 2018 and newer Privacy approvals
    // and pid 4734 for Privacy Approvals before 2018.  First check 9883 to see if it exists there and if not,
    // go to 4734.
    $privacy_fields_new = array('approved', 'd_lab_results', 'd_diag_proc', 'd_medications', 'd_demographics',
        'd_full_name', 'd_geographic', 'd_dates', 'd_telephone', 'd_fax', 'd_email', 'd_ssn', 'd_mrn', 'd_beneficiary_num',
        'd_insurance_num', 'd_certificate_num', 'd_vehicle_num', 'd_device_num', 'approval_date');
    $privacy_filter = "[prj_protocol] = '" . $irb_num . "' and [approved] = 1";
    $privacy_data = REDCap::getData($module->getSystemSetting("new_privacy_pid"), 'array', null, $privacy_fields_new, null, null, false, false, false, $privacy_filter);
    if (!is_null($privacy_data) and !empty($privacy_data)) {

        $last_modified_date = array();

        // Check to see which record has the last approval and select that one
        $privacy_event_id = $module->getSystemSetting("new_privacy_event_id");
        foreach ($privacy_data as $privacy_record_num => $privacy_record) {
            $last_modified_date[$privacy_record_num] = $privacy_record[$privacy_event_id]['approval_date'];

        }
        // Sorting is most recent first so take the first record
        arsort($last_modified_date);
        $privacy_record_id = array_keys($last_modified_date)[0];

        $privacy_record = $privacy_data[$privacy_record_id][$privacy_event_id];
        $module->emLog("This is latest record: " . json_encode($privacy_record));
        $module->emLog("Privacy record ID " . $privacy_record_id . ", and event id: " . $module->getSystemSetting("new_privacy_event_id"));

        // Convert the format to be the same as the old Privacy Report
        $full_name  = (($privacy_record["d_full_name"][1] === '1')or
                        ($privacy_record["d_full_name"][2] === '1') or
                        ($privacy_record["d_full_name"][3] === '1') ? "1" : "0");
        $phone      = (($privacy_record["d_telephone"][1] === '1') or
                        ($privacy_record["d_telephone"][2] === '1') or
                        ($privacy_record["d_telephone"][3] === '1') ? "1" : "0");
        $geography  = (($privacy_record["d_geographic"][1] === '1') or
                        ($privacy_record["d_geographic"][2] === '1') or
                        ($privacy_record["d_geographic"][3] === '1') ? "1" : "0");
        $dates      = (($privacy_record["d_dates"][1] === '1') or
                        ($privacy_record["d_dates"][2] === '1') or
                        ($privacy_record["d_dates"][3] === '1') ? "1" : "0");
        $email      = (($privacy_record["d_email"][1] === '1') or
                        ($privacy_record["d_email"][2] === '1') or
                        ($privacy_record["d_email"][3] === '1') ? "1" : "0");
        $mrn        = (($privacy_record["d_mrn"][1] === '1') or
                        ($privacy_record["d_mrn"][2] === '1') or
                        ($privacy_record["d_mrn"][3] === '1') ? "1" : "0");
        $insurance  = (($privacy_record["d_insurance_num"][1] === '1') or
                        ($privacy_record["d_insurance_num"][2] === '1') or
                        ($privacy_record["d_insurance_num"][3] === '1') ? "1" : "0");
        $labs       = (($privacy_record["d_lab_results"][1] === '1') or
                        ($privacy_record["d_lab_results"][2] === '1') or
                        ($privacy_record["d_lab_results"][3] === '1') ? "1" : "0");
        $billing    = (($privacy_record["d_diag_proc"][1] === '1') or
                        ($privacy_record["d_diag_proc"][2] === '1') or
                        ($privacy_record["d_diag_proc"][3] === '1') ? "1" : "0");
        $clinical   = (($privacy_record["d_medications"][1] === '1') or
                        ($privacy_record["d_medications"][2] === '1') or
                        ($privacy_record["d_medications"][3] === '1') ? "1" : "0");
        $nonPhi     = (($privacy_record["d_demographics"][1] === '1') or
                        ($privacy_record["d_demographics"][2] === '1') or
                        ($privacy_record["d_demographics"][3] === '1') ? "1" : "0");
        $phi =  ((($full_name == "1") or ($phone == "1") or ($geography == "1") or ($dates == "1") or
            ($email == "1") or ($mrn == "1") or ($insurance == "1")) ? "1" : "0");

        $privacy = array("approved" => $privacy_record["approved"],
                        "phi" => array( "1" => $full_name,
                                        "3" => $phone,
                                        "4" => $geography,
                                        "5" => $dates,
                                        "7" => $email,
                                        "8" => $mrn,
                                        "9" => $insurance
                            ),
                        "lab_results" => array("1" => $labs),
                        "billing_codes" => array("1" => $billing),
                        "clinical_records" => array("1" => $clinical),
                        "demographic" => array( "1" => $nonPhi,
                                                "2" => $phi)
        );

        $module->emLog("Return from privacy report 9883: " . json_encode($privacy));
        return $privacy;
    } else {
        $module->emLog("Privacy approval was not found in 9883, checking 4734");
    }

    // Privacy approval was not found in newer project so look through the old project.
    $privacy_fields_old = array('approved', 'lab_results', 'billing_codes', 'clinical_records', 'demographic', 'phi');
    $privacy_filter = '[protocol] = "' . $irb_num . '" and [approved] = 1';
    $privacy_data = REDCap::getData($module->getSystemSetting("old_privacy_pid"), 'array', null, $privacy_fields_old, null, null, false, false, false, $privacy_filter);
    if (!is_null($privacy_data) and !empty($privacy_data)) {
        $privacy_record_id = array_keys($privacy_data)[0];
        $module->emLog("Found privacy approval for IRB " . $irb_num . " in Privacy Project 4734");
        return $privacy_data[$privacy_record_id][$module->getSystemSetting("old_privacy_event_id")];
    } else {
        $module->emError("Privacy approval was not found in 4734.");
        return null;
    }

}


function http_request($type, $url, $header, $body=null)
{
    global $module;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($type == "GET") {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } else if ($type == "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
    }
    if (!is_null($body) and !empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if (!is_null($header) and !empty($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

   if (!empty($error) or ($info["http_code"] !== 200)) {
        $module->emLog("Curl returned output: " . $response);
        $module->emLog( "Curl returned error: " . $error);
        $module->emLog("Curl info: " . json_encode($info));
        return false;
    } else {
        return $response;
    }
}

function readDemoDDPData($filename) {
    global $module;

    // Open a handle to the file
    $handle = fopen($filename, "r");
    if (empty($handle) or is_null($handle)) {
        // Couldn't open a handle, something is wrong with the file
        $module->emError("Error opening file = " . $filename . ". Return code is: " . $handle . "\n");
        return null;

    } else {
        $metaData = file_get_contents($filename);
    }

    // Close the file
    fclose($handle);

    //return the data from the file
    return $metaData;
}


function findDataWithInTimestamp($arrayData, $fields) {
    global $module;

    $minTime = $maxTime = '';
    $requestedFields = array();
    // See if a min and max timestamp was given
    foreach ($fields as $onefield => $fieldData) {
        $requestedFields[] = $fieldData["field"];
        if (!empty($fieldData["timestamp_min"])) {
            $minTime = strtotime($fieldData["timestamp_min"]);
            $maxTime = strtotime($fieldData["timestamp_max"]);
        }
    }

    // Only return data within the timestamp range
    $inRangeData = array();
    if (!empty($minTime) and !empty($maxTime)) {
        foreach($arrayData as $onefield => $fieldData) {
            $timestamp = strtotime($fieldData["timestamp"]);

            // If this is one of the requested fields, continue
            if (in_array($fieldData["field"], $requestedFields) !== false) {
                if (!empty($timestamp)) {
                    if (($minTime <= $timestamp) and (($maxTime >= $timestamp))) {
                        $inRangeData[] = $fieldData;
                    }
                } else {
                    $inRangeData[] = $fieldData;
                }
            }
        }
    } else {
        // Only send back the fields that were requested
        foreach($arrayData as $onefield => $fieldData) {
            if (in_array($fieldData["field"], $requestedFields) !== false) {
                $inRangeData[] = $fieldData;
            }
        }
    }

    return $inRangeData;
}

/*
 * Figure out which PHI fields the project is approved for
 */
function getApprovedPHIfields($phi_report) {

    // Make an array of the approved PHI items
    $approved_categories = '';

    foreach ($phi_report as $item => $value) {

        if ($value == '1') {

            if ($approved_categories != '') {
                $approved_categories .= ',';
            }

            switch ($item) {
                case "fullname":
                    $approved_categories .= 1;
                    break;
                case "ssn":
                    $approved_categories .= 2;
                    break;
                case "phone":
                    $approved_categories .= 3;
                    break;
                case "geography":
                    $approved_categories .= 4;
                    break;
                case "dates":
                    $approved_categories .= 5;
                    break;
                case "fax":
                    $approved_categories .= 6;
                    break;
                case "email":
                    $approved_categories .= 7;
                    break;
                case "mrn":
                    $approved_categories .= 8;
                    break;
                case "insurance":
                    $approved_categories .= 9;
                    break;
                case "accounts":
                    $approved_categories .= 10;
                    break;
                case "license":
                    $approved_categories .= 11;
                    break;
                case "deviceids":
                    $approved_categories .= 13;
                    break;
                case "biometric":
                    $approved_categories .= 16;
                    break;
                case "photos":
                    $approved_categories .= 17;
                    break;
                case "other":
                    $approved_categories .= 18;
                    break;
            }

        }
    }

    return $approved_categories;
}


/*
 * Use this when sending message to error logger
 */

function packageError($service, $msg) {

    global $module;
    $error_info = array(
        "service" => $service,
        "message" => $msg
    );
    $module->emError($error_info);
}

?>


