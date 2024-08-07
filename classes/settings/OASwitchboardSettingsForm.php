<?php

namespace APP\plugins\generic\OASwitchboard\classes\settings;

use PKP\form\Form;
use APP\template\TemplateManager;
use APP\core\Application;
use APP\plugins\generic\OASwitchboard\classes\api\APIKeyEncryption;
use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardAPIClient;
use APP\notification\NotificationManager;
use Exception;
use PKP\notification\PKPNotification;
use PKP\form\validation\FormValidator;

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
            $this->addCheck(new \PKP\form\validation\FormValidator($this, $field, FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, null));
        }
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    private function authenticationFailNotification(): void
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_ERROR,
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

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm', '\OASwitchboardSettingsForm');
}
