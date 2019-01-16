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
    protected $state = self::FINE;
    protected $checked = false;
    /** @var ModuleStateFixer */
    protected $_oModuleFixHelper;

    const FINE = 0;
    const NEED_MANUAL_FIXED = 1;
    const MAY_NEED_MANUAL_FIX = 2;

    const OK = 'sok';
    const MODULE_FILE_NOT_FOUND = 'sfatalm';
    const SHOP_FILE_NOT_FOUND = 'sfatals';

    public function load($id){
        $this->metaDataVersion = 0;
        $this->checked = false;
        $this->state = self::FINE;
        $res = parent::load($id);
        return $res;
    }

    /**
     * @param ModuleStateFixer $oModuleFixHelper
     */
    public function setModuleFixHelper($oModuleFixHelper)
    {
        $this->_oModuleFixHelper = $oModuleFixHelper;
    }


    public function getModuleHelper(){
        return Registry::get(ModuleHelper::class);
    }


    /**
     * @return ModuleStateFixer
     */
    public function getModuleStateFixer()
    {
        if ($this->_oModuleFixHelper === null) {
            $this->_oModuleFixHelper = Registry::get(ModuleStateFixer::class);
	    //$this->_oModuleFixHelper->disableInitialCacheClear();
        }

        return $this->_oModuleFixHelper;
    }

    /**
     * Get template blocks defined in database.
     *
     * @return array
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function getModuleBlocks()
    {
        $config = Registry::getConfig();
        $activeThemeIds = oxNew(\OxidEsales\Eshop\Core\Theme::class)->getActiveThemesList();
        $activeThemeIds[] = '';

        $themeIdsSql = join(', ', \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->quoteArray($activeThemeIds));

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
     * @param string $sClassName
     * @param string $sExtention
     *
     * @return bool
     */
    public function checkPhpFileExists($sClassName)
    {
        if ($this->isMetadataVersionGreaterEqual('2.0')) {
            $composerClassLoader = $this->getModuleHelper()->getAutoloader();

            return $composerClassLoader->findFile($sClassName);
        } else {
            $sExtensionPath = $sClassName . '.php';
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
        $sDatabaseVersion = $this->getModuleEntries(ModuleList::MODULE_KEY_VERSIONS);

        $aResult = $this->toResult(['version'=>$sDatabaseVersion]);

        return $aResult;
    }

    public function getTitle() {
        if ($this->checked !== false) {
            return $this->checked['title'];
        }
        $title = parent::getTitle();
        $request = Registry::getRequest();
        $controller = $request->getRequestParameter('cl');

        if ($controller == 'module_list' || $controller == 'checkconsistency') {
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

    public function hasIssue(){
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
            $key_state = $iState = self::OK;

            if (strpos($sClassName,'OxidEsales\\EshopCommunity\\') === 0 ||
                strpos($sClassName,'OxidEsales\\EshopEnterprise\\') === 0 ||
                strpos($sClassName,'OxidEsales\\EshopProfessional\\') === 0 ||
                !class_exists($sClassName)
            ) {
                if (strpos($sClassName,'oxerp') === 0) {
                    //AS ERP module does still use a own autoloader
                    //classes of oxerp will not be found with class_exists
                    $erp_dir = $this->getConfig()->getModulesDir() .'erp/';
                    if (strpos($sClassName,'oxerptype_') === 0) {
                        $dir = $erp_dir.'objects/';
                    } else {
                        $dir = $erp_dir;
                    }

                    $sFullPath  = $dir.$sClassName.'.php';

                    if (!file_exists($sFullPath)) {
                        $key_state = self::SHOP_FILE_NOT_FOUND;
                        $this->state |= self::NEED_MANUAL_FIXED;
                    }
                } else {
                    $key_state = self::SHOP_FILE_NOT_FOUND;
                    $this->state |= self::NEED_MANUAL_FIXED;
                }
            }

            $aResult[ $sClassName ]['key_state'] = $key_state;

            if (!$this->checkPhpFileExists($sModuleName)) {
                $iState = self::MODULE_FILE_NOT_FOUND;
                $this->state |= self::NEED_MANUAL_FIXED;
            }

            $aResult[ $sClassName ][ 'data' ] = $sModuleName;
            $aResult[ $sClassName ][ 'state' ] = $iState;
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
            if (!$this->checkFileExists( $sModulePath . '/' . $file) &&
                !$this->checkFileExists( $sModulePath . '/out/blocks/' . basename($file)) &&
                !$this->checkFileExists( $sModulePath . '/out/blocks/' . basename($file) . '.tpl')
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
            if (!$sTemplate && isset($aMetadataTemplates[$template])) {

                if ($this->checkFileExists($aMetadataTemplates[$template])) {
                    $sTemplate = $sModulesDir . '/' . $aMetadataTemplates[$template];
                }
            }

            if (empty($sTemplate)) {
                $aBlock['t_state'] = self::SHOP_FILE_NOT_FOUND;
                $this->state |= self::MAY_NEED_MANUAL_FIX;
            } else {
                $aBlock['t_state'] = self::OK;
                $aBlock['b_state'] = self::OK;
                $sContent = file_get_contents($sTemplate);
                if (!preg_match('/\[{.*block.* name.*= *"' . $block . '".*}\]/', $sContent)) {
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

        $aResult = $this->toResult($list);
        return $aResult;
    }

    /**
     * Analyze templates in metadata ans settings.
     *
     * @return array
     */
    public function checkModuleTemplates()
    {
        $aDatabaseTemplates = $this->getModuleEntries(ModuleList::MODULE_KEY_TEMPLATES);
        $aResult = $this->checkFiles($aDatabaseTemplates, false);

        return $aResult;
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

    protected function toResult($array){
        $result = [];
        foreach ($array as $key => $data) {
            $result[$key]['data'] = $data;
            $result[$key]['state'] = self::OK;
        }
        return $result;
    }

    protected function checkFiles($files , $php){
        $result = [];
        foreach ($files as $key => $file) {
            $result[$key]['data'] = $file;
            $s = self::OK;
            if (($php && !$this->checkPhpFileExists($file))
                || ((!$php) && !$this->checkFileExists($file))) {
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
        $aDatabaseEvents = array_map(function ($value){return print_r($value,true);}, $aDatabaseEvents);
        $aResult = $this->toResult($aDatabaseEvents);
        foreach ($aResult as $eventName => &$data){
            $data['key_state'] = ($eventName == 'onActivate' || $eventName == 'onDeactivate') ? self::OK : self::SHOP_FILE_NOT_FOUND;
        }
        return $aResult;
    }


    /**
     * @param $oModule
     * @param $sModId
     * @param $sTitle
     * @return array
     */
    public function checkState($sTitle = '')
    {
        if ($this->checked !== false) {
            return $this->checked;
        }
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
        }

        $aModule['aVersions'] = $oModule->checkModuleVersions();


        if ($oModule->isMetadataVersionGreaterEqual('2.0')) {
            $aModule['aControllers'] = $oModule->checkModuleController();
        }
        return $aModule;
    }

    protected $filelist = [];
    /**
     * @param $filePath
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
     * @param $moduleId
     * @return bool|\Composer\Package\PackageInterface
     */
    protected function getComposerPackage($moduleId = null)
    {
        if ($moduleId == null){
            $moduleId = $this->getId();
        }
        /**
         * @var $packageService OxidComposerModulesService
         */
        $packageService = Registry::get(OxidComposerModulesService::class);
        $package = $packageService->getPackage($moduleId);
        return $package;
    }


}
