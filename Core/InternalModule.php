<?php
/**
 * @package   moduleinternals
 * @category  OXID Module
 * @license   GPL3 License http://opensource.org/licenses/GPL
 * @author    Alfonsas Cirtautas / OXID Community
 * @link      https://github.com/OXIDprojects/ocb_cleartmp
 * @see       https://github.com/acirtautas/oxid-module-internals
 */

namespace OxidCommunity\ModuleInternals\Core;

use \OxidEsales\Eshop\Core\DatabaseProvider as DatabaseProvider;
use \OxidEsales\Eshop\Core\Registry as Registry;
use \OxidEsales\Eshop\Core\Module\ModuleList as ModuleList;
use OxidEsales\Eshop\Core\Request;

/**
 * Class InternalModule: chain extends OxidEsales\Eshop\Core\Module\Module
 * @package OxidCommunity\ModuleInternals\Core
 */
class InternalModule extends InternalModule_parent
{
    protected $stateFine = true;
    protected $checked = false;

    const METADATA_NOT_IN_DB = 0;
    const OK = 1;
    const DB_HAS_WRONG_DATA = -1;
    const MODULE_FILE_NOT_FOUND = -2;
    const SHOP_FILE_NOT_FOUND = -3;

    protected function getAutoloader(){
        if (Registry::instanceExists('autoloader')){
            return Registry::get('autoloader');
        }
        $composerClassLoader = include VENDOR_PATH . 'autoload.php';
        Registry::set('autoloader', $composerClassLoader);
        return $composerClassLoader;
    }

    /**
     * Get template blocks defined in database.
     *
     * @return array
     */
    public function getModuleBlocks()
    {
        $aResults = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->select(
            'SELECT * FROM oxtplblocks WHERE oxModule = ? AND oxshopid = ?',
            [$this->getId(), Registry::getConfig()->getShopId()]
        );

        return $aResults->fetchAll();
    }

    /**
     * Get module settings defined in database.
     *
     * @return array
     */
    public function getModuleSettings()
    {
        $aResult = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->select(
            'SELECT * FROM oxconfig WHERE oxModule = ? AND oxshopid = ?',
            [sprintf('module:%s', $this->getId()), Registry::getConfig()->getShopId()]
        );

        return $aResult->fetchAll();
    }

    /**
     * Check supported metadata version
     *
     * @return bool
     */
    public function isMetadataSupported()
    {
        $sMetadataVersion = $this->getMetaDataVersion();

        $sLatestVersion = '1.0';

        if (method_exists('oxModuleList', 'getModuleVersions') || method_exists('oxModule', 'getModuleEvents')) {
            $sLatestVersion = '1.1';
        }

        if (method_exists(ModuleList::class, 'getModuleConfigParametersByKey')) {
            $sLatestVersion = '2.0';
        }

        if (method_exists(ModuleList::class, 'getSmartyPluginDirectories')) {
            $sLatestVersion = '2.1';
        }


        return version_compare($sLatestVersion, $sMetadataVersion) >= 0;
    }

    /**
     * check if current metadata version is $sVersion
     *
     * @param $sVersion
     *
     * @return bool
     */
    public function isMetadataVersionGreaterEqual($sVersion)
    {
        return version_compare($this->getMetaDataVersion(), $sVersion) >= 0;
    }

    /**
     * check if current metadata version is $sVersion
     *
     * @param $sVersion
     *
     * @return bool
     */
    public function checkMetadataVersion($sVersion)
    {
        return version_compare($this->getMetaDataVersion(), $sVersion) == 0;
    }

    /**
     * Returns array of module files / entries in metadata.php
     * like 'template' => / 'blocks' => ....
     * possible entries
     *
     * ModuleList::MODULE_KEY_FILES = 'files'
     * ModuleList::MODULE_KEY_CONTROLLERS = 'controllers'
     * ModuleList::MODULE_KEY_EVENTS => 'events'
     * ModuleList::MODULE_KEY_VERSIONS => module version
     *
     * @return array
     */
    public function getModuleEntries($sType)
    {
        $aReturn = [];
        $aList = oxNew(ModuleList::class)->getModuleConfigParametersByKey($sType);

        if (isset($aList[ $this->getId() ])) {
            $aReturn = $aList[ $this->getId() ];
        }

        return $aReturn;
    }

