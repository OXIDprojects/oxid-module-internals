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
     * @return string
     */
    protected function _doStartUpChecks()
    {
        $aMessage = parent::_doStartUpChecks();

        $this->checkModules($aMessage);

        return $aMessage;
    }

    /**
     * @param $aMessage
     */
    public function checkModules(& $aMessage)
    {
        $moduleService = Registry::get(OxidComposerModulesService::class);
        $aModules = $moduleService->getActiveModules();
        $stateFixer = Registry::get(ModuleStateFixer::class);
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
    }

}