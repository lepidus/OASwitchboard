<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardStatusController;
use APP\submission\Repository as SubmissionRepository;
use APP\submission\Submission;
use PKP\tests\PKPTestCase;

class OASwitchboardStatusControllerTest extends PKPTestCase
{
    private const SUBMISSION_ID = 123;
    private const CONTEXT_ID = 5;

    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), SubmissionRepository::class];
    }

    private function createController(): OASwitchboardStatusController
    {
        return new class (null) extends OASwitchboardStatusController {
            public function exposedGetSubmissionInContext(int $submissionId, int $contextId): ?Submission
            {
                return $this->getSubmissionInContext($submissionId, $contextId);
            }

            public function exposedIsPublished(Submission $submission): bool
            {
                return $this->isPublished($submission);
            }
        };
    }

    private function mockSubmissionRepository(): SubmissionRepository
    {
        $repository = $this->createMock(SubmissionRepository::class);
        app()->instance(SubmissionRepository::class, $repository);
        return $repository;
    }

    public function testShouldLoadSubmissionScopedToTheRequestContext()
    {
        $submission = new Submission();
        $repository = $this->mockSubmissionRepository();
        $repository->expects($this->once())
            ->method('get')
            ->with(self::SUBMISSION_ID, self::CONTEXT_ID)
            ->willReturn($submission);

        $result = $this->createController()
            ->exposedGetSubmissionInContext(self::SUBMISSION_ID, self::CONTEXT_ID);

        $this->assertSame($submission, $result);
    }

    public function testShouldReturnNullWhenSubmissionBelongsToAnotherContext()
    {
        $repository = $this->mockSubmissionRepository();
        $repository->method('get')
            ->with(self::SUBMISSION_ID, self::CONTEXT_ID)
            ->willReturn(null);

        $result = $this->createController()
            ->exposedGetSubmissionInContext(self::SUBMISSION_ID, self::CONTEXT_ID);

        $this->assertNull($result);
    }

    public function testShouldConsiderPublishedSubmissionAsResendable()
    {
        $submission = new Submission();
        $submission->setData('status', Submission::STATUS_PUBLISHED);

        $this->assertTrue($this->createController()->exposedIsPublished($submission));
    }

    public function testShouldNotConsiderUnpublishedSubmissionAsResendable()
    {
        $submission = new Submission();
        $submission->setData('status', Submission::STATUS_QUEUED);

        $this->assertFalse($this->createController()->exposedIsPublished($submission));
    }
}
