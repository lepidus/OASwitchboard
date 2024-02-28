<?php

/**
 * @file plugins/generic/OASwitchboardForOJS/classes/OASwitchboard.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboard
 *
 * @brief OASwitchboard plugin class
 */

import('plugins.generic.OASwitchboardForOJS.classes.messages.P1Pio');
import('plugins.generic.OASwitchboardForOJS.lib.APIKeyEncryption.APIKeyEncryption');
import('plugins.generic.OASwitchboardForOJS.OASwitchboardForOJSPlugin');
import('plugins.generic.OASwitchboardForOJS.classes.api.OASwitchboardAPIClient');

class OASwitchboardService
{
    private OASwitchboardForOJSPlugin $plugin;
    private OASwitchboardAPIClient $apiClient;
    private $contextId;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->apiClient = new OASwitchboardAPIClient(Application::get()->getHttpClient());
    }

    public function sendP1PioMessage()
    {
        $message = new P1Pio();
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
}
