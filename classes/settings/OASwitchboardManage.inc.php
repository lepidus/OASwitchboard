<?php

class OASwitchboardManage
{
    public $plugin;

    public function __construct(&$plugin)
    {
        $this->plugin = &$plugin;
    }

    public function execute($args, $request): JSONMessage
    {
        $user = $request->getUser();
        import('classes.notification.NotificationManager');
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
                        $notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS);
                        return new JSONMessage(true);
                    }
                }
                return new JSONMessage(true, $form->fetch($request));
            default:
                return new JSONMessage(false);
        }
    }

    protected function createSettingsForm($contextId)
    {
        import('plugins.generic.OASwitchboard.classes.settings.OASwitchboardSettingsForm');
        return new OASwitchboardSettingsForm($this->plugin, $contextId);
    }
}
