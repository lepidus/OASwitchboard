<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;

class SendStatusTest extends DatabaseTestCase
{
    private Submission $submission;

    protected function getAffectedTables()
    {
        return ['submission_settings'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $submissionId = DB::table('submissions')->value('submission_id');
        $this->assertNotNull($submissionId, 'The database fixture must contain a submission');
        $this->submission = new Submission();
        $this->submission->setId($submissionId);
    }

    private function storeSettings(array $settings): void
    {
        foreach ($settings as $name => $value) {
            DB::table('submission_settings')->updateOrInsert(
                ['submission_id' => $this->submission->getId(), 'locale' => '', 'setting_name' => $name],
                ['setting_value' => $value]
            );
        }
    }

    private function storedStatus(): ?string
    {
        return DB::table('submission_settings')
            ->where('submission_id', $this->submission->getId())
            ->where('locale', '')
            ->where('setting_name', SendStatus::SETTING_STATUS)
            ->value('setting_value');
    }

    private function hoursAgo(int $hours): string
    {
        return date('Y-m-d H:i:s', time() - $hours * 3600);
    }

    public function testShouldReadNullWhenNoSendWasRecorded()
    {
        $this->assertNull(SendStatus::read($this->submission));
    }

    public function testShouldRecordPendingStatusWithoutError()
    {
        SendStatus::recordPending($this->submission);

        $sendStatus = SendStatus::read($this->submission);
        $this->assertSame(SendStatus::STATUS_PENDING, $sendStatus['status']);
        $this->assertNotEmpty($sendStatus['updatedAt']);
        $this->assertNull($sendStatus['error']);
    }

    public function testShouldRecordFailureWithErrorAndClearItOnTheNextStatus()
    {
        SendStatus::recordFailure($this->submission, 'OA Switchboard API is unavailable');
        $this->assertSame('OA Switchboard API is unavailable', SendStatus::read($this->submission)['error']);

        SendStatus::recordSent($this->submission);

        $sendStatus = SendStatus::read($this->submission);
        $this->assertSame(SendStatus::STATUS_SENT, $sendStatus['status']);
        $this->assertNull($sendStatus['error']);
    }

    public function testShouldReadStatusFromAFreshlyLoadedSubmission()
    {
        SendStatus::recordNotSent($this->submission);

        $freshSubmission = new Submission();
        $freshSubmission->setId($this->submission->getId());

        $this->assertSame(SendStatus::STATUS_NOT_SENT, SendStatus::read($freshSubmission)['status']);
    }

    public function testShouldKeepStatusWhenCoreEditsAStaleSubmission()
    {
        $originalRow = (array) DB::table('submissions')->where('submission_id', $this->submission->getId())->first();
        SendStatus::recordPending($this->submission);
        $staleSubmission = Repo::submission()->get($this->submission->getId());
        SendStatus::recordSent($this->submission);

        try {
            Repo::submission()->edit($staleSubmission, []);
        } finally {
            DB::table('submissions')->where('submission_id', $this->submission->getId())->update($originalRow);
        }

        $this->assertSame(SendStatus::STATUS_SENT, SendStatus::read($this->submission)['status']);
    }

    public function testShouldAllowRetryOnlyForFailedOrStalePendingSends()
    {
        $this->assertFalse(SendStatus::canRetry($this->submission));

        foreach ([SendStatus::STATUS_SENT, SendStatus::STATUS_NOT_SENT] as $status) {
            $this->storeSettings([SendStatus::SETTING_STATUS => $status, SendStatus::SETTING_UPDATED_AT => $this->hoursAgo(5)]);
            $this->assertFalse(SendStatus::canRetry($this->submission), $status);
        }

        $this->storeSettings([SendStatus::SETTING_STATUS => SendStatus::STATUS_PENDING, SendStatus::SETTING_UPDATED_AT => $this->hoursAgo(0)]);
        $this->assertFalse(SendStatus::canRetry($this->submission));

        $this->storeSettings([SendStatus::SETTING_STATUS => SendStatus::STATUS_PENDING, SendStatus::SETTING_UPDATED_AT => $this->hoursAgo(2)]);
        $this->assertTrue(SendStatus::canRetry($this->submission));

        $this->storeSettings([SendStatus::SETTING_STATUS => SendStatus::STATUS_FAILED, SendStatus::SETTING_UPDATED_AT => $this->hoursAgo(0)]);
        $this->assertTrue(SendStatus::canRetry($this->submission));
    }

    public function testShouldTransitionFailedToPendingOnlyOnce()
    {
        $this->storeSettings([
            SendStatus::SETTING_STATUS => SendStatus::STATUS_FAILED,
            SendStatus::SETTING_UPDATED_AT => $this->hoursAgo(0),
            SendStatus::SETTING_ERROR => 'OA Switchboard API is unavailable',
        ]);

        $this->assertTrue(SendStatus::transitionToPending($this->submission));
        $this->assertSame(SendStatus::STATUS_PENDING, $this->storedStatus());
        $this->assertNull(SendStatus::read($this->submission)['error']);
        $this->assertFalse(SendStatus::transitionToPending($this->submission));
        $this->assertSame(SendStatus::STATUS_PENDING, $this->storedStatus());
    }

    public function testShouldTransitionStalePendingToPending()
    {
        $this->storeSettings([
            SendStatus::SETTING_STATUS => SendStatus::STATUS_PENDING,
            SendStatus::SETTING_UPDATED_AT => $this->hoursAgo(2),
        ]);

        $this->assertTrue(SendStatus::transitionToPending($this->submission));
        $this->assertFalse(SendStatus::canRetry($this->submission));
    }
}
