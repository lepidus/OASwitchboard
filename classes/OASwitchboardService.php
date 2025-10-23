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

namespace APP\plugins\generic\OASwitchboard\classes;

use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\classes\api\APIKeyEncryption;
use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardAPIClient;
use Exception;
use APP\core\Application;
use APP\facades\Repo;
use PKP\db\DAORegistry;

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
        $authors = $submission->getCurrentPublication()->getData('authors');
        foreach ($authors as $author) {
            if ($author->getData('rorId')) {
                return true;
            }
        }
        return false;
    }

    public static function validateJournalIssn($contextId)
    {
        $contextDao = DAORegistry::getDAO('JournalDAO');
        $context = $contextDao->getById($contextId);
        if (empty($context->getData('onlineIssn')) && empty($context->getData('printIssn'))) {
            return false;
        }
        return true;
    }
}
