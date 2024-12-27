<?php

/**
 * @file plugins/generic/OASwitchboard/OASwitchboardPlugin.php
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
use PKP\plugins\Hook;
use APP\core\Application;
use APP\plugins\generic\OASwitchboard\classes\Message;
use APP\plugins\generic\OASwitchboard\classes\Resources;
use APP\plugins\generic\OASwitchboard\classes\settings\Manage;
use APP\plugins\generic\OASwitchboard\classes\settings\Actions;

class OASwitchboardPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            $message = new Message($this);
            $resources = new Resources($this);
            Hook::add('Template::Workflow::Publication', [$message, 'addSubmissionStatusToWorkflow']);
            Hook::add('Publication::publish', [$message, 'sendToOASwitchboard']);
            Hook::add('TemplateManager::display', [$resources, 'addWorkflowNotificationsJavaScript']);
            Hook::add('Form::config::before', [$message, 'validateBeforePublicationEvent']);
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
