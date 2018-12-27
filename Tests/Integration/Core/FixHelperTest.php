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

    /**
     *
     */
    public function testFixVersion()
    {
        $moduleId = 'moduleinternals';
        $this->setConfigParam('aModuleVersions', [$moduleId => '0.1.0']);
        $this->setConfigParam('aModuleExtend', ['a' => 'b']);
        $module = oxNew(Module::class);
        $module->load($moduleId);
        $fixHelper = oxNew(ModuleStateFixer::class);

        $fixHelper->fix($module);

        $this->assertInstanceOf(FixHelper::class, $fixHelper);
        $this->assertNotEquals($this->getConfigParam('aModuleVersions'), [$moduleId => '0.1.0']);
        $this->assertNotEquals($this->getConfigParam('aModuleExtend'), ['a' => 'b']);
    }


    /**
     * @param $moduleId
     *
     * @return object
     */
    protected function createFixHelper($moduleId)
    {

        return $fixHelper;
    }
}
