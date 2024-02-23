<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.OASwitchboardForOJS.lib.APIKeyEncryption');
import('plugins.generic.OASwitchboardForOJS.api.APIAuthentication');

class OASwitchboardForOJSSettingsForm extends Form
{
    private $plugin;
    private $contextId;
    private $APIKeyEncryption;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->addFormValidators();

        try {
            $this->APIKeyEncryption = new APIKeyEncryption();
            parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        } catch (Exception $e) {
            parent::__construct($plugin->getTemplateResource('tokenError.tpl'));
        }
    }

    private function addFormValidators(): void
    {
        $fields = ['OASUsername', 'OASPassword'];
        foreach ($fields as $field) {
            $this->addCheck(new FormValidator($this, $field, FORM_VALIDATOR_REQUIRED_VALUE, null));
        }
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    private function authenticationFailNotification(): void
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        import('classes.notification.NotificationManager');
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            NOTIFICATION_TYPE_ERROR,
            array('contents' => __('plugins.generic.OASwitchboardForOJS.settings.apiAuthenticatorFailed'))
        );
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign(
            'hasCredentials',
            $this->plugin->getSetting($this->contextId, 'username')
        );
        return parent::fetch($request);
    }

    public function readInputData()
    {
        $this->readUserVars(['OASUsername', 'OASPassword']);
        parent::readInputData();
    }

    public function execute(...$functionArgs)
    {
        $encryptedPassword = $this->APIKeyEncryption->encryptString($this->getData('OASPassword'));
        $this->plugin->updateSetting($this->contextId, 'password', $encryptedPassword, 'string');
        $this->plugin->updateSetting($this->contextId, 'username', $this->getData('OASUsername'), 'string');
        parent::execute(...$functionArgs);
    }

    public function initData(): void
    {
        $this->setData('username', $this->plugin->getSetting($this->contextId, 'username'));
    }

    public function validateAPICredentials(): bool
    {
        $username = $this->getData('OASUsername');
        $password = $this->getData('OASPassword');

        if (!APIAuthentication::authenticate($username, $password)) {
            $this->authenticationFailNotification();
            return false;
        }
        return true;
    }
}
