<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\Message;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\plugins\generic\OASwitchboard\jobs\SendP1PioMessageJob;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use APP\publication\Publication;
use APP\submission\Repository as SubmissionRepository;
use APP\submission\Submission;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PKP\tests\PKPTestCase;

class MessageTest extends PKPTestCase
{
    private const SUBMISSION_ID = 456;
    private const CONTEXT_ID = 1;
    private const ACTING_USER_ID = 99;

    private $editedParams;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        // The job is ShouldBeUnique: dispatching acquires a cache lock that is
        // only released once the job processes. Under Bus::fake() it never does,
        // so the lock would leak into the next test and block its dispatch.
        Cache::clear();
        $this->editedParams = null;
        $this->registerSubmissionRepositoryMock();
    }

    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), SubmissionRepository::class];
    }

    private function registerSubmissionRepositoryMock(): void
    {
        $submissionRepository = $this->createMock(SubmissionRepository::class);
        $submissionRepository->method('edit')
            ->willReturnCallback(function ($submission, $params) {
                $this->editedParams = $params;
            });
        app()->instance(SubmissionRepository::class, $submissionRepository);
    }

    private function createPluginMock(bool $configured): OASwitchboardPlugin
    {
        $settings = $configured
            ? ['username' => 'user@example.com', 'password' => 'encrypted', 'isSandBoxAPI' => true]
            : [];
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(function ($contextId, $name) use ($settings) {
                return $settings[$name] ?? null;
            });
        return $plugin;
    }

    /**
     * Builds a Message with the P1 message construction stubbed, so the
     * scheduling decision can be exercised without a fully populated submission.
     * The default builder stands for a submission that meets the requirements.
     */
    private function createMessage(bool $configured = true, ?callable $messageBuilder = null): Message
    {
        $messageBuilder ??= fn ($submission) => $this->createMock(P1Pio::class);

        return new class ($this->createPluginMock($configured), $messageBuilder, self::ACTING_USER_ID) extends Message {
            private $messageBuilder;
            private $actingUserId;

            public function __construct($plugin, callable $messageBuilder, ?int $actingUserId)
            {
                parent::__construct($plugin);
                $this->messageBuilder = $messageBuilder;
                $this->actingUserId = $actingUserId;
            }

            protected function buildMessage($submission): P1Pio
            {
                return ($this->messageBuilder)($submission);
            }

            protected function getActingUserId(): ?int
            {
                return $this->actingUserId;
            }
        };
    }

    private function buildSubmission(): Submission
    {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', self::CONTEXT_ID);
        return $submission;
    }

    private function buildPublishHookArguments(int $publicationStatus): array
    {
        $submission = $this->buildSubmission();

        $publication = new Publication();
        $publication->setData('status', $publicationStatus);
        $publication->setData('submissionId', $submission->getId());

        return [$publication, null, $submission];
    }

    public function testShouldRecordPendingStatusAndDispatchSendJobWhenPublished()
    {
        $message = $this->createMessage();
        $args = $this->buildPublishHookArguments(Submission::STATUS_PUBLISHED);

        $message->sendToOASwitchboard('Publication::publish', $args);

        Bus::assertDispatched(
            SendP1PioMessageJob::class,
            fn (SendP1PioMessageJob $job) => $job->getUserId() === self::ACTING_USER_ID,
        );
        $this->assertSame(SendStatus::STATUS_PENDING, $this->editedParams[SendStatus::SETTING_STATUS]);
    }

    public function testShouldNotDispatchSendJobWhenPluginIsNotConfigured()
    {
        $message = $this->createMessage(configured: false);
        $args = $this->buildPublishHookArguments(Submission::STATUS_PUBLISHED);

        $message->sendToOASwitchboard('Publication::publish', $args);

        Bus::assertNotDispatched(SendP1PioMessageJob::class);
        $this->assertNull($this->editedParams);
    }

    public function testShouldRecordPendingStatusAndDispatchSendJobWhenSchedulingDirectly()
    {
        $message = $this->createMessage();

        $message->scheduleSendToOASwitchboard($this->buildSubmission());

        Bus::assertDispatched(SendP1PioMessageJob::class);
        $this->assertSame(SendStatus::STATUS_PENDING, $this->editedParams[SendStatus::SETTING_STATUS]);
    }

    public function testShouldRecordNotSentAndSkipDispatchWhenRequirementsAreMissing()
    {
        $message = $this->createMessage(messageBuilder: function ($submission) {
            throw new P1PioException(
                'requirements not met',
                0,
                ['plugins.generic.OASwitchboard.postRequirementsError.affiliation']
            );
        });

        $message->scheduleSendToOASwitchboard($this->buildSubmission());

        Bus::assertNotDispatched(SendP1PioMessageJob::class);
        $this->assertSame(SendStatus::STATUS_NOT_SENT, $this->editedParams[SendStatus::SETTING_STATUS]);
    }

    public function testShouldThrowWhenSchedulingWithPluginNotConfigured()
    {
        $message = $this->createMessage(configured: false);

        $this->expectException(\Exception::class);
        $message->scheduleSendToOASwitchboard($this->buildSubmission());
    }

    public function testShouldNotDispatchSendJobWhenPublicationIsNotPublished()
    {
        $message = $this->createMessage();
        $args = $this->buildPublishHookArguments(Submission::STATUS_QUEUED);

        $message->sendToOASwitchboard('Publication::publish', $args);

        Bus::assertNotDispatched(SendP1PioMessageJob::class);
        $this->assertNull($this->editedParams);
    }
}
