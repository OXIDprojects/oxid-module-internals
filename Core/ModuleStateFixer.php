<?php

namespace OxidCommunity\ModuleInternals\Core;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Contract\ControllerMapProviderInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Module\ModuleExtensionsCleaner;
use OxidEsales\Eshop\Core\Module\ModuleMetadataValidator;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Routing\Module\ClassProviderStorage;
use OxidEsales\Eshop\Core\Routing\ModuleControllerMapProvider;
use OxidEsales\Eshop\Core\Routing\ShopControllerMapProvider;
use OxidEsales\Eshop\Core\SettingsHandler;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;
use OxidEsales\Eshop\Core\Module\ModuleCache;
use OxidEsales\Eshop\Core\Exception\ModuleValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use OxidEsales\Eshop\Core\Module\ModuleVariablesLocator;

use function getLogger;

/**
 * Module state fixer
 */
class ModuleStateFixer extends ModuleInstaller
{
    /** @var ModuleExtensionsCleaner $moduleCleaner */
    private $moduleCleaner;

    public function __construct($cache = null, $cleaner = null)
    {
        if (is_null($cleaner)) {
            $cleaner = oxNew(ModuleExtensionCleanerDebug::class);
        }
        $this->moduleCleaner = $cleaner;
        $this->output = $this->getLogger();
        parent::__construct($cache, $cleaner);
    }

    /**
     * @var LoggerInterface $output
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

    /**
     * @var bool $dryRun
     */
    protected $dryRun = false;


    protected $moduleList;
    protected $modules;

    /**
     * @var bool $initDone
     */
    protected $initDone;

    public function setConfig($config)
    {
        parent::setConfig($config);
        Registry::set(Config::class, $config);
    }

    public function disableInitialCacheClear()
    {
        $this->initialCacheClearDone = true;
    }

