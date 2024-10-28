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
                if (!OASwitchboardService::isRorAssociated($submission)) {
                    $keyMessage = 'plugins.generic.OASwitchboard.rorRecommendation';
                    $this->sendNotification($userId, __($keyMessage), PKPNotification::NOTIFICATION_TYPE_INFORMATION);
                    $this->registerSubmissionEventLog($request, $submission, $keyMessage);
                }
            }
        } catch (P1PioException $e) {
            $this->sendNotification($userId, $e->getMessage(), PKPNotification::NOTIFICATION_TYPE_WARNING);

            if ($e->getP1PioErrors()) {
                foreach ($e->getP1PioErrors() as $error) {
                    $this->sendNotification($userId, __($error), PKPNotification::NOTIFICATION_TYPE_WARNING);
                    $this->registerSubmissionEventLog($request, $submission, $error);
                }
            }
            error_log($e->getMessage());
        }
    }

    public function validateRegister($hookName, $form)
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $submission = Repo::submission()->get($form->publication->getData('submissionId'));

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
        $activityLogLocale = $error . '.activityLog';
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION,
            'userId' => Validation::loggedInAs() ?? $request->getUser()->getId(),
            'message' => $activityLogLocale,
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
