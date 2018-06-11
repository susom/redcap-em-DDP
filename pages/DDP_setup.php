<?php
namespace Stanford\DDP;
/** @var  \Stanford\DDP\DDP $module **/

// Metadata Service
global $realtime_webservice_url_data, $realtime_webservice_url_metadata;

$metadata_url = $module->getUrl("pages/DDP_metadata_service.php", true, true);
$data_url = $module->getUrl("pages/DDP_data_service.php", true, true);

if ($realtime_webservice_url_metadata !== $metadata_url) {
    echo "Please update metadata webservice url to <pre>" .
        $realtime_webservice_url_metadata . "\n\n" .
        $metadata_url . "</pre>";
} else {
    echo "<pre>Metadata url is set!</pre>";
}

if ($realtime_webservice_url_data !== $data_url) {
    echo "Please update data webservice url to <pre>" .
        $realtime_webservice_url_data . "\n\n" .
        $data_url . "</pre>";
} else {
    echo "<pre>Data url is set!</pre>";
}


exit();


?>


