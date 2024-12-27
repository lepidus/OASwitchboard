<?php

import('plugins.generic.OASwitchboard.classes.OASwitchboardService');
import('plugins.generic.OASwitchboard.classes.exceptions.P1PioException');
import('plugins.generic.OASwitchboard.classes.messages.P1Pio');

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
                $keyMessage = 'plugins.generic.OASwitchboard.sendMessageWithSuccess';
                $this->sendNotification($userId, __($keyMessage), NOTIFICATION_TYPE_SUCCESS);
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
        $submission = Services::get('submission')->get($form->publication->getData('submissionId'));

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

    public function addSubmissionStatusToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submission = $smarty->get_template_vars('submission');
        $publication = $submission->getCurrentPublication();
        $request = Application::get()->getRequest();

        $output .= sprintf(
            '<tab id="OASwitchboard" label="%s">%s</tab>',
            __('plugins.generic.OASwitchboard.workflowTab.label'),
            $smarty->fetch($this->plugin->getTemplateResource('submissionStatuses.tpl'))
        );
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
