<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.OASwitchboard.lib.APIKeyEncryption.APIKeyEncryption');
import('plugins.generic.OASwitchboard.classes.api.OASwitchboardAPIClient');

class OASwitchboardSettingsForm extends Form
{
    private $plugin;
    private $contextId;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->addFormValidators();

        $template = APIKeyEncryption::secretConfigExists() ? 'settingsForm.tpl' : 'tokenError.tpl';
        parent::__construct($plugin->getTemplateResource($template));
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
            array('contents' => __('plugins.generic.OASwitchboard.settings.apiAuthenticatorFailed'))
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
        $this->readUserVars(['OASUsername', 'OASPassword', 'isSandBoxAPI']);
        parent::readInputData();
    }

    public function execute(...$functionArgs)
    {
        $encryptedPassword = APIKeyEncryption::encryptString($this->getData('OASPassword'));
        $this->plugin->updateSetting($this->contextId, 'isSandBoxAPI', $this->getData('isSandBoxAPI'), 'bool');
        $this->plugin->updateSetting($this->contextId, 'password', $encryptedPassword, 'string');
        $this->plugin->updateSetting($this->contextId, 'username', $this->getData('OASUsername'), 'string');
        parent::execute(...$functionArgs);
    }

    public function initData(): void
    {
        $this->setData('username', $this->plugin->getSetting($this->contextId, 'username'));
        $this->setData('isSandBoxAPI', $this->plugin->getSetting($this->contextId, 'isSandBoxAPI'));
    }

    public function validateAPICredentials(): bool
    {
        $username = $this->getData('OASUsername');
        $password = $this->getData('OASPassword');
        $useSandboxApi = (bool) $this->getData('isSandBoxAPI');

        $httpClient = Application::get()->getHttpClient();
        $APIClient = new OASwitchboardAPIClient($httpClient, $useSandboxApi);

        try {
            $APIClient->getAuthorization(
                $username,
                $password
            );
            return true;
        } catch (Exception $e) {
            $this->authenticationFailNotification();
            return false;
        }
    }
}
