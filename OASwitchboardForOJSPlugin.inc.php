<?php
/**
 * @file plugins/generic/OASwitchboardForOJS/OASwitchboardForOJSPlugin.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboardForOJSPlugin
 * @ingroup plugins_generic_OASwitchboardForOJS
 *
 * @brief OASwitchboardForOJS plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.OASwitchboardForOJS.classes.OASwitchboardService');

class OASwitchboardForOJSPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        HookRegistry::register('Publication::publish', array($this, 'sendOASwitchboardMessage'));
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.OASwitchboardForOJS.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.OASwitchboardForOJS.description');
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
                $this->import('classes.settings.OASwitchboardForOJSSettingsForm');
                $form = new OASwitchboardForOJSSettingsForm($this, $context->getId());
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

    public function sendOASwitchboardMessage($hookName, $args)
    {
        $publication = & $args[0];
        $submission = & $args[2];
        $contextId = PKPApplication::get()->getRequest()->getContext()->getId();
        $OASwitchboard = new OASwitchboardService($this, $contextId, $submission);

        try {
            if ($publication->getData('status') === STATUS_PUBLISHED) {
                $OASwitchboard->sendP1PioMessage();
            }
        } catch (Exception $e) {
            import('classes.notification.NotificationManager');
            $notificationManager = new NotificationManager();
            $userId = PKPApplication::get()->getRequest()->getUser()->getId();
            $notificationManager->createTrivialNotification(
                $userId,
                NOTIFICATION_TYPE_WARNING,
                array('contents' => $e->getMessage())
            );
            throw $e;
        }
    }
}
