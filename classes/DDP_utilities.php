<?php

namespace Stanford\DDP;
/** @var \Stanford\DDP\DDP $module **/

use \REDCap;

function findIRBNumber($pid) {
    // Find the IRB number for this project
    $query = "select project_irb_number from redcap_projects where project_id = " . $pid;
    $q = db_query($query);
    $results = db_fetch_row($q);
    if (is_null($results) or empty($results)) {
        return null;
    } else {
        // What should happen if there are more than one record with this IRB number?
        return $results[0];
    }
}

function checkIRBValidity($irb_num, $pid)
{
    $IRB = \ExternalModules\ExternalModules::getModuleInstance('irb_validity');
    return $IRB->isValid($irb_num, $pid);
}


function checkPrivacyReport($irb_num) {

    global $module;

    // There are 2 Redcap projects that hold Privacy approval: pid = 9883 for 2018 and newer Privacy approvals
    // and pid 4734 for Privacy Approvals before 2018.  First check 9883 to see if it exists there and if not,
    // go to 4734.
    $privacy_fields_new = array('approved', 'd_lab_results', 'd_diag_proc', 'd_medications', 'd_demographics',
        'd_full_name', 'd_geographic', 'd_dates', 'd_telephone', 'd_fax', 'd_email', 'd_ssn', 'd_mrn', 'd_beneficiary_num',
        'd_insurance_num', 'd_certificate_num', 'd_vehicle_num', 'd_device_num');
    $privacy_filter = '[prj_protocol] = "' . $irb_num . '"';
    $privacy_data = REDCap::getData($module->getSystemSetting("new_privacy_pid"), 'array', null, $privacy_fields_new, null, null, false, false, false, $privacy_filter);
    if (!is_null($privacy_data) and !empty($privacy_data)) {
        $privacy_record_id = array_keys($privacy_data)[0];

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
    }

    // Privacy approval was not found in newer project so look through the old project.
    $privacy_fields_old = array('approved', 'lab_results', 'billing_codes', 'clinical_records', 'demographic', 'phi');
    $privacy_filter = '[protocol] = "' . $irb_num . '"';
    $privacy_data = REDCap::getData($module->getSystemSetting("old_privacy_pid"), 'array', null, $privacy_fields_old, null, null, false, false, false, $privacy_filter);
    $privacy_record_id = array_keys($privacy_data)[0];
    return $privacy_data[$privacy_record_id][$module->getSystemSetting("old_privacy_event_id")];
}


function http_request($type, $url, $header, $body=null)
{
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
        DDP::log("Curl returned output: " . $response);
        DDP::log( "Curl returned error: " . $error);
        DDP::log("Curl info: " . json_encode($info));
        return false;
    } else {
        return $response;
    }
}

/*
function find_protocol($protocol_number) {
    //global $module;

    //$url = $module->getSystemSetting("irb_url");
    //$apiUrl = $url . $protocol_number;
    $apiUrl = "https://api.rit.stanford.edu/irb-validity/api/v1/protocol/".$protocol_number;
    DDP::log("This is the irb url: " . $apiUrl);
    return  invokeAPI($apiUrl);

}

function invokeAPI($apiUrl) {
    global $module;

    DDP::log("This is the token file path: " . $module->getModulePath() . "classes/token.json");
    $jsonToken = json_decode(file_get_contents($module->getModulePath() . "classes/token.json"));
    $todaysDate = date('Y-m-d');

    // token is only valid 24 hours so if it was issued yesterday it may need refreshing
    // logIt("PROBLEM refreshing API token ".print_r($jsonToken, true)." ".print_r($todaysDate,true) , "ERROR");
    if ( strcmp($todaysDate, $jsonToken->day ) != 0) {
        // dates are different, so refresh the token

        $data = array("refreshToken" => $jsonToken->refreshToken);
        $data_string = json_encode($data);
        DDP::log("Retrieving new token, refresh token: " . $jsonToken->refreshToken);

        $ch = curl_init('https://api.rit.stanford.edu/token/api/v1/refresh');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );


        $jsonTokenStr = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        DDP::log("From refresh, http code: " . $http_code);
        curl_close($ch);

        $jsonToken = json_decode($jsonTokenStr);
        DDP::log("Response from refresh: " . $jsonToken);

        if (isset($jsonToken->refreshToken) && strlen($jsonToken->refreshToken) > 0 &&
            isset($jsonToken->accessToken) && strlen($jsonToken->accessToken) > 0 ) {

            // now write out the resulting refresh and access tokens to the token file
            $data = array("day" => $todaysDate, "refreshToken" => $jsonToken->refreshToken, "accessToken" => $jsonToken->accessToken);
            file_put_contents("token.json", json_encode($data));

        } else {

            DDP::error("PROBLEM refreshing API token ".print_r($jsonToken, true));
            return false;
        }
    }

    // With a valid token, Invoke API for IRB Validity check
    $header = array('Authorization: Bearer '.$jsonToken->accessToken,
                    'Content-Type: application/json');
    $ch = curl_init( $apiUrl );
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $response = curl_exec( $ch );
    $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // See if this Get was successful
    $jsonResponse = json_decode($response,true);
    $this_protocol = $jsonResponse["protocols"][0];
    if ($info !== 200) {
        return false;
    } else {
        return $this_protocol;
    }
}
*/

?>


