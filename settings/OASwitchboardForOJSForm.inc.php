<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.OASwitchboardForOJS.settings.ApiPasswordEncryption');

class OASwitchboardForOJSForm extends Form
{
    use ApiPasswordEncryption;

    private $plugin;
    private $contextId;
    private $secret;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->secret = Config::getVar('security', 'api_key_secret');

        $this->addCheck(new FormValidator(
            $this,
            'OASUsername',
            FORM_VALIDATOR_REQUIRED_VALUE,
            null
        ));
        $this->addCheck(new FormValidator(
            $this,
            'OASPassword',
            FORM_VALIDATOR_REQUIRED_VALUE,
            null
        ));
        $this->addCheck(new FormValidatorPost($this));
        try {
            $this->validateSecret($this->secret);
            parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        } catch (Exception $e) {
            parent::__construct($plugin->getTemplateResource('tokenError.tpl'));
        }
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
        $this->plugin->updateSetting($this->contextId, 'username', $this->getData('OASUsername'), 'string');
        $this->plugin->updateSetting($this->contextId, 'password', $this->encryptPassword($this->getData('OASPassword'), $this->secret), 'string');
        parent::execute(...$functionArgs);
    }

    public function initData(): void
    {
        $this->setData('username', $this->plugin->getSetting($this->contextId, 'username'));
    }
}
