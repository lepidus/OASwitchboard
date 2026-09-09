<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.classes.OASwitchboardService');
import('plugins.generic.OASwitchboard.tests.helpers.ObjectFactory');
import('plugins.generic.OASwitchboard.tests.helpers.CreatesPluginMocks');

class OASwitchboardServiceTest extends PKPTestCase
{
    use CreatesPluginMocks;

    private const CONTEXT_ID = 1;

    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $journal = $journal = ObjectFactory::createMockedJournal($this, $onlineIssn = "0000-0001", $printIssn = "0000-0002");
        $this->submission = ObjectFactory::createTestSubmission($journal);
    }

    protected function getMockedDAOs()
    {
        return [
            'JournalDAO'
        ];
    }

    public function testSubmissionAtLeastOneAuthorWithRorAssociated()
    {
        $this->assertTrue(OASwitchboardService::isRorAssociated($this->submission));
    }

    public function testSubmissionWithoutAtLeastOneAuthorWithRorAssociated()
    {
        $firstAuthor = $this->submission->getAuthors()[0];
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
