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
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Module\ModuleCache as ModuleCache;
use OxidEsales\Eshop\Core\Module\ModuleList as ModuleList;
use OxidEsales\Eshop\Core\Module\Module as Module;
use OxidEsales\Eshop\Core\Registry;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Module internals tools.
 *
 * @author Oxid Community
 */

/**
 * Module state checker, compares module data across different storage levels (metadata file/database/configuration).
 */
class State extends AdminController
{

    /**
     * @var string
     */
    public $sTemplate = 'state.tpl';

    /** @var Module */
    protected $module;

    /**
     * init current Module
     * State constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->getModule();
    }


    /**
     * Get active module object.
     *
     * @return InternalModule
     */
    public function getModule()
    {
        if ($this->module === null) {
            $sModuleId = $this->getEditObjectId();

            $this->addTplParam('oxid', $sModuleId);

            $module = oxNew(Module::class);
            $module->load($sModuleId);
            $this->module = $module;
        }

        return $this->module;
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

        return $this->sTemplate;
    }


    /**
     * Fix module settings.
     */
    public function block()
    {
        $request = Registry::getRequest();
        $data = $request->getRequestParameter('data');
        DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->execute(
            "UPDATE oxtplblocks SET OXACTIVE = NOT OXACTIVE WHERE OXID = ?",
            [$data]
        );
    }
}
