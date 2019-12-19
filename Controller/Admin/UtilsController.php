<?php

/**
 * @package   moduleinternals
 * @category  OXID Module
 * @version   1.0.1
 * @license   GPL3 License http://opensource.org/licenses/GPL
 * @author    Alfonsas Cirtautas / OXID Community
 * @link      https://github.com/OXIDprojects/ocb_cleartmp
 * @see       https://github.com/acirtautas/oxid-module-internals
 */

namespace OxidCommunity\ModuleInternals\Controller\Admin;

use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleCache;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;

/**
 * Module internals tools.
 *
 * @author Oxid Community
 */

/**
 * Internal module utilities.
 */
class UtilsController extends \OxidEsales\Eshop\Application\Controller\Admin\AdminController
{

    /** @var oxModule */
    protected $module;

    /** @var oxModuleCache */
    protected $moduleCache;

    /** @var oxModuleInstaller */
    protected $moduleInstaller;

    /**
     * @var string
     */
    public $sTemplate = 'utils.tpl';

    /**
     * Get active module object.
     *
     * @return oxModule
     */
    public function getModule()
    {
        if ($this->module === null) {
            $sModuleId = $this->getEditObjectId();

            $this->addTplParam('oxid', $sModuleId);
            $this->module = oxNew(Module::class);
            $this->module->load($sModuleId);
        }

        return $this->module;
    }

    /**
     * Returns initialized cache instance
     *
     * @return oxModuleCache
     */
    public function getModuleCache()
    {
        if ($this->moduleCache === null) {
            $this->moduleCache = oxNew(ModuleCache::class, $this->getModule());
        }

        return $this->moduleCache;
    }

    /**
     * Returns initialized module installer instance
     *
     * @return oxModuleInstaller
     */
    public function getModuleInstaller()
    {
        if ($this->moduleInstaller === null) {
            $this->moduleInstaller = oxNew(ModuleInstaller::class, $this->getModuleCache());
        }

        return $this->moduleInstaller;
    }

    /**
     * @return string
     */
    public function render()
    {
        $oModule = $this->getModule();
        $sModuleId = $oModule->getId();

        $this->addTplParam('oxid', $sModuleId);
        $this->addTplParam('blIsActive', $oModule->isActive());

        return $this->sTemplate;
    }

    /**
     * Reset module cache.
     */
    public function resetModuleCache()
    {
        $this->getModuleCache()->resetCache();
    }

    /**
     * Activate module.
     */
    public function activateModule()
    {
        $this->getModuleInstaller()->activate($this->getModule());
    }

    /**
     * Deactivate module.
     */
    public function deactivateModule()
    {
        $this->getModuleInstaller()->deactivate($this->getModule());
    }
}
