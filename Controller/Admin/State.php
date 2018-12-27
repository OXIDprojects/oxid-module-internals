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

use OxidCommunity\ModuleInternals\Core\FixHelper as FixHelper;
use OxidCommunity\ModuleInternals\Core\InternalModule;
use OxidCommunity\ModuleInternals\Core\ModuleStateFixer;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Module\ModuleCache as ModuleCache;
use OxidEsales\Eshop\Core\Module\ModuleList as ModuleList;
use OxidEsales\Eshop\Core\Module\Module as Module;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Module internals tools.
 *
 * @author Oxid Community
 */

/**
 * Module state checker, compares module data across different storage levels (metadata file / database / configuration).
 */
class State extends AdminController
{

    /**
     * @var string
     */
    public $sTemplate = 'state.tpl';

    /** @var Module */
    protected $_oModule;

    /** @var ModuleStateFixer */
    protected $_oModuleFixHelper;

    /**
     * init current Module
     * State constructor.
     */
    public function __construct()
    {
        $this->getModule();
    }

    /**
     * @return ModuleStateFixer
     */
    public function getModuleFixHelper()
    {
        if ($this->_oModuleFixHelper === null) {
            $this->_oModuleFixHelper = $stateFixer = new ModuleStateFixer();
            $stateFixer->setDebugOutput(new NullOutput());
            $stateFixer->setOutput(new NullOutput());
        }

        return $this->_oModuleFixHelper;
    }

    /**
     * @param ModuleStateFixer $oModuleFixHelper
     */
    public function setModuleFixHelper($oModuleFixHelper)
    {
        $this->_oModuleFixHelper = $oModuleFixHelper;
    }

    /**
     * Get active module object.
     *
     * @return InternalModule
     */
    public function getModule()
    {
        if ($this->_oModule === null) {
            $sModuleId = $this->getEditObjectId();

            $this->addTplParam('oxid', $sModuleId);

            $module = oxNew(Module::class);
            $module->load($sModuleId);
            $this->_oModule = $module;
        }

        return $this->_oModule;
    }

    /**
     * Collect info about module state.
     *
     * @return string
     */
    public function render()
    {
        //valid for all metadata versions
        $module = $this->getModule();
        $state = $module->checkState();
        foreach ($state as $paramName => $paramValue) {
            $this->addTplParam($paramName, $paramValue);
        }


        $this->addTplParam(
            'sState',
            [
                -3 => 'sfatals',
                -2 => 'sfatalm',
                -1 => 'serror',
                0  => 'swarning',
                1  => 'sok',
            ]
        );

        return $this->sTemplate;
    }

    /**
     * Fix module version.
     */
    public function fix() {
        $this->getModuleFixHelper()->fix($this->getModule());
    }
    /**
     * Fix module settings.
     */
    public function fix_settings()
    {
        $this->getModuleFixHelper()->fixSettings();
    }

}
