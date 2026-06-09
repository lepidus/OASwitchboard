<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\tests\helpers\ObjectFactory;
use PKP\tests\PKPTestCase;

class OASwitchboardServiceTest extends PKPTestCase
{
    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRequest();
        $journal = ObjectFactory::createMockedJournal($onlineIssn = '0000-0001', $printIssn = '0000-0002');
        $this->submission = ObjectFactory::createTestSubmission($journal);
    }

    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'JournalDAO'];
    }

    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'site'];
    }

    public function testSubmissionAtLeastOneAuthorWithRorAssociated()
    {
        $this->assertTrue(OASwitchboardService::isRorAssociated($this->submission));
    }

    public function testSubmissionWithoutAtLeastOneAuthorWithRorAssociated()
    {
        $firstAuthor = $this->submission->getCurrentPublication()->getData('authors')[0];
        $firstAuthor->setAffiliations([
            ObjectFactory::buildAffiliation(ObjectFactory::AFFILIATION_NAME),
        ]);
        $this->assertFalse(OASwitchboardService::isRorAssociated($this->submission));
    }
}
