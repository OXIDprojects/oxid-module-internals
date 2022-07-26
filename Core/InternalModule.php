<?php

/**
 * @package   moduleinternals
 * @category  OXID Module
 * @license   GPL3 License http://opensource.org/licenses/GPL
 * @author    Alfonsas Cirtautas / OXID Community
 * @link      https://github.com/OXIDprojects/ocb_cleartmp
 * @see       https://github.com/acirtautas/oxid-module-internals
 * @phpcs:disable PSR12.Properties.ConstantVisibility.NotFound
 */

namespace OxidCommunity\ModuleInternals\Core;

use Composer\Package\PackageInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Theme;

/**
 * Class InternalModule: chain extends OxidEsales\Eshop\Core\Module\Module
 * @package OxidCommunity\ModuleInternals\Core
 */
class InternalModule extends InternalModule_parent
{
    protected $state = self::FINE;
    /**
     * @var bool|array $checked
     */
    protected $checked = false;
    /** @var ModuleStateFixer */
    protected $moduleFixHelper;

    const FINE = 0;
    const NEED_MANUAL_FIXED = 1;
    const MAY_NEED_MANUAL_FIX = 2;

    const OK = 'sok';
    const MODULE_FILE_NOT_FOUND = 'sfatalm';
    const SHOP_FILE_NOT_FOUND = 'sfatals';

    public function load($id)
    {
        $this->checked = false;
        $this->state = self::FINE;
        $this->metaDataVersion = null;
        return parent::load($id);
    }

    /**
     * @param ModuleStateFixer $oModuleFixHelper
     */
    public function setModuleFixHelper($oModuleFixHelper)
    {
        $this->moduleFixHelper = $oModuleFixHelper;
    }


    public function getModuleHelper()
    {
        return Registry::get(ModuleHelper::class);
    }


    /**
     * @return ModuleStateFixer
     */
    public function getModuleStateFixer()
    {
        if ($this->moduleFixHelper === null) {
            $this->moduleFixHelper = Registry::get(ModuleStateFixer::class);
        }

        return $this->moduleFixHelper;
    }

