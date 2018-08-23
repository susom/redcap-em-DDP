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
/** @var  \Stanford\DDP\DDP $module **/

use Stanford\DDP\DDP;

// Retrieve request from user
$_POST = json_decode(file_get_contents('php://input'), true);

$pid = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
$user = isset($_POST['user']) && !empty($_POST['user']) ? $_POST['user'] : null;
$redcap_url = isset($_POST['redcap_url']) && !empty($_POST['redcap_url']) ? $_POST['redcap_url'] : null;
$id = isset($_POST['id']) && !empty($_POST['id']) ? $_POST['id'] : null;
$fields = isset($_POST['fields']) && !empty($_POST['fields']) ? $_POST['fields'] : null;

$now = date('Y-m-d H:i:s');
$request_info = array(
    "project_id" => $pid,
    "starttime"  => $now,
    "user"       => $user,
    "fields"     => $fields
);
$module->emLog("Starting data request", $request_info);

// Find the IRB number for this project
$irb_num = findIRBNumber($pid);
if (is_null($irb_num) or empty($irb_num)) {
    $msg = "Invalid IRB number " . $irb_num . " entered into project " . $pid;
    packageError($msg);
    print $msg;
    return;
}

// Make sure IRB is still valid before retrieving data
$valid = checkIRBValidity($irb_num, $pid);
if ($valid == false) {
    $msg = "IRB number is not valid - it might have lapsed or it might not be approved";
    packageError($msg);
    print $msg;
    return;
}

// Find the token external module and retrieve our token for STARR Acess
$service = 'ddp';
$DDP = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
$token = $DDP->findValidToken($service);
if ($token == false) {
    $msg = "Could not connect to DDP data source";
    packageError($msg);
    print $msg;
    return;
}

// Package up our request
$header = array('Authorization: Bearer ' . $token,
                'Content-Type: application/json');
$data = array("project_id"          => $pid,
                "user"              => $user,
                "redcap_url"        => $redcap_url,
                "id"                => $id,
                "fields"            => $fields);
$json_data = json_encode($data);

// Capture start of our request
$tsstart = microtime(true);

// Make the API call to STARR DDP data service
$starr_url = $module->getSystemSetting("starr_url") . "data";
$results = http_request("POST", $starr_url, $header, $json_data);

// Log how long this request took to complete
$duration = round(microtime(true) - $tsstart, 1);
$module->emLog("Finished data request (pid=$pid) in $duration (microseconds) ", "In DDP data service");

// For debugging purposes
$debug_info = array(
    "project_id" => $pid,
    "user"       => $user,
    "redcap_url" => $redcap_url,
    "duration"   => $duration,
    "results"    => $results
);
$module->emLog($debug_info, "Results from DDP Data: ");

// Since java is forcing us to add a key for the results, we have to strip off the key ["results"] before
// re-encoding and sending back to Redcap
$data = json_decode($results, true);
$jsonResults =  json_encode($data["results"]);
header("Context-type: application/json");
print $jsonResults;


/*
 * Connect to loggers
 */
/*
function emLog() {
    $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
    $emLogger->log($this->PREFIX, func_get_args(), "INFO");
}

function emDebug() {
    // Check if debug enabled
    if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "DEBUG");
    }
}

function emError() {
    $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
    $emLogger->log($this->PREFIX, func_get_args(), "ERROR");
}
*/
/*
 * Use this when sending message to error logger
 */

function packageError($msg) {
    global $module;
    $error_info = array(
        "service" => "DDP_data_service",
        "message" => $msg
    );
    $module->emError($error_info);
}


?>


