<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\notification\NotificationManager;
use PKP\notification\PKPNotification;
use APP\core\Application;
use APP\submission\Submission;
use PKP\core\Core;
use APP\facades\Repo;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\security\Validation;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;

class Message
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function sendToOASwitchboard($hookName, $args)
    {
        $publication = & $args[0];
        $submission = & $args[2];
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $request = Application::get()->getRequest();
        $userId = $request->getUser()->getId();

        try {
            if ($publication->getData('status') === Submission::STATUS_PUBLISHED) {
                $OASwitchboard = new OASwitchboardService($this->plugin, $contextId, $submission);
                $OASwitchboard->sendP1PioMessage();
                $keyMessage = 'plugins.generic.OASwitchboard.sendMessageWithSuccess';
                $this->sendNotification(
                    $userId,
                    __($keyMessage),
                    PKPNotification::NOTIFICATION_TYPE_SUCCESS
                );
                $this->registerSubmissionEventLog($request, $submission, $keyMessage);
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function validateBeforePublicationEvent($hookName, $form)
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $contextId = Application::get()->getRequest()->getContext()->getId();
        $submission = Repo::submission()->get($form->publication->getData('submissionId'));

        try {
            OASwitchboardService::validatePluginIsConfigured($this->plugin, $contextId);
        } catch (\Exception $e) {
            $message = '<div class="pkpNotification pkpNotification--information">' . $e->getMessage() . '</div>';
            $form->addField(new \PKP\components\forms\FieldHTML('registerNotice', [
                'description' => $message,
                'groupId' => 'default',
            ]));
            return false;
        }

        try {
            $p1Pio = new P1Pio($submission);
            $successMessage = $this->getSubmissionAlreadyToSendMessage($submission);

            $form->addField(new \PKP\components\forms\FieldHTML('registerNotice', [
                'description' => $successMessage,
                'groupId' => 'default',
            ]));
        } catch (P1PioException $e) {
            if ($e->getP1PioErrors()) {
                $errorMessage = $this->getMandatoryDataErrorMessage($e->getP1PioErrors(), $submission);
                $form->addField(new \PKP\components\forms\FieldHTML('registerNotice', [
                    'description' => $errorMessage,
                    'groupId' => 'default',
                ]));
            }
        }

        return false;
    }

    private function registerSubmissionEventLog($request, $submission, $error)
    {
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION,
            'userId' => Validation::loggedInAs() ?? $request->getUser()->getId(),
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
            array('contents' => $message)
        );
    }

    private function getMandatoryDataErrorMessage($p1PioErrors, $submission): string
    {
        $introductionMessage = __('plugins.generic.OASwitchboard.postRequirementsError.introductionText');
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

    private function getSubmissionAlreadyToSendMessage($submission): string
    {
        $hasRorAssociated = OASwitchboardService::isRorAssociated($submission);
        $messageType = $hasRorAssociated ? 'success' : 'information';
        $successMessage = __('plugins.generic.OASwitchboard.postRequirementsSuccess');
        $rorRecommendationMessage = $hasRorAssociated ? '' : '<br><br>' . __('plugins.generic.OASwitchboard.rorRecommendation');

        return '<div class="pkpNotification pkpNotification--' . $messageType . '">' . $successMessage . $rorRecommendationMessage . '</div>';
    }
}
