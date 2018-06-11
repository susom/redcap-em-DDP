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


$_POST = json_decode(file_get_contents('php://input'), true);
DDP::log($_POST, "In DDP data service");

$pid = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
$user = isset($_POST['user']) && !empty($_POST['user']) ? $_POST['user'] : null;
$redcap_url = isset($_POST['redcap_url']) && !empty($_POST['redcap_url']) ? $_POST['redcap_url'] : null;
$id = isset($_POST['id']) && !empty($_POST['id']) ? $_POST['id'] : null;
$fields = isset($_POST['fields']) && !empty($_POST['fields']) ? $_POST['fields'] : null;

$now = date('Y-m-d H:i:s');
DDP::log("Starting data request (pid=$pid) at " . $now, "In DDP data service");

// Find the IRB number for this project
$irb_num = findIRBNumber($pid);
if (is_null($irb_num) or empty($irb_num)) {
    DDP::log ("Invalid IRB number " . $irb_num . " entered into project " . $pid, "DDP_data_service");
    exit();

}

// Make sure it is still valid before retrieving data
$valid = checkIRBValidity($irb_num, $pid);
if ($valid == false) {
    DDP::log( "IRB number is not valid - it might have lapsed or it might not be approved", "DDP_data_service");
    exit();
}

// Post to STARR server to retrieve data from tris_rim database
//STARR URL
$starr_url = $module->getSystemSetting("starr_url") . "data";
$secret = $module->getSystemSetting("secret");

//$starr_url = "http://localhost:8080/api/v1/ddp/data";
//$secret = "zclvkjwoijsaf3";

$data = array("project_id"          => $pid,
                "user"              => $user,
                "redcap_url"        => $redcap_url,
                "id"                => $id,
                "secret"            => $secret,
                "fields"            => $fields);
$json_data = json_encode($data);

$results = http_request("POST", $starr_url, null, $json_data);

$now = date('Y-m-d H:i:s');
DDP::log("Finished data request (pid=$pid) at " . $now, "In DDP data service");


print $results;
exit();


?>


