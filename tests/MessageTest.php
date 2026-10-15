<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.submission.Submission');
import('classes.publication.Publication');
import('plugins.generic.OASwitchboard.classes.Message');
import('plugins.generic.OASwitchboard.classes.OASwitchboardService');
import('plugins.generic.OASwitchboard.tests.helpers.CreatesPluginMocks');

class MessageTest extends PKPTestCase
{
    use CreatesPluginMocks;

    private const CONTEXT_ID = 1;

    private function createMessage($service, ArrayObject $calls, bool $enabled = true)
    {
        return new class ($this->createPluginMock([], $enabled), $service, $calls) extends Message {
            private $service;
            private $calls;

            public function __construct($plugin, $service, $calls)
            {
                parent::__construct($plugin);
                $this->service = $service;
                $this->calls = $calls;
            }

            protected function getSubmissionWithPublicationIdentifiers($submission, $contextId)
            {
                $reloaded = new Submission();
                $reloaded->setId($submission->getId());
                $this->calls['reloadedContextId'] = $contextId;
                $this->calls['reloadedSubmission'] = $reloaded;
                return $reloaded;
            }

            protected function createService($contextId, $submission)
            {
                $this->calls['serviceContextId'] = $contextId;
                $this->calls['serviceSubmission'] = $submission;
                return $this->service;
            }

            protected function reportSuccess($submission)
            {
                $this->calls['reported'] = $submission;
            }
        };
    }

    private function buildPublishHookArguments($publicationStatus): array
    {
        $submission = new Submission();
        $submission->setId(456);
        $submission->setData('contextId', self::CONTEXT_ID);

        $publication = new Publication();
        $publication->setData('status', $publicationStatus);

        return [$publication, null, $submission];
    }

    public function testShouldSendForTheSubmissionJournalWhenPublished()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->once())->method('sendP1PioMessage');

        $this->createMessage($service, $calls)->sendToOASwitchboard('Publication::publish', $this->buildPublishHookArguments(STATUS_PUBLISHED));

        $this->assertSame(self::CONTEXT_ID, $calls['serviceContextId']);
        $this->assertSame(self::CONTEXT_ID, $calls['reloadedContextId']);
        $this->assertSame($calls['reloadedSubmission'], $calls['serviceSubmission']);
        $this->assertArrayHasKey('reported', $calls);
    }

    public function testShouldNotSendWhenThePublicationIsOnlyScheduled()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())->method('sendP1PioMessage');

        $this->createMessage($service, $calls)->sendToOASwitchboard('Publication::publish', $this->buildPublishHookArguments(STATUS_SCHEDULED));

        $this->assertArrayNotHasKey('reported', $calls);
    }

    public function testShouldNotSendWhenThePluginIsDisabledForTheSubmissionJournal()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())->method('sendP1PioMessage');

        $this->createMessage($service, $calls, false)->sendToOASwitchboard('Publication::publish', $this->buildPublishHookArguments(STATUS_PUBLISHED));

        $this->assertArrayNotHasKey('reported', $calls);
    }

    public function testFailedSendShouldNotInterruptThePublication()
    {
        $calls = new ArrayObject();
        $service = $this->createMock(OASwitchboardService::class);
        $service->method('sendP1PioMessage')->willThrowException(new Error('unexpected'));

        $result = $this->createMessage($service, $calls)->sendToOASwitchboard('Publication::publish', $this->buildPublishHookArguments(STATUS_PUBLISHED));

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('reported', $calls);
    }
}
