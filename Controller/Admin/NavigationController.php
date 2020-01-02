<?php

/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 02.01.19
 * Time: 22:34
 */

namespace OxidCommunity\ModuleInternals\Controller\Admin;

use OxidCommunity\ModuleInternals\Core\ModuleStateFixer;
use OxidCommunity\ModuleInternals\Core\OxidComposerModulesService;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Registry;

class NavigationController extends NavigationController_parent
{
    /**
     * Every Time Admin starts we perform these checks
     * returns some messages if there is something to display
     *
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     *
     * @return array
     */
    protected function _doStartUpChecks()
    {
        /** @var array $aMessage */
        $aMessage = parent::_doStartUpChecks();

        $aMessage = array_merge($aMessage, $this->checkModules());

        return $aMessage;
    }

     /**
     * @return array
     */
    public function checkModules()
    {
        $moduleService = Registry::get(OxidComposerModulesService::class);
        $aModules = $moduleService->getActiveModules();
        $stateFixer = Registry::get(ModuleStateFixer::class);
        $stateFixer->cleanUp();
        $aMessage = array();
        
        /*
         * @var InternalModule $oModule
         */
        foreach ($aModules as $oModule) {
            $sTitle = $oModule->getTitle();
            $oModule->checkState();
            $link = Registry::getSession()->processUrl("?&cl=module");
            if ($oModule->hasIssue()) {
                $aMessage['warning'] .= "<p><a href=\"$link\">Module $sTitle has Issues</a></p>";
            }
            if ($stateFixer->fix($oModule)) {
                $aMessage['warning'] .= "<p><a href=\"$link\">Module $sTitle was fixed</a></p>";
            }
        }
        return $aMessage;
    }
}
