<?php
/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 03.01.19
 * Time: 22:08
 */

namespace OxidCommunity\ModuleInternals\Core;


use OxidEsales\EshopCommunity\Core\Registry;
use Psr\Log\AbstractLogger;

class FallbackLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        \writeToLog("[$level]" . $message);
    }
}