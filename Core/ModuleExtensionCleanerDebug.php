<?php

/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 23.11.18
 * Time: 11:37
 */

namespace OxidCommunity\ModuleInternals\Core;

use OxidEsales\Eshop\Core\Module\ModuleExtensionsCleaner;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Module\ModuleList;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ModuleExtensionCleanerDebug extends ModuleExtensionsCleaner
{
    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $out
     */
    public function setLogger(LoggerInterface $out)
    {

        $this->logger = $out;
    }

    /**
     * Removes garbage ( module not used extensions ) from all installed extensions list.
     * For example: some classes were renamed, so these should be removed.
     *
     * @param array                                $installedExtensions
     * @param Module $module
     *
     * @return array
     */
    public function cleanExtensions($installedExtensions, Module $module)
    {
        $moduleExtensions = $module->getExtensions();

        $installedModuleExtensions = $this->filterExtensionsByModule($installedExtensions, $module);

        if (count($installedModuleExtensions)) {
            $garbage = $this->getModuleExtensionsGarbage($moduleExtensions, $installedModuleExtensions);

            if (count($garbage)) {
                $installedExtensions = $this->removeGarbage($installedExtensions, $garbage);
            }
        }

        $oModules = oxNew(ModuleList::class);
        //ids will include garbage in case there are files that not registered by any module
        $ids = $oModules->getModuleIds();

        $config = Registry::getConfig();
        $knownIds = array_keys($config->getConfigParam('aModulePaths'));
        $diff = array_diff($ids, $knownIds);
        if ($diff) {
            foreach ($diff as $item) {
                foreach ($installedExtensions as &$coreClassExtension) {
                    foreach ($coreClassExtension as $i => $ext) {
                        if ($ext === $item) {
                            $this->logger->debug("$item will be removed");
                            unset($coreClassExtension[$i]);
                        }
                    }
                }
            }
        }

        return $installedExtensions;
    }

    protected function removeGarbage($aInstalledExtensions, $aarGarbage)
    {
        foreach ($aarGarbage as $moduleId => $aExt) {
            $this->logger->info("removing garbage for module $moduleId: " . implode(',', $aExt));
        }
        return parent::removeGarbage($aInstalledExtensions, $aarGarbage);
    }

    /**
     * Returns extension which is no longer in metadata - garbage
     *
     * @param array $moduleMetaDataExtensions  extensions defined in metadata.
     * @param array $moduleInstalledExtensions extensions which are installed
     *
     * @return array
     */
    protected function getModuleExtensionsGarbage($moduleMetaDataExtensions, $moduleInstalledExtensions)
    {

        $garbage = parent::getModuleExtensionsGarbage($moduleMetaDataExtensions, $moduleInstalledExtensions);

        foreach ($moduleInstalledExtensions as $coreClassName => $listOfExtensions) {
            foreach ($listOfExtensions as $extensions) {
                if (!(isset($moduleMetaDataExtensions[$coreClassName])
                    && $moduleMetaDataExtensions[$coreClassName] == $extensions
                    )
                ) {
                    $garbage[$coreClassName][] = $extensions;
                }
            }
        }

        return $garbage;
    }

    /**
     * Returns extensions list by module id.
     *
     * @param array  $modules  Module array (nested format)
     * @param Module $module Module
     *
     * @return array
     */
    protected function filterExtensionsByModule($modules, $module)
    {
        if ($this->isMetadataVersionGreaterEqual($module, '2.0')) {
            $moduleHelper = Registry::get(ModuleHelper::class);
            $moduleHelper->setModule($module);
            $path = $moduleHelper->getModuleNameSpace();
        } else {
            $modulePaths = Registry::getConfig()->getConfigParam('aModulePaths');

            $moduleId = $module->getId();
            $path = '';
            if (isset($modulePaths[$moduleId])) {
                $path = $modulePaths[$moduleId] . '/';
            }

            if (!$path) {
                $path = $moduleId . "/";
            }
        }
        $filteredModules = [];

        if (!$path) {
            return $filteredModules;
        }

        foreach ($modules as $class => $extend) {
            foreach ($extend as $extendPath) {
                if (strpos($extendPath, $path) === 0) {
                    $filteredModules[$class][] = $extendPath;
                }
            }
        }

        return $filteredModules;
    }

    /**
     * @param Module $module
     * @param string $sVersion
     * @return bool
     */
    public function isMetadataVersionGreaterEqual($module, $sVersion)
    {
        return version_compare($module->getMetaDataVersion(), $sVersion) >= 0;
    }
}
