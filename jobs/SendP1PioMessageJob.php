<?php

/**
 * @file plugins/generic/OASwitchboard/jobs/SendP1PioMessageJob.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SendP1PioMessageJob
 *
 * @brief Queue job that sends the P1 PIO message to the OA Switchboard API
 */

namespace APP\plugins\generic\OASwitchboard\jobs;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use PKP\core\Core;
use PKP\jobs\BaseJob;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\plugins\PluginRegistry;

class SendP1PioMessageJob extends BaseJob
{
    protected int $submissionId;
    protected int $contextId;

    public function __construct(int $submissionId, int $contextId)
    {
        parent::__construct();
        $this->submissionId = $submissionId;
        $this->contextId = $contextId;
    }

    public function handle(): void
    {
        $submission = Repo::submission()->get($this->submissionId);
        if (!$submission) {
            $this->fail('Submission ' . $this->submissionId . ' not found.');
            return;
        }

        $service = $this->createOASwitchboardService($submission);
        $service->sendP1PioMessage();
        SendStatus::recordSent($submission);
        $this->registerSubmissionEventLog($submission, 'plugins.generic.OASwitchboard.sendMessageWithSuccess');
    }

    public function failed(?\Throwable $exception = null): void
    {
        $submission = Repo::submission()->get($this->submissionId);
        if (!$submission) {
            return;
        }

        $this->ensurePluginIsLoaded();
        SendStatus::recordFailure($submission, $exception ? $exception->getMessage() : '');
        $this->registerSubmissionEventLog($submission, 'plugins.generic.OASwitchboard.sendMessageWithError');
    }

    private function registerSubmissionEventLog($submission, string $message): void
    {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION,
            'userId' => null,
            'message' => $message,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    protected function createOASwitchboardService($submission): OASwitchboardService
    {
        return new OASwitchboardService($this->ensurePluginIsLoaded(), $this->contextId, $submission);
    }

    /**
     * In a queue worker the plugin may not be registered yet; loading it also
     * registers the submission schema hook that send status recording relies on.
     */
    protected function ensurePluginIsLoaded()
    {
        return PluginRegistry::getPlugin('generic', 'oaswitchboardplugin')
            ?? PluginRegistry::loadPlugin('generic', 'OASwitchboard', $this->contextId);
    }
}
