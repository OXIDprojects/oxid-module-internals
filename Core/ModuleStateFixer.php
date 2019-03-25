<?php

namespace OxidCommunity\ModuleInternals\Core;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\SettingsHandler;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;
use OxidEsales\Eshop\Core\Module\ModuleCache;
use OxidEsales\Eshop\Core\Exception\ModuleValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use OxidEsales\Eshop\Core\Module\ModuleVariablesLocator;
/**
 * Module state fixer
 */
class ModuleStateFixer extends ModuleInstaller
{

    public function __construct($cache = null, $cleaner = null){
        $cleaner = oxNew(ModuleExtensionCleanerDebug::class);
        $this->output = $this->getLogger();
        parent::__construct($cache, $cleaner);
    }

    /**
     * @var $output LoggerInterface
     */
    protected $output;

    protected $needCacheClear = false;
    protected $initialCacheClearDone = false;
    protected $initialDone = false;
    protected $isRunning = false;

    /**
     * @var null|Module $module
     */
    protected $module = null;

    protected $dryRun = false;


    protected $moduleList;
    protected $modules;


    public function setConfig($config)
    {
        parent::setConfig($config);
        Registry::set(\OxidEsales\Eshop\Core\Config::class, $config);
    }

    public function disableInitialCacheClear(){
        $this->initialCacheClearDone = true;
    }

    protected function init()
    {
        if (!$this->initDone) {
            if ($this->isRunning) {
                return false;
            }
            $this->isRunning = true;
            $this->moduleList = Registry::get('oxModuleList');
            $this->moduleList->getModulesFromDir(Registry::getConfig()->getModulesDir());
            $this->modules = $this->moduleList->getList();
            $this->initDone = true;

            if (!$this->initialCacheClearDone) {

                //clearing some cache to be sure that fix runs not against a stale cache
                ModuleVariablesLocator::resetModuleVariables();
                if (extension_loaded('apc') && ini_get('apc.enabled')) {
                    apc_clear_cache();
                }
                $this->output->debug("initial cache cleared");
                $this->initialCacheClearDone = true;
            }
            $this->isRunning = false;
        }
        return true;
    }


    /**
     * Fix module states task runs version, extend, files, templates, blocks,
     * settings and events information fix tasks
     *
     * @param Module      $module
     * @param Config|null $oConfig If not passed uses default base shop config
     */
    public function fix($module, $oConfig = null)
    {
        if ($oConfig !== null) {
            $this->setConfig($oConfig);
        }

        $moduleId = $module->getId();
        $this->needCacheClear = false;

        if ($this->init()) {
            $this->module = $module;
            $this->restoreModuleInformation($module, $moduleId);
        }
        $somethingWasFixed = $this->needCacheClear;
        $this->clearCache($module);
        return $somethingWasFixed;
    }

    /**
     * After fixing all modules call this method to clean up trash that is not related to any installed module
     */
    public function cleanUp() {
        if ($this->init()) {
            $this->cleanUpControllers();
            $this->cleanUpExtensions();
        }
    }

    /**
     * remove extensions that are not registered by any module
     */
    public function cleanUpExtensions(){

        //get all extions from all metadata
        $oxModuleList = $this->moduleList;
        $aModules = $oxModuleList->getList();

        //get extensions from metadata file
        $moduleClassesMf = [];
        foreach ($aModules as $module) {
            $extensions = $module->getExtensions();
            foreach ($extensions as $moduleClass) {
                $moduleClassesMf[$moduleClass] = 1;
            }
        }

        //get all extesions from db
        $extensionChainDb = Registry::getConfig()->getConfigParam('aModules');
        $extensionChainDb = $oxModuleList->parseModuleChains($extensionChainDb);

        //calculate trash as extensions that are only in db
        foreach ($extensionChainDb as $oxidClass => &$arrayOfExtendingClasses) {

            foreach ($arrayOfExtendingClasses as $key => $extendingClass){
                if (!isset($moduleClassesMf[$extendingClass])) {
                    $this->output->warning("module extension trash found: '$extendingClass'' (registered for $oxidClass)");
                    unset($arrayOfExtendingClasses[$key]);
                }
            }
            $arrayOfExtendingClasses = array_values($arrayOfExtendingClasses);
            if (empty($arrayOfExtendingClasses)) {
                unset($extensionChainDb[$oxidClass]);
            }
        }

        if (!$this->dryRun) {
            $extensionChainDb = $this->buildModuleChains($extensionChainDb);
            $this->_saveToConfig('aModules', $extensionChainDb);
        }

    }

