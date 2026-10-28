<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use PKP\components\forms\FieldHTML;
use PKP\core\Core;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\PKPNotification;
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

        $contextId = (int) $event->submission->getData('contextId');
        if (!$this->plugin->getEnabled($contextId) || !OASwitchboardService::isConfigured($this->plugin, $contextId)) {
            return;
        }

        try {
            $submission = $this->getSubmission($event->submission->getId());
            $this->createService($contextId, $submission)->sendP1PioMessage();
            $this->reportSuccess($submission);
        } catch (\Throwable $exception) {
            error_log('OASwitchboard message delivery failed for submission ' . $event->submission->getId() . ': ' . $exception->getMessage());
        }
    }

    protected function getSubmission(int $submissionId): Submission
    {
        return Repo::submission()->get($submissionId);
    }

    protected function createService(int $contextId, Submission $submission): OASwitchboardService
    {
        return new OASwitchboardService($this->plugin, $contextId, $submission);
    }

    protected function reportSuccess(Submission $submission): void
    {
        $keyMessage = 'plugins.generic.OASwitchboard.sendMessageWithSuccess';
        $userId = Validation::loggedInAs() ?? Application::get()->getRequest()->getUser()?->getId();
        if ($userId) {
            $this->sendNotification($userId, __($keyMessage), PKPNotification::NOTIFICATION_TYPE_SUCCESS);
        }
        $this->registerSubmissionEventLog($userId, $submission, $keyMessage);
    }

    public function validateBeforePublicationEvent($hookName, $form)
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $context = Application::get()->getRequest()->getContext();
        $submission = Repo::submission()->get($form->publication->getData('submissionId'));

        if (!OASwitchboardService::isConfigured($this->plugin, $context->getId())) {
            $this->addPublishNotice($form, '<div class="pkpNotification pkpNotification--information">' . __('plugins.generic.OASwitchboard.pluginIsNotConfigured') . '</div>');
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

    public function addSubmissionStatusToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];
        $request = Application::get()->getRequest();

        $submission = $smarty->getTemplateVars('submission');
        $context = $request->getContext();

        if (!OASwitchboardService::isConfigured($this->plugin, $context->getId())) {
            $smarty->assign([
                'pluginIsNotConfiguredInfo' => '<div class="pkpNotification pkpNotification--information">' . __('plugins.generic.OASwitchboard.pluginIsNotConfigured') . '</div>',
            ]);
        }

        $missingRequirements = OASwitchboardService::getMissingRequirements($submission, $context);
        if (empty($missingRequirements)) {
            $smarty->assign([
                'submissionIsAlreadySendMessage' => $this->getSubmissionAlreadyToSendMessage($submission, false),
            ]);
        } else {
            $smarty->assign([
                'submissionRequirementsIsPending' => $this->getMandatoryDataErrorMessage($missingRequirements, $submission, false),
            ]);
        }

        $output .= sprintf(
            '<tab id="OASwitchboard" label="%s">%s</tab>',
            __('plugins.generic.OASwitchboard.workflowTab.label'),
            $smarty->fetch($this->plugin->getTemplateResource('submissionStatus.tpl'))
        );
    }

    private function registerSubmissionEventLog(?int $userId, $submission, $error)
    {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_PUBLISH,
            'userId' => $userId,
            'message' => $error,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    private function sendNotification($userId, $message, $notificationType)
    {
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $userId,
            $notificationType,
            ['contents' => $message]
        );
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