    /**
     * checks if module file exists on directory
     * switches between metadata version for checking
     * checks also for namespace class if metadata version = 2.0
     *
     * @param string $sModulesDir
     * @param string $sClassName
     * @param string $sExtention
     *
     * @return bool
     */
    public function checkPhpFileExists($sModulesDir = null, $sClassName, $sExtention = '.php')
    {
        if ($this->isMetadataVersionGreaterEqual('2.0')) {
            $composerClassLoader = $this->getAutoloader();

            return $composerClassLoader->findFile($sClassName);
        } else {
            $sExtensionPath = $sModulesDir . $sClassName . $sExtention;
            $res = $this->checkFileExists($sExtensionPath);
            return $res;
        }
    }



    /**
     * Analyze versions in metadata
     * checks if metadata version is same as database entry for metadata
     *
     * @return array
     */
    public function checkModuleVersions()
    {
        $sMetadataVersion = $this->getInfo('version');
        $sDatabaseVersion = $this->getModuleEntries(ModuleList::MODULE_KEY_VERSIONS);

        $aResult = [];
        // Check version..
        if ($sMetadataVersion) {
            $aResult[ $sMetadataVersion ] = self::METADATA_NOT_IN_DB;
        }

        // Check for versions match injected.
        if ($sDatabaseVersion) {
            if (!isset($aResult[ $sDatabaseVersion ])) {
                $aResult[ $sDatabaseVersion ] = self::DB_HAS_WRONG_DATA;
                $this->stateFine = false;
            } else {
                $aResult[ $sDatabaseVersion ] = self::OK;
            }
        }

        return $aResult;
    }

    public function getTitle() {
        if ($this->checked !== false) {
            return $this->checked;
        }
        $title = parent::getTitle();
        $cl = Registry::getRequest()->getRequestParameter('cl');
        if ($cl == 'module_list') {
            $this->checkState();
            if (!$this->stateFine) {
                $title .= ' <strong style="color: #900">Issue found!</strong>';
            }
            $this->checked = $title;
        }
        return $title;
    }

    /**
     * Analyze extended class information in metadata and database.
     *
     * @return array
     */
    public function checkExtendedClasses()
    {
        $sModulePath = $this->getModulePath();

        $aMetadataExtend = $this->getInfo('extend');
        $oxidConfig = Registry::getConfig();

        if (method_exists($oxidConfig, 'getModulesWithExtendedClass')) {
            $aAllModules = $oxidConfig->getModulesWithExtendedClass();
        } else {
            $aAllModules = $oxidConfig->getAllModules();
        }

        $aResult = [];
        $sModulesDir = Registry::getConfig()->getModulesDir(true);

        // Check if all classes are extended.
        if (is_array($aMetadataExtend)) {
            /**
             * only convert class names to lower if we don't use namespace
             */
            if (!$this->isMetadataVersionGreaterEqual('2.0')) {
                $aMetadataExtend = array_change_key_case($aMetadataExtend, CASE_LOWER);
                //convert legacy classnames because $aAllModules dos not contain legacy classes
                if (method_exists(Registry::class ,'getBackwardsCompatibilityClassMap')) {
                    $map = Registry::getBackwardsCompatibilityClassMap();
                    foreach ($aMetadataExtend as $legacyName => $file) {
                        if (isset($map[$legacyName])) {
                            $namespaceName = $map[$legacyName];
                            $aMetadataExtend[$namespaceName] = $file;
                            unset($aMetadataExtend[$legacyName]);
                        }
                    }
                }
            }

            foreach ($aMetadataExtend as $sClassName => $sModuleName) {
                $iState = 0;
                if (is_array($aAllModules) && isset($aAllModules[ $sClassName ])) {
                    // Is module extending class
                    if (is_array($aAllModules[ $sClassName ])) {
                        $iState = in_array($sModuleName, $aAllModules[ $sClassName ]) ? 1 : 0;
                    }
                }

                if (strpos($sClassName,'OxidEsales\\EshopCommunity\\') !== false ||
                    strpos($sClassName,'OxidEsales\\EshopEnterprise\\') !== false ||
                    strpos($sClassName,'OxidEsales\\EshopProfessional\\') !== false
                ) {
                    $iState = -3;
                }

                if (!$this->checkPhpFileExists($sModulesDir, $sModuleName)) {
                    $iState = -2; // class sfatalm
                }
                if ($iState != 1) {
                    $this->stateFine = false;
                }
                $aResult[ $sClassName ][ $sModuleName ] = $iState;
            }
        }

        // Check for redundant extend data by path
        if ($sModulePath && is_array($aAllModules)) {
            if ($this->isMetadataVersionGreaterEqual('2.0')) {
                $moduleNameSpace = $this->getModuleNameSpace($sModulePath);
            }
            foreach ($aAllModules as $sClassName => $mModuleName) {
                if (is_array($mModuleName)) {
                    foreach ($mModuleName as $sModuleName) {
                        /**
                         * we don't need to check for filesystem directory - we only use namespaces in version 2.0
                         */
                        if ($this->isMetadataVersionGreaterEqual('2.0')) {
                            if ($moduleNameSpace && !isset($aResult[$sClassName][$sModuleName]) && strpos($sModuleName,
                                    $moduleNameSpace) === 0) {
                                $this->stateFine = false;
                                $aResult[ $sClassName ][ $sModuleName ] = self::DB_HAS_WRONG_DATA;
                            }
                        } else {
                            if (!isset($aResult[ $sClassName ][ $sModuleName ]) && strpos($sModuleName, $sModulePath . '/') === 0) {
                                $this->stateFine = false;
                                $aResult[ $sClassName ][ $sModuleName ] = self::DB_HAS_WRONG_DATA;
                            }
                        }
                    }
                }
            }
        }

        return $aResult;
    }

