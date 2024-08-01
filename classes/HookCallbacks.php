<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use PKP\log\SubmissionLog;
use APP\notification\NotificationManager;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\security\Validation;
use APP\facades\Repo;
use PKP\core\Core;
use APP\core\Application;

class HookCallbacks
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function addJavaScripts($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];
        $request = Application::get()->getRequest();

        if ($template == 'workflow/workflow.tpl') {
            $data = [];
            $data['notificationUrl'] = $request->url(null, 'notification', 'fetchNotification');
            $classNameWithNamespace = get_class($this->plugin);
            $className = basename(str_replace('\\', '/', $classNameWithNamespace));

            $templateMgr->addJavaScript(
                'workflowData',
                '$.pkp.plugins.generic = $.pkp.plugins.generic || {};' .
                    '$.pkp.plugins.generic.' . strtolower($className) . ' = ' . json_encode($data) . ';',
                [
                    'inline' => true,
                    'contexts' => 'backend',
                ]
            );

            $templateMgr->addJavaScript(
                'plugin-OASwitchboard-workflow',
                $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/Workflow.js',
                [
                    'contexts' => 'backend',
                    'priority' => STYLE_SEQUENCE_LATE,
                ]
            );
        }

        return false;
    }


    public function sendOASwitchboardMessage($hookName, $args)
    {
        $publication = & $args[0];
        $submission = & $args[2];
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $request = Application::get()->getRequest();
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
        } catch (\Exception $e) {
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
