<?php
/**
 * @file plugins/generic/OASwitchboard/OASwitchboardPlugin.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboardPlugin
 * @ingroup plugins_generic_OASwitchboard
 *
 * @brief OASwitchboard plugin class
 */

namespace APP\plugins\generic\OASwitchboard;

use PKP\plugins\GenericPlugin;
use APP\plugins\generic\classes\OASwitchboard\OASwitchboardService;
use PKP\log\SubmissionLog;
use PKP\plugins\Hook;
use APP\core\Application;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\notification\NotificationManager;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm;
use PKP\core\JSONMessage;
use APP\template\TemplateManager;

class OASwitchboardPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        Hook::call('Publication::publish', array($this, 'sendOASwitchboardMessage'));
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.OASwitchboard.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.OASwitchboard.description');
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        return array_merge(
            $this->getEnabled() ? array(
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url(
                            $request,
                            null,
                            null,
                            'manage',
                            null,
                            array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')
                        ),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ) : array(),
            parent::getActions($request, $actionArgs)
        );
    }

    public function manage($args, $request)
    {
        $user = $request->getUser();
        import('classes.notification.NotificationManager');
        $notificationManager = new NotificationManager();

        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $form = new OASwitchboardSettingsForm($this, $context->getId());
                $form->initData();
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate() && $form->validateAPICredentials()) {
                        $form->execute();
                        $notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS);
                        return new JSONMessage(true);
                    }
                }
                return new JSONMessage(true, $form->fetch($request));
            default:
                return parent::manage($verb, $args, $message, $messageParams);
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

    public function sendOASwitchboardMessage($hookName, $args)
    {
        $publication = & $args[0];
        $submission = & $args[2];
        $contextId = PKPApplication::get()->getRequest()->getContext()->getId();
        $request = PKPApplication::get()->getRequest();
        $userId = $request->getUser()->getId();

        try {
            if ($publication->getData('status') === STATUS_PUBLISHED) {
                $OASwitchboard = new OASwitchboardService($this, $contextId, $submission);
                $OASwitchboard->sendP1PioMessage();
                if (!OASwitchboardService::isRorAssociated($submission)) {
                    $keyMessage = 'plugins.generic.OASwitchboard.postRequirementsError.recipient';
                    $this->sendNotification($userId, __($keyMessage), NOTIFICATION_TYPE_WARNING);
                    $this->registerSubmissionEventLog($request, $submission, $keyMessage);
                }
                $this->sendNotification($userId, __('plugins.generic.OASwitchboard.sendMessageWithSuccess'), NOTIFICATION_TYPE_SUCCESS);
            }
        } catch (Exception $e) {
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

    public function getCanEnable()
    {
        $request = Application::get()->getRequest();
        return $request->getContext() !== null;
    }

    public function getCanDisable()
    {
        $request = Application::get()->getRequest();
        return $request->getContext() !== null;
    }
}
