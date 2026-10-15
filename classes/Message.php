<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\jobs\SendP1PioMessageJob;
use APP\submission\Submission;
use PKP\components\forms\FieldHTML;
use PKP\observers\events\PublicationPublished;
use PKP\security\Validation;

class Message
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function handlePublicationPublished(PublicationPublished $event): void
    {
        if ($event->publication->getData('status') !== Submission::STATUS_PUBLISHED) {
            return;
        }

        if (!$this->plugin->getEnabled((int) $event->submission->getData('contextId'))) {
            return;
        }

        try {
            $this->scheduleSendToOASwitchboard(Repo::submission()->get($event->submission->getId()));
        } catch (\Throwable $exception) {
            error_log('OASwitchboard message scheduling failed for submission ' . $event->submission->getId() . ': ' . $exception->getMessage());
        }
    }

    public function scheduleSendToOASwitchboard($submission): void
    {
        $contextId = (int) $submission->getData('contextId');
        OASwitchboardService::validatePluginIsConfigured($this->plugin, $contextId);

        try {
            $this->buildMessage($submission);
        } catch (P1PioException $e) {
            SendStatus::recordNotSent($submission);
            return;
        }

        SendStatus::recordPending($submission);
        $this->dispatchSendJob($submission->getId(), $contextId, $this->getActingUserId());
    }

    public function retrySendToOASwitchboard($submission): bool
    {
        $contextId = (int) $submission->getData('contextId');
        OASwitchboardService::validatePluginIsConfigured($this->plugin, $contextId);

        try {
            $this->buildMessage($submission);
        } catch (P1PioException $exception) {
            return false;
        }

        if (!SendStatus::transitionToPending($submission)) {
            return false;
        }

        try {
            $this->dispatchSendJob($submission->getId(), $contextId, $this->getActingUserId());
        } catch (\Throwable $exception) {
            SendStatus::recordFailure(
                $submission,
                __('plugins.generic.OASwitchboard.serverError')
            );
            throw $exception;
        }

        return true;
    }

    protected function buildMessage($submission): P1Pio
    {
        return new P1Pio($submission);
    }

    protected function dispatchSendJob(int $submissionId, int $contextId, ?int $userId = null): void
    {
        dispatch(new SendP1PioMessageJob($submissionId, $contextId, $userId));
    }

    protected function getActingUserId(): ?int
    {
        return Validation::loggedInAs() ?? Application::get()->getRequest()->getUser()?->getId();
    }

    public function validateBeforePublicationEvent($hookName, $form)
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $context = Application::get()->getRequest()->getContext();
        $submission = Repo::submission()->get($form->publication->getData('submissionId'));

        try {
            OASwitchboardService::validatePluginIsConfigured($this->plugin, $context->getId());
        } catch (\Exception $e) {
            $this->addPublishNotice($form, '<div class="pkpNotification pkpNotification--information">' . $e->getMessage() . '</div>');
            return false;
        }

        $missingRequirements = OASwitchboardService::getMissingRequirements($submission, $context);
        $this->addPublishNotice(
            $form,
            empty($missingRequirements)
                ? $this->getSubmissionAlreadyToSendMessage($submission)
                : $this->getMandatoryDataErrorMessage($missingRequirements, $submission)
        );

        return false;
    }

    private function addPublishNotice($form, string $description): void
    {
        $form->addField(new FieldHTML('registerNotice', [
            'description' => $description,
            'groupId' => 'default',
        ]));
    }

    private function getMandatoryDataErrorMessage($p1PioErrors, $submission, $includePrefix = true): string
    {
        $introductionMessage = $includePrefix ?
            __('plugins.generic.OASwitchboard.includePrefix') . __('plugins.generic.OASwitchboard.postRequirementsError.introductionText') :
            ucfirst(__('plugins.generic.OASwitchboard.postRequirementsError.introductionText'));
        $message = '<div class="pkpNotification pkpNotification--information">' . $introductionMessage . '<br><br>';
        foreach ($p1PioErrors as $error) {
            $noticeMessage = __($error);
            $message .= '- ' . $noticeMessage . '<br>';
        }
        if (!OASwitchboardService::isRorAssociated($submission)) {
            $message .= '<br>' . __('plugins.generic.OASwitchboard.rorRecommendation') . '<br>';
        }
        $message .= '<br>' . __('plugins.generic.OASwitchboard.postRequirementsError.conclusionText');

        $message .= '</div>';
        return $message;
    }

    private function getSubmissionAlreadyToSendMessage($submission, $includePrefix = true): string
    {
        $hasRorAssociated = OASwitchboardService::isRorAssociated($submission);
        $messageType = $hasRorAssociated ? 'success' : 'information';
        $successMessage = $includePrefix ?
            __('plugins.generic.OASwitchboard.includePrefix') . __('plugins.generic.OASwitchboard.postRequirementsSuccess') :
            ucfirst(__('plugins.generic.OASwitchboard.postRequirementsSuccess'));
        $rorRecommendationMessage = $hasRorAssociated ? '' : '<br><br>' . __('plugins.generic.OASwitchboard.rorRecommendation');

        return '<div class="pkpNotification pkpNotification--' . $messageType . '">' . $successMessage . $rorRecommendationMessage . '</div>';
    }
}
