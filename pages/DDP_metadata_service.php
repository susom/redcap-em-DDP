<?php
/**
 *
 * This script will retrieve the DDP Meta Data from the STARR database that this project (IRB Number) is approved for.
 * Each time the project changes the DDP mapping fields, this function will run to ensure the iRB/Privacy are still
 * up to date.
 *
 * For the DDP Demonstration projects, this function will read in a dummy file so users can see how DDP works.
 *
 * This function is called from the DDP base code and is specified on the REDCap Control Center page.  The link is
 * specified on the left hand sidebar called Dynamic Data Pull (DDP) - Custom. Once th IRB and privacy are verified,
 * a call to the STARR database is made to retrieve a list of available data fields. This list is returned to the
 * REDCap core DDP code which displays the list to the user during DDP setup.
 */

namespace Stanford\DDP;
/** @var \Stanford\DDP\DDP $module **/

use Stanford\DDP\DDP;

$starr_url = $module->getSystemSetting("starr_url") . "metadata";
$pid = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
$user = isset($_POST['user']) && !empty($_POST['user']) ? $_POST['user'] : null;
$redcap_url = isset($_POST['redcap_url']) && !empty($_POST['redcap_url']) ? $_POST['redcap_url'] : null;
$ddp_service = "DDP_metadata_service";

$server_host = SERVER_NAME;

if (((strpos($server_host, 'redcap') !== false) and ($pid == 15800)) or
    ((strpos($server_host, 'localhost') !== false) and ($pid == 33)) or
    ((strpos($server_host, 'redcap-dev') !== false) and ($pid == 14437))) {

    // Filename of demo meta data
    $filename = $module->getModulePath() . 'pages/DDP_data_dictionary_sample.txt';

    $metaData = readDemoDDPData($filename);

    header("Context-type: application/json");
    print $metaData;

} else {
    $now = date('Y-m-d H:i:s');
    $request_info = array(
        "project_id" => $pid,
        "starttime" => $now,
        "user" => $user
    );
    $module->emDebug("DDP Metadata request: " . json_encode($request_info));

    $IRBL = \ExternalModules\ExternalModules::getModuleInstance('irb_lookup');
    $irb_num = $IRBL->findIRBNumber($pid);
    $privacy_report = $IRBL->getPrivacySettings($irb_num, $pid);
    $module->emDebug("This is the returned attestation for irb $irb_num: " . json_encode($privacy_report));
    if (!$privacy_report["status"]) {
        $msg = "IRB has not been approved for IRB number " . $irb_num;
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    // Make sure privacy approved this request
    $privacy = $privacy_report["privacy"];
    if ($privacy['approved'] <> '1') {
        $msg = "Privacy has not approved your attestation for " . $irb_num;
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    // If this project is not approved for MRNs, they cannot use DDP
    if ($privacy['demographic']['phi_approved']['mrn'] <> '1') {
        $msg = "You are not approved for MRNs which is a requirement for DDP use";
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    // If we've gotten here, this project can be setup with DDP.  Look through the IRB/privacy list to see which
    // fields they are approved for in their project. To get labs, billing and medications, they must have dates
    // more specific than a year checked.
    $labs_ok = ((($privacy['lab_results'] == '1') and ($privacy['demographic']['phi_approved']['dates'] == '1')) ? "1" : "0");
    $billing_ok = ((($privacy['billing_codes'] == '1') and ($privacy['demographic']['phi_approved']['dates'] == '1')) ? "1" : "0");
    $medications_ok = ((($privacy['medications'] == '1') and ($privacy['demographic']['phi_approved']['dates'] == '1')) ? "1" : "0");
    $demo_nonphi_ok = (($privacy['demographic']['nonphi'] == '1') ? "1" : "0");
    $demo_phi_ok = (($privacy['demographic']['phi'] == '1') ? "1" : "0");

    // PHI items are special because they have to have approval for each category of fields
    if ($demo_phi_ok) {
        $metadata_phi_list = getApprovedPHIfields($privacy['demographic']['phi_approved']);
    } else {
        $metadata_phi_list = "";
    }

    $metadata_list = array("project_id" => $pid,
        "user" => $user,
        "redcap_url" => $redcap_url,
        "labs_ok" => $labs_ok,
        "billing_ok" => $billing_ok,
        "medications_ok" => $medications_ok,
        "demo_nonphi_ok" => $demo_nonphi_ok,
        "demo_phi_ok" => $demo_phi_ok,
        "demo_phi_approved" => $metadata_phi_list
    );

    $json_string = json_encode($metadata_list);
    $module->emDebug("DDP MetaData request to Vertx for $irb_num: " . $json_string);

    //Find the token from the external module
    $service = 'ddp';
    $DDP = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
    $token = $DDP->findValidToken($service);
    if ($token == false) {
        $msg = "Could not retrieve a valid token for DDP";
        packageError($ddp_service, $msg);
        print $msg;
        return;
    }

    $tsstart = microtime(true);

    // Post to STARR server to retrieve metadata from tris_rim database
    $header = array('Authorization: Bearer ' . $token,
        'Content-Type: application/json');

    $results = http_request("POST", $starr_url, $header, $json_string);

    $duration = round(microtime(true) - $tsstart, 1);
    $module->emLog("Finished metadata request (pid=$pid) taking duration $duration");
    $debug_info = array(
        "project_id" => $pid,
        "user" => $user,
        "redcap_url" => $redcap_url,
        "duration" => $duration,
        "results" => $results
    );

    // Since java is forcing us to add a key for the results, we have to strip off the key ["results"] before
    // re-encoding and sending back to Redcap
    $metaData = json_decode($results, true);
    header("Context-type: application/json");
    print json_encode($metaData["results"]);
}


?>


