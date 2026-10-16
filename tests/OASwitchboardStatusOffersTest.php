<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardStatusController;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;

class OASwitchboardStatusOffersTest extends DatabaseTestCase
{
    protected function getAffectedTables()
    {
        return ['submission_settings'];
    }

    private function createController(?string $installationDate, bool $userCanResend = true): OASwitchboardStatusController
    {
        return new class ($installationDate, $userCanResend) extends OASwitchboardStatusController {
            private ?string $installationDate;
            private bool $userCanResend;

            public function __construct(?string $installationDate, bool $userCanResend)
            {
                parent::__construct(null);
                $this->installationDate = $installationDate;
                $this->userCanResend = $userCanResend;
            }

            public function exposedWasPublishedBeforePluginInstallation(Submission $submission): bool
            {
                return $this->wasPublishedBeforePluginInstallation($submission);
            }

            public function exposedCanOfferSend(array $missingFields, Submission $submission, int $contextId): bool
            {
                return $this->canOfferSend($missingFields, $submission, $contextId);
            }

            protected function getPluginInstallationDate(): ?string
            {
                return $this->installationDate;
            }

            protected function currentUserCanResend(int $contextId): bool
            {
                return $this->userCanResend;
            }
        };
    }

    private function buildSubmission(int $status, ?string $datePublished): Submission
    {
        $publication = new Publication();
        $publication->setId(1);
        $publication->setData('datePublished', $datePublished);

        $submission = new Submission();
        $submission->setId((int) DB::table('submissions')->value('submission_id'));
        $submission->setData('status', $status);
        $submission->setData('currentPublicationId', 1);
        $submission->setData('publications', [$publication]);
        return $submission;
    }

    public function testArticlePublishedOnAnEarlierDayThanTheInstallationWasPublishedBeforeThePlugin()
    {
        $submission = $this->buildSubmission(Submission::STATUS_PUBLISHED, '2025-03-10');

        $this->assertTrue($this->createController('2026-10-15 09:00:00')->exposedWasPublishedBeforePluginInstallation($submission));
    }

    public function testArticlePublishedOnTheInstallationDayOrLaterWasNotPublishedBeforeThePlugin()
    {
        $controller = $this->createController('2026-10-15 09:00:00');

        $this->assertFalse($controller->exposedWasPublishedBeforePluginInstallation($this->buildSubmission(Submission::STATUS_PUBLISHED, '2026-10-15')));
        $this->assertFalse($controller->exposedWasPublishedBeforePluginInstallation($this->buildSubmission(Submission::STATUS_PUBLISHED, '2026-11-01')));
    }

    public function testUnknownDatesOrUnpublishedArticlesAreNotTreatedAsPublishedBeforeThePlugin()
    {
        $this->assertFalse($this->createController(null)->exposedWasPublishedBeforePluginInstallation($this->buildSubmission(Submission::STATUS_PUBLISHED, '2025-03-10')));
        $this->assertFalse($this->createController('2026-10-15 09:00:00')->exposedWasPublishedBeforePluginInstallation($this->buildSubmission(Submission::STATUS_PUBLISHED, null)));
        $this->assertFalse($this->createController('2026-10-15 09:00:00')->exposedWasPublishedBeforePluginInstallation($this->buildSubmission(Submission::STATUS_QUEUED, '2025-03-10')));
    }

    public function testSendIsOfferedOnlyWhenTheRequirementsAreMet()
    {
        $submission = $this->buildSubmission(Submission::STATUS_PUBLISHED, '2025-03-10');
        $controller = $this->createController('2026-10-15 09:00:00');

        $this->assertTrue($controller->exposedCanOfferSend([], $submission, 1));
        $this->assertFalse($controller->exposedCanOfferSend([P1Pio::REQUIREMENT_DOI], $submission, 1));
    }

    public function testSendIsNotOfferedToUsersWhoCannotResendOrForUnpublishedArticles()
    {
        $this->assertFalse($this->createController('2026-10-15 09:00:00', false)->exposedCanOfferSend([], $this->buildSubmission(Submission::STATUS_PUBLISHED, '2025-03-10'), 1));
        $this->assertFalse($this->createController('2026-10-15 09:00:00')->exposedCanOfferSend([], $this->buildSubmission(Submission::STATUS_QUEUED, null), 1));
    }
}
