<?php
/**
 * @package   moduleinternals
 * @category  OXID Module
 * @version   1.0.1
 * @license   GPL3 License http://opensource.org/licenses/GPL
 * @author    Alfonsas Cirtautas / OXID Community
 * @link      https://github.com/OXIDprojects/ocb_cleartmp
 * @see       https://github.com/acirtautas/oxid-module-internals
 */

namespace OxidCommunity\ModuleInternals\Tests\Integration\core;

use OxidCommunity\ModuleInternals\Core\FixHelper;
use OxidCommunity\ModuleInternals\Core\ModuleStateFixer;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleCache;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\TestingLibrary\UnitTestCase;

/**
 * Class FixHelperTest:
 */
class FixHelperTest extends UnitTestCase
{

   public function testFixTemplate()
    {
        $this->setConfigParam('aModuleTemplates', ['a' => 'b']);
        $this->callSut();
        $this->assertNotEquals($this->getConfigParam('aModuleTemplates'), ['a' => 'b']);
        $this->assertLogEntry("fixing templates");
    }

    public function testFixVersion()
    {
        $this->setConfigParam('aModuleVersions', ['moduleinternals' => '0.1.0']);
        $this->callSut();

        $this->assertNotEquals($this->getConfigParam('aModuleVersions'), ['moduleinternals' => '0.1.0']);
        $this->assertLogEntry("fixing module version");
    }

    public function testFixExtensions()
    {
        $this->setConfigParam('aModuleExtensions', ['a' => ['b']]);
        $this->callSut();
        $this->assertNotEquals($this->getConfigParam('aModuleExtensions'), ['a' => ['b']]);
        $this->assertLogEntry("fixing module extensions");
    }

    public function testFixControllers()
    {
        print_r ($this->getConfigParam('aModuleControllers'));
        $this->setConfigParam('aModuleControllers', ['a' => 'b']);
        $this->callSut();
        $this->assertNotEquals($this->getConfigParam('aModuleControllers'), ['a' => 'b']);
        //$this->assertLogEntry("fixing module controllers");
    }

    /**
     * @param $moduleId
     * @return array
     */
    private function callSut()
    {
        $moduleId = 'moduleinternals';
        $module = oxNew(Module::class);
        $module->load($moduleId);
        $fixHelper = oxNew(ModuleStateFixer::class);

        $fixHelper->fix($module);
    }

    private function assertLogEntry($text)
    {
        $content = $this->exceptionLogHelper->getExceptionLogFileContent();
        $this->assertContains($text, $content);
        $this->exceptionLogHelper->clearExceptionLogFile();
    }

}
