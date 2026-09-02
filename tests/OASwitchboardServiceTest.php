<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use PKP\tests\PKPTestCase;
use APP\plugins\generic\OASwitchboard\tests\helpers\CreatesPluginMocks;
use APP\plugins\generic\OASwitchboard\tests\helpers\ObjectFactory;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use Exception;

class OASwitchboardServiceTest extends PKPTestCase
{
    use CreatesPluginMocks;

    private const CONTEXT_ID = 1;

    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $journal = ObjectFactory::createMockedJournal($this, $onlineIssn = "0000-0001", $printIssn = "0000-0002");
        $this->submission = ObjectFactory::createTestSubmission($journal);
    }

    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'JournalDAO'];
    }

    public function testSubmissionAtLeastOneAuthorWithRorAssociated()
    {
        $this->assertTrue(OASwitchboardService::isRorAssociated($this->submission));
    }

    public function testSubmissionWithoutAtLeastOneAuthorWithRorAssociated()
    {
        $firstAuthor = $this->submission->getCurrentPublication()->getData('authors')[0];
        $firstAuthor->setData('rorId', null);
        $this->assertFalse(OASwitchboardService::isRorAssociated($this->submission));
    }

    public function testPluginIsConfiguredWithUsernameAndPasswordOnly()
    {
        $plugin = $this->createConfiguredPluginMock();
        OASwitchboardService::validatePluginIsConfigured($plugin, self::CONTEXT_ID);
        $this->addToAssertionCount(1);
    }

    public function testPluginIsNotConfiguredWithoutCredentials()
    {
        $plugin = $this->createPluginMock([]);
        $this->expectException(Exception::class);
        OASwitchboardService::validatePluginIsConfigured($plugin, self::CONTEXT_ID);
    }
}
