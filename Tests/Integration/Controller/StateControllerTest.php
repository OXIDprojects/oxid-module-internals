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

namespace OxidCommunity\ModuleInternals\Tests\Integration\Controller;

use OxidCommunity\ModuleInternals\Core\ModuleStateFixer;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\EshopCommunity\Core\DatabaseProvider;
use OxidEsales\TestingLibrary\UnitTestCase;
use OxidCommunity\ModuleInternals\Controller\Admin\State;
use OxidEsales\Eshop\Core\Module\Module as Module;
use OxidCommunity\ModuleInternals\Core\DataHelper as DataHelper;
use OxidCommunity\ModuleInternals\Core\FixHelper as FixHelper;

/**
 *
 */
class StateControllerTest extends UnitTestCase
{
    /**
     *
     */
    public function testGetModule()
    {
        $moduleId = 'moduleinternals';
        $this->setRequestParameter('oxid', $moduleId);
        $stateController = oxNew(State::class);

        $module = $stateController->getModule();

        $this->assertInstanceOf(Module::class, $module, 'class not as expected');
        $this->assertEquals($module->getId(), $moduleId);
    }

    public function testBlock()
    {
        $moduleId = 'moduleinternals';
        $this->setRequestParameter('oxid', $moduleId);
        $data = 'testid';
        $this->setRequestParameter('data', $data);

        $db = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $db->execute(
            "INSERT INTO `oxtplblocks`
                (`OXID`, `OXSHOPID`, `OXTHEME`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`)
                VALUES (?, 1, 'testtheme', 'testtemplate', 'block', 1, 'testfile', ?)",
            [$data,$moduleId]
        );

        $stateController = oxNew(State::class);
        $stateController->block();

        $this->checkActive('0');
        $stateController->block();
        $this->checkActive('1');
    }

    /**
     * @param bool $should
     * @return mixed
     */
    private function checkActive($should)
    {
        $db = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $active = $db->getOne(
            "SELECT OXACTIVE FROM `oxtplblocks`
            WHERE `OXID` = 'testid' AND OXMODULE ='moduleinternals' AND OXSHOPID = 1"
        );
        $this->assertSame($should, $active);

        return $active;
    }
}
