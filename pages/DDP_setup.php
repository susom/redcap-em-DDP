<?php
namespace Stanford\DDP;
/** @var  \Stanford\DDP\DDP $module **/

// Metadata Service
global $realtime_webservice_url_data, $realtime_webservice_url_metadata;

$metadata_url = $module->getUrl("pages/DDP_metadata_service.php", true, true);
$data_url = $module->getUrl("pages/DDP_data_service.php", true, true);

if ($realtime_webservice_url_metadata !== $metadata_url) {
    echo "<b>Please update metadata webservice url to </b><pre>" .
        $realtime_webservice_url_metadata . "<br>" .
        "from <br>" .
        $metadata_url . "</pre>";
} else {
    echo "<b>Metadata url is set!</b><br>URL is: " . $metadata_url . "<br><br><br>";
}

if ($realtime_webservice_url_data !== $data_url) {
    echo "<b>Please update data webservice url to </b><pre>" .
        $realtime_webservice_url_data . "<br>" .
        "from <br>" .
        $data_url . "</pre>";
} else {
    echo "<b>Data url is set!</b><br>URL is: " . $data_url . "<br>";
}


exit();


?>


