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
import('plugins.generic.OASwitchboard.classes.settings.Manage');
import('plugins.generic.OASwitchboard.classes.settings.Actions');

class OASwitchboardPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled()) {
            import('plugins.generic.OASwitchboard.classes.Message');
            import('plugins.generic.OASwitchboard.classes.Resources');
            $message = new Message($this);
            $resources = new Resources($this);
            HookRegistry::register('Publication::publish', [$message, 'sendToOASwitchboard']);
            HookRegistry::register('TemplateManager::display', [$resources, 'addWorkflowNotificationsJavaScript']);
            HookRegistry::register('NotificationManager::getNotificationMessage', [$resources, 'addMessageToInformationNotification']);
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
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    public function manage($args, $request)
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
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
