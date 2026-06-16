<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\Message;
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

    private function buildPublishHookArguments(int $publicationStatus): array
    {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', self::CONTEXT_ID);

        $publication = new Publication();
        $publication->setData('status', $publicationStatus);
        $publication->setData('submissionId', $submission->getId());

        return [$publication, null, $submission];
    }

    public function testShouldRecordPendingStatusAndDispatchSendJobWhenPublished()
    {
        $message = new Message($this->createPluginMock(true));
        $args = $this->buildPublishHookArguments(Submission::STATUS_PUBLISHED);

        $message->sendToOASwitchboard('Publication::publish', $args);

        Bus::assertDispatched(SendP1PioMessageJob::class);
        $this->assertSame(SendStatus::STATUS_PENDING, $this->editedParams[SendStatus::SETTING_STATUS]);
    }

    public function testShouldNotDispatchSendJobWhenPluginIsNotConfigured()
    {
        $message = new Message($this->createPluginMock(false));
        $args = $this->buildPublishHookArguments(Submission::STATUS_PUBLISHED);

        $message->sendToOASwitchboard('Publication::publish', $args);

        Bus::assertNotDispatched(SendP1PioMessageJob::class);
        $this->assertNull($this->editedParams);
    }

    public function testShouldRecordPendingStatusAndDispatchSendJobWhenSchedulingDirectly()
    {
        $message = new Message($this->createPluginMock(true));
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', self::CONTEXT_ID);

        $message->scheduleSendToOASwitchboard($submission);

        Bus::assertDispatched(SendP1PioMessageJob::class);
        $this->assertSame(SendStatus::STATUS_PENDING, $this->editedParams[SendStatus::SETTING_STATUS]);
    }

    public function testShouldThrowWhenSchedulingWithPluginNotConfigured()
    {
        $message = new Message($this->createPluginMock(false));
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', self::CONTEXT_ID);

        $this->expectException(\Exception::class);
        $message->scheduleSendToOASwitchboard($submission);
    }

    public function testShouldNotDispatchSendJobWhenPublicationIsNotPublished()
    {
        $message = new Message($this->createPluginMock(true));
        $args = $this->buildPublishHookArguments(Submission::STATUS_QUEUED);

        $message->sendToOASwitchboard('Publication::publish', $args);

        Bus::assertNotDispatched(SendP1PioMessageJob::class);
        $this->assertNull($this->editedParams);
    }
}