    protected function init(): bool
    {
        if (!$this->initDone) {
            if ($this->isRunning) {
                return false;
            }
            $this->isRunning = true;
            $this->moduleList = Registry::get(ModuleList::class);
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
     * @param Config|null $config If not passed uses default base shop config
     */
    public function fix($module, $config = null): bool
    {
        if ($config !== null) {
            $this->setConfig($config);
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
    public function cleanUp(): void
    {
        if ($this->init()) {
            $this->cleanUpControllers();
            $this->cleanUpExtensions();
        }
    }

    /**
     * remove extensions that are not registered by any module
     */
    public function cleanUpExtensions(): void
    {

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
            foreach ($arrayOfExtendingClasses as $key => $extendingClass) {
                if (!isset($moduleClassesMf[$extendingClass])) {
                    $this->output->warning(
                        "module extension trash found: '$extendingClass'' (registered for $oxidClass)"
                    );
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
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _addTemplateFiles($aModuleTemplates, $sModuleId): void
    {
        $aTemplates = (array) Registry::getConfig()->getConfigParam('aModuleTemplates');
        $old = isset($aTemplates[$sModuleId]) ? $aTemplates[$sModuleId] : null;
        if (is_array($aModuleTemplates)) {
            $diff = $this->diff($old, $aModuleTemplates);
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
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _addModuleFiles($aModuleFiles, $sModuleId): void
    {
        $aFiles = (array) Registry::getConfig()->getConfigParam('aModuleFiles');

        $old =  isset($aFiles[$sModuleId]) ? $aFiles[$sModuleId] : null;
        if ($aModuleFiles !== null) {
            $aModuleFiles = array_change_key_case($aModuleFiles, CASE_LOWER);
        }

        if (is_array($aModuleFiles)) {
            $diff = $this->diff($old, $aModuleFiles);
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
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _addModuleEvents($aModuleEvents, $sModuleId)
    {
        $aEvents = (array) Registry::getConfig()->getConfigParam('aModuleEvents');
        $old =  isset($aEvents[$sModuleId]) ? $aEvents[$sModuleId] : null;
        if (is_array($aModuleEvents) && count($aModuleEvents)) {
            $diff = $this->diff($old, $aModuleEvents);
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
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
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
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _addModuleVersion($sModuleVersion, $sModuleId)
    {
        $aVersions = (array) Registry::getConfig()->getConfigParam('aModuleVersions');
        $old =  isset($aVersions[$sModuleId]) ? $aVersions[$sModuleId] : '';
        if (isset($sModuleVersion)) {
            if ($old !== $sModuleVersion) {
                $aVersions[$sModuleId] = $sModuleVersion;
                if ($old == '') {
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
     * @param array $array1
     * @param array $array2
     * @return bool
     */
    protected function diff($array1, $array2)
    {
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
        $array_diff_assoc1 = @array_diff_assoc($array1, $array2);
        $array_diff_assoc2 = @array_diff_assoc($array2, $array1);
        $diff = array_merge($array_diff_assoc1, $array_diff_assoc2);
        return $diff;
    }


    /**
     * Code taken from OxidEsales\EshopCommunity\Core\Module::activate
     *
     * @param Module $module
     * @param string $moduleId
     *
     * @throws StandardException
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
        $this->_addTemplateFiles($active ? $module->getInfo("templates") : [], $moduleId);
        $this->addModuleSettings($module->getInfo("settings"), $moduleId);
        $this->_addModuleVersion($active ? $module->getInfo("version") : null, $moduleId);

        $this->_addModuleEvents($active ? $module->getInfo("events") : [], $moduleId);

        if (version_compare($metaDataVersion, '2.0', '>=')) {
            try {
                $moduleControllers = $active ? $module->getControllers() : [];
                $this->setModuleControllers($moduleControllers, $moduleId, $module);
            } catch (ModuleValidationException $exception) {
                print "[ERROR]: duplicate controllers:" . $exception->getMessage() . "\n";
            }
        }
    }

    /**
     * @param string $id
     * @return bool
     */
    protected function isActive($id)
    {
        return !in_array($id, (array) Registry::getConfig()->getConfigParam('aDisabledModules'));
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
    protected function addModuleSettings($moduleSettings, $moduleId)
    {
        $config = Registry::getConfig();
        $shopId = $config->getShopId();
        if (is_array($moduleSettings)) {
            $diff = false;
            foreach ($moduleSettings as $setting) {
                $module = $this->getModuleConfigId($moduleId);
                $name = $setting["name"];
                $type = $setting["type"];

                if (isset($setting["value"]) && is_null($config->getConfigParam($name))) {
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

    public function getModuleControllerEntries($moduleId)
    {
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

    public function cleanUpControllers()
    {
        $allFromDb = $this->getAllControllers();
        $modules = $this->modules;

        //? is aModuleVersions fixed already in that place
        $modules = array_change_key_case($modules, CASE_LOWER);
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
     * @param OutputInterface $o
     * @deprecated please use setOutput
     */
    public function setDebugOutput($o)
    {
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput($output)
    {
        $o  = new ConsoleLogger($output);
        $this->output = $o;
        $this->getModuleCleaner()->setLogger($o);
    }


    /**
     * Add extension to module
     *
     * @param Module $module
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _addExtensions(Module $module)
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
     * this method sets template blocks with less conflicting writes as possible
     * to reduce race conditions (in the moment the blocks are deleted)
     * it will only report major changes even if id may be changed to ensure consistency of the data
     *
     *
     * @param array  $moduleBlocks Module blocks array
     * @param string $moduleId     Module id
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _addTemplateBlocks($moduleBlocks, $moduleId)
    {
        if (!is_array($moduleBlocks)) {
            $moduleBlocks = array();
        }
        $shopId = Registry::getConfig()->getShopId();
        $db = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $knownBlocks = ['dummy']; // Start with a dummy value to prevent having an empty list in the NOT IN statement.
        $rowsEffected = 0;
        $select_sql = "SELECT `OXID`, `OXTHEME` as `theme`, `OXTEMPLATE` as `template`, `OXBLOCKNAME` as `block`,
            `OXPOS` as `position`, `OXFILE` as `file`
            FROM `oxtplblocks`
            WHERE `OXMODULE` = ? AND `OXSHOPID` = ?";
        $existingBlocks = $db->getAll($select_sql, [$moduleId,$shopId]);

        foreach ($existingBlocks as $block) {
            $id = $block['OXID'];
            $block['position'] = (int) $block['position'];

            unset($block['OXID']);
            ksort($block);
            $str1 = $moduleId . json_encode($block) . $shopId;
            $wellGeneratedId = md5($str1);
            if ($id !== $wellGeneratedId) {
                $sql = "UPDATE IGNORE `oxtplblocks` SET OXID = ? WHERE OXID = ?";
                $this->output->debug("$sql, $wellGeneratedId, $id");
                $db->execute($sql, [$wellGeneratedId, $id]);
            }
        }


        foreach ($moduleBlocks as $moduleBlock) {
            $moduleBlock['theme'] = $moduleBlock['theme'] ?? '';
            $moduleBlock['position'] = isset($moduleBlock['position']) && is_numeric($moduleBlock['position'])
                ? (int) $moduleBlock['position']
                : 1;
            ksort($moduleBlock);

            $str = $moduleId . json_encode($moduleBlock) . $shopId;
            $blockId = md5($str);
            $knownBlocks[] = $blockId;

            $template = $moduleBlock["template"];

            $position = $moduleBlock['position'];
            $block = $moduleBlock["block"];
            $filePath = $moduleBlock["file"];
            $theme = isset($moduleBlock['theme']) ? $moduleBlock['theme'] : '';

            $sql = "INSERT INTO `oxtplblocks`
                    (`OXID`, `OXSHOPID`, `OXTHEME`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`)
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
        $deleteblocks = 'DELETE FROM oxtplblocks WHERE OXSHOPID = ? ';
        $deleteblocks .= "AND OXMODULE = ? AND OXID NOT IN ({$listOfKnownBlocks});";

        $rowsEffected += $db->execute(
            $deleteblocks,
            array($shopId, $moduleId)
        );

        if ($rowsEffected) {
            $this->needCacheClear = true;
            $this->output->info("fixed template blocks for module " . $moduleId);
        }
    }

    /**
     * @param Module $module
     * @param array $aModulesDefault
     * @param array $aModules
     * @return bool
     */
    protected function checkExtensions(Module $module, &$aModulesDefault, &$aModules)
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
     * @param Module $module
     */
    public function fixExtensions($module)
    {
        $this->_addExtensions($module);
        $this->_addModuleExtensions($module->getExtensions(), $module->getId());
    }

    /**
     * @param Module $module
     */
    private function clearCache($module)
    {
        if ($this->needCacheClear) {
            $this->resetModuleCache($module);
            $this->output->info("cache cleared for " . $module->getId());
        }
    }

    public function getLogger(): LoggerInterface
    {
        if (function_exists("getLogger")) {
            return getLogger();
        }
        return new NullLogger();
    }

    /**
     * Save module parameters to shop config
     *
     * @param string $sVariableName  config name
     * @param string $sVariableValue config value
     * @param string $sVariableType  config type
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    protected function _saveToConfig($sVariableName, $sVariableValue, $sVariableType = 'aarr')
    {
        $oConfig = Registry::getConfig();
        $oConfig->saveShopConfVar($sVariableType, $sVariableName, $sVariableValue);
    }

    /**
     * Validate module metadata extend section.
     * Only Unified Namespace shop classes are free to patch.
     *
     * @param Module $module
     *
     * @throws ModuleValidationException
     */
    protected function validateMetadataExtendSection(Module $module)
    {
        $validator = $this->getModuleMetadataValidator();
        $validator->checkModuleExtensionsForIncorrectNamespaceClasses($module);
    }

    /**
     * @return object
     */
    protected function getClassProviderStorage()
    {
        $classProviderStorage = oxNew(ClassProviderStorage::class);

        return $classProviderStorage;
    }

    /**
     * Remove controllers map for a given module Id from config
     *
     * @param string $moduleId The Id of the module
     */
    protected function deleteModuleControllers($moduleId)
    {
        $moduleControllerProvider = $this->getClassProviderStorage();
        $moduleControllerProvider->remove($moduleId);
    }

    /**
     * @return ControllerMapProviderInterface
     */
    protected function getModuleControllerMapProvider()
    {
        return oxNew(ModuleControllerMapProvider::class);
    }
    /**
     * @return ControllerMapProviderInterface
     */
    protected function getShopControllerMapProvider()
    {
        return oxNew(ShopControllerMapProvider::class);
    }

    /**
     * Ensure integrity of the controllerMap before storing it.
     * Both keys and values must be unique with in the same shop or sub-shop.
     *
     * @param array $moduleControllers
     *
     * @throws ModuleValidationException
     */
    protected function validateModuleMetadataControllersOnActivation($moduleControllers)
    {
        $moduleControllerMapProvider = $this->getModuleControllerMapProvider();
        $shopControllerMapProvider = $this->getShopControllerMapProvider();
        $moduleControllerMap = $moduleControllerMapProvider->getControllerMap();
        $shopControllerMap = $shopControllerMapProvider->getControllerMap();
        $existingMaps = array_merge($moduleControllerMap, $shopControllerMap);
        /**
         * Ensure, that controller keys are unique.
         * As keys are always stored in lower case, we must test against lower case keys here as well
         */
        $duplicatedKeys = array_intersect_key(array_change_key_case($moduleControllers, CASE_LOWER), $existingMaps);
        if (!empty($duplicatedKeys)) {
            throw new ModuleValidationException(implode(',', $duplicatedKeys));
        }
        /**
         * Ensure, that controller values are unique.
         */
        $duplicatedValues = array_intersect($moduleControllers, $existingMaps);
        if (!empty($duplicatedValues)) {
            throw new ModuleValidationException(implode(',', $duplicatedValues));
        }
    }

    /**
     * @return ModuleMetadataValidator
     */
    protected function getModuleMetadataValidator()
    {
        return oxNew(ModuleMetadataValidator::class);
    }

    /**
     * Merge two nested module arrays together so that the values of
     * $aAddModuleArray are appended to the end of the $aAllModuleArray
     *
     * @param array $aAllModuleArray All Module array (nested format)
     * @param array $aAddModuleArray Added Module array (nested format)
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     *
     * @return array
     */
    protected function _mergeModuleArrays($aAllModuleArray, $aAddModuleArray)
    {
        if (is_array($aAllModuleArray) && is_array($aAddModuleArray)) {
            foreach ($aAddModuleArray as $sClass => $aModuleChain) {
                if (!is_array($aModuleChain)) {
                    $aModuleChain = [$aModuleChain];
                }
                if (isset($aAllModuleArray[$sClass])) {
                    foreach ($aModuleChain as $sModule) {
                        if (!in_array($sModule, $aAllModuleArray[$sClass])) {
                            $aAllModuleArray[$sClass][] = $sModule;
                        }
                    }
                } else {
                    $aAllModuleArray[$sClass] = $aModuleChain;
                }
            }
        }

        return $aAllModuleArray;
    }

    /**
     * Removes garbage ( module not used extensions ) from all installed extensions list
     *
     * @param array                                $installedExtensions Installed extensions
     * @param Module $module              Module
     *
     * @return array
     *@deprecated on b-dev, \OxidEsales\Eshop\Core\Module\ModuleExtensionsCleaner::cleanExtensions() should be used.
     * @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     *
     */
    protected function _removeNotUsedExtensions($installedExtensions, Module $module)
    {
        return $this->getModuleCleaner()->cleanExtensions($installedExtensions, $module);
    }

    /**
     * Returns module cleaner object.
     *
     * @return ModuleExtensionsCleaner
     */
    protected function getModuleCleaner()
    {
        return $this->moduleCleaner;
    }
}
