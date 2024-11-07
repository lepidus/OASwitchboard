<?php

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\notification\PKPNotification;
use PKP\db\DAORegistry;

class Resources
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function addWorkflowNotificationsJavaScript($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1];
        $request = Application::get()->getRequest();

        if ($template == 'workflow/workflow.tpl') {
            $data = [];
            $data['notificationUrl'] = $request->url(null, 'notification', 'fetchNotification');
            $classNameWithNamespace = get_class($this->plugin);
            $className = basename(str_replace('\\', '/', $classNameWithNamespace));

            $templateMgr->addJavaScript(
                'workflowData',
                '$.pkp.plugins.generic = $.pkp.plugins.generic || {};' .
                    '$.pkp.plugins.generic.' . strtolower($className) . ' = ' . json_encode($data) . ';',
                [
                    'inline' => true,
                    'contexts' => 'backend',
                ]
            );

            $templateMgr->addJavaScript(
                'plugin-OASwitchboard-workflow',
                $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/Workflow.js',
                [
                    'contexts' => 'backend',
                    'priority' => TemplateManager::STYLE_SEQUENCE_LATE,
                ]
            );
        }

        return false;
    }
}
