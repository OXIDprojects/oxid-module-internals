<?php
namespace OxidCommunity\ModuleInternals\Tests\Integration\Controller;
use OxidCommunity\ModuleInternals\Controller\Admin\NavigationController;
use OxidEsales\TestingLibrary\UnitTestCase;

class NavigationControllerTest extends UnitTestCase
{

    public function testNavigationController()
    {
        $controller = oxNew(NavigationController::class);
        $messages = [];
        $controller->checkModules($messages);
        print_r($messages);
        $this->assertEquals($messages, []);
    }
}
