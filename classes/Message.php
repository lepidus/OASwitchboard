<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\jobs\SendP1PioMessageJob;
use APP\submission\Submission;
use PKP\security\Validation;

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

        if ($publication->getData('status') !== Submission::STATUS_PUBLISHED) {
            return false;
        }

        try {
            $this->scheduleSendToOASwitchboard($submission);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return false;
    }

    public function scheduleSendToOASwitchboard($submission): void
    {
        $contextId = (int) $submission->getData('contextId');
        OASwitchboardService::validatePluginIsConfigured($this->plugin, $contextId);

        try {
            $this->buildMessage($submission);
        } catch (P1PioException $e) {
            SendStatus::recordNotSent($submission);
            return;
        }

        SendStatus::recordPending($submission);
        $this->dispatchSendJob($submission->getId(), $contextId, $this->getActingUserId());
    }

    protected function buildMessage($submission): P1Pio
    {
        return new P1Pio($submission);
    }

    protected function dispatchSendJob(int $submissionId, int $contextId, ?int $userId = null): void
    {
        dispatch(new SendP1PioMessageJob($submissionId, $contextId, $userId));
    }

    protected function getActingUserId(): ?int
    {
        return Validation::loggedInAs() ?? Application::get()->getRequest()->getUser()?->getId();
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
