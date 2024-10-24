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
            $message = new P1Pio($submission);
        } catch (P1PioException $e) {
            if ($e->getP1PioErrors()) {
                foreach ($e->getP1PioErrors() as $error) {
                    $warningIconHtml = '<span class="fa fa-exclamation-triangle pkpIcon--inline"></span>';
                    $noticeMsg = __($error);
                    $msg = '<div class="pkpNotification pkpNotification--warning">' . $warningIconHtml . $noticeMsg . '</div>';

                    $form->addField(new \PKP\components\forms\FieldHTML('registerNotice', [
                        'description' => $msg,
                        'groupId' => 'default',
                    ]));

                    error_log($e->getMessage());
                }
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
}
