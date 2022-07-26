<?php

/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 04.01.19
 * Time: 00:01
 */

namespace OxidCommunity\ModuleInternals\Tests\Integration\Controller;

use OxidEsales\TestingLibrary\UnitTestCase;
use OxidCommunity\ModuleInternals\Controller\CheckConsistency;
use OxidEsales\Eshop\Core\Utils;
use OxidEsales\Eshop\Core\Registry;

class CheckConsistencyTest extends UnitTestCase
{
    public function testRender()
    {
        $controller = oxNew(CheckConsistency::class);
        $result = $controller->render();
        $this->assertSame('checkconsistency.tpl', $result);
    }

    public function testInit()
    {
        $controller = oxNew(CheckConsistency::class);
        $mockBuilder = $this->getMockBuilder(Utils::class);
        $mockBuilder->setMethods(['handlePageNotFoundError']);
        $utils = $mockBuilder->getMock();
        $utils->expects($this->once())->method("handlePageNotFoundError");
        Registry::set(Utils::class, $utils);
        $controller->init();
    }
}