    /**
     * Add module template files to config for smarty.
     *
     * @param array  $aModuleTemplates Module templates array
     * @param string $sModuleId        Module id
     */
    protected function _addTemplateFiles($aModuleTemplates, $sModuleId)
    {
        $aTemplates = (array) Registry::getConfig()->getConfigParam('aModuleTemplates');
        $old = isset($aTemplates[$sModuleId]) ? $aTemplates[$sModuleId] : null;
        if (is_array($aModuleTemplates)) {
            $diff = $this->diff($old,$aModuleTemplates);
            if ($diff) {
                $what = $old === null ? ' everything ' :  var_export($diff, true);
                $this->output->warning("$sModuleId fixing templates");
                $this->output->debug(" $what");
                $aTemplates[$sModuleId] = $aModuleTemplates;
                $this->_saveToConfig('aModuleTemplates', $aTemplates);
                $this->needCacheClear = true;
            }
        } else {
            if ($old) {
                $this->output->warning("$sModuleId unregister templates:");
                $this->_deleteTemplateFiles($sModuleId);
                $this->needCacheClear = true;
            }
        }
    }


    /**
     * Add module files to config for auto loader.
     *
     * @param array  $aModuleFiles Module files array
     * @param string $sModuleId    Module id
     */
    protected function _addModuleFiles($aModuleFiles, $sModuleId)
    {
        $aFiles = (array) Registry::getConfig()->getConfigParam('aModuleFiles');

        $old =  isset($aFiles[$sModuleId]) ? $aFiles[$sModuleId] : null;
        if ($aModuleFiles !== null) {
            $aModuleFiles = array_change_key_case($aModuleFiles, CASE_LOWER);
        }

        if (is_array($aModuleFiles)) {
            $diff = $this->diff($old,$aModuleFiles);
            if ($diff) {
                $what = $old === null ? ' everything' : var_export($diff, true);
                $this->output->warning("$sModuleId fixing files");
                $this->output->debug(" $what");
                $aFiles[$sModuleId] = $aModuleFiles;
                $this->_saveToConfig('aModuleFiles', $aFiles);
                $this->needCacheClear = true;
            }
        } else {
            if ($old) {
                $this->output->warning("$sModuleId unregister files");
                $this->_deleteModuleFiles($sModuleId);
                $this->needCacheClear = true;
            }
        }

    }


    /**
     * Add module events to config.
     *
     * @param array  $aModuleEvents Module events
     * @param string $sModuleId     Module id
     */
    protected function _addModuleEvents($aModuleEvents, $sModuleId)
    {
        $aEvents = (array) Registry::getConfig()->getConfigParam('aModuleEvents');
        $old =  isset($aEvents[$sModuleId]) ? $aEvents[$sModuleId] : null;
        if (is_array($aModuleEvents) && count($aModuleEvents)) {
            $diff = $this->diff($old,$aModuleEvents);
            if ($diff) {
                $aEvents[$sModuleId] = $aModuleEvents;
                $what = $old == null ? ' everything ' : var_export($diff, true);
                $this->output->warning("$sModuleId fixing module events");
                $this->output->debug(" $what");
                $this->_saveToConfig('aModuleEvents', $aEvents);
                $this->needCacheClear = true;
            }
        } else {
            if ($old) {
                $this->output->info("$sModuleId unregister events");
                $this->_deleteModuleEvents($sModuleId);
                $this->needCacheClear = true;
            }
        }

    }

    /**
     * Add module id with extensions to config.
     *
     * @param array  $moduleExtensions Module version
     * @param string $moduleId         Module id
     */
    protected function _addModuleExtensions($moduleExtensions, $moduleId)
    {
        $extensions = (array) Registry::getConfig()->getConfigParam('aModuleExtensions');
        $old = isset($extensions[$moduleId]) ? $extensions[$moduleId] : null;
        $old = (array) $old;
        $new = $moduleExtensions === null ? [] : array_values($moduleExtensions);
        if (is_array($moduleExtensions)) {
            $diff = $this->diff($old, $new);
            if ($diff) {
                $extensions[$moduleId] = array_values($moduleExtensions);
                $what =  $old === null ? ' everything ' : var_export($diff, true);
                $this->output->warning("$moduleId fixing module extensions");
                $this->output->debug(" $what");

                $this->_saveToConfig('aModuleExtensions', $extensions);
                $this->needCacheClear = true;
            }
        } else {
            $this->output->warning("$moduleId unregister module extensions");
            $this->needCacheClear = true;
            $this->_saveToConfig('aModuleExtensions', []);
        }
    }

