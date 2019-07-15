<?php
namespace OxidCommunity\ModuleInternals\Testscc\checkconsistency;

use OxidCommunity\ModuleInternals\Testscc\AcceptanceTester;

class overviewCest
{
    public function _before(AcceptanceTester $I)
    {
        $I->amOnPage('/index.php?cl=checkconsistency&key=abc');
        $I->waitForElement('.accordion');
    }

    /**
     * @skip
     */
    public function checkConsitencyOverview(AcceptanceTester $I)
    {
        $I->click('.accordion');
        $I->see('module_internals_metadata');
        $I->see('OxidCommunity\ModuleInternals\Controller\Admin\Metadatatest');
    }
}
