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
use PKP\plugins\Hook;
use APP\core\Application;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\notification\NotificationManager;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm;
use PKP\core\JSONMessage;
use APP\plugins\generic\OASwitchboard\classes\HookCallbacks;
use APP\plugins\generic\OASwitchboard\classes\settings\Manage;

class OASwitchboardPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            $hookCallbacks = new HookCallbacks($this);
            Hook::add('Publication::publish', [$hookCallbacks, 'sendOASwitchboardMessage']);
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