    public function setModuleData($aModule)
    {
        $id = $aModule['id'];
        if (!isset($aModule['version'])) {
            $packageService = Registry::get(OxidComposerModulesService::class);
            $list = $packageService->getList();
            if (isset($list[$id])) {
                $package = $list[$id];
                $version = $package->getVersion();
                $aModule['version'] = $version;
            }
        }
        parent::setModuleData($aModule);
    }

    /**
     * Analyze template block information in metadata and database.
     *
     * @return array
     */
    public function checkTemplateBlocks()
    {
        $sModulePath = $this->getModulePath();
        $aMetadataBlocks = $this->getInfo('blocks');
        $aDatabaseBlocks = $this->getModuleBlocks();
        $aMetadataTemplates = $this->getInfo('templates');

        $sModulesDir = Registry::getConfig()->getModulesDir();

        $aResult = [];

        // Check if all blocks are injected.
        if (is_array($aMetadataBlocks)) {
            foreach ($aMetadataBlocks as $aBlock) {
                $iState = self::METADATA_NOT_IN_DB;
                if (is_array($aDatabaseBlocks)) {
                    foreach ($aDatabaseBlocks as $aDbBlock) {
                        // Is template block inserted
                        if (
                            ($aBlock['template'] == $aDbBlock['OXTEMPLATE']) &&
                            ($aBlock['block'] == $aDbBlock['OXBLOCKNAME']) &&
                            ($aBlock['file'] == $aDbBlock['OXFILE'])
                        ) {
                            $iState = self::OK;
                        }
                    }
                }

                if (!file_exists($sModulesDir . '/' . $sModulePath . '/' . $aBlock['file']) &&
                    !file_exists($sModulesDir . '/' . $sModulePath . '/out/blocks/' . basename($aBlock['file'])) &&
                    !file_exists($sModulesDir . '/' . $sModulePath . '/out/blocks/' . basename($aBlock['file']) . '.tpl')
                ) {
                    $iState = self::MODULE_FILE_NOT_FOUND;
                }
                if ($iState != self::OK ) {
                    $this->stateFine = false;
                }
                $aResult[$aBlock['template']][$aBlock['block']][$aBlock['file']]['file'] = $iState;
            }
        }

        // Check for redundant blocks for current module.
        if (is_array($aDatabaseBlocks)) {
            foreach ($aDatabaseBlocks as $aDbBlock) {

                $sBaseFile = basename($aDbBlock['OXFILE']);

                if (!isset($aResult[$aDbBlock['OXTEMPLATE']][$aDbBlock['OXBLOCKNAME']][$aDbBlock['OXFILE']])) {
                    $aResult[$aDbBlock['OXTEMPLATE']][$aDbBlock['OXBLOCKNAME']][$aDbBlock['OXFILE']]['file'] = self::DB_HAS_WRONG_DATA;
                    if (!file_exists($sModulesDir . '/' . $sModulePath . '/' . $aDbBlock['OXFILE']) &&
                        !file_exists($sModulesDir . '/' . $sModulePath . '/out/blocks/' . $sBaseFile) &&
                        !file_exists($sModulesDir . '/' . $sModulePath . '/out/blocks/' . $sBaseFile) . '.tpl'
                    ) {
                        $aResult[$aDbBlock['OXTEMPLATE']][$aDbBlock['OXBLOCKNAME']][$aDbBlock['OXFILE']]['file'] = self::SHOP_FILE_NOT_FOUND;
                    }
                }
            }
        }

        // Check if template file exists and block is defined.
        if (is_array($aMetadataBlocks)) {
            foreach ($aMetadataBlocks as $aBlock) {

                // Get template from shop..
                $sTemplate = Registry::getConfig()->getTemplatePath($aBlock['template'], false);

                // Get template from shop admin ..
                if (!$sTemplate) {
                    $sTemplate = Registry::getConfig()->getTemplatePath($aBlock['template'], true);
                }

                // Get template from module ..
                if (!$sTemplate && isset($aMetadataTemplates[ $aBlock['template'] ])) {

                    $sModulesDir = Registry::getConfig()->getModulesDir();

                    if (file_exists($sModulesDir . '/' . $aMetadataTemplates[ $aBlock['template'] ])) {
                        $sTemplate = $sModulesDir . '/' . $aMetadataTemplates[ $aBlock['template'] ];
                    }
                }

                if (empty($sTemplate)) {
                    $aResult[$aBlock['template']][$aBlock['block']][$aBlock['file']]['template'] = self::SHOP_FILE_NOT_FOUND;
                    $this->stateFine = false;
                } else {
                    $sContent = file_get_contents($sTemplate);
                    if (!preg_match('/\[{.*block.* name.*= *"' . $aBlock['block'] . '".*}\]/', $sContent)) {
                        $aResult[$aBlock['template']][$aBlock['block']][$aBlock['file']]['block'] = self::SHOP_FILE_NOT_FOUND;
                    }
                }
            }
        }

        return $aResult;
    }

