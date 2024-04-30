<?php

namespace APP\plugins\generic\OASwitchboard\classes\api;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use Exception;

class OASwitchboardAPIClient
{
    private const API_AUTHORIZATION_ENDPOINT = 'authorize';
    private const API_SEND_MESSAGE_ENDPOINT = 'message';
    private const API_BASE_URL = 'https://api.oaswitchboard.org/v2/';
    private const API_SANDBOX_BASE_URL = 'https://sandboxapi.oaswitchboard.org/v2/';
    private $httpClient;
    private $apiBaseUrl;

    public function __construct($httpClient, bool $useSandboxApi = false)
    {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = $useSandboxApi ? self::API_SANDBOX_BASE_URL : self::API_BASE_URL;
    }

    public function sendMessage(P1Pio $message, string $authToken): int
    {
        $options = [
            'headers' => ['Authorization' => 'Bearer ' . $authToken],
            'json' => $message->getContent(),
        ];
        $response = $this->makeRequest('POST', self::API_SEND_MESSAGE_ENDPOINT, $options);
        return $response->getStatusCode();
    }

    public function getAuthorization(string $email, string $password): string
    {
        $options  = [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ];
        $response = $this->makeRequest('POST', self::API_AUTHORIZATION_ENDPOINT, $options);
        $responseBody = json_decode($response->getBody());
        return $responseBody->token;
    }

    private function makeRequest(string $method, string $endpoint, array $options)
    {
        try {
            $response = $this->httpClient->request(
                $method,
                $this->apiBaseUrl . $endpoint,
                $options
            );
            return $response;
        } catch (ServerException $e) {
            error_log($e);
            throw new Exception(
                __('plugins.generic.OASwitchboard.serverError')
            );
        } catch (ClientException $e) {
            error_log($e);
            throw new Exception(
                __('plugins.generic.OASwitchboard.postRequirements')
            );
        }
    }
}
