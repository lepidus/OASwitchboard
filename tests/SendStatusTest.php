<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\submission\Repository as SubmissionRepository;
use APP\submission\Submission;
use PKP\tests\PKPTestCase;

class SendStatusTest extends PKPTestCase
{
    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), SubmissionRepository::class];
    }

    public function testShouldUpdateInMemorySubmissionWhenRecording()
    {
        $submission = new Submission();
        $repository = $this->createMock(SubmissionRepository::class);
        $repository->expects($this->once())
            ->method('edit')
            ->with(
                $submission,
                $this->callback(function (array $settings) {
                    return $settings[SendStatus::SETTING_STATUS] === SendStatus::STATUS_PENDING &&
                        !empty($settings[SendStatus::SETTING_UPDATED_AT]) &&
                        $settings[SendStatus::SETTING_ERROR] === null;
                })
            );
        app()->instance(SubmissionRepository::class, $repository);

        SendStatus::recordPending($submission);

        $sendStatus = SendStatus::readFromSubmission($submission);
        $this->assertSame(SendStatus::STATUS_PENDING, $sendStatus['status']);
        $this->assertNotEmpty($sendStatus['updatedAt']);
    }

    public function testShouldAddSendStatusPropertiesToSubmissionSchema()
    {
        $schema = (object) ['properties' => (object) []];

        SendStatus::addToSubmissionSchema('Schema::get::submission', [&$schema]);

        foreach ([SendStatus::SETTING_STATUS, SendStatus::SETTING_UPDATED_AT, SendStatus::SETTING_ERROR] as $setting) {
            $this->assertObjectHasProperty($setting, $schema->properties);
            $this->assertSame('string', $schema->properties->{$setting}->type);
            $this->assertTrue($schema->properties->{$setting}->writeDisabledInApi);
        }
    }

    public function testShouldRecordNotSentStatusWithoutError()
    {
        app()->instance(SubmissionRepository::class, $this->createMock(SubmissionRepository::class));
        $submission = new Submission();

        SendStatus::recordNotSent($submission);

        $sendStatus = SendStatus::readFromSubmission($submission);
        $this->assertSame(SendStatus::STATUS_NOT_SENT, $sendStatus['status']);
        $this->assertNotEmpty($sendStatus['updatedAt']);
        $this->assertNull($sendStatus['error']);
    }

    public function testShouldReadNullWhenNoSendWasRecorded()
    {
        $submission = new Submission();

        $this->assertNull(SendStatus::readFromSubmission($submission));
    }

    public function testShouldReadRecordedSendStatusFromSubmission()
    {
        $submission = new Submission();
        $submission->setData(SendStatus::SETTING_STATUS, SendStatus::STATUS_FAILED);
        $submission->setData(SendStatus::SETTING_UPDATED_AT, '2026-06-12 10:00:00');
        $submission->setData(SendStatus::SETTING_ERROR, 'OA Switchboard API is unavailable');

        $sendStatus = SendStatus::readFromSubmission($submission);

        $this->assertSame(SendStatus::STATUS_FAILED, $sendStatus['status']);
        $this->assertSame('2026-06-12 10:00:00', $sendStatus['updatedAt']);
        $this->assertSame('OA Switchboard API is unavailable', $sendStatus['error']);
    }
}
