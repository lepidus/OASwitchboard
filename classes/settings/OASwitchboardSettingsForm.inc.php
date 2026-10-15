<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.OASwitchboard.lib.APIKeyEncryption.APIKeyEncryption');
import('plugins.generic.OASwitchboard.classes.api.OASwitchboardAPIClient');
import('plugins.generic.OASwitchboard.classes.exceptions.ConnectionFailedException');
import('plugins.generic.OASwitchboard.classes.OASwitchboardService');

class OASwitchboardSettingsForm extends Form
{
    private $plugin;
    private $contextId;
    private $apiKeyEncryption;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->apiKeyEncryption = new APIKeyEncryption();
        $this->addFormValidators();

        $template = $this->apiKeyEncryption->secretConfigExists() ? 'settingsForm.tpl' : 'missingSecretConfigWarning.tpl';
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

    protected function notifyCredentialsFailure(string $message): void
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        import('classes.notification.NotificationManager');
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            NOTIFICATION_TYPE_ERROR,
            array('contents' => $message)
        );
    }

    protected function createAPIClient()
    {
        return new OASwitchboardAPIClient(Application::get()->getHttpClient());
    }

    public function canBeSaved(): bool
    {
        return $this->apiKeyEncryption->secretConfigExists();
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign(
            'storedUsername',
            $this->plugin->getSetting($this->contextId, 'username')
        );
        $templateMgr->assign(
            'hasJournalIssn',
            OASwitchboardService::validateJournalIssn($this->contextId)
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
        $encryptedPassword = $this->apiKeyEncryption->encryptString($this->getData('OASPassword'));
        $this->plugin->updateSetting($this->contextId, 'password', $encryptedPassword, 'string');
        $this->plugin->updateSetting($this->contextId, 'username', $this->getData('OASUsername'), 'string');
        parent::execute(...$functionArgs);
    }

    public function initData(): void
    {
        $this->setData('OASUsername', $this->plugin->getSetting($this->contextId, 'username'));
    }

    public function validateAPICredentials(): bool
    {
        $username = $this->getData('OASUsername');
        $password = $this->getData('OASPassword');

        try {
            $this->createAPIClient()->getAuthorization($username, $password);
            return true;
        } catch (ConnectionFailedException $e) {
            $this->notifyCredentialsFailure($e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->notifyCredentialsFailure(__('plugins.generic.OASwitchboard.settings.apiAuthenticatorFailed'));
            return false;
        }
    }
}
