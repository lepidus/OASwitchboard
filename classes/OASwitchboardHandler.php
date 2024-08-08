<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\notification\NotificationManager;
use PKP\log\event\PKPSubmissionEventLogEntry;
use APP\core\Application;
use PKP\notification\PKPNotification;
use PKP\security\Validation;
use PKP\core\Core;
use APP\core\Application;
use APP\submission\Submission;
use APP\template\TemplateManager;

class OASwitchboardHandler
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
                    'priority' => TemplateManager::STYLE_SEQUENCE_LATE,
                ]
            );
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
}
