<?php

namespace OxidCommunity\ModuleInternals\Controller;

use OxidCommunity\ModuleInternals\Core\OxidComposerModulesService;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\ModuleList as ModuleList;
use OxidEsales\Eshop\Core\Module\Module as Module;
use OxidEsales\Eshop\Core\SeoEncoder;
use OxidEsales\Eshop\Core\Request;

class CheckConsistency  extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    /**
     * @var string
     */
    public $sTemplate = 'checkconsistency.tpl';

    /** @var Module */
    protected $_oModule;

    public function init()
    {
        $oConfig  = Registry::get(Config::class);

        $sKey = Registry::get(Request::class)->getRequestParameter('key');

        //todo: add Exeception / Logging
        $utils = Registry::getUtils();
        if((bool)$oConfig->getConfigParam('blACActiveCompleteCheck') == false )
        {
            $utils->handlePageNotFoundError();
        }

        //todo: add Exeception / Logging
        if($sKey != $oConfig->getConfigParam('sACActiveCompleteKey'))
        {
            $utils->handlePageNotFoundError();
        }
    }

    /**
     * @return null|string
     */
    public function render()
    {
        $moduleService = Registry::get(OxidComposerModulesService::class);
        $aModules = $moduleService->getActiveModules();
        $aModuleChecks = array();
        /*
         * @var InternalModule $oModule
         */
        foreach($aModules as $oModule)
        {
            $aModule = $oModule->checkState();
            $sModId = $oModule->getId();
            $aModuleChecks[$sModId] = $aModule;
        }
        $this->_aViewData['aModules'] = $aModuleChecks;

        return $this->sTemplate;
    }


}