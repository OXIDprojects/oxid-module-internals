<?php
/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 15.01.19
 * Time: 22:06
 */

namespace OxidCommunity\ModuleInternals\Core;


use OxidEsales\Eshop\Core\Registry;

class ModuleHelper
{
    protected $module;

    public function setModule($module){
        $this->module = $module;
    }

    /**
     * @param Module $module
     *
     * @return string
     */
    public function getModuleNameSpace()
    {
        $module = $this->module;
        $package = $this->getComposerPackage();
        $sModulePath = $module->getModulePath();
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
            $namesspaces = $autoload["psr-4"];
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
     * @param Module $module
     * @return bool|\Composer\Package\PackageInterface
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


    public function getAutoloader(){
        if (Registry::instanceExists('autoloader')){
            return Registry::get('autoloader');
        }
        $composerClassLoader = include VENDOR_PATH . 'autoload.php';
        Registry::set('autoloader', $composerClassLoader);
        return $composerClassLoader;
    }

}
