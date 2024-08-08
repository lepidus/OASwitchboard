<?php

import('plugins.generic.OASwitchboard.classes.OASwitchboardService');
import('plugins.generic.OASwitchboard.classes.exceptions.P1PioException');

class HookCallbacks
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function sendOASwitchboardMessage($hookName, $args)
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

    public function addJavaScripts($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];
        $request = Application::get()->getRequest();

        if ($template == 'workflow/workflow.tpl') {
            $data = [];
            $data['notificationUrl'] = $request->url(null, 'notification', 'fetchNotification');

            $templateMgr->addJavaScript(
                'workflowData',
                '$.pkp.plugins.generic = $.pkp.plugins.generic || {};' .
                    '$.pkp.plugins.generic.' . strtolower(get_class($this->plugin)) . ' = ' . json_encode($data) . ';',
                [
                    'inline' => true,
                    'contexts' => 'backend',
                ]
            );

            $templateMgr->addJavaScript(
                'plugin-oaswitchboard-workflow',
                $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/Workflow.js',
                [
                    'contexts' => 'backend',
                    'priority' => STYLE_SEQUENCE_LATE,
                ]
            );
        }

        return false;
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
