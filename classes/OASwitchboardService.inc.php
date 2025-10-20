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
import('plugins.generic.OASwitchboard.classes.api.OASwitchboardAPIClient');

class OASwitchboardService
{
    private $plugin;
    private $apiClient;
    private $contextId;
    private $submission;

    public function __construct($plugin, $contextId, $submission)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        $this->submission = $submission;
        self::validatePluginIsConfigured($this->plugin, $this->contextId);

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
        $apiKeyEncryption = new APIKeyEncryption();
        $email = $this->plugin->getSetting($this->contextId, 'username');
        $password = $apiKeyEncryption->decryptString(
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

    public static function validatePluginIsConfigured($plugin, $contextId)
    {
        $username = $plugin->getSetting($contextId, 'username');
        $password = $plugin->getSetting($contextId, 'password');
        $useSandboxApi = $plugin->getSetting($contextId, 'isSandBoxAPI');
        if (is_null($username) || is_null($password) || is_null($useSandboxApi)) {
            throw new Exception(__("plugins.generic.OASwitchboard.pluginIsNotConfigured"));
        }
    }

    public static function isRorAssociated($submission)
    {
        $authors = $submission->getAuthors();
        foreach ($authors as $author) {
            if ($author->getData('rorId')) {
                return true;
            }
        }
        return false;
    }

    public static function validateJournalIssn($contextId)
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($contextId);
        if (empty($context->getData('onlineIssn')) && empty($context->getData('printIssn'))) {
            return false;
        }
        return true;
    }
}
