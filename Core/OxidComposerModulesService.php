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
    /**
     * get List of packages
     */
    public function getList(){
        $list = [];
        $packages = $this->getOxidModulePackages();
        //$moduleList = Registry::get(ModuleList::class);
        //$pathList = $moduleList->getModuleConfigParametersByKey(ModuleList::MODULE_KEY_PATHS);
        //$path2Id = array_flip($pathList);
        foreach ($packages as $package) {
            $dir = $package->getName();
            $dir = VENDOR_PATH . $dir;
            $file = $dir . DIRECTORY_SEPARATOR . 'metadata.php';
            $id = $this->getIdFromMetadata($file);
            $list[$id] = $package;
        }
        return $list;
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