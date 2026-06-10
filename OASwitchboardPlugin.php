<?php

/**
 * @file plugins/generic/OASwitchboard/OASwitchboardPlugin.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboardPlugin
 *
 * @ingroup plugins_generic_OASwitchboard
 *
 * @brief OASwitchboard plugin class
 */

namespace APP\plugins\generic\OASwitchboard;

use APP\core\Application;
use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardStatusController;
use APP\plugins\generic\OASwitchboard\classes\Message;
use APP\plugins\generic\OASwitchboard\classes\migrations\EncryptApiCredentialsMigration;
use APP\plugins\generic\OASwitchboard\classes\Resources;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardActions;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardManage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class OASwitchboardPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            $message = new Message($this);
            $resources = new Resources($this);
            $statusController = new OASwitchboardStatusController($this);

            Hook::add('Publication::publish', [$message, 'sendToOASwitchboard']);
            Hook::add('Form::config::before', [$message, 'validateBeforePublicationEvent']);

            $statusController->register();
            $resources->loadBackendBuild();
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
        $actions = new OASwitchboardActions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    public function manage($args, $request)
    {
        $manage = new OASwitchboardManage($this);
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
    public function getInstallMigration()
    {
        return new EncryptApiCredentialsMigration();
    }
}
