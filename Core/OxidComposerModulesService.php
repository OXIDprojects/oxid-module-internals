<?php

namespace OxidCommunity\ModuleInternals\Core;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Repository\FilesystemRepository;
use Composer\Repository\InstalledFilesystemRepository;
use OxidEsales\EshopCommunity\Core\Module\ModuleList;
use OxidEsales\EshopCommunity\Core\Registry;

/**
 * Class Resposible for abstracting Composer access
 * and mapping Oxid modules to Composer packages
 */
class OxidComposerModulesService
{
    protected $list;
    /**
     * get List of packages
     */
    public function getList(){
        if ($this->list !== null){
            return $this->list;
        }
        $list = [];
        $packages = $this->getOxidModulePackages();
        //$moduleList = Registry::get(ModuleList::class);
        //$pathList = $moduleList->getModuleConfigParametersByKey(ModuleList::MODULE_KEY_PATHS);
        //$path2Id = array_flip($pathList);
        $config = Registry::getConfig();
        $paths = $config->getConfigParam('aModulePaths');
        $path2id = array_flip($paths);
        foreach ($packages as $package) {
            $extra = $package->getExtra();
            $oxideshop = isset($extra['oxideshop']) ? $extra['oxideshop'] : [];
            if (isset($oxideshop['target-directory'])) {
                $id = $path2id[$oxideshop['target-directory']];
                $list[$id] = $package;
            }
        }
        $this->list = $list;
        return $list;
    }

    /**
     * @param $moduleId
     * @return bool|PackageInterface
     */
    public function getPackage($moduleId) {
        $list = $this->getList();
        if (!isset($list[$moduleId])) {
            return false;
        }
        return $list[$moduleId];
    }

    /**
     * @param $metadataPath
     * @return string
     */
    protected function getIdFromMetadata($metadataPath)
    {
        include $metadataPath;
        if (isset($aModule)) {
            return $aModule['id'];
        }
        return '';
    }

    /**
     * @return array|\Composer\Package\PackageInterface[]
     */
    public function getOxidModulePackages()
    {
        $packages = $this->getPackages();
        $packages = array_filter($packages, function (PackageInterface $package) {
            return $package->getType() == 'oxideshop-module';
        }
        );
        return $packages;
    }

    /**
     * @return \Composer\Package\PackageInterface[]
     */
    public function getPackages(){
        $localRepository = new InstalledFilesystemRepository(new JsonFile(VENDOR_PATH.'/composer/installed.json'));
        $packages = $localRepository->getPackages();
        return $packages;
    }
}