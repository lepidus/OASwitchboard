<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.classes.OASwitchboardService');
import('plugins.generic.OASwitchboard.tests.helpers.ObjectFactory');

class OASwitchboardServiceTest extends PKPTestCase
{
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
}
