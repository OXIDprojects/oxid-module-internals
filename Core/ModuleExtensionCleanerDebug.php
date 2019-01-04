<?php
/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 23.11.18
 * Time: 11:37
 */

namespace OxidCommunity\ModuleInternals\Core;


use OxidEsales\Eshop\Core\Module\ModuleExtensionsCleaner;
use OxidEsales\Eshop\Core\Registry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleExtensionCleanerDebug extends ModuleExtensionsCleaner
{
    protected $_debugOutput;

    public function __construct()
    {
        $this->_debugOutput= new NullLogger();
    }

    public function setOutput(LoggerInterface $out){

        $this->_debugOutput = $out;
    }

    /**
     * Removes garbage ( module not used extensions ) from all installed extensions list.
     * For example: some classes were renamed, so these should be removed.
     *
     * @param array                                $installedExtensions
     * @param \OxidEsales\Eshop\Core\Module\Module $module
     *
     * @return array
     */
    public function cleanExtensions($installedExtensions, \OxidEsales\Eshop\Core\Module\Module $module)
    {
        $moduleExtensions = $module->getExtensions();

        $installedModuleExtensions = $this->filterExtensionsByModule($installedExtensions, $module);

        if (count($installedModuleExtensions)) {
            $garbage = $this->getModuleExtensionsGarbage($moduleExtensions, $installedModuleExtensions);

            if (count($garbage)) {
                $installedExtensions = $this->removeGarbage($installedExtensions, $garbage);
            }
        }

        $oModules = oxNew( \OxidEsales\EshopCommunity\Core\Module\ModuleList::class );
        //ids will include garbage in case there are files that not registered by any module
        $ids = $oModules->getModuleIds();

        $config = Registry::getConfig();
        $knownIds = array_keys($config->getConfigParam('aModulePaths'));
        $diff = array_diff($ids,$knownIds);
        if ($diff) {
            foreach ($diff as $item) {
                foreach ($installedExtensions as &$coreClassExtension) {
                    foreach ($coreClassExtension as $i => $ext) {
                        if ($ext === $item) {
                            $this->_debugOutput->debug("$item will be removed");
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
            $this->_debugOutput->info("removing garbage for module $moduleId: " . join(',', $aExt));
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
                if (! (isset($moduleMetaDataExtensions[$coreClassName]) && $moduleMetaDataExtensions[$coreClassName] == $extensions)) {
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
     * @param string $module Module id/folder name
     *
     * @return array
     */
    protected function filterExtensionsByModule($modules, $module)
    {
        if ($module->isMetadataVersionGreaterEqual('2.0')) {
            $path = $module->getModuleNameSpace();
        } else {

            $modulePaths = \OxidEsales\Eshop\Core\Registry::getConfig()->getConfigParam('aModulePaths');

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
}