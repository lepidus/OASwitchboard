<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardAPIClient;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\plugins\generic\OASwitchboard\jobs\SendP1PioMessageJob;
use APP\submission\Repository as SubmissionRepository;
use APP\submission\Submission;
use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
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

    public function testRedirectShouldFailWithoutRecordingSentOrLeakingCredentials()
    {
        $tokenMarker = 'audit-job-token-marker';
        $locationMarker = 'audit-job-location-marker';
        $bodyMarker = 'audit-job-body-marker';
        $requestHistory = [];
        $logMessages = new ArrayObject();
        $mockHandler = new MockHandler([
            new Response(
                302,
                ['Location' => 'https://untrusted.example.test/message?' . $locationMarker],
                $bodyMarker
            ),
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($requestHistory));
        $apiClient = new class (new Client(['handler' => $handlerStack]), $logMessages) extends OASwitchboardAPIClient {
            private ArrayObject $logMessages;

            public function __construct($httpClient, ArrayObject $logMessages)
            {
                parent::__construct($httpClient);
                $this->logMessages = $logMessages;
            }

            protected function writeLog(string $message): void
            {
                $this->logMessages->append($message);
            }
        };
        $message = $this->createMock(P1Pio::class);
        $message->method('getContent')->willReturn([]);
        $service = $this->createMock(OASwitchboardService::class);
        $service->method('sendP1PioMessage')
            ->willReturnCallback(fn () => $apiClient->sendMessage($message, $tokenMarker));
        $job = $this->createJobWithService($service);

        try {
            $job->handle();
            $this->fail('Expected redirect response to fail the job');
        } catch (\Exception $exception) {
            $this->assertStringNotContainsString($tokenMarker, $exception->getMessage());
            $this->assertStringNotContainsString($locationMarker, $exception->getMessage());
            $this->assertStringNotContainsString($bodyMarker, $exception->getMessage());
        }

        $this->assertCount(1, $requestHistory);
        $this->assertSame(1, count($mockHandler));
        $this->assertNull($this->editedParams);
        $this->assertNotSame(SendStatus::STATUS_SENT, $this->submission->getData(SendStatus::SETTING_STATUS));
        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('status=302', $logMessages[0]);
        $this->assertStringNotContainsString($tokenMarker, $logMessages[0]);
        $this->assertStringNotContainsString($locationMarker, $logMessages[0]);
        $this->assertStringNotContainsString($bodyMarker, $logMessages[0]);
        $this->assertStringNotContainsString('untrusted.example.test', $logMessages[0]);
    }

    public function testShouldRecordFailedStatusWithoutExceptionDetailsWhenJobDefinitivelyFails()
    {
        $secretMarker = 'audit-job-exception-secret-marker';
        $service = $this->createMock(OASwitchboardService::class);
        $job = $this->createJobWithService($service);

        $job->failed(new \Exception('OA Switchboard API is unavailable: ' . $secretMarker));

        $this->assertSame(SendStatus::STATUS_FAILED, $this->editedParams[SendStatus::SETTING_STATUS]);
        $this->assertSame(
            '##plugins.generic.OASwitchboard.serverError##',
            $this->editedParams[SendStatus::SETTING_ERROR]
        );
        $this->assertStringNotContainsString($secretMarker, $this->editedParams[SendStatus::SETTING_ERROR]);
        $this->assertNotNull($this->registeredEventLogEntry);
        $this->assertSame(
            'plugins.generic.OASwitchboard.sendMessageWithError',
            $this->registeredEventLogEntry->getData('message')
        );
    }
}
