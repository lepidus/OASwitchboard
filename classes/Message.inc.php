<?php

import('plugins.generic.OASwitchboard.classes.OASwitchboardService');
import('plugins.generic.OASwitchboard.classes.exceptions.P1PioException');

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
        $contextId = PKPApplication::get()->getRequest()->getContext()->getId();
        $request = PKPApplication::get()->getRequest();
        $userId = $request->getUser()->getId();

        try {
            if ($publication->getData('status') === STATUS_PUBLISHED) {
                $OASwitchboard = new OASwitchboardService($this->plugin, $contextId, $submission);
                $OASwitchboard->sendP1PioMessage();
                if (!OASwitchboardService::isRorAssociated($submission)) {
                    $keyMessage = 'plugins.generic.OASwitchboard.postRequirementsError.recipient';
                    $this->sendNotification($userId, __($keyMessage), NOTIFICATION_TYPE_WARNING);
                    $this->registerSubmissionEventLog($request, $submission, $keyMessage);
                }
                $this->sendNotification($userId, __('plugins.generic.OASwitchboard.sendMessageWithSuccess'), NOTIFICATION_TYPE_SUCCESS);
            }
        } catch (P1PioException $e) {
            $this->sendNotification($userId, $e->getMessage(), NOTIFICATION_TYPE_WARNING);

            if ($e->getP1PioErrors()) {
                foreach ($e->getP1PioErrors() as $error) {
                    $this->sendNotification($userId, __($error), NOTIFICATION_TYPE_WARNING);
                    $this->registerSubmissionEventLog($request, $submission, $error);
                }
            }
            throw $e;
        }
    }

    private function registerSubmissionEventLog($request, $submission, $error)
    {
        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_TYPE_DEFAULT,
            $error,
            []
        );
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