    /**
     * Add module version to config.
     *
     * @param string $sModuleVersion Module version
     * @param string $sModuleId      Module id
     */
    protected function _addModuleVersion($sModuleVersion, $sModuleId)
    {
        $aVersions = (array) Registry::getConfig()->getConfigParam('aModuleVersions');
        $old =  isset($aVersions[$sModuleId]) ? $aVersions[$sModuleId] : '';
        if (isset($sModuleVersion)) {
            if ($old !== $sModuleVersion) {
                $aVersions[$sModuleId] = $sModuleVersion;
                if($old == '') {
                    $this->output->info("register module '$sModuleId' with version $sModuleVersion");
                } else {
                    $this->output->warning("$sModuleId fixing module version from $old to $sModuleVersion");
                }
                $this->_saveToConfig('aModuleVersions', $aVersions);
                $this->needCacheClear = true;
            }
        } else {
            if ($old) {
                $this->output->info("$sModuleId unregister module version");
                $this->_deleteModuleVersions($sModuleId);
                $this->needCacheClear = true;
            }
        }

    }

    /**
     * compares 2 assoc arrays
     * true if there is something changed
     * @param $array1
     * @param $array2
     * @return bool
     */
    protected function diff($array1,$array2){
        if ($array1 === null) {
            if ($array2 === null) {
                return false; //indicate no diff
            }
            return $array2; //full array2 is new
        }
        if ($array2 === null) {
            //indicate that diff is there  (so return a true value) but everthing should be droped
            return 'null';
        }
        $diff = array_merge(array_diff_assoc($array1,$array2),array_diff_assoc($array2,$array1));
        return $diff;
    }


    /**
     * Code taken from OxidEsales\EshopCommunity\Core\Module::activate
     *
     * @param Module $module
     * @param string $moduleId
     *
     * @throws \OxidEsales\Eshop\Core\Exception\StandardException
     */
    private function restoreModuleInformation($module, $moduleId)
    {
        $active = $this->isActive($moduleId);

        $this->fixExtensions($module);
        $metaDataVersion = $module->getMetaDataVersion();
        $metaDataVersion = $metaDataVersion == '' ? $metaDataVersion = "1.0" : $metaDataVersion;
        if (version_compare($metaDataVersion, '2.0', '<')) {
            $this->_addModuleFiles($active ? $module->getInfo("files") : [], $moduleId);
        }
        $this->_addTemplateBlocks($active ? $module->getInfo("blocks") : [], $moduleId);
        $this->_addTemplateFiles($active ? $module->getInfo("templates"): [], $moduleId);
        $this->_addModuleSettings($module->getInfo("settings"), $moduleId);
        $this->_addModuleVersion($active ? $module->getInfo("version") : null, $moduleId);

        $this->_addModuleEvents($active ? $module->getInfo("events") : [], $moduleId);

        if (version_compare($metaDataVersion, '2.0', '>=')) {
            try {
                $moduleControllers = $active ? $module->getControllers() : [];
                $this->setModuleControllers($moduleControllers, $moduleId, $module);
            } catch (ModuleValidationException $exception) {
                print "[ERROR]: duplicate controllers:" . $exception->getMessage() ."\n";
            }
        }
    }


    protected function isActive($sId)
    {
        return !in_array($sId, (array) Registry::getConfig()->getConfigParam('aDisabledModules'));
    }

    /**
     * Get config tables specific module id
     *
     * @param string $moduleId
     * @return string
     */
    protected function getModuleConfigId($moduleId)
    {
        return 'module:' . $moduleId;
    }

    /**
     * Adds settings to database.
     *
     * @param array  $moduleSettings Module settings array
     * @param string $moduleId       Module id
     */
    protected function _addModuleSettings($moduleSettings, $moduleId)
    {
        $config = Registry::getConfig();
        $shopId = $config->getShopId();
        if (is_array($moduleSettings)) {
            $diff = false;
            foreach ($moduleSettings as $setting) {

                $module = $this->getModuleConfigId($moduleId);
                $name = $setting["name"];
                $type = $setting["type"];

                if (isset($setting["value"]) && is_null($config->getConfigParam($name))){
                    $diff = true;
                    $value = $setting["value"];
                    $config->saveShopConfVar($type, $name, $value, $shopId, $module);
                    $this->output->debug("$moduleId: setting for '$name' fixed'");
                } ;
            }
            if ($diff) {
                $this->output->warning("$moduleId: settings fixed'");
                $this->needCacheClear = true;
            }
        }
    }