    /**
     * Analyze settings in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleSettings()
    {
        $aMetadataSettings = $this->getInfo('settings');
        $aDatabaseSettings = $this->getModuleSettings();

        $aResult = [];

        // Check if all settings are injected.
        if (is_array($aMetadataSettings)) {
            foreach ($aMetadataSettings as $aData) {
                $sName = $aData['name'];
                $aResult[ $sName ] = self::METADATA_NOT_IN_DB;
            }
        }

        $problems = $aResult;
        // Check for redundant settings for current module.
        if (is_array($aDatabaseSettings)) {
            foreach ($aDatabaseSettings as $aData) {
                $sName = $aData['OXVARNAME'];

                if (!isset($aResult[ $sName ])) {
                    $aResult[ $sName ] = self::DB_HAS_WRONG_DATA;
                    $this->stateFine = false;
                } else {
                    $aResult[ $sName ] = self::OK;
                    unset($problems[$sName]);
                }
            }
        }

        if ($problems) {
            $this->stateFine = false;
        }

        return $aResult;
    }

    /**
     * Analyze templates in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleTemplates()
    {
        $aMetadataTemplates = $this->getInfo('templates');
        $aDatabaseTemplates = $this->getModuleEntries(ModuleList::MODULE_KEY_TEMPLATES);

        $sModulesDir = Registry::getConfig()->getModulesDir();

        $aResult = [];

        // Check if all module templates are injected.
        if (is_array($aMetadataTemplates)) {
            foreach ($aMetadataTemplates as $sTemplate => $sFile) {
                $aResult[ $sTemplate ][ $sFile ] = self::METADATA_NOT_IN_DB;
                if (!$this->checkFileExists($sModulesDir . '/' . $sFile)) {
                    $aResult[ $sTemplate ][ $sFile ] = self::MODULE_FILE_NOT_FOUND;
                    $this->stateFine = false;
                }
            }
        }

        // Check for redundant or missing module templates
        if (is_array($aDatabaseTemplates)) {
            foreach ($aDatabaseTemplates as $sTemplate => $sFile) {
                if (!isset($aResult[ $sTemplate ][ $sFile ])) {
                    $this->stateFine = false;
                    @$aResult[ $sTemplate ][ $sFile ] = self::DB_HAS_WRONG_DATA;
                    if (!file_exists($sModulesDir . '/' . $sFile)) {
                        @$aResult[ $sTemplate ][ $sFile ] = self::SHOP_FILE_NOT_FOUND;
                    }
                } elseif ($aResult[ $sTemplate ][ $sFile ] == self::METADATA_NOT_IN_DB) {
                    @$aResult[ $sTemplate ][ $sFile ] = self::OK;
                }
            }
        }

        return $aResult;
    }

    /**
     * Analyze controller in metadata
     *
     * @return array
     */
    public function checkModuleController()
    {
        $aMetadataFiles = $this->getControllers();
        $oModuleStateFixer = Registry::get(ModuleStateFixer::class);
        $aDatabaseFiles =  $oModuleStateFixer->getModuleControllerEntries($this->getId());

        return $this->checkModuleFileConsistency($aMetadataFiles, $aDatabaseFiles);
    }

