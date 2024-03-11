<?php

/**
 * @file plugins/generic/OASwitchboard/classes/OASwitchboard.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboard
 *
 * @brief OASwitchboard plugin class
 */

import('plugins.generic.OASwitchboard.classes.messages.P1Pio');
import('plugins.generic.OASwitchboard.lib.APIKeyEncryption.APIKeyEncryption');
import('plugins.generic.OASwitchboard.OASwitchboardPlugin');
import('plugins.generic.OASwitchboard.classes.api.OASwitchboardAPIClient');

class OASwitchboardService
{
    private OASwitchboardPlugin $plugin;
    private OASwitchboardAPIClient $apiClient;
    private $contextId;
    private $submission;

    public function __construct($plugin, $contextId, $submission)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->submission = $submission;
        $this->validatePluginIsConfigured($this->plugin);

        $httpClient = Application::get()->getHttpClient();
        $useSandboxApi = $this->plugin->getSetting($this->contextId, 'isSandBoxAPI');
        $this->apiClient = new OASwitchboardAPIClient($httpClient, $useSandboxApi);
    }

    public function sendP1PioMessage()
    {
        $message = new P1Pio($this->submission);
        $authToken = $this->getAuthTokenByCredentials();
        $this->apiClient->sendMessage($message, $authToken);
    }

    private function retrieveCredentials()
    {
        $email = $this->plugin->getSetting($this->contextId, 'username');
        $password = APIKeyEncryption::decryptString(
            $this->plugin->getSetting($this->contextId, 'password')
        );

        return ['email' => $email, 'password' => $password];
    }

    private function getAuthTokenByCredentials()
    {
        $credentials = $this->retrieveCredentials();
        return $this->apiClient->getAuthorization(
            $credentials['email'],
            $credentials['password']
        );
    }

    private function validatePluginIsConfigured($plugin)
    {
        $username = $this->plugin->getSetting($this->contextId, 'username');
        $password = $this->plugin->getSetting($this->contextId, 'password');
        $useSandboxApi = $this->plugin->getSetting($this->contextId, 'isSandBoxAPI');
        if (is_null($username) || is_null($password) || is_null($useSandboxApi)) {
            throw new Exception(__("plugins.generic.OASwitchboard.pluginIsNotConfigured"));
        }
    }
}
