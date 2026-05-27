<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\core\Application;
use APP\template\TemplateManager;

class Resources
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function loadBackendBuild(): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $buildUrl = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/public/build';

        $templateMgr->addJavaScript(
            'OASwitchboardPlugin',
            $buildUrl . '/build.iife.js',
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
            ]
        );

        $templateMgr->addStyleSheet(
            'OASwitchboardPlugin',
            $buildUrl . '/build.css',
            ['contexts' => ['backend']]
        );
    }
}
