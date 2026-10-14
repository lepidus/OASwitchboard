<?php

/**
 * @file plugins/generic/OASwitchboard/classes/SendStatus.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SendStatus
 *
 * @brief Records and reads the OA Switchboard message send status on a submission
 */

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;

class SendStatus
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NOT_SENT = 'notSent';

    public const SETTING_STATUS = 'oaSwitchboardSendStatus';
    public const SETTING_UPDATED_AT = 'oaSwitchboardSendStatusUpdatedAt';
    public const SETTING_ERROR = 'oaSwitchboardSendError';

    public const STALE_PENDING_SECONDS = 3600;

    private const SETTINGS_TABLE = 'submission_settings';

    public static function recordPending(Submission $submission): void
    {
        self::record($submission->getId(), self::STATUS_PENDING);
    }

    public static function recordSent(Submission $submission): void
    {
        self::record($submission->getId(), self::STATUS_SENT);
    }

    public static function recordFailure(Submission $submission, string $errorMessage): void
    {
        self::record($submission->getId(), self::STATUS_FAILED, $errorMessage);
    }

    public static function recordNotSent(Submission $submission): void
    {
        self::record($submission->getId(), self::STATUS_NOT_SENT);
    }

    public static function read(Submission $submission): ?array
    {
        $settings = self::readSettings($submission->getId());
        if (empty($settings[self::SETTING_STATUS])) {
            return null;
        }

        return [
            'status' => $settings[self::SETTING_STATUS],
            'updatedAt' => $settings[self::SETTING_UPDATED_AT] ?? null,
            'error' => $settings[self::SETTING_ERROR] ?? null,
        ];
    }

    public static function canRetry(Submission $submission): bool
    {
        return self::isRetryable(self::readSettings($submission->getId()));
    }

    public static function transitionToPending(Submission $submission): bool
    {
        return DB::transaction(function () use ($submission) {
            $settings = self::readSettings($submission->getId(), true);
            if (!self::isRetryable($settings)) {
                return false;
            }

            self::record($submission->getId(), self::STATUS_PENDING);
            return true;
        });
    }

    private static function isRetryable(array $settings): bool
    {
        $status = $settings[self::SETTING_STATUS] ?? null;
        if ($status === self::STATUS_FAILED) {
            return true;
        }

        if ($status !== self::STATUS_PENDING) {
            return false;
        }

        $updatedAt = strtotime($settings[self::SETTING_UPDATED_AT] ?? '');
        return $updatedAt === false || $updatedAt <= strtotime(Core::getCurrentDate()) - self::STALE_PENDING_SECONDS;
    }

    private static function readSettings(int $submissionId, bool $lockForUpdate = false): array
    {
        $query = DB::table(self::SETTINGS_TABLE)
            ->where('submission_id', $submissionId)
            ->where('locale', '')
            ->whereIn('setting_name', [self::SETTING_STATUS, self::SETTING_UPDATED_AT, self::SETTING_ERROR]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->pluck('setting_value', 'setting_name')->all();
    }

    private static function record(int $submissionId, string $status, ?string $errorMessage = null): void
    {
        $settings = [
            self::SETTING_STATUS => $status,
            self::SETTING_UPDATED_AT => Core::getCurrentDate(),
            self::SETTING_ERROR => $errorMessage,
        ];

        DB::transaction(function () use ($submissionId, $settings) {
            foreach ($settings as $name => $value) {
                $key = ['submission_id' => $submissionId, 'locale' => '', 'setting_name' => $name];
                if ($value === null) {
                    DB::table(self::SETTINGS_TABLE)->where($key)->delete();
                    continue;
                }
                DB::table(self::SETTINGS_TABLE)->updateOrInsert($key, ['setting_value' => $value]);
            }
        });
    }
}
