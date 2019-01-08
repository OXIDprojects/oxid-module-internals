<?php
/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 08.01.19
 * Time: 22:26
 */

namespace OxidCommunity\ModuleInternals\Tests\Integration\Controller;
use OxidEsales\Eshop\Application\Controller\Admin\NavigationController;
use OxidEsales\TestingLibrary\UnitTestCase;

class NavigationControllerTest extends UnitTestCase
{

    public function testGet()
    {
        $controller = oxNew(NavigationController::class);
        $messages = [];
        $controller->checkModules($messages);
        print_r($messages);
        $this->assertEquals($messages, []);
    }
}