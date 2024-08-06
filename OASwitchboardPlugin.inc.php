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

import('lib.pkp.classes.plugins.GenericPlugin');

class OASwitchboardPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled()) {
            import('plugins.generic.OASwitchboard.classes.HookCallbacks');
            $hookCallbacks = new HookCallbacks($this);
            HookRegistry::register('Publication::publish', array($hookCallbacks, 'sendOASwitchboardMessage'));
        }
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
                $this->import('classes.settings.OASwitchboardSettingsForm');
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