    /**
     * Analyze file in metadata
     *
     * @return array
     */
    public function checkModuleFiles()
    {
        $aMetadataFiles = $this->getInfo('files');
        $aDatabaseFiles = $this->getModuleEntries(ModuleList::MODULE_KEY_FILES);

        return $this->checkModuleFileConsistency($aMetadataFiles, $aDatabaseFiles);
    }

    /**
     * checks for database entries and filesystem check
     *
     * @param $aMetadataFiles
     * @param $aDatabaseFiles
     *
     * @return array
     */
    public function checkModuleFileConsistency($aMetadataFiles, $aDatabaseFiles)
    {
        $aResult = [];

        // Check if all module files are injected.
        if (is_array($aMetadataFiles)) {
            if (!$this->isMetadataVersionGreaterEqual('2.0')) {
                $aMetadataFiles = array_change_key_case($aMetadataFiles, CASE_LOWER);
                $sModulesDir = Registry::getConfig()->getModulesDir();
            }
            foreach ($aMetadataFiles as $sClass => $sFile) {
                $aResult[ $sClass ][ $sFile ] = self::METADATA_NOT_IN_DB;

                if (!$this->checkPhpFileExists($sModulesDir, $sFile, null)) {
                    $aResult[$sClass][$sFile] = self::MODULE_FILE_NOT_FOUND;
                    $this->stateFine = false;
                }
            }
        }

        // Check for redundant or missing module files
        if (is_array($aDatabaseFiles)) {
            foreach ($aDatabaseFiles as $sClass => $sFile) {
                if (!isset($aResult[ $sClass ][ $sFile ])) {
                    @$aResult[ $sClass ][ $sFile ] = self::DB_HAS_WRONG_DATA;
                    /**
                     * @todo update to $this->checkFileExists()
                     */
                    if ($this->isMetadataVersionGreaterEqual('2.0')) {
                        $composerClassLoader = $this->getAutoloader();
                        if (!$composerClassLoader->findFile($sFile)) {
                            @$aResult[ $sClass ][ $sFile ] = self::MODULE_FILE_NOT_FOUND;
                            $this->stateFine = false;
                        }
                    } else {
                        if (!file_exists($sModulesDir . $sFile)) {
                            @$aResult[ $sClass ][ $sFile ] = self::SHOP_FILE_NOT_FOUND;
                            $this->stateFine = false;
                        }
                    }
                } elseif ($aResult[ $sClass ][ $sFile ] == self::METADATA_NOT_IN_DB) {
                    @$aResult[ $sClass ][ $sFile ] = self::OK;
                }
            }
        }

        return $aResult;
    }

