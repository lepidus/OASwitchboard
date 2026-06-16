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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PKP\core\Core;
use PKP\jobs\BaseJob;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\plugins\PluginRegistry;

class SendP1PioMessageJob extends BaseJob implements ShouldBeUnique
{
    /**
     * Seconds before the overlap lock auto-expires, releasing it if a worker
     * dies mid-send. Kept above the job timeout (60s) so it never expires while
     * a send is legitimately in progress.
     */
    private const LOCK_EXPIRES_AFTER_SECONDS = 90;

    protected int $submissionId;
    protected int $contextId;

    public function __construct(int $submissionId, int $contextId)
    {
        parent::__construct();
        $this->submissionId = $submissionId;
        $this->contextId = $contextId;
    }

    /**
     * Prevents a duplicate dispatch (e.g. publishing twice in quick succession)
     * from enqueuing two sends for the same submission. Acquired at dispatch
     * time only, so it does not cover jobs re-inserted by "Requeue All Failed
     * Jobs" (which bypass dispatch) -- that case is handled by middleware() and
     * the already-sent guard in handle().
     */
    public function uniqueId(): string
    {
        return (string) $this->submissionId;
    }

    /**
     * Serializes concurrent runs for the same submission. The non-transactional
     * external POST cannot be made exactly-once, so the lock is held across the
     * whole handle() (including the send): a concurrent duplicate cannot acquire
     * it and is dropped (dontRelease) -- the holder is already sending it. A
     * duplicate that runs *after* the send completed acquires the lock freely
     * and is short-circuited by the already-sent guard instead.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->submissionId))
                ->dontRelease()
                ->expireAfter(self::LOCK_EXPIRES_AFTER_SECONDS),
        ];
    }

    public function handle(): void
    {
        $submission = Repo::submission()->get($this->submissionId);
        if (!$submission) {
            $this->fail('Submission ' . $this->submissionId . ' not found.');
            return;
        }

        if ($this->wasAlreadySent($submission)) {
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

    /**
     * Guards against duplicate deliveries: requeuing failed jobs (e.g. the admin
     * "Requeue All Failed Jobs" action) re-runs handle() for every piled-up job,
     * which would re-send the P1 message to the OA Switchboard for the same
     * submission. A fresh publish resets the status to pending before dispatch,
     * so this only short-circuits already-delivered sends.
     */
    private function wasAlreadySent($submission): bool
    {
        $sendStatus = SendStatus::readFromSubmission($submission);
        return ($sendStatus['status'] ?? null) === SendStatus::STATUS_SENT;
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
