<?php

namespace OxidCommunity\ModuleInternals\Tests\Integration\Controller;

use OxidEsales\Eshop\Application\Controller\Admin\NavigationController;
use OxidEsales\TestingLibrary\UnitTestCase;

class NavigationControllerTest extends UnitTestCase
{

    public function testNavigationController()
    {
        $controller = oxNew(NavigationController::class);
        $messages = [];
        $this->setConfigParam('aModuleExtensions', ['a' => ['b']]);
        $controller->checkModules($messages);
        $this->assertContains("Module OXID Community Module Internals was fixed", $messages["warning"]);
        $this->exceptionLogHelper->clearExceptionLogFile();
    }
}
