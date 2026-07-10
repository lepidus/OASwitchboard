<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;

class SendStatusDatabaseTest extends DatabaseTestCase
{
    protected function getAffectedTables()
    {
        return ['submission_settings'];
    }

    public function testShouldAcquireFailedStatusOnlyOnce()
    {
        $submissionId = DB::table('submissions')->value('submission_id');
        $this->assertNotNull($submissionId, 'The database fixture must contain a submission');

        DB::table('submission_settings')->updateOrInsert(
            [
                'submission_id' => $submissionId,
                'locale' => '',
                'setting_name' => SendStatus::SETTING_STATUS,
            ],
            ['setting_value' => SendStatus::STATUS_FAILED]
        );

        $submission = new Submission();
        $submission->setId($submissionId);
        $submission->setData(SendStatus::SETTING_STATUS, SendStatus::STATUS_FAILED);

        $this->assertTrue(SendStatus::transitionFailedToPending($submission));
        $this->assertSame(
            SendStatus::STATUS_PENDING,
            DB::table('submission_settings')
                ->where('submission_id', $submissionId)
                ->where('locale', '')
                ->where('setting_name', SendStatus::SETTING_STATUS)
                ->value('setting_value')
        );
        $this->assertFalse(SendStatus::transitionFailedToPending($submission));
    }
}
