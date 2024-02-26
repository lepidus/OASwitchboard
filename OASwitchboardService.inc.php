<?php
/**
 * @file plugins/generic/OASwitchboardForOJS/OASwitchboard.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboard
 * @ingroup plugins_generic_OASwitchboard
 *
 * @brief OASwitchboard plugin class
 */

import('plugins.generic.OASwitchboardForOJS.messages.P1Pio');
import('plugins.generic.OASwitchboardForOJS.lib.APIKeyEncryption.APIKeyEncryption');
import('plugins.generic.OASwitchboardForOJS.api.APIAuthentication');
import('plugins.generic.OASwitchboardForOJS.api.MessageSender');
import('plugins.generic.OASwitchboardForOJS.OASwitchboardForOJSPlugin');

class OASwitchboardService
{
    private OASwitchboardForOJSPlugin $plugin;
    private $contextId;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
    }

    public function sendP1PioMessage()
    {
        $message = new P1Pio();
        $authToken = $this->getAuthTokenByCredentials();
        MessageSender::sendMessage($message, $authToken);
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
        return APIAuthentication::getAuthenticationToken(
            $credentials['email'],
            $credentials['password']
        );
    }
}
