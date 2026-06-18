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

use APP\facades\Repo;
use APP\submission\Submission;
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

    // prevent the settings from being silently dropped on edit() by EntityDAO.
    public static function addToSubmissionSchema($hookName, $args): bool
    {
        $schema = & $args[0];
        foreach ([self::SETTING_STATUS, self::SETTING_UPDATED_AT, self::SETTING_ERROR] as $setting) {
            $schema->properties->{$setting} = (object) [
                'type' => 'string',
                'apiSummary' => false,
                'validation' => ['nullable'],
            ];
        }
        return false;
    }

    public static function recordPending(Submission $submission): void
    {
        self::record($submission, self::STATUS_PENDING);
    }

    public static function recordSent(Submission $submission): void
    {
        self::record($submission, self::STATUS_SENT);
    }

    public static function recordFailure(Submission $submission, string $errorMessage): void
    {
        self::record($submission, self::STATUS_FAILED, $errorMessage);
    }

    public static function recordNotSent(Submission $submission): void
    {
        self::record($submission, self::STATUS_NOT_SENT);
    }

    public static function readFromSubmission(Submission $submission): ?array
    {
        $status = $submission->getData(self::SETTING_STATUS);
        if (!$status) {
            return null;
        }

        return [
            'status' => $status,
            'updatedAt' => $submission->getData(self::SETTING_UPDATED_AT),
            'error' => $submission->getData(self::SETTING_ERROR),
        ];
    }

    private static function record(Submission $submission, string $status, ?string $errorMessage = null): void
    {
        $settings = [
            self::SETTING_STATUS => $status,
            self::SETTING_UPDATED_AT => Core::getCurrentDate(),
            self::SETTING_ERROR => $errorMessage,
        ];

        Repo::submission()->edit($submission, $settings);
        foreach ($settings as $name => $value) {
            $submission->setData($name, $value);
        }
    }
}
