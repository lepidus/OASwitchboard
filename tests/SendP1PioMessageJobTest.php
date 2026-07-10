<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\plugins\generic\OASwitchboard\jobs\SendP1PioMessageJob;
use APP\submission\Repository as SubmissionRepository;
use APP\submission\Submission;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PKP\log\event\EventLogEntry;
use PKP\log\event\Repository as EventLogRepository;
use PKP\tests\PKPTestCase;

class SendP1PioMessageJobTest extends PKPTestCase
{
    private const SUBMISSION_ID = 456;
    private const CONTEXT_ID = 1;

    private $submission;
    private $editedParams;
    private $registeredEventLogEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->submission = new Submission();
        $this->submission->setId(self::SUBMISSION_ID);
        $this->editedParams = null;
        $this->registeredEventLogEntry = null;
        $this->registerSubmissionRepositoryMock();
        $this->registerEventLogRepositoryMock();
    }

    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), SubmissionRepository::class, EventLogRepository::class];
    }

    private function registerSubmissionRepositoryMock(): void
    {
        $submissionRepository = $this->createMock(SubmissionRepository::class);
        $submissionRepository->method('get')
            ->willReturn($this->submission);
        $submissionRepository->method('edit')
            ->willReturnCallback(function ($submission, $params) {
                $this->editedParams = $params;
            });
        app()->instance(SubmissionRepository::class, $submissionRepository);
    }

    private function registerEventLogRepositoryMock(): void
    {
        $eventLogRepository = $this->createMock(EventLogRepository::class);
        $eventLogRepository->method('newDataObject')
            ->willReturnCallback(function ($params) {
                $entry = new EventLogEntry();
                $entry->setAllData($params);
                return $entry;
            });
        $eventLogRepository->method('add')
            ->willReturnCallback(function ($entry) {
                $this->registeredEventLogEntry = $entry;
                return 1;
            });
        app()->instance(EventLogRepository::class, $eventLogRepository);
    }

    private function createJobWithService(OASwitchboardService $service, ?int $userId = null): SendP1PioMessageJob
    {
        return new class (self::SUBMISSION_ID, self::CONTEXT_ID, $service, $userId) extends SendP1PioMessageJob {
            private $serviceForTests;

            public function __construct(int $submissionId, int $contextId, OASwitchboardService $serviceForTests, ?int $userId)
            {
                parent::__construct($submissionId, $contextId, $userId);
                $this->serviceForTests = $serviceForTests;
            }

            protected function createOASwitchboardService($submission): OASwitchboardService
            {
                return $this->serviceForTests;
            }

            protected function ensurePluginIsLoaded()
            {
                return null;
            }

            protected function isPluginEnabled(): bool
            {
                return true;
            }
        };
    }

    public function testShouldSendMessageAndRecordSentStatusOnSuccess()
    {
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->once())
            ->method('sendP1PioMessage');

        $job = $this->createJobWithService($service);
        $job->handle();

        $this->assertSame(SendStatus::STATUS_SENT, $this->editedParams[SendStatus::SETTING_STATUS]);
        $this->assertNotEmpty($this->editedParams[SendStatus::SETTING_UPDATED_AT]);
        $this->assertNull($this->editedParams[SendStatus::SETTING_ERROR]);
    }

    public function testShouldBeUniquePerSubmissionToAvoidDuplicateDispatch()
    {
        $job = new SendP1PioMessageJob(self::SUBMISSION_ID, self::CONTEXT_ID);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) self::SUBMISSION_ID, $job->uniqueId());
    }

    public function testShouldSerializeConcurrentSendsForTheSameSubmission()
    {
        $job = new SendP1PioMessageJob(self::SUBMISSION_ID, self::CONTEXT_ID);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $overlapping = $middleware[0];
        $this->assertInstanceOf(WithoutOverlapping::class, $overlapping);
        $this->assertSame((string) self::SUBMISSION_ID, $overlapping->key);
        $this->assertNull($overlapping->releaseAfter);
        $this->assertSame(90, $overlapping->expiresAfter);
    }

    public function testShouldSkipSendingWhenSubmissionWasAlreadySent()
    {
        $this->submission->setData(SendStatus::SETTING_STATUS, SendStatus::STATUS_SENT);

        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())
            ->method('sendP1PioMessage');

        $job = $this->createJobWithService($service);
        $job->handle();

        $this->assertNull($this->editedParams);
        $this->assertNull($this->registeredEventLogEntry);
    }

    public function testShouldNotSendWhenPluginIsDisabled()
    {
        $service = $this->createMock(OASwitchboardService::class);
        $service->expects($this->never())
            ->method('sendP1PioMessage');

        $job = new class (self::SUBMISSION_ID, self::CONTEXT_ID, $service) extends SendP1PioMessageJob {
            private $serviceForTests;

            public function __construct(int $submissionId, int $contextId, OASwitchboardService $serviceForTests)
            {
                parent::__construct($submissionId, $contextId);
                $this->serviceForTests = $serviceForTests;
            }

            protected function createOASwitchboardService($submission): OASwitchboardService
            {
                return $this->serviceForTests;
            }

            protected function isPluginEnabled(): bool
            {
                return false;
            }
        };

        $job->handle();

        $this->assertNull($this->editedParams);
        $this->assertNull($this->registeredEventLogEntry);
    }

    public function testShouldRegisterSubmissionEventLogOnSuccess()
    {
        $service = $this->createMock(OASwitchboardService::class);

        $job = $this->createJobWithService($service);
        $job->handle();

        $this->assertNotNull($this->registeredEventLogEntry);
        $this->assertSame(
            'plugins.generic.OASwitchboard.sendMessageWithSuccess',
            $this->registeredEventLogEntry->getData('message')
        );
        $this->assertSame(self::SUBMISSION_ID, $this->registeredEventLogEntry->getData('assocId'));
        $this->assertNull($this->registeredEventLogEntry->getData('userId'));
    }

    public function testShouldAttributeEventLogToTheActingUser()
    {
        $service = $this->createMock(OASwitchboardService::class);

        $job = $this->createJobWithService($service, $actingUserId = 42);
        $job->handle();

        $this->assertSame($actingUserId, $this->registeredEventLogEntry->getData('userId'));
    }

    public function testShouldPropagateExceptionAndNotRecordStatusWhenSendingFails()
    {
        $service = $this->createMock(OASwitchboardService::class);
        $service->method('sendP1PioMessage')
            ->willThrowException(new \Exception('OA Switchboard API is unavailable'));

        $job = $this->createJobWithService($service);

        $this->expectException(\Exception::class);
        try {
            $job->handle();
        } finally {
            $this->assertNull($this->editedParams);
        }
    }

    public function testShouldRecordFailedStatusWithErrorMessageWhenJobDefinitivelyFails()
    {
        $service = $this->createMock(OASwitchboardService::class);
        $job = $this->createJobWithService($service);

        $job->failed(new \Exception('OA Switchboard API is unavailable'));

        $this->assertSame(SendStatus::STATUS_FAILED, $this->editedParams[SendStatus::SETTING_STATUS]);
        $this->assertSame('OA Switchboard API is unavailable', $this->editedParams[SendStatus::SETTING_ERROR]);
        $this->assertNotNull($this->registeredEventLogEntry);
        $this->assertSame(
            'plugins.generic.OASwitchboard.sendMessageWithError',
            $this->registeredEventLogEntry->getData('message')
        );
    }
}
