<?php

namespace OxidCommunity\ModuleInternals\Tests\Integration\Controller;

use OxidEsales\Eshop\Application\Controller\Admin\NavigationController;
use OxidEsales\TestingLibrary\UnitTestCase;

class NavigationControllerTest extends UnitTestCase
{
    public function testNavigationController(): void
    {
        $controller = oxNew(NavigationController::class);
        $this->setConfigParam('aModuleExtensions', ['a' => ['b']]);
        $this->assertContains(
            'Module OXID Community Module Internals was fixed',
            $controller->checkModules()['warning']
        );
        $this->exceptionLogHelper->clearExceptionLogFile();
    }
}
