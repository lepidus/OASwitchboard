<?php

namespace APP\plugins\generic\OASwitchboard\classes\settings;

use APP\core\Request;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class Actions
{
    public OASwitchboardPlugin $plugin;

    public function __construct(OASwitchboardPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    public function execute(Request $request, array $actionArgs, array $parentActions): array
    {
        $router = $request->getRouter();

        return array_merge(
            $this->plugin->getEnabled() ? array(
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url(
                            $request,
                            null,
                            null,
                            'manage',
                            null,
                            array('verb' => 'settings', 'plugin' => $this->plugin->getName(), 'category' => 'generic')
                        ),
                        $this->plugin->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ) : array(),
            $parentActions
        );
    }
}
