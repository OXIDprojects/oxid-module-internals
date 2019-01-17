<?php
namespace OxidCommunity\ModuleInternals\Testscc\startpage;

use OxidCommunity\ModuleInternals\Testscc\AcceptanceTester;
use OxidCommunity\ModuleInternals\Testscc\Page\page;

class searchCest
{
    public function _before(AcceptanceTester $I, page $page)
    {
        $I->amOnPage('/');
        $I->waitForElement($page::$startpageSearchInput);
    }

    public function tryToTest(AcceptanceTester $I, page $page)
    {
        $search = 'kiteboard';
        $I->fillField($page::$startpageSearchInput, $search);
        $I->click($page::$startpageSearchButton);

        $I->waitForElement($page::$searchResultHeadline);
        $I->canSeeInCurrentUrl($search);

        $searchInputValue = $I->grabValueFrom($page::$startpageSearchInput);
        $I->assertSame($searchInputValue, $search);

        $headline = explode(' ', $I->grabTextFrom($page::$searchResultHeadline));
        $I->assertContains('"' . $search . '"', $headline);
    }
}
