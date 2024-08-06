<?php

class Manage
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
                import('plugins.generic.OASwitchboard.classes.settings.OASwitchboardSettingsForm');
                $form = new OASwitchboardSettingsForm($this->plugin, $context->getId());
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
                return $this->plugin::manage($verb, $args, $message, $messageParams);
        }
    }
}
