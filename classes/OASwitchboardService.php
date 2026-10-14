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

use APP\core\Application;
use APP\plugins\generic\OASwitchboard\classes\api\APIKeyEncryption;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardAPIClient;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\submission\Submission;
use Exception;
use PKP\context\Context;
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
        $this->apiClient = new OASwitchboardAPIClient($httpClient);
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
        if (is_null($username) || is_null($password)) {
            throw new Exception(__('plugins.generic.OASwitchboard.pluginIsNotConfigured'));
        }
    }

    public static function getMissingRequirements(Submission $submission, Context $context): array
    {
        try {
            new P1Pio($submission);
            return [];
        } catch (P1PioException $exception) {
            return self::excludeRequirementsMetOnPublication(
                array_values(array_unique($exception->getP1PioErrors() ?? [])),
                $submission,
                $context
            );
        }
    }

    public static function excludeRequirementsMetOnPublication(array $missingRequirements, Submission $submission, Context $context): array
    {
        if ($submission->getData('status') === Submission::STATUS_PUBLISHED || !self::willAssignDoiOnPublication($context)) {
            return $missingRequirements;
        }

        return array_values(array_diff($missingRequirements, [P1Pio::REQUIREMENT_DOI]));
    }

    public static function willAssignDoiOnPublication(Context $context): bool
    {
        return $context->areDoisEnabled()
            && $context->isDoiTypeEnabled(Repo::doi()::TYPE_PUBLICATION)
            && $context->getData(Context::SETTING_DOI_CREATION_TIME) !== Repo::doi()::CREATION_TIME_NEVER
            && !empty($context->getData(Context::SETTING_DOI_PREFIX));
    }

    public static function isRorAssociated($submission)
    {
        $authors = $submission->getCurrentPublication()->getData('authors');
        foreach ($authors as $author) {
            foreach ($author->getAffiliations() as $affiliation) {
                if (!empty($affiliation->getRor())) {
                    return true;
                }
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
