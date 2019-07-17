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
$user = isset($_POST['user']) && !empty($_POST['user']) ? $_POST['user'] : 'cron';
$redcap_url = isset($_POST['redcap_url']) && !empty($_POST['redcap_url']) ? $_POST['redcap_url'] : null;
$id = isset($_POST['id']) && !empty($_POST['id']) ? $_POST['id'] : null;
$fields = isset($_POST['fields']) && !empty($_POST['fields']) ? $_POST['fields'] : null;
$ddp_service = "DDP_data_service";

$server_host = SERVER_NAME;
if (((strpos($server_host, 'redcap') !== false) and ($pid == 15800)) or
    ((strpos($server_host, 'localhost') !== false) and ($pid == 33)) or
    ((strpos($server_host, 'redcap-dev') !== false) and ($pid == 14437))) {

    $module->emLog("Retrieve fake data for demo site, user $user and pid $pid");
    
    // Filename of demo meta data
    $filename = $module->getModulePath() . 'pages/DDP_data_sample.txt';

    $data = readDemoDDPData($filename);
    $allData = json_decode($data, true);
    $dataInTimestamp = findDataWithInTimestamp($allData[$id], $fields);
    $returnData = json_encode($dataInTimestamp);

    header("Context-type: application/json");
    print $returnData;

} else {

    $now = date('Y-m-d H:i:s');
    $request_info = array(
        "project_id" => $pid,
        "starttime" => $now,
        "user" => $user,
        "fields" => $fields
    );
    $module->emDebug("Starting data request for $id", $request_info);

    // Find the entered IRB number in the project setup page
    $IRBL = \ExternalModules\ExternalModules::getModuleInstance('irb_lookup');
    $irb_num = $IRBL->findIRBNumber($pid);
    if (is_null($irb_num) or empty($irb_num)) {
        $msg = "No IRB number is entered into project " . $pid;
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    // Check to see if the IRB is valid
    $valid = $IRBL->isIRBValid($irb_num, $pid);
    if ($valid == false) {
        $msg = "IRB number is not valid - it might have lapsed or it might not be approved";
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    // Find the token external module and retrieve our token for STARR Acess
    $service = 'ddp';
    $DDP = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
    $token = $DDP->findValidToken($service);
    if ($token == false) {
        $msg = "Could not connect to DDP data source";
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    // Package up our request
    $header = array('Authorization: Bearer ' . $token,
        'Content-Type: application/json');
    $data = array("project_id" => $pid,
        "user" => $user,
        "redcap_url" => $redcap_url,
        "id" => $id,
        "fields" => $fields);
    $json_data = json_encode($data);
    $module->emDebug("Sending this to Vertx: " . $json_data);

    // Capture start of our request
    $tsstart = microtime(true);

    // Make the API call to STARR DDP data service
    $starr_url = $module->getSystemSetting("starr_url") . "data";
    $results = http_request("POST", $starr_url, $header, $json_data);

    // Log how long this request took to complete
    $duration = round(microtime(true) - $tsstart, 1);
    $module->emDebug("Finished data request (pid=$pid) in $duration (microseconds) ", "In DDP data service");

    // For debugging purposes
    $debug_info = array(
        "project_id" => $pid,
        "user" => $user,
        "redcap_url" => $redcap_url,
        "duration" => $duration,
        "results" => $results
    );

    // Since java is forcing us to add a key for the results, we have to strip off the key ["results"] before
    // re-encoding and sending back to Redcap
    $module->emDebug("These are the results: " . json_encode($results));
    $data = json_decode($results, true);
    $jsonResults = json_encode($data["results"]);
    header("Context-type: application/json");
    print $jsonResults;
}


?>


