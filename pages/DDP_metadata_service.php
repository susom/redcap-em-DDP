<?php
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 9/13/2017
 * Time: 1:42 PM
 *
 * This script will retrieve the DDP Meta Data from the STARR database and create a JSON metadata file used
 * by Redcap DDP MetaData service. The metadata file will run on a weekly schedule or can be run manually when
 * new STARR fields are supported.
 *
 * A JSON file will be created and moved to the Redcap server so as to not use network resources for information
 * which remains fairly static.
 *
 * The format of the file is specified by Redcap with each accessible field having an entry:
 *  [ { "field"       : "SPEC",
 *      "label"       : "SPECIFICITY",
 *      "description" : "SPECIFICITY",
 *      "temporal"    : "1",
 *      "category"    : "Labs",
 *      "subcategory" : "BBTests",
 *      "identifier"  : "0" }]
 */

namespace Stanford\DDP;
/** @var \Stanford\DDP\DDP $module **/

$starr_url = $module->getSystemSetting("starr_url") . "metadata";
$pid = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
$user = isset($_POST['user']) && !empty($_POST['user']) ? $_POST['user'] : null;
$redcap_url = isset($_POST['redcap_url']) && !empty($_POST['redcap_url']) ? $_POST['redcap_url'] : null;

$now = date('Y-m-d H:i:s');
$request_info = array(
                "project_id" => $pid,
                "starttime"  => $now,
                "user"       => $user
                );
$module->log( json_encode($request_info), "Starting metadata request: ");

// Find the IRB number for this project
$irb_num = findIRBNumber($pid);

//Check to see if Protocol is valid using IRB Validity API
if (!is_null($irb_num) and !empty($irb_num)) {
    $valid = checkIRBValidity($irb_num, $pid);
    if (!$valid) {
        $msg = "IRB number " . $irb_num . " is not valid - might have lapsed or might not be approved";
        packageError($msg);
        print $msg;
        return;
    }
} else {
    $msg = "Invalid IRB number " . $irb_num . " entered into project " . $pid;
    packageError($msg);
    print $msg;
    return;
}

// Check to see if privacy has approved this IRB
// These are the categories (we are not retrieving all of them - only those in tris_rim.pat_map and tris_rim.rit* tables)
//      approved => 'Yes' (1), 'No' (0)
//      lab_results => 1 (Lab test results [non PHI]), 2 (Pathology reports [PHI])
//      billing_codes => 1 (ICDx, CPT, etc [non PHI])
//      clinical_records => 1 (Medication Orders [non PHI]), 2 (narrative documentation [PHI])
//      demographics => 1 (gender, race, height (latest), weight (latest), etc [non PHI]), 2 (HIPAA identifiers [PHI])
//      HIPAA identifiers => 1 (Names), 2 (SSN), 3 (telephone numbers), 4 (address), 5 (dates more precise than year),
//                           6 (FAX numbers), 7 (Email address), 8 (Medical record numbers), 9 (Health plan record numbers),
//                          10 (account numbers), 11 (certificate/license numbers), 13 (device identifiers and serial numbers),
//                          16 (biometric identifiers), 17 (full face photographic image), 18 (any other PHI value)
//
$privacy_report = checkPrivacyReport($irb_num);
if (is_null($privacy_report) or empty($privacy_report)) {
    $msg = "Cannot find a privacy record for IRB number " . $irb_num;
    packageError($msg);
    print $msg;
    return;
}

// Make sure privacy approved this request
if ($privacy_report['approved'] <> '1') {
    $msg = "Privacy has not approved your request for IRB number " . $irb_num;
    packageError($msg);
    print $msg;
    return;
}

// If this project is not approved for MRNs, they cannot use DDP
if ($privacy_report['phi']['8'] <> 1) {
    $msg = "You are not approved for MRNs which is a requirement for DDP use";
    packageError($msg);
    print $msg;
    return;
}

// If we've gotten here, this project can be setup with DDP.  Look through the IRB/privacy list to see which
// fields they are approved for in their project. To get labs, billing and medications, they must have dates
// more specific than a year checked.
$labs_ok = ((($privacy_report['lab_results']['1'] == '1') and ($privacy_report['phi']['5'] == '1')) ? "1" : "0");
$billing_ok = ((($privacy_report['billing_codes']['1'] == '1') and ($privacy_report['phi']['5'] == '1')) ? "1" : "0");
$medications_ok = ((($privacy_report['clinical_records']['1'] == '1') and ($privacy_report['phi']['5'] == '1')) ? "1" : "0");
$demo_nonphi_ok = (($privacy_report['demographic']['1'] == '1') ? "1" : "0");
$demo_phi_ok = (($privacy_report['demographic']['2'] == '1') ? "1" : "0");

// PHI items are special because they have to have approval for each category of fields
if ($demo_phi_ok) {
    $metadata_phi_list = getApprovedPHIfields($privacy_report['phi']);
} else {
    $metadata_phi_list = "";
}

$metadata_list = array( "project_id"        => $pid,
                        "user"              => $user,
                        "redcap_url"        => $redcap_url,
                        "labs_ok"           => $labs_ok,
                        "billing_ok"        => $billing_ok,
                        "medications_ok"    => $medications_ok,
                        "demo_nonphi_ok"    => $demo_nonphi_ok,
                        "demo_phi_ok"       => $demo_phi_ok,
                        "demo_phi_approved" => $metadata_phi_list
);

$json_string = json_encode($metadata_list);

//Find the token from the external module
$service = 'ddp';
$DDP = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
$token = $DDP->findValidToken($service);
if ($token == false) {
    $msg = "Could not retrieve a valid token for DDP";
    packageError($msg);
    print $msg;
    return;
}

$tsstart = microtime(true);

// Post to STARR server to retrieve metadata from tris_rim database
$header = array('Authorization: Bearer ' . $token,
                'Content-Type: application/json');

$results = http_request("POST", $starr_url, $header, $json_string);

$duration = round(microtime(true) - $tsstart, 1);
$module->log("Finished metadata request (pid=$pid) taking duration $duration");
$debug_info = array(
    "project_id" => $pid,
    "user"       => $user,
    "redcap_url" => $redcap_url,
    "duration"   => $duration,
    "results"    => $results
            );
$module->debug(json_encode($debug_info), "Results from DDP MetaData: ");

// Since java is forcing us to add a key for the results, we have to strip off the key ["results"] before
// re-encoding and sending back to Redcap
$metaData = json_decode($results, true);
header("Context-type: application/json");
print json_encode($metaData["results"]);


/*
 * Connect to loggers
 */
function log() {
    $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
    $emLogger->log($this->PREFIX, func_get_args(), "INFO");
}

function debug() {
    // Check if debug enabled
    if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "DEBUG");
    }
}

function error() {
    $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
    $emLogger->log($this->PREFIX, func_get_args(), "ERROR");
}

/*
 * Figure out which PHI fields the project is approved for
 */
function getApprovedPHIfields($phi_report) {
    // Make an array of the approved PHI items
    $approved_categories = '';
    foreach ($phi_report as $item => $value) {
        if ($value == '1') {
            $approved_categories .= ',' . $item;
        }
    }

    return substr($approved_categories, 1);
}

/*
 * Use this when sending message to error logger
 */

function packageError($msg) {
    global $module;

    $error_info = array(
        "service" => "DDP_metadata_service",
        "message" => $msg
    );
    $module->error($error_info);

}


?>


