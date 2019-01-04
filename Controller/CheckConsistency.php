<?php

namespace OxidCommunity\ModuleInternals\Controller;

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
        $oConfig  = Registry::get(Config::class);
        $aModules = $this->_getActiveModules($oConfig->getConfigParam('aDisabledModules'),$oConfig->getConfigParam('aModulePaths'));
        $aModuleChecks = array();
        $oModule = oxNew(Module::class);
        /*
         * @var InternalModule $oModule
         */
        foreach($aModules as $sModId => $sTitle)
        {
            $oModule->load($sModId);
            $aModule = $oModule->checkState($sTitle);

            $aModuleChecks[$sModId] = $aModule;
        }
        $this->_aViewData['aModules'] = $aModuleChecks;

        return $this->sTemplate;
    }

    /**
     * @param array $aDisabledModules
     * @param array $aModulePaths
     *
     * @return array
     */
    protected function _getActiveModules(array $aDisabledModules, array $aModulePaths)
    {
        $oConfig  = Registry::get(Config::class);
        $aModulePaths = array_flip($aModulePaths);
        $aActiveModules = array_diff($aModulePaths,$aDisabledModules);

        $aTmpActiveModules = array_flip($aActiveModules);

        $aActiveModules = array();
        $oModule = oxNew(Module::class);
        $oSeoEncoder = oxNew(SeoEncoder::class);
        foreach($aTmpActiveModules as $sKey => $sValue)
        {
            $oModule->load($sKey);
            //Version einbinden
            $aVersions = $oConfig->getConfigParam('aModuleVersions');
            $sTitle = $oModule->getTitle().' - v'.$aVersions[$oModule->getId()];
            $aActiveModules[$sKey] = utf8_encode($oSeoEncoder->encodeString(strip_tags($sTitle)));
        }

        $sModulesDir = $oConfig->getModulesDir();

        $oModuleList = oxNew(ModuleList::Class);
        $aModules = $oModuleList->getModulesFromDir($sModulesDir);

        $aTmpModules = $aActiveModules;
        $aActiveModules = array();

        /* Sortieren, nach der Anzeige im Admin zum einfacheren Vergleich*/
        foreach($aModules as $oModule)
        {
            if(array_key_exists($oModule->getId(),$aTmpModules))
            {
                $aActiveModules[$oModule->getId()] = $aTmpModules[$oModule->getId()];
            }
        }

        return $aActiveModules;
    }
}