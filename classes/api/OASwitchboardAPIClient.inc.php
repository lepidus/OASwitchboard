<?php

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;

class OASwitchboardAPIClient
{
    private const API_BASE_URL = 'https://sandboxapi.oaswitchboard.org/v2/';
    private const API_AUTHORIZATION_ENDPOINT = 'authorize';
    private const API_SEND_MESSAGE_ENDPOINT = 'message';
    private $httpClient;

    public function __construct($httpClient)
    {
        $this->httpClient = $httpClient;
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
                self::API_BASE_URL . $endpoint,
                $options
            );
            return $response;
        } catch (ServerException $e) {
            throw new Exception(
                "Server error when sending message. The OA Switchboard API server encountered an internal error."
                . $e->getMessage()
            );
        } catch (ClientException $e) {
            throw new Exception(
                "Client error when sending message. Please check your request parameters and try again."
                . $e->getMessage(),
                false
            );
        }
    }
}
