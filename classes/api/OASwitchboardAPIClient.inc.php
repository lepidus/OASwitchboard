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


    public function getAuthorizationToken(string $email, string $password): string
    {
        $response = $this->httpClient->request(
            'POST',
            self::API_BASE_URL . self::API_AUTHORIZATION_ENDPOINT,
            [
                'json' => [
                    'email' => $email,
                    'password' => $password
                ]
            ]
        );
        $responseBody = json_decode($response->getBody());
        return $responseBody->token;
    }

    public static function validateCredentials(string $email, string $password, object $httpClient): bool
    {
        $httpClient = $httpClient;
        $credentials = ['email' => $email, 'password' => $password];
        try {
            $response = $httpClient->request(
                'POST',
                self::API_BASE_URL . self::API_AUTHORIZATION_ENDPOINT,
                [
                    'json' => [
                        'email' => $email,
                        'password' => $password
                    ]
                ]
            );
            return $response->getStatusCode() === 200;
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return false;
        }
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
            );
            return false;
        } catch (ClientException $e) {
            throw new Exception(
                "Client error when sending message. Please check your request parameters and try again."
            );
            return false;
        }
    }
}
