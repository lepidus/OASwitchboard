<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\submission\Submission;
use PKP\context\Context;
use APP\plugins\generic\OASwitchboard\tests\helpers\CreatesPluginMocks;
use APP\plugins\generic\OASwitchboard\tests\helpers\ObjectFactory;
use Exception;
use PKP\tests\PKPTestCase;

class OASwitchboardServiceTest extends PKPTestCase
{
    use CreatesPluginMocks;

    private const CONTEXT_ID = 1;

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

    private function buildContextAssigningDoiOnPublication(): Journal
    {
        $context = new Journal();
        $context->setData(Context::SETTING_ENABLE_DOIS, true);
        $context->setData(Context::SETTING_ENABLED_DOI_TYPES, [Repo::doi()::TYPE_PUBLICATION]);
        $context->setData(Context::SETTING_DOI_CREATION_TIME, Repo::doi()::CREATION_TIME_PUBLICATION);
        $context->setData(Context::SETTING_DOI_PREFIX, '10.1234');
        return $context;
    }

    public function testMissingDoiShouldNotBeRequiredBeforePublishingWhenCoreAssignsItOnPublication()
    {
        $missing = [P1Pio::REQUIREMENT_DOI, 'plugins.generic.OASwitchboard.postRequirementsError.issn'];
        $this->submission->setData('status', Submission::STATUS_QUEUED);

        $this->assertSame(
            ['plugins.generic.OASwitchboard.postRequirementsError.issn'],
            OASwitchboardService::excludeRequirementsMetOnPublication($missing, $this->submission, $this->buildContextAssigningDoiOnPublication())
        );
    }

    public function testMissingDoiShouldStillBeReportedOnceTheSubmissionIsPublished()
    {
        $this->submission->setData('status', Submission::STATUS_PUBLISHED);

        $this->assertSame(
            [P1Pio::REQUIREMENT_DOI],
            OASwitchboardService::excludeRequirementsMetOnPublication([P1Pio::REQUIREMENT_DOI], $this->submission, $this->buildContextAssigningDoiOnPublication())
        );
    }

    public function testCoreShouldNotBeExpectedToAssignDoisItIsNotConfiguredFor()
    {
        $this->assertTrue(OASwitchboardService::willAssignDoiOnPublication($this->buildContextAssigningDoiOnPublication()));

        $neverCreates = $this->buildContextAssigningDoiOnPublication();
        $neverCreates->setData(Context::SETTING_DOI_CREATION_TIME, Repo::doi()::CREATION_TIME_NEVER);
        $this->assertFalse(OASwitchboardService::willAssignDoiOnPublication($neverCreates));

        $withoutPrefix = $this->buildContextAssigningDoiOnPublication();
        $withoutPrefix->setData(Context::SETTING_DOI_PREFIX, '');
        $this->assertFalse(OASwitchboardService::willAssignDoiOnPublication($withoutPrefix));

        $withoutPublicationDois = $this->buildContextAssigningDoiOnPublication();
        $withoutPublicationDois->setData(Context::SETTING_ENABLED_DOI_TYPES, []);
        $this->assertFalse(OASwitchboardService::willAssignDoiOnPublication($withoutPublicationDois));

        $disabled = $this->buildContextAssigningDoiOnPublication();
        $disabled->setData(Context::SETTING_ENABLE_DOIS, false);
        $this->assertFalse(OASwitchboardService::willAssignDoiOnPublication($disabled));
    }
}