    /**
     * Add controllers map for a given module Id to config
     *
     * @param array  $moduleControllers Map of controller ids and class names
     * @param string $moduleId          The Id of the module
     */
    protected function setModuleControllers($moduleControllers, $moduleId, $module)
    {
        $controllersForThatModuleInDb = $this->getModuleControllerEntries($moduleId);
        $diff = $this->diff($controllersForThatModuleInDb, $moduleControllers);


        if ($diff) {
            $shopId = Registry::getConfig()->getShopId();
            $this->output->warning("in shop $shopId: $moduleId fixing module controllers");
            $this->output->warning(" (in md):"  . var_export($moduleControllers, true));
            $this->output->warning(" (in db):"  . var_export($controllersForThatModuleInDb, true));

            $this->deleteModuleControllers($moduleId);
            $this->resetModuleCache($module);
            if ($moduleControllers) {
                $this->validateModuleMetadataControllersOnActivation($moduleControllers);

                $classProviderStorage = $this->getClassProviderStorage();

                $classProviderStorage->add($moduleId, $moduleControllers);
            }

            $afterControllersForThatModuleInDb = $this->getModuleControllerEntries($moduleId);

            $this->needCacheClear = true;
        }

    }

    public function getModuleControllerEntries($moduleId){
        $dbMap = $this->getAllControllers();
        //for some unknown reasons the core uses lowercase module id to reference controllers
        $moduleIdLc = strtolower($moduleId);
        $controllersForThatModuleInDb = isset($dbMap[$moduleIdLc]) ? $dbMap[$moduleIdLc] : [];
        return $controllersForThatModuleInDb;
    }

    /**
     * @return mixed
     */
    private function getAllControllers()
    {
        $classProviderStorage = $this->getClassProviderStorage();
        $dbMap = $classProviderStorage->get();
        return $dbMap;
    }

    public function cleanUpControllers(){
        $allFromDb = $this->getAllControllers();
        $modules = $this->modules;

        //? is aModuleVersions fixed already in that place
        $modules = array_change_key_case($modules,CASE_LOWER);
        $cleaned = array_intersect_key($allFromDb, $modules);
        if ($this->diff($allFromDb, $cleaned)) {
            $this->needCacheClear = true;
            $this->output->warning(" cleaning up controllers");
            $classProviderStorage = $this->getClassProviderStorage();
            $classProviderStorage->set($cleaned);
        }
    }


    /**
     * Reset module cache
     *
     * @param Module $module
     */
    private function resetModuleCache($module)
    {
        $moduleCache = oxNew(ModuleCache::class, $module);
        $moduleCache->resetCache();
    }

    /**
     * @param $o OutputInterface
     * @deprecated
     */
    public function setDebugOutput($o)
    {
        $this->_debugOutput = $o;
    }

    /**
     * @param $o OutputInterface
     */
    public function setOutput($output)
    {
        $o  = new ConsoleLogger($output);
        $this->output = $o;
        $this->getModuleCleaner()->setOutput($o);
    }


    /**
     * Add extension to module
     *
     * @param \OxidEsales\Eshop\Core\Module\Module $module
     */
    protected function _addExtensions(\OxidEsales\Eshop\Core\Module\Module $module)
    {
        $needFix = $this->checkExtensions($module, $aModulesDefault, $aModules);
        if ($needFix) {
            $this->needCacheClear = true;
            $onlyInAfterFix = array_diff($aModules, $aModulesDefault);
            $onlyInBeforeFix = array_diff($aModulesDefault, $aModules);
            $this->output->info("fixing " . $module->getId());
            foreach ($onlyInAfterFix as $core => $ext) {
                if ($oldChain = $onlyInBeforeFix[$core]) {
                    $newExt = substr($ext, strlen($oldChain));
                    if (!$newExt) {
                        //$newExt = substr($ext, strlen($oldChain));
                        $this->output->debug(" remove ext for $core");
                        $this->output->debug(" old: $oldChain");
                        $this->output->debug(" new: $ext");
                        //return;
                        continue;
                    } else {
                        $this->output->debug(" append $core => ...$newExt");
                    }
                    unset($onlyInBeforeFix[$core]);
                } else {
                    $this->output->debug(" add $core => $ext");
                }
            }
            foreach ($onlyInBeforeFix as $core => $ext) {
                $this->output->debug(" remove $core => $ext");
            }
            $this->_saveToConfig('aModules', $aModules);
        }
    }



