<?php

import('plugins.generic.OASwitchboard.classes.OASwitchboardService');

class Message
{
    private $plugin;
    private $contextsWithIdentifierPlugins = [];

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function sendToOASwitchboard($hookName, $args)
    {
        $publication = $args[0];
        $submission = $args[2];
        if ($publication->getData('status') !== STATUS_PUBLISHED) {
            return false;
        }

        $contextId = (int) $submission->getData('contextId');
        if (!$this->plugin->getEnabled($contextId)) {
            return false;
        }

        try {
            $submission = $this->getSubmissionWithPublicationIdentifiers($submission, $contextId);
            $this->createService($contextId, $submission)->sendP1PioMessage();
            $this->reportSuccess($submission);
        } catch (Throwable $exception) {
            error_log('OASwitchboard message delivery failed for submission ' . $submission->getId() . ': ' . $exception->getMessage());
        }

        return false;
    }

    protected function getSubmissionWithPublicationIdentifiers($submission, $contextId)
    {
        if ($this->publicationSchemaHasIdentifiers() || in_array($contextId, $this->contextsWithIdentifierPlugins, true)) {
            return $submission;
        }

        $this->contextsWithIdentifierPlugins[] = $contextId;
        $this->loadPublicationIdentifierPlugins($contextId);
        return $this->reloadSubmission($submission);
    }

    protected function publicationSchemaHasIdentifiers(): bool
    {
        return isset(Services::get('schema')->get(SCHEMA_PUBLICATION)->properties->{'pub-id::doi'});
    }

    protected function loadPublicationIdentifierPlugins($contextId): void
    {
        PluginRegistry::loadCategory('pubIds', true, $contextId);
        Services::get('schema')->get(SCHEMA_PUBLICATION, true);
    }

    protected function reloadSubmission($submission)
    {
        return Services::get('submission')->get($submission->getId());
    }

    protected function createService($contextId, $submission)
    {
        return new OASwitchboardService($this->plugin, $contextId, $submission);
    }

    protected function reportSuccess($submission)
    {
        $request = Application::get()->getRequest();
        $keyMessage = 'plugins.generic.OASwitchboard.sendMessageWithSuccess';
        $user = $request->getUser();
        if ($user) {
            $this->sendNotification($user->getId(), __($keyMessage), NOTIFICATION_TYPE_SUCCESS);
        }
        $this->registerSubmissionEventLog($request, $submission, $keyMessage);
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

        $missingRequirements = OASwitchboardService::getMissingRequirements($submission);
        $form->addField(new \PKP\components\forms\FieldHTML('registerNotice', [
            'description' => empty($missingRequirements)
                ? $this->getSubmissionAlreadyToSendMessage($submission)
                : $this->getMandatoryDataErrorMessage($missingRequirements, $submission),
            'groupId' => 'default',
        ]));

        return false;
    }

    public function addSubmissionStatusToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];
        $request = Application::get()->getRequest();

        $submission = $smarty->get_template_vars('submission');
        $contextId = $request->getContext()->getId();

        try {
            OASwitchboardService::validatePluginIsConfigured($this->plugin, $contextId);
        } catch (\Exception $e) {
            $message = '<div class="pkpNotification pkpNotification--information">' . $e->getMessage() . '</div>';
            $smarty->assign([
                'pluginIsNotConfiguredInfo' => $message,
            ]);
        }

        $missingRequirements = OASwitchboardService::getMissingRequirements($submission);
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
