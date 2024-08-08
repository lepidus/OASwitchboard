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
                if (!OASwitchboardService::isRorAssociated($submission)) {
                    $keyMessage = 'plugins.generic.OASwitchboard.postRequirementsError.recipient';
                    $this->sendNotification($userId, __($keyMessage), PKPNotification::NOTIFICATION_TYPE_WARNING);
                    $this->registerSubmissionEventLog($request, $submission, $keyMessage);
                }
                $this->sendNotification(
                    $userId,
                    __('plugins.generic.OASwitchboard.sendMessageWithSuccess'),
                    PKPNotification::NOTIFICATION_TYPE_SUCCESS
                );
            }
        } catch (P1PioException $e) {
            $this->sendNotification($userId, $e->getMessage(), PKPNotification::NOTIFICATION_TYPE_WARNING);

            if ($e->getP1PioErrors()) {
                foreach ($e->getP1PioErrors() as $error) {
                    $this->sendNotification($userId, __($error), PKPNotification::NOTIFICATION_TYPE_WARNING);
                    $this->registerSubmissionEventLog($request, $submission, $error);
                }
            }
            throw $e;
        }
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
}