    /**
     * Analyze events in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleEvents()
    {
        $aMetadataEvents = $this->getInfo('events');
        $aDatabaseEvents = $this->getModuleEntries(ModuleList::MODULE_KEY_EVENTS);

        $aResult = [];

        // Check if all events are injected.
        if (is_array($aMetadataEvents)) {
            foreach ($aMetadataEvents as $sEvent => $mCallback) {
                $sCallback = print_r($mCallback, 1);
                $aResult[ $sEvent ][ $sCallback ] = self::METADATA_NOT_IN_DB;
            }
        }

        // Check for redundant or missing events.
        if (is_array($aDatabaseEvents)) {
            foreach ($aDatabaseEvents as $sEvent => $mCallback) {
                $sCallback = print_r($mCallback, 1);
                if (!isset($aResult[ $sEvent ][ $sCallback ])) {
                    $aResult[ $sEvent ][ $sCallback ] = self::DB_HAS_WRONG_DATA;
                    $this->stateFine = false;
                } else {
                    $aResult[ $sEvent ][ $sCallback ] = self::OK;
                }
            }
        }

        return $aResult;
    }

    /**
     * @param string $sModulePath
     *
     * @return string
     */
    public function getModuleNameSpace($sModulePath)
    {
        $sModulesDir = Registry::getConfig()->getModulesDir();
        $file = $sModulesDir . $sModulePath . '/composer.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $namesspaces = $data["autoload"]["psr-4"];
            $prefix = array_keys($namesspaces);
            $moduleNameSpace = $prefix[0];
            return $moduleNameSpace;
        }


        $moduleNameSpace = '';
        $composerClassLoader = $this->getAutoloader();
        $nameSpacePrefixes = $composerClassLoader->getPrefixesPsr4();
        foreach ($nameSpacePrefixes as $nameSpacePrefix => $paths) {
            foreach ($paths as $path) {
                if (strpos($path, $sModulePath) !== false) {
                    $moduleNameSpace = $nameSpacePrefix;

                    return $moduleNameSpace;
                }
            }
        }

        return $moduleNameSpace;
    }

    /**
     * @param $oModule
     * @param $sModId
     * @param $sTitle
     * @return array
     */
    public function checkState($sTitle = '')
    {
        $oModule = $this;
        $aModule = array();
        $aModule['oxid'] = $sId = $oModule->getId();
        $aModule['title'] = $aModule['oxid'] . " - " . $sTitle;
        if ($this->_isInDisabledList($sId)) {
            return $aModule;
        }
        $aModule['aExtended'] = $oModule->checkExtendedClasses();
        $aModule['aBlocks'] = $oModule->checkTemplateBlocks();
        $aModule['aSettings'] = $oModule->checkModuleSettings();
        $aModule['aTemplates'] = $oModule->checkModuleTemplates();

        $aModule['aFiles'] = array();
        $aModule['aEvents'] = array();
        $aModule['aVersions'] = array();
        $aModule['aControllers'] = array();

        // files are valid for  metadata version < 2.0
        if ($oModule->isMetadataVersionGreaterEqual('2.0')) {
            /**
             * @todo check if files is set - should'nt be
             */
        } else {
            $aModule['aFiles'] = $oModule->checkModuleFiles();
        }

        // valid  for  metadata version >= 1.1
        if ($oModule->isMetadataVersionGreaterEqual('1.1')) {
            $aModule['aEvents'] = $oModule->checkModuleEvents();
            $aModule['aVersions'] = $oModule->checkModuleVersions();
        }


        if ($oModule->isMetadataVersionGreaterEqual('2.0')) {
            $aModule['aControllers'] = $oModule->checkModuleController();
        }
        return $aModule;
    }

    /**
     * @param $sExtensionPath
     * @return bool
     */
    protected function checkFileExists($sExtensionPath)
    {
        $res = file_exists($sExtensionPath);
        if ($res) {
            $dir = dirname($sExtensionPath);
            $file = basename($sExtensionPath);

            //check if filename case sensitive so we will see errors
            //also on case insensitive filesystems
            if (!in_array($file, scandir($dir))) {
                $res = false;
            }

        }
        return $res;
    }
}
