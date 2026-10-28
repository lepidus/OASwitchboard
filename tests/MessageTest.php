<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\journal\Journal;
use APP\plugins\generic\OASwitchboard\classes\Message;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\tests\helpers\CreatesPluginMocks;
use APP\publication\Publication;
use APP\submission\Submission;
use ArrayObject;
use PKP\observers\events\PublicationPublished;
use PKP\tests\PKPTestCase;

class MessageTest extends PKPTestCase
{
    use CreatesPluginMocks;

    private const SUBMISSION_ID = 456;
    private const CONTEXT_ID = 1;

    private function createMessage(OASwitchboardService $service, ArrayObject $calls, bool $enabled = true, bool $configured = true): Message
    {
        $settings = $configured ? ['username' => 'user@example.com', 'password' => 'encrypted'] : [];
        $plugin = $this->createPluginMock($settings, $enabled);

        return new class ($plugin, $service, $calls) extends Message {
            private $service;
            private $calls;

            public function __construct($plugin, OASwitchboardService $service, ArrayObject $calls)
            {
                parent::__construct($plugin);
                $this->service = $service;
                $this->calls = $calls;
            }

            protected function getSubmission(int $submissionId): Submission
            {
                $submission = new Submission();
                $submission->setId($submissionId);
                $this->calls['loadedSubmission'] = $submission;
                return $submission;
            }

            protected function createService(int $contextId, Submission $submission): OASwitchboardService
            {
                $this->calls['serviceContextId'] = $contextId;
                $this->calls['serviceSubmission'] = $submission;
                return $this->service;
            }

            protected function reportSuccess(Submission $submission): void
            {
                $this->calls['reported'] = $submission;
            }
        };
    }

    private function buildPublishedEvent(int $publicationStatus): PublicationPublished
    {
        $submission = new Submission();
        $submission->setId(self::SUBMISSION_ID);
        $submission->setData('contextId', self::CONTEXT_ID);

        $publication = new Publication();
        $publication->setData('status', $publicationStatus);

        $context = new Journal();
        $context->setId(self::CONTEXT_ID);

        return new PublicationPublished($publication, new Publication(), $submission, $context);
    }

    public function testShouldSendTheMessageBuiltFromTheStoredSubmissionWhenPublished()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->once())->method('sendP1PioMessage');
        $event = $this->buildPublishedEvent(Submission::STATUS_PUBLISHED);

        $this->createMessage($service, $calls)->handlePublicationPublished($event);

        $this->assertSame(self::CONTEXT_ID, $calls['serviceContextId']);
        $this->assertSame($calls['loadedSubmission'], $calls['serviceSubmission']);
        $this->assertNotSame($event->submission, $calls['serviceSubmission']);
        $this->assertSame($calls['loadedSubmission'], $calls['reported']);
    }

    public function testShouldNotSendWhenThePublicationIsOnlyScheduled()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())->method('sendP1PioMessage');

        $this->createMessage($service, $calls)->handlePublicationPublished($this->buildPublishedEvent(Submission::STATUS_SCHEDULED));

        $this->assertArrayNotHasKey('reported', $calls);
    }

    public function testShouldNotSendWhenThePluginIsDisabledForTheSubmissionJournal()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())->method('sendP1PioMessage');

        $this->createMessage($service, $calls, false)->handlePublicationPublished($this->buildPublishedEvent(Submission::STATUS_PUBLISHED));

        $this->assertArrayNotHasKey('reported', $calls);
    }

    public function testShouldNotCreateTheServiceWhenThePluginIsNotConfigured()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())->method('sendP1PioMessage');

        $this->createMessage($service, $calls, true, false)->handlePublicationPublished($this->buildPublishedEvent(Submission::STATUS_PUBLISHED));

        $this->assertArrayNotHasKey('serviceContextId', $calls);
        $this->assertArrayNotHasKey('reported', $calls);
    }

    public function testFailedSendShouldNotInterruptThePublication()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->method('sendP1PioMessage')->willThrowException(new \Error('unexpected'));

        $this->createMessage($service, $calls)->handlePublicationPublished($this->buildPublishedEvent(Submission::STATUS_PUBLISHED));

        $this->assertArrayNotHasKey('reported', $calls);
    }
}
