<?php

/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 15.01.19
 * Time: 22:06
 */

namespace OxidCommunity\ModuleInternals\Core;

use Composer\Package\PackageInterface;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\Module;


class ModuleHelper
{
    /**
     * @var Module $module
     */
    protected $module;

    public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * @return string
     */
    public function getModuleNameSpace()
    {
        $module = $this->module;
        $package = $this->getComposerPackage();
        $sModulePath = $module->getModulePath();
        $autoload = false;
        if ($package) {
            $autoload = $package->getAutoload();
        } else {
            $sModulesDir = Registry::getConfig()->getModulesDir();
            $file = $sModulesDir . $sModulePath . '/composer.json';
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                $autoload = $data["autoload"];
            }
        }

        if ($autoload) {
            $namespaces = $autoload["psr-4"];
            $prefix = array_keys($namespaces);
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
     * @return bool|PackageInterface
     */
    protected function getComposerPackage()
    {
        $moduleId = $this->module->getId();

        /**
         * @var $packageService OxidComposerModulesService
         */
        $packageService = Registry::get(OxidComposerModulesService::class);
        $package = $packageService->getPackage($moduleId);
        return $package;
    }


    public function getAutoloader()
    {
        if (Registry::instanceExists('autoloader')) {
            return Registry::get('autoloader');
        }
        $composerClassLoader = include VENDOR_PATH . 'autoload.php';
        Registry::set('autoloader', $composerClassLoader);
        return $composerClassLoader;
    }
}