    /**
     * Get template blocks defined in database.
     *
     * @return array
     * @throws DatabaseErrorException
     */
    public function getModuleBlocks()
    {
        $config = Registry::getConfig();
        $activeThemeIds = oxNew(Theme::class)->getActiveThemesList();
        $activeThemeIds[] = '';

        $themeIdsSql = implode(', ', DatabaseProvider::getDb()->quoteArray($activeThemeIds));

        $aResults = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->select(
            "SELECT OXID as id,
                           OXACTIVE as active,
                           OXTHEME as theme,
                           OXTEMPLATE as template,
                           OXBLOCKNAME as block,
                           OXFILE as file,
                           OXPOS
                    FROM oxtplblocks
                    WHERE oxModule = ? AND oxshopid = ? AND OXTHEME IN ($themeIdsSql)
                    ORDER BY OXTHEME, OXTEMPLATE, OXBLOCKNAME",
            [$this->getId(), $config->getShopId()]
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
     * @param string $version
     *
     * @return bool
     */
    public function isMetadataVersionGreaterEqual($version)
    {
        return version_compare($this->getMetaDataVersion(), $version) >= 0;
    }

    /**
     * check if current metadata version is $sVersion
     *
     * @param string $version
     *
     * @return bool
     */
    public function checkMetadataVersion($version)
    {
        return version_compare($this->getMetaDataVersion(), $version) == 0;
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
     * @param string $type
     * @return array
     */
    public function getModuleEntries($type)
    {
        $aReturn = [];
        $aList = oxNew(ModuleList::class)->getModuleConfigParametersByKey($type);

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
     * @param string $className
     *
     * @return bool
     */
    public function checkPhpFileExists($className)
    {
        if ($this->isMetadataVersionGreaterEqual('2.0')) {
            return $this->getModuleHelper()->getAutoloader()->findFile($className);
        }

        $sExtensionPath = $className . '.php';
        return $this->checkFileExists($sExtensionPath);
    }



    /**
     * Analyze versions in metadata
     * checks if metadata version is same as database entry for metadata
     *
     * @return array
     */
    public function checkModuleVersions()
    {
        $iLang = Registry::getLang()->getTplLanguage();
        $databaseVersion = $this->getInfo("version", $iLang);

        return $this->toResult(['version' => $databaseVersion]);
    }

    public function getTitle()
    {
        if ($this->checked !== false) {
            return $this->checked['title'];
        }
        $title = parent::getTitle();
        $request = Registry::getRequest();
        $controller = $request->getRequestParameter('cl');

        if ($controller === 'module_list' || $controller === 'checkconsistency') {
            $fixed = $this->getModuleStateFixer()->fix($this);
            if ($fixed) {
                $title .= ' <strong style="color: #00e200">State fixed</strong>';
            }
            $this->checked = $this->checkState();
            if ($this->hasIssue()) {
                $title .= ' <strong style="color: #009ddb">Issue found!</strong>';
            }

            $this->checked['title'] = $title;
        }
        return $title;
    }

    public function hasIssue()
    {
        return ($this->state & self::NEED_MANUAL_FIXED) == self::NEED_MANUAL_FIXED;
    }

    /**
     * Analyze extended class information in metadata and database.
     *
     * @return array
     */
    public function checkExtendedClasses()
    {
        $aMetadataExtend = $this->getInfo('extend');
        $aMetadataExtend = is_array($aMetadataExtend) ? $aMetadataExtend : [];

        $aResult = [];

        /**
         * only convert class names to lower if we don't use namespace
         */
        if (!$this->isMetadataVersionGreaterEqual('2.0')) {
            $aMetadataExtend = array_change_key_case($aMetadataExtend, CASE_LOWER);
            //convert legacy classnames because $aAllModules dos not contain legacy classes
            if (method_exists(Registry::class, 'getBackwardsCompatibilityClassMap')) {
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

        $moduleClassSeenBefore = [];
        foreach ($aMetadataExtend as $oxidClass => $moduleClass) {
            $key_state = $iState = self::OK;
            if (isset($moduleClassSeenBefore[$moduleClass])) {
                $iState = self::MODULE_FILE_NOT_FOUND;
                $key_state =  self::OK;
            }
            $moduleClassSeenBefore[$moduleClass] = 1;

            if (strpos($oxidClass, 'OxidEsales\\EshopCommunity\\') === 0 ||
                strpos($oxidClass, 'OxidEsales\\EshopEnterprise\\') === 0 ||
                strpos($oxidClass, 'OxidEsales\\EshopProfessional\\') === 0 ||
                !class_exists($oxidClass)
            ) {
                if (strpos($oxidClass, 'oxerp') === 0) {
                    //AS ERP module does still use a own autoloader
                    //classes of oxerp will not be found with class_exists
                    $erp_dir = Registry::getConfig()->getModulesDir() . 'erp/';
                    if (strpos($oxidClass, 'oxerptype_') === 0) {
                        $dir = $erp_dir . 'objects/';
                    } else {
                        $dir = $erp_dir;
                    }

                    $sFullPath  = $dir . $oxidClass . '.php';

                    if (!file_exists($sFullPath)) {
                        $key_state = self::SHOP_FILE_NOT_FOUND;
                        $this->state |= self::NEED_MANUAL_FIXED;
                    }
                } else {
                    $key_state = self::SHOP_FILE_NOT_FOUND;
                    $this->state |= self::NEED_MANUAL_FIXED;
                }
            }

            $aResult[ $oxidClass ]['key_state'] = $key_state;

            if (!$this->checkPhpFileExists($moduleClass)) {
                $iState = self::MODULE_FILE_NOT_FOUND;
                $this->state |= self::NEED_MANUAL_FIXED;
            }

            $aResult[ $oxidClass ][ 'data' ] = $moduleClass;
            $aResult[ $oxidClass ][ 'state' ] = $iState;
        }

        return $aResult;
    }

    public function setModuleData($aModule)
    {
        $moduleId = $aModule['id'];
        if (!isset($aModule['version'])) {
            $package = $this->getComposerPackage($moduleId);

            if ($package) {
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
        $aDatabaseBlocks = $this->getModuleBlocks();
        $aDatabaseBlocks = is_array($aDatabaseBlocks) ? $aDatabaseBlocks : [];
        $aMetadataTemplates = $this->getInfo('templates');

        $config = Registry::getConfig();
        $sModulesDir = $config->getModulesDir();

        // Check if all blocks are injected.
        foreach ($aDatabaseBlocks as &$aBlock) {
            $iState = self::OK;


            $file = $aBlock['file'];
            if (!$this->checkFileExists($sModulePath . '/' . $file) &&
                !$this->checkFileExists($sModulePath . '/out/blocks/' . basename($file)) &&
                !$this->checkFileExists($sModulePath . '/out/blocks/' . basename($file) . '.tpl')
            ) {
                $iState = self::MODULE_FILE_NOT_FOUND;
                $this->state |= self::NEED_MANUAL_FIXED;
            }

            $block = $aBlock['block'];
            $template = $aBlock['template'];
            $aBlock['state'] = $iState;

            // Check if template file exists and block is defined.

            // Get template from shop..
            $sTemplate = $config->getTemplatePath($template, false);

            // Get template from shop admin ..
            if (!$sTemplate) {
                $sTemplate = $config->getTemplatePath($template, true);
            }

            // Get template from module ..
            if (!$sTemplate && isset($aMetadataTemplates[$template]) && $this->checkFileExists(
                    $aMetadataTemplates[$template]
                )) {
                    $sTemplate = $sModulesDir . '/' . $aMetadataTemplates[$template];
                }

            if (empty($sTemplate)) {
                $aBlock['t_state'] = self::SHOP_FILE_NOT_FOUND;
                $this->state |= self::MAY_NEED_MANUAL_FIX;
            } else {
                $aBlock['t_state'] = self::OK;
                $aBlock['b_state'] = self::OK;
                $sContent = file_get_contents($sTemplate);
                if (!preg_match('/\[{\s*block[^}]+?name\s*=\s*["\']' . $block . '[\'"].*?}\]/', $sContent)) {
                    $aBlock['b_state'] = self::SHOP_FILE_NOT_FOUND;
                    $this->state |= self::MAY_NEED_MANUAL_FIX;
                }
            }
        }

        return $aDatabaseBlocks;
    }

    /**
     * Analyze settings in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleSettings()
    {
        $aDatabaseSettings = $this->getModuleSettings();
        $aDatabaseSettings = is_array($aDatabaseSettings) ? $aDatabaseSettings : [];
        $list = [];
        foreach ($aDatabaseSettings as $v) {
            $list[$v['OXVARNAME']] = $v['OXVARTYPE'];
        }

        return $this->toResult($list);
    }

    /**
     * Analyze templates in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleTemplates()
    {
        $aDatabaseTemplates = $this->getModuleEntries(ModuleList::MODULE_KEY_TEMPLATES);
        return $this->checkFiles($aDatabaseTemplates, false);
    }

    /**
     * Analyze controller in metadata
     *
     * @return array
     */
    public function checkModuleController()
    {
        $oModuleStateFixer = Registry::get(ModuleStateFixer::class);
        $controllers =  $oModuleStateFixer->getModuleControllerEntries($this->getId());
        return $this->checkFiles($controllers, true);
    }

    /**
     * Analyze file in metadata
     *
     * @return array
     */
    public function checkModuleFiles()
    {
        $aDatabaseFiles = $this->getModuleEntries(ModuleList::MODULE_KEY_FILES);
        return $this->checkFiles($aDatabaseFiles, false);
    }

    protected function toResult($array)
    {
        $result = [];
        foreach ($array as $key => $data) {
            $result[$key]['data'] = $data;
            $result[$key]['state'] = self::OK;
        }
        return $result;
    }

    protected function checkFiles($files, $php)
    {
        $result = [];
        foreach ($files as $key => $file) {
            $result[$key]['data'] = $file;
            $s = self::OK;
            if (($php && !$this->checkPhpFileExists($file))
                || ((!$php) && !$this->checkFileExists($file))
            ) {
                $s = self::MODULE_FILE_NOT_FOUND;
                $this->state |= self::NEED_MANUAL_FIXED;
            }
            $result[$key]['state'] = $s;
        }
        return $result;
    }

    /**
     * Analyze events in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleEvents()
    {
        $aDatabaseEvents = $this->getModuleEntries(ModuleList::MODULE_KEY_EVENTS);

        $aDatabaseEvents = is_array($aDatabaseEvents) ? $aDatabaseEvents : [];
        $aDatabaseEvents = array_map(function ($value) {
            return print_r($value, true);
        }, $aDatabaseEvents);
        $aResult = $this->toResult($aDatabaseEvents);
        foreach ($aResult as $eventName => &$data) {
            $data['key_state'] = ($eventName === 'onActivate' || $eventName === 'onDeactivate')
                ? self::OK
                : self::SHOP_FILE_NOT_FOUND;
        }
        return $aResult;
    }


    /**
     * @param string $title
     * @return array
     */
    public function checkState($title = '')
    {
        if ($this->checked !== false) {
            return $this->checked;
        }
        $oModule = $this;
        $aModule = array();
        $aModule['oxid'] = $oModule->getId();
        $aModule['title'] = $aModule['oxid'] . " - " . $title;

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
        }

        $aModule['aVersions'] = $oModule->checkModuleVersions();


        if ($oModule->isMetadataVersionGreaterEqual('2.0')) {
            $aModule['aControllers'] = $oModule->checkModuleController();
        }
        return $aModule;
    }

    protected $filelist = [];
    /**
     * @param string $filePath
     * @return bool
     */
    protected function checkFileExists($filePath)
    {
        $sModulesDir = Registry::getConfig()->getModulesDir();
        $filePath = $sModulesDir . $filePath;

        $dir = dirname($filePath);
        $file = basename($filePath);
        $res = true;
        //check if filename case sensitive so we will see errors
        //also on case insensitive filesystems
        $filelist = $this->filelist[$dir];
        if (!isset($filelist)) {
            $filelist = $this->filelist[$dir] = scandir($dir);
        }
        if (!in_array($file, $filelist)) {
            $res = false;
        }
        return $res;
    }

    /**
     * @param string|null $moduleId
     * @return bool|PackageInterface
     */
    protected function getComposerPackage($moduleId = null)
    {
        if ($moduleId == null) {
            $moduleId = $this->getId();
        }
        /**
         * @var OxidComposerModulesService $packageService
         */
        $packageService = Registry::get(OxidComposerModulesService::class);
        return $packageService->getPackage($moduleId);
    }
}
