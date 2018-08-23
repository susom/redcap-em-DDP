<?php

namespace Stanford\DDP;
/** @var \Stanford\DDP\DDP $module **/

/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 6/1/2018
 * Time: 9:58 AM
 */

require_once ("classes/DDP_utilities.php");
use \REDCap;

class DDP extends \ExternalModules\AbstractExternalModule
{

    public function __construct()
    {
        parent::__construct();
    }


    public function emLog($obj = "Here", $detail = null, $type = "INFO") {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "INFO");
    }

    public function emDebug($obj = "Here", $detail = null, $type = "DEBUG") {
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    public function emError($obj = "Here", $detail = null, $type = "ERROR") {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }

}