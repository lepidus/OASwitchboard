<?php

namespace APP\plugins\generic\OASwitchboard\classes\settings;

use APP\core\Request;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm;
use PKP\core\JSONMessage;
use APP\notification\NotificationManager;
use PKP\notification\PKPNotification;

class OASwitchboardManage
{
    public OASwitchboardPlugin $plugin;

    public function __construct(OASwitchboardPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    public function execute(array $args, Request $request)
    {
        $user = $request->getUser();
        $notificationManager = new NotificationManager();

        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $form = $this->createSettingsForm($context->getId());
                $form->initData();
                if ($request->getUserVar('save') && $form->canBeSaved()) {
                    $form->readInputData();
                    if ($form->validate() && $form->validateAPICredentials()) {
                        $form->execute();
                        $notificationManager->createTrivialNotification(
                            $user->getId(),
                            PKPNotification::NOTIFICATION_TYPE_SUCCESS
                        );
                        return new JSONMessage(true);
                    }
                }
                return new JSONMessage(true, $form->fetch($request));
            default:
                return new JSONMessage(false);
        }
    }

    protected function createSettingsForm(int $contextId): OASwitchboardSettingsForm
    {
        return new OASwitchboardSettingsForm($this->plugin, $contextId);
    }
}
