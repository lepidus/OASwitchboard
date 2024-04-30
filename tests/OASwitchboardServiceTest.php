<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use PKP\tests\PKPTestCase;
use APP\plugins\generic\OASwitchboard\tests\helpers\ObjectFactory;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;

class OASwitchboardServiceTest extends PKPTestCase
{
    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $journal = ObjectFactory::createMockedJournal($this, $issn = "0000-0001");
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
}
