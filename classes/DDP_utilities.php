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
        'd_insurance_num', 'd_certificate_num', 'd_vehicle_num', 'd_device_num');
    $privacy_filter = "[prj_protocol] = '" . $irb_num . "'";
    $privacy_data = REDCap::getData($module->getSystemSetting("new_privacy_pid"), 'array', null, $privacy_fields_new, null, null, false, false, false, $privacy_filter);
    if (!is_null($privacy_data) and !empty($privacy_data)) {
        $module->emLog("This is return from Redcap Privacy POST " .  json_encode($privacy_data));
        $privacy_record_id = array_keys($privacy_data)[0];
        $module->emLog("This is return from POST " . json_encode($privacy_record_id));

        // Convert the format to be the same as the old Privacy Report
        $full_name = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_full_name"][1] === '1')or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_full_name"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_full_name"][3] === '1') ? "1" : "0");
        $phone = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_telephone"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_telephone"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_telephone"][3] === '1') ? "1" : "0");
        $geography = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_geographic"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_geographic"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_geographic"][3] === '1') ? "1" : "0");
        $dates = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_dates"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_dates"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_dates"][3] === '1') ? "1" : "0");
        $email = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_email"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_email"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_email"][3] === '1') ? "1" : "0");
        $mrn = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_mrn"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_mrn"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_mrn"][3] === '1') ? "1" : "0");
        $insurance = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_insurance_num"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_insurance_num"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_insurance_num"][3] === '1') ? "1" : "0");
        $labs = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_lab_results"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_lab_results"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_lab_results"][3] === '1') ? "1" : "0");
        $billing = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_diag_proc"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_diag_proc"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_diag_proc"][3] === '1') ? "1" : "0");
        $clinical = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_medications"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_medications"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_medications"][3] === '1') ? "1" : "0");
        $nonPhi = (($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_demographics"][1] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_demographics"][2] === '1') or
            ($privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["d_demographics"][3] === '1') ? "1" : "0");
        $phi =  ((($full_name == "1") or ($phone == "1") or ($geography == "1") or ($dates == "1") or
            ($email == "1") or ($mrn == "1") or ($insurance == "1")) ? "1" : "0");

        $privacy = array("approved" => $privacy_data[$privacy_record_id][$module->getSystemSetting("new_privacy_event_id")]["approved"],
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

        return $privacy;
    } else {
        $module->emLog("Post error: " . json_encode($privacy_data));
    }

    // Privacy approval was not found in newer project so look through the old project.
    $module->emLog("Privacy approval was not found in 9883, checking 4734");
    $privacy_fields_old = array('approved', 'lab_results', 'billing_codes', 'clinical_records', 'demographic', 'phi');
    $privacy_filter = '[protocol] = "' . $irb_num . '"';
    $privacy_data = REDCap::getData($module->getSystemSetting("old_privacy_pid"), 'array', null, $privacy_fields_old, null, null, false, false, false, $privacy_filter);
    $privacy_record_id = array_keys($privacy_data)[0];
    return $privacy_data[$privacy_record_id][$module->getSystemSetting("old_privacy_event_id")];
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

?>