    /**
     * Add module templates to database.
     *
     * @deprecated please use setTemplateBlocks this method will be removed because
     * the combination of deleting and adding does unnessery writes and so it does not scale
     * also it's more likely to get race conditions (in the moment the blocks are deleted)
     *
     * @param array  $moduleBlocks Module blocks array
     * @param string $moduleId     Module id
     */
    protected function _addTemplateBlocks($moduleBlocks, $moduleId)
    {
        if (!is_array($moduleBlocks)) {
            $moduleBlocks = array();
        }
        $shopId = Registry::getConfig()->getShopId();
        $db = \OxidEsales\Eshop\Core\DatabaseProvider::getDb();
        $knownBlocks = ['dummy']; // Start with a dummy value to prevent having an empty list in the NOT IN statement.
        $rowsEffected = 0;
        foreach ($moduleBlocks as $moduleBlock) {
            $blockId = md5($moduleId . json_encode($moduleBlock) . $shopId);
            $knownBlocks[] = $blockId;

            $template = $moduleBlock["template"];
            $position = isset($moduleBlock['position']) && is_numeric($moduleBlock['position']) ?
                intval($moduleBlock['position']) : 1;

            $block = $moduleBlock["block"];
            $filePath = $moduleBlock["file"];
            $theme = isset($moduleBlock['theme']) ? $moduleBlock['theme'] : '';

            $sql = "INSERT INTO `oxtplblocks` (`OXID`, `OXSHOPID`, `OXTHEME`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                      `OXID` = VALUES(OXID),
                      `OXSHOPID` = VALUES(OXSHOPID),
                      `OXTHEME` = VALUES(OXTHEME),
                      `OXTEMPLATE` = VALUES(OXTEMPLATE),
                      `OXBLOCKNAME` = VALUES(OXBLOCKNAME),
                      `OXPOS` = VALUES(OXPOS),
                      `OXFILE` = VALUES(OXFILE),
                      `OXMODULE` = VALUES(OXMODULE)";

            $rowsEffected += $db->execute($sql, array(
                $blockId,
                $shopId,
                $theme,
                $template,
                $block,
                $position,
                $filePath,
                $moduleId
            ));
        }

        $listOfKnownBlocks = join(',', $db->quoteArray($knownBlocks));
        $deleteblocks = "DELETE FROM oxtplblocks WHERE OXSHOPID = ? AND OXMODULE = ? AND OXID NOT IN ({$listOfKnownBlocks});";

        $rowsEffected += $db->execute(
            $deleteblocks,
            array($shopId, $moduleId)
        );

        if ($rowsEffected) {
            $this->needCacheClear = true;
        }
    }

    /**
     * @param Module $module
     * @param $aModulesDefault
     * @param $aModules
     */
    protected function checkExtensions(\OxidEsales\Eshop\Core\Module\Module $module, &$aModulesDefault, &$aModules)
    {
        $aModulesDefault = Registry::getConfig()->getConfigParam('aModules');
        //in case someone deleted values from the db using a empty array avoids php warnings
        $aModulesDefault = is_null($aModulesDefault) ? [] : $aModulesDefault;
        $aModules = $this->getModulesWithExtendedClass();

        if ($module->hasExtendClass()) {
            $this->validateMetadataExtendSection($module);
            $aAddModules = $module->getExtensions();
            $aModules = $this->_mergeModuleArrays($aModules, $aAddModules);
        }

        $aModules = $this->buildModuleChains($aModules);
        return $aModulesDefault != $aModules;
    }

    /**
     * @param $module
     */
    public function fixExtensions($module)
    {
        $this->_addExtensions($module);
        $this->_addModuleExtensions($module->getExtensions(), $module->getId());
    }

    /**
     * @param $module Module
     */
    private function clearCache($module)
    {
        if ($this->needCacheClear) {
            $this->resetModuleCache($module);
            $this->output->info("cache cleared for " . $module->getId());
        }
    }

    public function getLogger(){
        if(function_exists("\getLogger")){
            return \getLogger();
        }
        return new FallbackLogger();
    }

}
