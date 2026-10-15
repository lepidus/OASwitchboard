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
use APP\plugins\generic\OASwitchboard\classes\exceptions\OASwitchboardException;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\exceptions\UnconfirmedDeliveryException;
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
    private const LOCK_EXPIRES_AFTER_SECONDS = 90;

    public int $uniqueFor = SendStatus::STALE_PENDING_SECONDS;

    protected int $submissionId;
    protected int $contextId;
    protected ?int $userId;

    public function __construct(int $submissionId, int $contextId, ?int $userId = null)
    {
        parent::__construct();
        $this->submissionId = $submissionId;
        $this->contextId = $contextId;
        $this->userId = $userId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    // Prevents a duplicate dispatch from enqueuing two sends for the same submission.
    public function uniqueId(): string
    {
        return (string) $this->submissionId;
    }


    // Serializes concurrent runs for the same submission. Locks the whole handle()
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

        if (!$this->isPluginEnabled()) {
            SendStatus::recordFailure($submission, __('plugins.generic.OASwitchboard.sendStatus.pluginDisabled'));
            return;
        }

        if ($this->wasAlreadySent($submission)) {
            return;
        }

        $service = $this->createOASwitchboardService($submission);
        try {
            $service->sendP1PioMessage();
        } catch (UnconfirmedDeliveryException $exception) {
            $this->fail($exception);
            return;
        }
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
        SendStatus::recordFailure($submission, $this->describeFailure($exception));
        $this->registerSubmissionEventLog($submission, 'plugins.generic.OASwitchboard.sendMessageWithError');
    }

    private function describeFailure(?\Throwable $exception): string
    {
        if ($exception instanceof OASwitchboardException || $exception instanceof P1PioException) {
            return $exception->getMessage();
        }
        return __('plugins.generic.OASwitchboard.serverError');
    }

    // Guards against duplicate deliveries when requeuing multiple failed jobs.
    private function wasAlreadySent($submission): bool
    {
        $sendStatus = SendStatus::read($submission);
        return ($sendStatus['status'] ?? null) === SendStatus::STATUS_SENT;
    }

    private function registerSubmissionEventLog($submission, string $message): void
    {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_PUBLISH,
            'userId' => $this->userId,
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

    protected function ensurePluginIsLoaded()
    {
        return PluginRegistry::getPlugin('generic', 'oaswitchboardplugin')
            ?? PluginRegistry::loadPlugin('generic', 'OASwitchboard', $this->contextId);
    }

    protected function isPluginEnabled(): bool
    {
        $plugin = $this->ensurePluginIsLoaded();
        return $plugin && $plugin->getEnabled($this->contextId);
    }
}
